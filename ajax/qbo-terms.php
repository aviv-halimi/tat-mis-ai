<?php
/**
 * List QBO payment terms (Term) for a store (for mapping dropdown).
 * POST: store_id
 */
require_once('../_config.php');
header('Content-Type: application/json');

$store_id = getVarInt('store_id', 0, 0, 99999);
if (!$store_id) {
    echo json_encode(array('success' => false, 'terms' => array(), 'error' => 'Missing store_id'));
    exit;
}
require_once(BASE_PATH . 'inc/qbo.php');
$out = qbo_list_terms($store_id);
echo json_encode($out);
exit;
