<?php
/**
 * Push PO (status 5) to QuickBooks Online as a Bill.
 * POST: c = po_code [, action = 'save_mapping', vendor_id, qbo_vendor_id ]
 */
require_once('../_config.php');
header('Content-Type: application/json');

$po_code = getVar('c');
$action = getVar('action');
$vendor_id = getVarInt('vendor_id');
$qbo_vendor_id = getVar('qbo_vendor_id');

if (!$po_code) {
    echo json_encode(array('success' => false, 'response' => 'Missing PO code'));
    exit;
}

require_once(BASE_PATH . 'inc/qbo.php');

if ($action === 'save_mapping' && $vendor_id && $qbo_vendor_id !== '') {
    $rs = getRs("SELECT p.store_id, p.vendor_id, s.db AS store_db FROM po p INNER JOIN store s ON s.store_id = p.store_id WHERE p.po_code = ? AND " . is_enabled('p,s'), array($po_code));
    $po = getRow($rs);
    if (!$po || (int)$po['vendor_id'] !== $vendor_id) {
        echo json_encode(array('success' => false, 'response' => 'PO or vendor mismatch.'));
        exit;
    }
    $db = preg_replace('/[^a-z0-9_]/i', '', $po['store_db']);
    if ($db !== '') {
        dbUpdate($db . '.vendor', array('QBO_ID' => $qbo_vendor_id), $vendor_id, 'vendor_id');
    }
}

$result = po_qbo_push_bill($po_code);
echo json_encode($result);
exit;
