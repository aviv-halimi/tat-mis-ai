<?php
/**
 * Save vendor QBO mapping for a store.
 * POST: store_id, vendor_id, qbo_vendor_id
 */
require_once('../_config.php');
header('Content-Type: application/json');

// Allow any valid store/vendor id (getVarInt defaults to max 1 which breaks multi-store)
$store_id = getVarInt('store_id', 0, 0, 99999);
$vendor_id = getVarInt('vendor_id', 0, 0, 999999);
$qbo_vendor_id = trim(getVar('qbo_vendor_id'));

if (!$store_id || !$vendor_id || $qbo_vendor_id === '') {
    echo json_encode(array('success' => false, 'response' => 'Missing store, vendor, or QBO vendor.'));
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
    $stmt = $dbconn->prepare("UPDATE `{$db}`.`vendor` SET `QBO_ID` = ? WHERE `vendor_id` = ?");
    $stmt->execute(array($qbo_vendor_id, $vendor_id));
    $rows = $stmt->rowCount();
    if ($rows === 0) {
        echo json_encode(array('success' => false, 'response' => 'No vendor row updated. Check that vendor_id ' . (int)$vendor_id . ' exists in ' . $db . '.vendor and that column QBO_ID exists.'));
        exit;
    }
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'response' => 'Database error: ' . $e->getMessage()));
    exit;
}

echo json_encode(array('success' => true, 'response' => 'Mapping saved.'));
exit;
