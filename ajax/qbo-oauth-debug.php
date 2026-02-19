<?php
/**
 * QBO OAuth debug: show exact redirect_uri and authorization URL we send (no secrets).
 * GET store_id. Use this to compare with Intuit portal and troubleshoot "undefined didn't connect".
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Content-Type: text/html; charset=utf-8');

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if ($store_id <= 0) {
    echo '<!DOCTYPE html><html><body><p>Missing store_id.</p></body></html>';
    exit;
}

$status6_row = getRow(getRs("SELECT module_code FROM po_status WHERE po_status_id = 6"));
if (!$status6_row || !$_Session->HasModulePermission($status6_row['module_code'])) {
    echo '<!DOCTYPE html><html><body><p>No permission.</p></body></html>';
    exit;
}

$client_id    = defined('QBO_CLIENT_ID') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
$client_secret = defined('QBO_CLIENT_SECRET') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
$redirect_uri = trim(defined('QBO_REDIRECT_URI') ? QBO_REDIRECT_URI : (getenv('QBO_REDIRECT_URI') ?: ''));
$redirect_uri = rtrim($redirect_uri, '/');

$debug = array(
    'store_id' => $store_id,
    'client_id_set' => $client_id !== '',
    'client_id_length' => strlen($client_id),
    'client_id_prefix' => $client_id !== '' ? substr($client_id, 0, 8) . '...' : '(not set)',
    'redirect_uri_raw' => $redirect_uri,
    'redirect_uri_length' => strlen($redirect_uri),
    'redirect_uri_hex' => $redirect_uri !== '' ? bin2hex($redirect_uri) : '(empty)',
    'scope' => 'com.intuit.quickbooks.accounting',
    'state' => (string)$store_id,
);
$debug['client_id_looks_like_google'] = (strlen($client_id) >= 6 && substr($client_id, 0, 6) === 'AIzaSy');

if (!class_exists('QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper', false)) {
    $autoload = BASE_PATH . 'vendor/autoload.php';
    $debug['sdk_autoload_path'] = $autoload;
    $debug['sdk_autoload_exists'] = is_file($autoload);
    if (is_file($autoload)) require_once $autoload;
}
$debug['sdk_class_loaded'] = class_exists('QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper', false);

if ($client_id !== '' && $redirect_uri !== '' && class_exists('QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper', false)) {
    try {
        $oauth2Helper = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper($client_id, $client_secret ?: 'dummy', $redirect_uri, $debug['scope'], $debug['state']);
        $authUrl = $oauth2Helper->getAuthorizationCodeURL();
        $debug['authorization_url_full'] = $authUrl;
        $parsed = parse_url($authUrl);
        $debug['authorization_host'] = isset($parsed['host']) ? $parsed['host'] : '';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
            $debug['authorization_params'] = $params;
            $debug['redirect_uri_in_url'] = isset($params['redirect_uri']) ? $params['redirect_uri'] : '(missing)';
            $debug['redirect_uri_match'] = isset($params['redirect_uri']) && $params['redirect_uri'] === $redirect_uri;
        }
    } catch (Exception $e) {
        $debug['authorization_url_full'] = '(error: ' . $e->getMessage() . ')';
    }
} else {
    $debug['authorization_url_full'] = '(could not build - check client_id, redirect_uri, SDK)';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QBO OAuth debug</title>
    <style>
        body { font-family: sans-serif; padding: 1.5rem; max-width: 900px; }
        pre { background: #f5f5f5; padding: 1rem; overflow-x: auto; font-size: 12px; }
        .ok { color: green; }
        .warn { color: #b8860b; }
        .err { color: #c00; }
        h2 { margin-top: 1.5rem; }
        a { color: #06c; }
    </style>
</head>
<body>
    <h1>QBO OAuth debug (store_id=<?php echo (int)$store_id; ?>)</h1>

    <?php if (!empty($debug['client_id_looks_like_google'])) { ?>
    <div style="background:#fff3cd; border:1px solid #ffc107; padding:1rem; margin-bottom:1rem;">
        <strong>Wrong client ID.</strong> Your <code>QBO_CLIENT_ID</code> starts with <code>AIzaSy</code> — that is a <strong>Google</strong> API key, not an Intuit QuickBooks client ID. Intuit IDs usually look like <code>AB...</code> or <code>Q0...</code>. Set <code>QBO_CLIENT_ID</code> and <code>QBO_CLIENT_SECRET</code> in <code>_config.php</code> to your app’s values from the Intuit Developer Portal (Keys & credentials). If they are commented out, uncomment them.
    </div>
    <?php } ?>

    <p>Use this to make sure the <strong>Redirect URI</strong> matches exactly what you added in the Intuit Developer Portal (Keys & credentials → Redirect URIs).</p>

    <h2>1. Redirect URI we send</h2>
    <p>Copy this <strong>exactly</strong> and compare to Intuit portal (character-by-character, including <code>http</code> vs <code>https</code>):</p>
    <pre><?php echo htmlspecialchars($debug['redirect_uri_raw'] ?: '(not set)'); ?></pre>
    <p>Length: <strong><?php echo $debug['redirect_uri_length']; ?></strong> characters.</p>

    <h2>2. Full authorization URL</h2>
    <p>This is the URL we redirect to when you click "Connect to QuickBooks". You can open it in a new tab to test:</p>
    <pre><?php echo htmlspecialchars($debug['authorization_url_full']); ?></pre>
    <?php if (!empty($debug['authorization_url_full']) && strpos($debug['authorization_url_full'], 'http') === 0) { ?>
    <p><a href="<?php echo htmlspecialchars($debug['authorization_url_full']); ?>" target="_blank" rel="noopener">Open this URL in a new tab</a></p>
    <?php } ?>

    <?php if (isset($debug['redirect_uri_in_url'])) { ?>
    <h2>3. Redirect URI inside the auth URL</h2>
    <p>After encoding, the <code>redirect_uri</code> parameter in the auth URL is:</p>
    <pre><?php echo htmlspecialchars($debug['redirect_uri_in_url']); ?></pre>
    <p>Matches config: <?php echo !empty($debug['redirect_uri_match']) ? '<span class="ok">Yes</span>' : '<span class="err">No – check encoding</span>'; ?></p>
    <?php } ?>

    <h2>4. Common causes of "undefined didn't connect"</h2>
    <ul>
        <li><strong>Redirect URI mismatch</strong> – In Intuit: <a href="https://developer.intuit.com" target="_blank" rel="noopener">developer.intuit.com</a> → Your app → Keys & credentials. Under <em>Redirect URIs</em>, add the <strong>exact</strong> value from section 1 above (no trailing slash unless you use it in config).</li>
        <li><strong>Development vs Production</strong> – If you use <em>Development</em> keys, the redirect URI must be listed under the Development section. Production keys use the Production redirect list.</li>
        <li><strong>http vs https</strong> – If your site is <code>http://</code>, the redirect URI must be <code>http://...</code> in both config and Intuit. No mixing.</li>
        <li><strong>App not in "Development"</strong> – For testing, use Development keys and ensure the app environment matches.</li>
    </ul>

    <h2>5. Full debug (JSON)</h2>
    <pre><?php echo htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>

    <p><a href="javascript:history.back()">← Back</a></p>
</body>
</html>
