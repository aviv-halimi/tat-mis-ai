<?php
/**
 * Save vendor → QBO mapping and push the PO to QuickBooks (called from map-vendor modal).
 * POST: c, vendor_id, qbo_vendor_id
 */
require_once('../_config.php');
header('Content-Type: application/json');

$po_code = getVar('c');
$vendor_id = getVarInt('vendor_id', 0, 0, 999999);
$qbo_vendor_id = trim(getVar('qbo_vendor_id'));

if (!$po_code || !$vendor_id || $qbo_vendor_id === '') {
    echo json_encode(array('success' => false, 'response' => 'Missing PO code, vendor, or QBO vendor.'));
    exit;
}

$rs = getRs("SELECT p.vendor_id, s.db AS store_db FROM po p INNER JOIN store s ON s.store_id = p.store_id WHERE p.po_code = ? AND " . is_enabled('p,s'), array($po_code));
$po = getRow($rs);
$po_vendor_id = isset($po['vendor_id']) ? (int)$po['vendor_id'] : (isset($po['Vendor_id']) ? (int)$po['Vendor_id'] : null);
if (!$po || $po_vendor_id === null || $po_vendor_id !== (int)$vendor_id) {
    echo json_encode(array('success' => false, 'response' => 'PO or vendor mismatch.'));
    exit;
}
$db = preg_replace('/[^a-z0-9_]/i', '', $po['store_db']);
if ($db === '') {
    echo json_encode(array('success' => false, 'response' => 'Invalid store database.'));
    exit;
}
$table = $db . '.vendor';
dbUpdate($table, array('QBO_ID' => $qbo_vendor_id), $vendor_id, 'vendor_id');
//dbUpdate($table, array('QBO_ID' => $qbo_vendor_id), $vendor_id, 'id');
$check = getRow(getRs("SELECT 1 FROM {$table} WHERE (vendor_id = ? OR id = ?) AND QBO_ID = ?", array($vendor_id, $vendor_id, $qbo_vendor_id)));
if (!$check) {
    echo json_encode(array('success' => false, 'response' => 'No row updated. Check that the vendor exists in ' . $db . '.vendor and that column QBO_ID exists.'));
    exit;
}

require_once(BASE_PATH . 'inc/qbo.php');
$result = po_qbo_push_bill($po_code);
echo json_encode($result);
exit;
