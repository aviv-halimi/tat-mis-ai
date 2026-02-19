<?php
/**
 * Start QBO OAuth2: redirects the user to Intuit's authorization page.
 * GET store_id (required). User must be logged in; store_id is passed as state and used after callback.
 */
require_once dirname(__FILE__) . '/../_config.php';

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if ($store_id <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>Missing store_id.</p></body></html>';
    exit;
}

$status6_row = getRow(getRs("SELECT module_code FROM po_status WHERE po_status_id = 6"));
if (!$status6_row || !$_Session->HasModulePermission($status6_row['module_code'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>You do not have permission to connect QuickBooks for this store.</p></body></html>';
    exit;
}
$rs = getRs("SELECT store_id FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
if (!$rs || !getRow($rs)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>Store not found.</p></body></html>';
    exit;
}

$client_id    = defined('QBO_CLIENT_ID') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
$client_secret = defined('QBO_CLIENT_SECRET') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
$redirect_uri = defined('QBO_REDIRECT_URI') ? QBO_REDIRECT_URI : (getenv('QBO_REDIRECT_URI') ?: '');
if ($client_id === '' || $client_secret === '' || $redirect_uri === '') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>QBO OAuth not configured (QBO_CLIENT_ID, QBO_CLIENT_SECRET, QBO_REDIRECT_URI).</p></body></html>';
    exit;
}

if (!class_exists('QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper', false)) {
    $autoload = BASE_PATH . 'vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}

$scope = 'com.intuit.quickbooks.accounting';
$state = (string)$store_id;

$oauth2Helper = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper($client_id, $client_secret, $redirect_uri, $scope, $state);
$authUrl = $oauth2Helper->getAuthorizationCodeURL();
header('Location: ' . $authUrl);
exit;
