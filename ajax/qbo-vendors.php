<?php
/**
 * List QBO vendors for a store (for mapping dropdown).
 * POST: store_id
 */
require_once('../_config.php');
header('Content-Type: application/json');

$store_id = getVarInt('store_id');
if (!$store_id) {
    echo json_encode(array('success' => false, 'vendors' => array()));
    exit;
}
require_once(BASE_PATH . 'inc/qbo.php');
$out = qbo_list_vendors($store_id);
echo json_encode($out);
exit;
