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
 * List payment terms (Term) from QBO for a store.
 * @param int $store_id
 * @return array { success, terms: [ { id, Name } ], error }
 */
function qbo_list_terms($store_id) {
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        $err = (is_array($token) && isset($token['error'])) ? $token['error'] : 'QBO not configured or token failed';
        return array('success' => false, 'terms' => array(), 'error' => $err);
    }
    try {
        $dataService = qbo_data_service($token);
        if (!$dataService) {
            return array('success' => false, 'terms' => array(), 'error' => 'Could not create DataService');
        }
        $termsResult = $dataService->Query('SELECT * FROM Term MAXRESULTS 100');
        $error = $dataService->getLastError();
        if ($error) {
            return array(
                'success' => false,
                'terms' => array(),
                'error' => $error->getResponseBody() ?: $error->getOAuthHelperError() ?: 'QBO API error',
            );
        }
        $terms = array();
        if (is_array($termsResult)) {
            foreach ($termsResult as $t) {
                $id = is_object($t) ? (isset($t->Id) ? $t->Id : null) : (isset($t['Id']) ? $t['Id'] : null);
                $name = is_object($t) ? (isset($t->Name) ? $t->Name : 'Term ' . $id) : (isset($t['Name']) ? $t['Name'] : 'Term ' . $id);
                if ($id !== null) {
                    $terms[] = array('id' => $id, 'Name' => $name);
                }
            }
        }
        return array('success' => true, 'terms' => $terms);
    } catch (\Exception $e) {
        return array('success' => false, 'terms' => array(), 'error' => $e->getMessage());
    }
}

/**
 * Get the display name of a QBO Term by Id.
 * @param int $store_id
 * @param string $term_id QBO Term Id
 * @return string Term name or empty string on failure
 */
function qbo_get_term_name($store_id, $term_id) {
    if ($term_id === '' || $term_id === null) {
        return '';
    }
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        return '';
    }
    try {
        $dataService = qbo_data_service($token);
        if (!$dataService) {
            return '';
        }
        $term = $dataService->FindById('Term', $term_id);
        $error = $dataService->getLastError();
        if ($error || !$term) {
            return '';
        }
        $name = is_object($term) ? (isset($term->Name) ? $term->Name : '') : (isset($term['Name']) ? $term['Name'] : '');
        return $name !== null ? trim((string)$name) : '';
    } catch (\Exception $e) {
        return '';
    }
}

/**
 * Look up QBO Term Id for a store by payment_terms (days). Uses store's payment_terms table (min_days <= days <= max_days).
 * @param string $store_db Store database name (e.g. blaze1)
 * @param int|null $payment_terms_days PO payment_terms (days) or null
 * @return array { qbo_term_id => string|null, qbo_term_name => string }
 */
function qbo_lookup_payment_term($store_db, $payment_terms_days) {
    $out = array('qbo_term_id' => null, 'qbo_term_name' => '');
    if ($payment_terms_days === null || $payment_terms_days === '') {
        return $out;
    }
    $days = (int)$payment_terms_days;
    $db = preg_replace('/[^a-z0-9_]/i', '', $store_db);
    if ($db === '') {
        return $out;
    }
    $rs = getRs(
        "SELECT qbo_term_id, qbo_term_name FROM {$db}.payment_terms WHERE " . is_enabled() . " AND ? BETWEEN min_days AND max_days ORDER BY min_days DESC LIMIT 1",
        array($days)
    );
    $row = getRow($rs);
    if ($row && !empty($row['qbo_term_id'])) {
        $out['qbo_term_id'] = trim($row['qbo_term_id']);
        $out['qbo_term_name'] = trim(isset($row['qbo_term_name']) ? $row['qbo_term_name'] : '');
    }
    return $out;
}

/**
 * Extract TermRef value from a vendor entity (object or array from QBO).
 * Handles TermRef/termRef and value/Value for different serialization formats.
 * @param object|array $vendor Vendor entity from FindById or Query
 * @return string|null Term id or null
 */
function qbo_vendor_term_ref_value($vendor) {
    if ($vendor === null) {
        return null;
    }
    $termRef = null;
    if (is_object($vendor)) {
        if (isset($vendor->TermRef)) {
            $termRef = $vendor->TermRef;
        } elseif (isset($vendor->termRef)) {
            $termRef = $vendor->termRef;
        }
    } else {
        if (isset($vendor['TermRef'])) {
            $termRef = $vendor['TermRef'];
        } elseif (isset($vendor['termRef'])) {
            $termRef = $vendor['termRef'];
        }
    }
    if ($termRef === null) {
        return null;
    }
    if (is_scalar($termRef)) {
        $val = trim((string)$termRef);
        return $val === '' ? null : $val;
    }
    $value = null;
    if (is_object($termRef)) {
        if (isset($termRef->value)) {
            $value = $termRef->value;
        } elseif (isset($termRef->Value)) {
            $value = $termRef->Value;
        }
    } else {
        if (isset($termRef['value'])) {
            $value = $termRef['value'];
        } elseif (isset($termRef['Value'])) {
            $value = $termRef['Value'];
        }
    }
    if ($value === null || trim((string)$value) === '') {
        return null;
    }
    return trim((string)$value);
}

/**
 * Get the default payment term (TermRef) for a vendor in QBO.
 * Tries FindById first, then Query (SELECT TermRef FROM Vendor WHERE Id = ?) if needed.
 * @param int $store_id
 * @param string $vendor_ref_id QBO Vendor Id
 * @return array { success, term_ref_id => string|null, error?, _debug? }
 */
function qbo_get_vendor_term_ref($store_id, $vendor_ref_id) {
    $out = array('success' => true, 'term_ref_id' => null);
    if ($vendor_ref_id === '' || $vendor_ref_id === null) {
        return $out;
    }
    $vendor_ref_id = trim((string)$vendor_ref_id);
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        return array('success' => false, 'term_ref_id' => null, 'error' => 'QBO not configured or token failed');
    }
    try {
        $dataService = qbo_data_service($token);
        if (!$dataService) {
            return array('success' => false, 'term_ref_id' => null, 'error' => 'Could not create DataService');
        }

        $termId = null;
        $vendor = $dataService->FindById('Vendor', $vendor_ref_id);
        $error = $dataService->getLastError();
        if ($error) {
            return array('success' => false, 'term_ref_id' => null, 'error' => $error->getResponseBody() ?: $error->getOAuthHelperError() ?: 'QBO API error');
        }
        if ($vendor) {
            $termId = qbo_vendor_term_ref_value($vendor);
        }

        if ($termId === null && $dataService) {
            $safe_id = str_replace("'", "''", $vendor_ref_id);
            $query = "SELECT TermRef FROM Vendor WHERE Id = '" . $safe_id . "'";
            $results = $dataService->Query($query);
            if ($dataService->getLastError()) {
                $results = null;
            }
            if ($results !== null) {
                if (is_array($results) && !empty($results)) {
                    $first = isset($results[0]) ? $results[0] : reset($results);
                    $termId = qbo_vendor_term_ref_value($first);
                } else {
                    $termId = qbo_vendor_term_ref_value($results);
                }
            }
        }

        if ($termId !== null) {
            $out['term_ref_id'] = $termId;
        }
        if (defined('QBO_DEBUG_VENDOR_TERM') && QBO_DEBUG_VENDOR_TERM && $termId === null && $vendor) {
            $out['_debug'] = array(
                'vendor_id' => $vendor_ref_id,
                'has_vendor' => true,
                'term_ref_raw' => is_object($vendor) ? (isset($vendor->TermRef) ? $vendor->TermRef : null) : (isset($vendor['TermRef']) ? $vendor['TermRef'] : null),
            );
        }
        return $out;
    } catch (\Exception $e) {
        return array('success' => false, 'term_ref_id' => null, 'error' => $e->getMessage());
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
 * @param string|null $private_note Optional note on the bill (e.g. "Added via MIS by {name}")
 * @param string|null $sales_term_ref_id QBO Term Id for payment terms (SalesTermRef)
 * @return array { success, BillId, error }
 */
function qbo_create_bill($store_id, $vendor_ref_id, $subtotal, $discounts, $doc_number = '', $txn_date = null, $private_note = null, $sales_term_ref_id = null) {
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
    if ($private_note !== null && $private_note !== '') {
        $payload['PrivateNote'] = $private_note;
    }
    if ($sales_term_ref_id !== null && $sales_term_ref_id !== '') {
        $payload['SalesTermRef'] = array('value' => $sales_term_ref_id);
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

/** Store vendor.id values (vendor table "id" column) that use DocNumber = LEFT(invoice_number + '-' + po_name, 21); others use LEFT(invoice_number, 21). */
define('QBO_DOCNUMBER_WITH_PO_NAME_IDS', '68cdcf401c44c0b22a777c91,5dcf89fb002f09082a7558ba,606f4dacabc6dc08d64b3206,68ed31e7b9121ceb14aa573f,682b62fc91e28c51768aa8c1,67c6279d2de2587fdc6c47df,5dd8834bf6afa10828aa099a,691fc20c8377f73fcd2cb401,65cbc54907f5504f1ec21c60,6807b5fa0175b724c820b0d3,65ca7c44a9ebc20d1cb8e727,66f42feaa3a7187435acbece658b221c2579c43e0c249559');

/**
 * Push a PO (status 5) to QBO as a Bill.
 * @param string $po_code
 * @return array JSON-ready result
 */
function po_qbo_push_bill($po_code) {
    $rs = getRs(
        "SELECT p.po_id, p.po_code, p.po_number, p.po_name, p.po_status_id, p.store_id, p.vendor_id, p.r_subtotal, p.date_received, p.invoice_filename, p.invoice_number, p.payment_terms, s.db AS store_db " .
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

    $vendor_rs = getRs("SELECT vendor_id, id, name, QBO_ID FROM {$store_db}.vendor WHERE vendor_id = ?", array($po_vendor_id));
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
    $invoice_num = trim(!empty($po['invoice_number']) ? $po['invoice_number'] : '');
    $po_name = trim(!empty($po['po_name']) ? $po['po_name'] : '');
    $vendor_table_id = isset($vendor['id']) ? trim((string)$vendor['id']) : '';
    $use_po_name = $vendor_table_id !== '' && in_array($vendor_table_id, array_map('trim', explode(',', QBO_DOCNUMBER_WITH_PO_NAME_IDS)), true);
    if ($use_po_name && $invoice_num !== '' && $po_name !== '') {
        $doc_number = mb_substr($invoice_num . '-' . $po_name, 0, 21);
    } else {
        $doc_number = $invoice_num !== '' ? mb_substr($invoice_num, 0, 21) : ('PO ' . $po['po_number']);
    }
    $private_note = '';
    if (function_exists('getAdminName') && !empty($GLOBALS['_Session']->admin_id)) {
        $admin_name = getAdminName($GLOBALS['_Session']->admin_id);
        if ($admin_name !== '') {
            $private_note = 'Added via MIS by ' . $admin_name;
        }
    }
    $vendor_term = qbo_get_vendor_term_ref($store_id, $qbo_id);
    $sales_term_ref_id = (!empty($vendor_term['success']) && !empty($vendor_term['term_ref_id'])) ? $vendor_term['term_ref_id'] : null;
    $result = qbo_create_bill($store_id, $qbo_id, $subtotal, $discounts, $doc_number, $txn_date, $private_note, $sales_term_ref_id);
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
                qbo_po_note_bill_created($po['po_id'], $bill_id, $subtotal - $discounts, $store_id);
                if (function_exists('dbUpdate')) {
                    dbUpdate('po', array('qbo_bill_id' => $bill_id), $po['po_id']);
                }
                return array(
                    'success' => true,
                    'response' => 'Bill created in QuickBooks (Bill #' . $bill_id . '). Invoice PDF could not be attached: ' . $attach_result['error'],
                    'BillId' => $bill_id,
                );
            }
        }
    }
    qbo_po_note_bill_created($po['po_id'], $bill_id, $subtotal - $discounts, $store_id);
    if (function_exists('dbUpdate')) {
        dbUpdate('po', array('qbo_bill_id' => $bill_id), $po['po_id']);
    }
    return array(
        'success' => true,
        'response' => 'Bill created in QuickBooks (Bill #' . $bill_id . ').' . ($attached ? ' Invoice PDF attached.' : ''),
        'BillId' => $bill_id,
    );
}

/**
 * Get the QBO app URL to open a bill (for use in PO page link).
 * @param int $store_id
 * @param string $bill_id QBO Bill Id
 * @return string URL or empty if realm not available
 */
function qbo_bill_url($store_id, $bill_id) {
    if ($bill_id === '' || $bill_id === null) {
        return '';
    }
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['realm_id'])) {
        return '';
    }
    return 'https://qbo.intuit.com/app/company/' . $token['realm_id'] . '/bill?txnId=' . urlencode($bill_id);
}

/**
 * Add a PO note (Files/Notes) when a QBO bill is created: Bill ID, invoice total, and link to bill if realm available.
 * @param int $po_id
 * @param string $bill_id QBO Bill Id
 * @param float $invoice_total subtotal - discounts
 * @param int $store_id for realm_id / link
 */
function qbo_po_note_bill_created($po_id, $bill_id, $invoice_total, $store_id) {
    $lines = array(
        'QBO Bill created.',
        'Bill ID: ' . $bill_id,
        'Invoice total: $' . number_format((float)$invoice_total, 2),
    );
    $token = qbo_get_access_token($store_id);
    if (is_array($token) && !empty($token['realm_id'])) {
        $lines[] = 'Bill (open in QBO): https://qbo.intuit.com/app/company/' . $token['realm_id'] . '/bill?txnId=' . urlencode($bill_id);
    }
    $description = implode("\n", $lines);
    $admin_id = isset($GLOBALS['_Session']->admin_id) ? (int)$GLOBALS['_Session']->admin_id : 0;
    setRs("INSERT INTO file (re_tbl, re_id, admin_id, description, is_auto) VALUES ('po', ?, ?, ?, 1)", array($po_id, $admin_id, $description));
}
