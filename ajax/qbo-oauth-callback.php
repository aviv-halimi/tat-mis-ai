<?php
/**
 * QBO OAuth2 callback: Intuit redirects here with code, state (store_id), realmId.
 * Exchanges code for tokens and saves refresh_token + realm_id to store params.
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/qbo.php';

header('Content-Type: text/html; charset=utf-8');

$code    = isset($_GET['code']) ? trim($_GET['code']) : '';
$state   = isset($_GET['state']) ? trim($_GET['state']) : '';
$realmId = isset($_GET['realmId']) ? trim($_GET['realmId']) : (isset($_GET['realm_id']) ? trim($_GET['realm_id']) : '');

if ($code === '' || $state === '' || $realmId === '') {
    echo '<!DOCTYPE html><html><head><title>QBO Connect</title></head><body><p>Missing code, state, or realmId. Please try again from the app.</p></body></html>';
    exit;
}

$store_id = (int)$state;
if ($store_id <= 0) {
    echo '<!DOCTYPE html><html><head><title>QBO Connect</title></head><body><p>Invalid state.</p></body></html>';
    exit;
}

$client_id     = defined('QBO_CLIENT_ID') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
$client_secret = defined('QBO_CLIENT_SECRET') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
$redirect_uri  = defined('QBO_REDIRECT_URI') ? QBO_REDIRECT_URI : (getenv('QBO_REDIRECT_URI') ?: '');
if ($client_id === '' || $client_secret === '' || $redirect_uri === '') {
    echo '<!DOCTYPE html><html><head><title>QBO Connect</title></head><body><p>QBO OAuth not configured.</p></body></html>';
    exit;
}

$rs = getRs("SELECT store_id FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
if (!$rs || !getRow($rs)) {
    echo '<!DOCTYPE html><html><head><title>QBO Connect</title></head><body><p>Store not found.</p></body></html>';
    exit;
}

try {
    $oauth2Helper = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper($client_id, $client_secret, $redirect_uri, 'com.intuit.quickbooks.accounting', $state);
    $accessTokenObj = $oauth2Helper->exchangeAuthorizationCodeForToken($code, $realmId);
    $refresh_token = $accessTokenObj->getRefreshToken();
    $realm_id = $accessTokenObj->getRealmID();
    if ($realm_id === '' || $realm_id === null) {
        $realm_id = $realmId;
    }
    if ($refresh_token === '' || $refresh_token === null) {
        throw new \Exception('No refresh token in response');
    }
    qbo_save_tokens($store_id, $refresh_token, $realm_id);
} catch (\Exception $e) {
    echo '<!DOCTYPE html><html><head><title>QBO Connect</title></head><body><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p><p>Please try again from the app.</p></body></html>';
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QuickBooks connected</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; text-align: center; }
        .ok { color: #0a0; }
    </style>
</head>
<body>
    <p class="ok"><strong>QuickBooks has been connected successfully.</strong></p>
    <p>You can close this window and continue in the app.</p>
    <script>
        try {
            if (window.opener) {
                window.opener.postMessage('qbo-auth-done', '*');
            }
        } catch (e) {}
        setTimeout(function() { window.close(); }, 2000);
    </script>
</body>
</html>
