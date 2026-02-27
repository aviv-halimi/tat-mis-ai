<?php
/**
 * Handle form POST from daily-discount-report-qbo-map-vendor modal (save mapping).
 * POST: daily_discount_report_brand_id, qbo_vendor_id[store_id]
 */
require_once('../_config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'response' => 'Method not allowed.'));
    exit;
}

$daily_discount_report_brand_id = getVarInt('daily_discount_report_brand_id', 0, 0, 999999);
$qbo_vendor_ids_raw = getVar('qbo_vendor_id');
if (!is_array($qbo_vendor_ids_raw)) {
    $qbo_vendor_ids_raw = array();
}
// Normalize to integer keys so lookup by (int)$store_id works for every store
$qbo_vendor_ids = array();
foreach ($qbo_vendor_ids_raw as $k => $v) {
    $qbo_vendor_ids[(int)$k] = is_string($v) ? trim($v) : '';
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
if (!is_array($stores) || count($stores) === 0) {
    echo json_encode(array('success' => true, 'response' => 'No stores to update.'));
    exit;
}

$updated = 0;
for ($i = 0; $i < count($stores); $i++) {
    $s = $stores[$i];
    $store_id = isset($s['store_id']) ? (int)$s['store_id'] : 0;
    $store_db = isset($s['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s['store_db']) : '';
    if ($store_id <= 0 || $store_db === '') {
        continue;
    }
    $qbo_id = isset($qbo_vendor_ids[$store_id]) ? $qbo_vendor_ids[$store_id] : '';
    if ($qbo_id !== '' && !is_string($qbo_id)) {
        $qbo_id = trim((string)$qbo_id);
    }
    $table = $store_db . '.brand';
    dbUpdate($table, array('qbo_vendor_id' => $qbo_id === '' ? null : $qbo_id), $brand_id, 'brand_id');
    $updated++;
}

echo json_encode(array('success' => true, 'response' => 'Mapping saved.', 'updated' => $updated));
exit;
