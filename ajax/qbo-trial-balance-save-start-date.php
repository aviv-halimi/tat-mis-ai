<?php
require_once('../_config.php');
header('Content-Type: application/json');

$store_id = (int)getVar('store_id');
$start_date = trim(getVar('start_date'));

if (!$store_id) {
    echo json_encode(array('success' => false, 'error' => 'Invalid store_id'));
    exit;
}

if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    echo json_encode(array('success' => false, 'error' => 'Invalid date format — expected YYYY-MM-DD'));
    exit;
}

try {
    dbUpdate('store', array('qbo_tb_start_date' => ($start_date !== '' ? $start_date : null)), $store_id, 'store_id');
    echo json_encode(array('success' => true));
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'error' => $e->getMessage()));
}
exit;
