<?php
/**
 * List QBO vendors for a store (for mapping dropdown).
 * POST: store_id
 */
$GLOBALS['qbo_vendors_response_sent'] = false;
register_shutdown_function(function () {
    if (!empty($GLOBALS['qbo_vendors_response_sent'])) {
        return;
    }
    $err = error_get_last();
    if (!$err || !in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'vendors' => array(),
        'error' => 'PHP fatal: ' . $err['message'],
        'error_detail' => isset($err['file']) ? $err['file'] . ':' . $err['line'] : '',
    ));
});

require_once(__DIR__ . '/../_config.php');
header('Content-Type: application/json');

$store_id = getVarInt('store_id', 0, 0, 99999);
if (!$store_id) {
    $GLOBALS['qbo_vendors_response_sent'] = true;
    echo json_encode(array('success' => false, 'vendors' => array(), 'error' => 'Missing or invalid store_id'));
    exit;
}
require_once(BASE_PATH . 'inc/qbo.php');
try {
    $out = qbo_list_vendors($store_id);
    $GLOBALS['qbo_vendors_response_sent'] = true;
    echo json_encode($out);
} catch (Throwable $e) {
    $GLOBALS['qbo_vendors_response_sent'] = true;
    echo json_encode(array(
        'success' => false,
        'vendors' => array(),
        'error' => $e->getMessage(),
        'error_detail' => $e->getFile() . ':' . $e->getLine(),
    ));
}
exit;
