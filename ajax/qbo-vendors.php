<?php
/**
 * List QBO vendors for a store (for mapping dropdown).
 * POST: store_id
 */
require_once(__DIR__ . '/../_config.php');
header('Content-Type: application/json');

$store_id = getVarInt('store_id', 0, 0, 99999);
if (!$store_id) {
    echo json_encode(array('success' => false, 'vendors' => array(), 'error' => 'Missing or invalid store_id'));
    exit;
}
require_once(BASE_PATH . 'inc/qbo.php');
try {
    $out = qbo_list_vendors($store_id);
    echo json_encode($out);
} catch (Throwable $e) {
    echo json_encode(array(
        'success' => false,
        'vendors' => array(),
        'error' => $e->getMessage(),
        'error_detail' => $e->getFile() . ':' . $e->getLine(),
    ));
}
exit;
