<?php
/**
 * QuickBooks Online API helpers (using official quickbooks/v3-php-sdk).
 * Requires per-store params: qbo_realm_id, qbo_refresh_token.
 * Optional: qbo_account_id_products (GL 14-100), qbo_account_id_rebates (40-102).
 * Set QBO_CLIENT_ID and QBO_CLIENT_SECRET in env or define in config.
 */

if (!defined('BASE_PATH')) {
    return;
}

/** Ensure SDK is loaded (project root vendor from Composer) */
if (!class_exists('QuickBooksOnline\API\DataService\DataService', false)) {
    $autoload = BASE_PATH . 'vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
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
 * @param int $store_id
 * @param string $new_refresh_token
 * @return bool
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
 * Get OAuth2 access token for a store using the QBO SDK (refresh token).
 * @param int $store_id
 * @param array|null $request_log If provided, append note that SDK was used (no raw curl when using SDK)
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
    if (is_array($request_log)) {
        $request_log[] = array(
            'label' => '1. OAuth token (refresh)',
            'sdk_used' => true,
            'note' => 'QuickBooks V3 PHP SDK OAuth2LoginHelper::refreshAccessTokenWithRefreshToken()',
        );
    }
    try {
        $oauth2Helper = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper($client_id, $client_secret);
        $accessTokenObj = $oauth2Helper->refreshAccessTokenWithRefreshToken($params['refresh_token']);
        $access_token = $accessTokenObj->getAccessToken();
        $new_refresh = $accessTokenObj->getRefreshToken();
        if ($new_refresh !== '' && $new_refresh !== $params['refresh_token']) {
            qbo_update_refresh_token($store_id, $new_refresh);
        }
        $effective_refresh = ($new_refresh !== '' ? $new_refresh : $params['refresh_token']);
        return array(
            'access_token' => $access_token,
            'refresh_token' => $effective_refresh,
            'realm_id' => $params['realm_id'],
            'account_id_products' => $params['account_id_products'],
            'account_id_rebates' => $params['account_id_rebates'],
        );
    } catch (\QuickBooksOnline\API\Exception\ServiceException $e) {
        $msg = $e->getMessage();
        $hint = 'Check refresh_token is valid and not revoked. If invalid_grant / Incorrect Token type or clientID: use REFRESH token (not access token) and ensure QBO_CLIENT_ID/QBO_CLIENT_SECRET match the app that issued the token.';
        return array(
            'success' => false,
            'error' => 'OAuth refresh failed: ' . $msg,
            'debug' => array(
                'step' => 'oauth_refresh_token',
                'sdk_used' => true,
                'exception_message' => $msg,
                'hint' => $hint,
            ),
        );
    } catch (\Exception $e) {
        return array(
            'success' => false,
            'error' => 'OAuth refresh failed: ' . $e->getMessage(),
            'debug' => array(
                'step' => 'oauth_refresh_token',
                'sdk_used' => true,
                'exception_message' => $e->getMessage(),
            ),
        );
    }
}

/**
 * Build a DataService instance for a store (uses current access token from qbo_get_access_token).
 * @param array $token Result from qbo_get_access_token (must contain access_token, realm_id)
 * @return \QuickBooksOnline\API\DataService\DataService|null
 */
function qbo_data_service($token) {
    if (empty($token['access_token']) || empty($token['realm_id'])) {
        return null;
    }
    $client_id = defined('QBO_CLIENT_ID') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
    $client_secret = defined('QBO_CLIENT_SECRET') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
    if ($client_id === '' || $client_secret === '') {
        return null;
    }
    $refresh = isset($token['refresh_token']) ? $token['refresh_token'] : $token['access_token'];
    $dataService = \QuickBooksOnline\API\DataService\DataService::Configure(array(
        'auth_mode' => 'oauth2',
        'ClientID' => $client_id,
        'ClientSecret' => $client_secret,
        'accessTokenKey' => $token['access_token'],
        'refreshTokenKey' => $refresh,
        'QBORealmID' => $token['realm_id'],
        'baseUrl' => 'Production',
    ));
    $dataService->throwExceptionOnError(false);
    return $dataService;
}

/**
 * List vendors from QBO for a store (using SDK).
 * @param int $store_id
 * @return array { success, vendors: [ { id, DisplayName } ], error, request_log? }
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
    if (is_array($request_log)) {
        $request_log[] = array(
            'label' => '2. QBO API request (Query Vendor)',
            'sdk_used' => true,
            'note' => 'DataService::Query("SELECT * FROM Vendor MAXRESULTS 1000")',
        );
    }
    try {
        $dataService = qbo_data_service($token);
        if (!$dataService) {
            $out = array('success' => false, 'vendors' => array(), 'error' => 'Could not create DataService');
            $out['request_log'] = $request_log;
            return $out;
        }
        $vendorsResult = $dataService->Query('SELECT * FROM Vendor MAXRESULTS 1000');
        $error = $dataService->getLastError();
        if ($error) {
            $out = array(
                'success' => false,
                'vendors' => array(),
                'error' => $error->getResponseBody() ?: $error->getOAuthHelperError() ?: 'QBO API error',
                'debug' => array(
                    'step' => 'qbo_query_vendor',
                    'sdk_used' => true,
                    'http_status' => $error->getHttpStatusCode(),
                    'response_body' => $error->getResponseBody(),
                ),
            );
            $out['request_log'] = $request_log;
            return $out;
        }
        $vendors = array();
        if (is_array($vendorsResult)) {
            foreach ($vendorsResult as $v) {
                $id = is_object($v) ? (isset($v->Id) ? $v->Id : null) : (isset($v['Id']) ? $v['Id'] : null);
                $name = is_object($v) ? (isset($v->DisplayName) ? $v->DisplayName : 'Vendor ' . $id) : (isset($v['DisplayName']) ? $v['DisplayName'] : 'Vendor ' . $id);
                if ($id !== null) {
                    $vendors[] = array('id' => $id, 'DisplayName' => $name);
                }
            }
        }
        return array('success' => true, 'vendors' => $vendors, 'request_log' => $request_log);
    } catch (\Exception $e) {
        $out = array(
            'success' => false,
            'vendors' => array(),
            'error' => $e->getMessage(),
            'debug' => array('step' => 'qbo_list_vendors', 'sdk_used' => true, 'exception_message' => $e->getMessage()),
        );
        $out['request_log'] = $request_log;
        return $out;
    }
}

/**
 * Create a Bill in QBO (using SDK).
 * @param int $store_id
 * @param string $vendor_ref_id QBO Vendor Id
 * @param float $subtotal
 * @param float $discounts
 * @param string $doc_number
 * @param string|null $txn_date Y-m-d
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
    try {
        $dataService = qbo_data_service($token);
        if (!$dataService) {
            return array('success' => false, 'error' => 'Could not create DataService');
        }
        $billObj = \QuickBooksOnline\API\Facades\Bill::create($payload);
        $resultObj = $dataService->Add($billObj);
        $error = $dataService->getLastError();
        if ($error) {
            return array('success' => false, 'error' => $error->getResponseBody() ?: $error->getOAuthHelperError() ?: 'QBO API error');
        }
        $bill_id = is_object($resultObj) && isset($resultObj->Id) ? $resultObj->Id : (is_array($resultObj) && isset($resultObj['Id']) ? $resultObj['Id'] : null);
        return array('success' => true, 'BillId' => $bill_id);
    } catch (\Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Attach a file (e.g. invoice PDF) to an existing QBO Bill.
 * @param int $store_id
 * @param string $bill_id QBO Bill Id
 * @param string $file_path Full path to the file
 * @param string $file_name Filename for the attachment (e.g. invoice.pdf)
 * @return array { success, error? }
 */
function qbo_attach_file_to_bill($store_id, $bill_id, $file_path, $file_name = '') {
    if ($file_name === '') {
        $file_name = basename($file_path);
    }
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        return array('success' => false, 'error' => 'QBO not configured or token failed');
    }
    $dataService = qbo_data_service($token);
    if (!$dataService) {
        return array('success' => false, 'error' => 'Could not create DataService');
    }
    $contents = @file_get_contents($file_path);
    if ($contents === false) {
        return array('success' => false, 'error' => 'Could not read file');
    }
    $mimeType = 'application/pdf';
    if (preg_match('/\.(jpe?g|gif|png)$/i', $file_name)) {
        $mimeType = 'image/jpeg';
        if (preg_match('/\.png$/i', $file_name)) {
            $mimeType = 'image/png';
        } elseif (preg_match('/\.gif$/i', $file_name)) {
            $mimeType = 'image/gif';
        }
    }
    try {
        $entityRef = new \QuickBooksOnline\API\Data\IPPReferenceType(array('value' => $bill_id, 'type' => 'Bill'));
        $attachableRef = new \QuickBooksOnline\API\Data\IPPAttachableRef(array('EntityRef' => $entityRef));
        $objAttachable = new \QuickBooksOnline\API\Data\IPPAttachable();
        $objAttachable->FileName = $file_name;
        $objAttachable->AttachableRef = $attachableRef;
        $objAttachable->Category = 'Other';
        $dataService->Upload(base64_encode($contents), $file_name, $mimeType, $objAttachable);
        $error = $dataService->getLastError();
        if ($error) {
            return array('success' => false, 'error' => $error->getResponseBody() ?: $error->getOAuthHelperError() ?: 'Upload failed');
        }
        return array('success' => true);
    } catch (\Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Push a PO (status 5) to QBO as a Bill.
 * @param string $po_code
 * @return array JSON-ready result
 */
function po_qbo_push_bill($po_code) {
    $rs = getRs(
        "SELECT p.po_id, p.po_code, p.po_number, p.po_status_id, p.store_id, p.vendor_id, p.r_subtotal, p.date_received, p.invoice_filename, p.invoice_number, s.db AS store_db " .
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
            'dialog' => array('url' => 'po-qbo-map-vendor', 'title' => 'Map vendor to QuickBooks', 'a' => null, 'c' => $po_code),
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
    $doc_number = !empty($po['invoice_number']) ? trim($po['invoice_number']) : ('PO ' . $po['po_number']);
    $result = qbo_create_bill($store_id, $qbo_id, $subtotal, $discounts, $doc_number, $txn_date);
    if (!$result['success']) {
        return array('success' => false, 'response' => $result['error']);
    }
    $bill_id = $result['BillId'];
    $attached = false;
    if (!empty($po['invoice_filename']) && defined('MEDIA_PATH')) {
        $invoice_path = MEDIA_PATH . 'po/' . $po['invoice_filename'];
        if (is_file($invoice_path)) {
            $attach_result = qbo_attach_file_to_bill($store_id, $bill_id, $invoice_path, $po['invoice_filename']);
            $attached = !empty($attach_result['success']);
            if (!$attached && !empty($attach_result['error'])) {
                return array(
                    'success' => true,
                    'response' => 'Bill created in QuickBooks (Bill #' . $bill_id . '). Invoice PDF could not be attached: ' . $attach_result['error'],
                    'BillId' => $bill_id,
                );
            }
        }
    }
    return array(
        'success' => true,
        'response' => 'Bill created in QuickBooks (Bill #' . $bill_id . ').' . ($attached ? ' Invoice PDF attached.' : ''),
        'BillId' => $bill_id,
    );
}
