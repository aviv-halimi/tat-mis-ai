<?php
require_once('../_config.php');
header('Content-Type: application/json');

// Prefer POST (jQuery sends form data via POST)
$store_id  = isset($_POST['store_id'])  ? (int)$_POST['store_id']  : (int)getVar('store_id');
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : trim(getVar('start_date'));

if (!$store_id) {
    echo json_encode(array('success' => false, 'error' => 'Invalid store_id'));
    exit;
}

if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    echo json_encode(array('success' => false, 'error' => 'Invalid date format — expected YYYY-MM-DD'));
    exit;
}

$value = ($start_date !== '' ? $start_date : null);

try {
    // Use qualified table name in case default DB differs
    dbUpdate('store', array('qbo_tb_start_date' => $value), $store_id, 'store_id');
    // Verify it stuck
    $rs = getRs("SELECT qbo_tb_start_date FROM store WHERE store_id = ?", array($store_id));
    $row = $rs && isset($rs[0]) ? $rs[0] : null;
    $got = $row ? trim((string)($row['qbo_tb_start_date'] ?? '')) : null;
    if ($got === '') $got = null;
    $saved = ($value === null && $got === null) || ($value !== null && $got === $value);
    if (!$saved) {
        echo json_encode(array('success' => false, 'error' => 'Update did not persist. Check database.'));
        exit;
    }
    echo json_encode(array('success' => true));
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'error' => $e->getMessage()));
}
exit;
