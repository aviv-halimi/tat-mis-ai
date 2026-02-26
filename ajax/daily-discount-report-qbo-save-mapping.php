<?php
/**
 * Save brand → QBO vendor mapping per store for daily discount report push.
 * POST: daily_discount_report_brand_id, qbo_vendor_id[store_id] (array)
 */
require_once('../_config.php');
header('Content-Type: application/json');

$daily_discount_report_brand_id = getVarInt('daily_discount_report_brand_id', 0, 0, 999999);
$qbo_vendor_ids = getVar('qbo_vendor_id');
if (!is_array($qbo_vendor_ids)) {
    $qbo_vendor_ids = array();
}

if (!$daily_discount_report_brand_id) {
    echo json_encode(array('success' => false, 'response' => 'Missing report brand.'));
    exit;
}

$rb = getRow(getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id FROM daily_discount_report_brand rb WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb'),
    array($daily_discount_report_brand_id)
));
if (!$rb) {
    echo json_encode(array('success' => false, 'response' => 'Report brand not found.'));
    exit;
}
$brand_id = (int)$rb['brand_id'];

$stores = getRs(
    "SELECT s.store_id, s.db AS store_db FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s'),
    array($daily_discount_report_brand_id)
);
if (!$stores) {
    echo json_encode(array('success' => true, 'response' => 'No stores to update.'));
    exit;
}

$updated = 0;
foreach ($stores as $s) {
    $store_id = (int)$s['store_id'];
    $store_db = isset($s['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s['store_db']) : '';
    if ($store_db === '') {
        continue;
    }
    $qbo_id = isset($qbo_vendor_ids[$store_id]) ? trim((string)$qbo_vendor_ids[$store_id]) : '';
    $table = $store_db . '.brand';
    dbUpdate($table, array('qbo_vendor_id' => $qbo_id === '' ? null : $qbo_id), $brand_id, 'brand_id');
    $updated++;
}

echo json_encode(array('success' => true, 'response' => 'Mapping saved.', 'updated' => $updated));
exit;
