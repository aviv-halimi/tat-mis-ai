<?php
/**
 * Add or update a payment_terms row in a store's DB (QBO mapping).
 * POST: store_id, min_days, max_days, qbo_term_id, qbo_term_name [, id for update ]
 */
require_once('../_config.php');
header('Content-Type: application/json; charset=utf-8');

$store_id = getVarInt('store_id', 0, 0, 99999);
$min_days = getVarInt('min_days', 0, 0, 9999);
$max_days = getVarInt('max_days', 0, 0, 9999);
$qbo_term_id = trim(getVar('qbo_term_id'));
$qbo_term_name = trim(getVar('qbo_term_name'));
$id = getVarInt('id', 0, 0, 999999);

if (!$store_id) {
    echo json_encode(array('success' => false, 'response' => 'Missing store.'));
    exit;
}
if ($qbo_term_id === '') {
    echo json_encode(array('success' => false, 'response' => 'Please select a QBO payment term.'));
    exit;
}
if ($min_days > $max_days) {
    echo json_encode(array('success' => false, 'response' => 'Min days cannot be greater than max days.'));
    exit;
}

$rs = getRs("SELECT db FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
$r = getRow($rs);
if (!$r) {
    echo json_encode(array('success' => false, 'response' => 'Store not found.'));
    exit;
}
$db = preg_replace('/[^a-z0-9_]/i', '', $r['db']);
if ($db === '') {
    echo json_encode(array('success' => false, 'response' => 'Invalid store database.'));
    exit;
}

try {
    global $dbconn;
    $table = "`{$db}`.`payment_terms`";
    if ($id > 0) {
        $stmt = $dbconn->prepare("UPDATE {$table} SET min_days = ?, max_days = ?, qbo_term_id = ?, qbo_term_name = ? WHERE id = ?");
        $stmt->execute(array($min_days, $max_days, $qbo_term_id, $qbo_term_name, $id));
    } else {
        $stmt = $dbconn->prepare("INSERT INTO {$table} (is_enabled, is_active, min_days, max_days, qbo_term_id, qbo_term_name, date_created) VALUES (1, 1, ?, ?, ?, ?, NOW())");
        $stmt->execute(array($min_days, $max_days, $qbo_term_id, $qbo_term_name));
    }
} catch (PDOException $e) {
    echo json_encode(array('success' => false, 'response' => 'Database error: ' . $e->getMessage()));
    exit;
}
echo json_encode(array('success' => true, 'response' => $id > 0 ? 'Updated.' : 'Added.'));
exit;
