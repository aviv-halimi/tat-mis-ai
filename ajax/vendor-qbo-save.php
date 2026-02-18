<?php
/**
 * Save vendor QBO mapping for a store.
 * POST: store_id, vendor_id, qbo_vendor_id
 */
require_once('../_config.php');
header('Content-Type: application/json; charset=utf-8');

function sendJson($data) {
    echo json_encode($data);
    exit;
}

// Allow any valid store/vendor id (getVarInt defaults to max 1 which breaks multi-store)
$store_id = getVarInt('store_id', 0, 0, 99999);
$vendor_id = getVarInt('vendor_id', 0, 0, 999999);
$qbo_vendor_id = trim(getVar('qbo_vendor_id'));

if (!$store_id || !$vendor_id || $qbo_vendor_id === '') {
    sendJson(array('success' => false, 'response' => 'Missing store, vendor, or QBO vendor.'));
}

$rs = getRs("SELECT db FROM store WHERE store_id = ? AND " . is_enabled(), array($store_id));
$r = getRow($rs);
if (!$r) {
    sendJson(array('success' => false, 'response' => 'Store not found.'));
}
$db = preg_replace('/[^a-z0-9_]/i', '', $r['db']);
if ($db === '') {
    sendJson(array('success' => false, 'response' => 'Invalid store database.'));
}

try {
    global $dbconn;
    $table = "`{$db}`.`vendor`";
    // Try vendor_id first (most store DBs); fallback to id if no row updated
    $stmt = $dbconn->prepare("UPDATE {$table} SET QBO_ID = ? WHERE vendor_id = ?");
    $stmt->execute(array($qbo_vendor_id, $vendor_id));
    $rows = $stmt->rowCount();
    if ($rows === 0) {
        $stmt = $dbconn->prepare("UPDATE {$table} SET QBO_ID = ? WHERE id = ?");
        $stmt->execute(array($qbo_vendor_id, $vendor_id));
        $rows = $stmt->rowCount();
    }
    if ($rows === 0) {
        sendJson(array('success' => false, 'response' => 'No row updated. Check that the vendor exists in ' . $db . '.vendor and that column QBO_ID exists.'));
    }
} catch (PDOException $e) {
    sendJson(array('success' => false, 'response' => 'Database error: ' . $e->getMessage()));
}

sendJson(array('success' => true, 'response' => 'Mapping saved.'));
