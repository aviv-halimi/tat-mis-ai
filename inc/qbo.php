<?php
/**
 * QuickBooks Online API helpers.
 * Requires per-store params: qbo_realm_id, qbo_refresh_token.
 * Optional: qbo_account_id_products (GL 14-100), qbo_account_id_rebates (40-102).
 * Set QBO_CLIENT_ID and QBO_CLIENT_SECRET in env or define in config.
 */

if (!defined('BASE_PATH')) {
    return;
}

/**
 * Get store's QBO params (realm_id, refresh_token, optional account IDs).
 * @param int $store_id
 * @return array|null
 */
function qbo_get_store_params($store_id) {
    $rs = getRs("SELECT params FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
    if (!$rs || !($r = getRow($rs))) {
        return null;
    }
    $params = is_array($r['params']) ? $r['params'] : (is_string($r['params']) ? json_decode($r['params'], true) : array());
    if (!is_array($params)) {
        $params = array();
    }
    $realm = isset($params['qbo_realm_id']) ? trim($params['qbo_realm_id']) : '';
    $refresh = isset($params['qbo_refresh_token']) ? trim($params['qbo_refresh_token']) : '';
    if ($realm === '' || $refresh === '') {
        return null;
    }
    return array(
        'realm_id' => $realm,
        'refresh_token' => $refresh,
        'account_id_products' => isset($params['qbo_account_id_products']) ? trim($params['qbo_account_id_products']) : '',
        'account_id_rebates' => isset($params['qbo_account_id_rebates']) ? trim($params['qbo_account_id_rebates']) : '',
    );
}

/**
 * Save a new refresh token to the store's params (OAuth2 token rotation).
 * Intuit may return a new refresh_token when you use the current one; you must store it for the next refresh.
 *
 * @param int $store_id
 * @param string $new_refresh_token
 * @return bool true if updated, false if store not found
 */
function qbo_update_refresh_token($store_id, $new_refresh_token) {
    $rs = getRs("SELECT params FROM store WHERE store_id = ?", array($store_id));
    if (!$rs || !($r = getRow($rs))) {
        return false;
    }
    $params = is_array($r['params']) ? $r['params'] : (is_string($r['params']) ? json_decode($r['params'], true) : array());
    if (!is_array($params)) {
        $params = array();
    }
    $params['qbo_refresh_token'] = trim($new_refresh_token);
    dbUpdate('store', array('params' => json_encode($params)), $store_id, 'store_id');
    return true;
}

/**
 * Get OAuth2 access token for a store using refresh token.
 * @param int $store_id
 * @param array|null $request_log If provided, append request details (url, method, headers, body) for testing outside PHP
 * @return array Success: { access_token, realm_id, ... }. Failure: { success => false, error => string, debug => array }
 */
function qbo_get_access_token($store_id, &$request_log = null) {
    $params = qbo_get_store_params($store_id);
    if (!$params) {
        $rs = getRs("SELECT params FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
        $has_store = $rs && getRow($rs);
        return array(
            'success' => false,
            'error' => 'Store QBO params missing or invalid',
            'debug' => array(
                'step' => 'qbo_get_store_params',
                'store_id' => $store_id,
                'store_found' => (bool)$has_store,
                'hint' => 'Store params (JSON) must include qbo_realm_id and qbo_refresh_token',
            ),
        );
    }
    $client_id = defined('QBO_CLIENT_ID') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
    $client_secret = defined('QBO_CLIENT_SECRET') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
    if ($client_id === '' || $client_secret === '') {
        return array(
            'success' => false,
            'error' => 'QBO client credentials not set',
            'debug' => array(
                'step' => 'qbo_client_credentials',
                'client_id_set' => $client_id !== '',
                'client_secret_set' => $client_secret !== '',
                'hint' => 'Set QBO_CLIENT_ID and QBO_CLIENT_SECRET in config or environment',
            ),
        );
    }
    $url = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    $body = http_build_query(array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $params['refresh_token'],
    ));
    if (is_array($request_log)) {
        $request_log[] = array(
            'label' => '1. OAuth token (refresh)',
            'url' => $url,
            'method' => 'POST',
            'headers' => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Accept: application/json',
            ),
            'body' => $body,
            'curl' => 'curl -X POST "' . $url . '" -H "Content-Type: application/x-www-form-urlencoded" -H "Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret) . '" -H "Accept: application/json" -d "' . str_replace('"', '\\"', $body) . '"',
        );
    }
    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret) . "\r\n" .
                'Accept: application/json',
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ),
    );
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        $php_err = error_get_last();
        return array(
            'success' => false,
            'error' => 'OAuth token request failed (connection/timeout)',
            'debug' => array(
                'step' => 'oauth_refresh_token',
                'url_host' => 'oauth.platform.intuit.com',
                'http_response_header' => (isset($http_response_header) && is_array($http_response_header)) ? $http_response_header : 'no response',
                'php_error' => ($php_err && isset($php_err['message'])) ? $php_err['message'] : null,
            ),
        );
    }
    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        $intuit_error = isset($data['error']) ? $data['error'] : '';
        $intuit_desc = isset($data['error_description']) ? $data['error_description'] : '';
        $err_msg = 'OAuth response missing access_token';
        if ($intuit_error || $intuit_desc) {
            $err_msg .= ': ' . $intuit_error . ($intuit_desc ? ' — ' . $intuit_desc : '');
        }
        $hint = 'Check refresh_token is valid and not revoked; Intuit may return error in oauth_response.';
        if ($intuit_error === 'invalid_grant' && (stripos($intuit_desc, 'token type') !== false || stripos($intuit_desc, 'clientID') !== false)) {
            $hint = 'Incorrect Token type or clientID usually means: (1) You stored an ACCESS token — use the REFRESH token in store params (refresh tokens are long-lived; access tokens expire in 1 hour). (2) The refresh token was issued by a different Intuit app — ensure QBO_CLIENT_ID and QBO_CLIENT_SECRET match the app where you connected the company and got the token.';
        }
        return array(
            'success' => false,
            'error' => $err_msg,
            'debug' => array(
                'step' => 'oauth_refresh_token',
                'oauth_response' => $data,
                'oauth_response_raw' => substr($response, 0, 500),
                'hint' => $hint,
            ),
        );
    }
    // OAuth2 token rotation: Intuit may return a new refresh_token; save it so the next refresh uses it
    if (!empty($data['refresh_token'])) {
        qbo_update_refresh_token($store_id, $data['refresh_token']);
    }
    return array(
        'access_token' => $data['access_token'],
        'realm_id' => $params['realm_id'],
        'account_id_products' => $params['account_id_products'],
        'account_id_rebates' => $params['account_id_rebates'],
    );
}

/**
 * Make a QBO API request (GET or POST).
 * @param string $realm_id
 * @param string $access_token
 * @param string $method GET|POST
 * @param string $path e.g. "query?query=select * from Vendor"
 * @param mixed $body for POST, array (will be JSON encoded)
 * @param array|null $request_log If provided, append request details for testing outside PHP
 * @return array { success, data, error }
 */
function qbo_api_request($realm_id, $access_token, $method, $path, $body = null, &$request_log = null) {
    $base = 'https://quickbooks.api.intuit.com/v3/company/' . $realm_id . '/';
    $url = $base . ltrim($path, '/');
    $headers = array(
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json',
    );
    if ($method === 'POST' && $body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $body_str = ($method === 'POST' && $body !== null) ? (is_string($body) ? $body : json_encode($body)) : null;
    if (is_array($request_log)) {
        $curl_headers = '-H "Authorization: Bearer ' . $access_token . '" -H "Accept: application/json"';
        if ($method === 'POST' && $body_str !== null) {
            $curl_headers .= ' -H "Content-Type: application/json"';
        }
        $entry = array(
            'label' => '2. QBO API request',
            'url' => $url,
            'method' => $method,
            'headers' => array(
                'Authorization: Bearer ' . $access_token,
                'Accept: application/json',
            ),
        );
        if ($body_str !== null) {
            $entry['body'] = $body_str;
            $entry['curl'] = 'curl -X ' . $method . ' "' . $url . '" ' . $curl_headers . ' -d \'' . str_replace("'", "'\\''", $body_str) . '\'';
        } else {
            $entry['curl'] = 'curl -X ' . $method . ' "' . $url . '" ' . $curl_headers;
        }
        $request_log[] = $entry;
    }
    $opts = array(
        'http' => array(
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 30,
            'ignore_errors' => true,
        ),
    );
    if ($method === 'POST' && $body_str !== null) {
        $opts['http']['content'] = $body_str;
    }
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        $php_err = error_get_last();
        $debug = array(
            'step' => 'qbo_api_request',
            'url_host' => parse_url($url, PHP_URL_HOST),
            'path' => $path,
            'realm_id' => $realm_id,
            'http_response_header' => (isset($http_response_header) && is_array($http_response_header)) ? $http_response_header : 'no response (connection failed or timeout)',
        );
        if ($php_err && isset($php_err['message'])) {
            $debug['php_error'] = $php_err['message'];
        }
        return array('success' => false, 'error' => 'Request failed', 'debug' => $debug);
    }
    $data = json_decode($response, true);
    if (isset($data['Fault'])) {
        $msg = isset($data['Fault']['Error'][0]['Message']) ? $data['Fault']['Error'][0]['Message'] : 'QBO API error';
        return array('success' => false, 'error' => $msg, 'data' => $data);
    }
    return array('success' => true, 'data' => $data);
}

/**
 * List vendors from QBO for a store.
 * @param int $store_id
 * @return array { success, vendors: [ { id, DisplayName } ], error }
 */
function qbo_list_vendors($store_id) {
    $request_log = array();
    $token = qbo_get_access_token($store_id, $request_log);
    if (!is_array($token) || empty($token['access_token'])) {
        $err = isset($token['error']) ? $token['error'] : 'QBO not configured or token failed';
        $out = array('success' => false, 'vendors' => array(), 'error' => $err);
        if (!empty($token['debug'])) {
            $out['debug'] = $token['debug'];
        }
        $out['request_log'] = $request_log;
        return $out;
    }
    $query = urlencode('SELECT * FROM Vendor MAXRESULTS 1000');
    $result = qbo_api_request($token['realm_id'], $token['access_token'], 'GET', 'query?query=' . $query, null, $request_log);
    if (!$result['success']) {
        $out = array('success' => false, 'vendors' => array(), 'error' => $result['error']);
        if (!empty($result['debug'])) {
            $out['debug'] = $result['debug'];
        }
        $out['request_log'] = $request_log;
        return $out;
    }
    $vendors = array();
    if (isset($result['data']['QueryResponse']['Vendor'])) {
        foreach ($result['data']['QueryResponse']['Vendor'] as $v) {
            $vendors[] = array(
                'id' => $v['Id'],
                'DisplayName' => isset($v['DisplayName']) ? $v['DisplayName'] : ('Vendor ' . $v['Id']),
            );
        }
    }
    return array('success' => true, 'vendors' => $vendors, 'request_log' => $request_log);
}

/**
 * Create a Bill in QBO.
 * Line items: (1) Cannabis Products (14-100) = order subtotal, (2) Monthly Rebates (40-102) = total discounts.
 * @param int $store_id
 * @param string $vendor_ref_id QBO Vendor Id
 * @param float $subtotal po.rsubtotal
 * @param float $discounts total from po_discount (receiving)
 * @param string $doc_number optional (e.g. PO number)
 * @param string $txn_date optional Y-m-d
 * @return array { success, BillId, error }
 */
function qbo_create_bill($store_id, $vendor_ref_id, $subtotal, $discounts, $doc_number = '', $txn_date = null) {
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        $err = (is_array($token) && isset($token['error'])) ? $token['error'] : 'QBO not configured or token failed';
        return array('success' => false, 'error' => $err);
    }
    $account_products = $token['account_id_products'];
    $account_rebates = $token['account_id_rebates'];
    if ($account_products === '' || $account_rebates === '') {
        return array('success' => false, 'error' => 'QBO account IDs for products and rebates must be set in store params');
    }
    $lines = array();
    if ($subtotal > 0) {
        $lines[] = array(
            'DetailType' => 'AccountBasedExpenseLineDetail',
            'Amount' => round($subtotal, 2),
            'Description' => 'Cannabis Products',
            'AccountBasedExpenseLineDetail' => array(
                'AccountRef' => array('value' => $account_products),
            ),
        );
    }
    if ($discounts > 0) {
        $lines[] = array(
            'DetailType' => 'AccountBasedExpenseLineDetail',
            'Amount' => -round($discounts, 2),
            'Description' => 'Monthly Rebates',
            'AccountBasedExpenseLineDetail' => array(
                'AccountRef' => array('value' => $account_rebates),
            ),
        );
    }
    if (empty($lines)) {
        return array('success' => false, 'error' => 'Bill must have at least one line (subtotal or discounts)');
    }
    $payload = array(
        'VendorRef' => array('value' => $vendor_ref_id),
        'Line' => $lines,
    );
    if ($doc_number !== '') {
        $payload['DocNumber'] = $doc_number;
    }
    if ($txn_date !== null && $txn_date !== '') {
        $payload['TxnDate'] = $txn_date;
    }
    $result = qbo_api_request($token['realm_id'], $token['access_token'], 'POST', 'bill', $payload);
    if (!$result['success']) {
        return array('success' => false, 'error' => $result['error']);
    }
    $bill_id = isset($result['data']['Bill']['Id']) ? $result['data']['Bill']['Id'] : null;
    return array('success' => true, 'BillId' => $bill_id);
}

/**
 * Push a PO (status 5) to QBO as a Bill. Used by ajax/po-qbo-bill and ajax/po-qbo-map-vendor.
 * @param string $po_code
 * @return array JSON-ready result (success, response, need_mapping, vendor_id, vendor_name, store_id, BillId, etc.)
 */
function po_qbo_push_bill($po_code) {
    $rs = getRs(
        "SELECT p.po_id, p.po_code, p.po_number, p.po_status_id, p.store_id, p.vendor_id, p.r_subtotal, p.date_received, s.db AS store_db " .
        "FROM po p INNER JOIN store s ON s.store_id = p.store_id WHERE p.po_code = ? AND " . is_enabled('p,s'),
        array($po_code)
    );
    $po = getRow($rs);
    if (!$po || (int)$po['po_status_id'] !== 5) {
        return array('success' => false, 'response' => 'PO not found or not in status 5 (Validated).');
    }
    $store_id = (int)$po['store_id'];
    $store_db = $po['store_db'];
    $po_vendor_id = (int)$po['vendor_id'];

    $vendor_rs = getRs("SELECT vendor_id, name, QBO_ID FROM {$store_db}.vendor WHERE vendor_id = ?", array($po_vendor_id));
    $vendor = getRow($vendor_rs);
    if (!$vendor) {
        return array('success' => false, 'response' => 'Vendor not found.');
    }
    $qbo_id = isset($vendor['QBO_ID']) ? trim($vendor['QBO_ID']) : '';
    if ($qbo_id === '') {
        return array(
            'success' => false,
            'need_mapping' => true,
            'response' => 'This vendor is not mapped to QuickBooks. Please map the vendor below, then try again.',
            'vendor_id' => $po_vendor_id,
            'vendor_name' => $vendor['name'],
            'store_id' => $store_id,
            'po_code' => $po_code,
        );
    }
    $dr = getRs(
        "SELECT COALESCE(SUM(d.discount_amount), 0) AS discounts FROM po_discount d WHERE d.po_id = ? AND d.is_enabled = 1 AND d.is_active = 1 AND d.is_receiving = 1",
        array($po['po_id'])
    );
    $discount_row = getRow($dr);
    $discounts = $discount_row ? (float)$discount_row['discounts'] : 0.0;
    $subtotal = (float)$po['r_subtotal'];
    $txn_date = !empty($po['date_received']) ? date('Y-m-d', strtotime($po['date_received'])) : date('Y-m-d');
    $doc_number = 'PO ' . $po['po_number'];
    $result = qbo_create_bill($store_id, $qbo_id, $subtotal, $discounts, $doc_number, $txn_date);
    if (!$result['success']) {
        return array('success' => false, 'response' => $result['error']);
    }
    return array(
        'success' => true,
        'response' => 'Bill created in QuickBooks (Bill #' . $result['BillId'] . ').',
        'BillId' => $result['BillId'],
    );
}
