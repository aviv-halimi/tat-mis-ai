<?php
/**
 * Save TB start date for an extra entity (qbo_tb_extra_entity).
 * POST: extra_entity_id, start_date (YYYY-MM-DD or empty to clear)
 */
require_once dirname(__DIR__) . '/_config.php';
header('Content-Type: application/json');

$extra_entity_id = isset($_POST['extra_entity_id']) ? (int)$_POST['extra_entity_id'] : (int)getVar('extra_entity_id');
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : trim(getVar('start_date'));

if (!$extra_entity_id) {
    echo json_encode(array('success' => false, 'error' => 'Invalid extra_entity_id'));
    exit;
}

if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    echo json_encode(array('success' => false, 'error' => 'Invalid date format — expected YYYY-MM-DD'));
    exit;
}

$value = ($start_date !== '' ? $start_date : null);

try {
    $row = getRow(getRs("SELECT id FROM qbo_tb_extra_entity WHERE id = ?", array($extra_entity_id)));
    if (!$row) {
        echo json_encode(array('success' => false, 'error' => 'Extra entity not found.'));
        exit;
    }
    dbUpdate('qbo_tb_extra_entity', array('qbo_tb_start_date' => $value), $extra_entity_id, 'id');
    $rs = getRs("SELECT qbo_tb_start_date FROM qbo_tb_extra_entity WHERE id = ?", array($extra_entity_id));
    $got = ($rs && isset($rs[0]['qbo_tb_start_date'])) ? trim((string)$rs[0]['qbo_tb_start_date']) : '';
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
