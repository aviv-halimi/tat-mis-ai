<?php
/**
 * Save PDF/Excel format preference for a brand (daily discount report row).
 * POST: daily_discount_report_brand_id, excel_report (0 = PDF, 1 = Excel)
 * Updates blaze1.brand.excel_report.
 */
require_once(__DIR__ . '/../_config.php');
header('Content-Type: application/json');

$daily_discount_report_brand_id = isset($_POST['daily_discount_report_brand_id']) ? (int)$_POST['daily_discount_report_brand_id'] : 0;
$excel_report = isset($_POST['excel_report']) ? (int)$_POST['excel_report'] : -1;
if ($daily_discount_report_brand_id < 1) $daily_discount_report_brand_id = 0;
if ($excel_report !== 0 && $excel_report !== 1) $excel_report = -1;

if (!$daily_discount_report_brand_id) {
    echo json_encode(array('success' => false, 'response' => 'Missing report brand.'));
    exit;
}
if ($excel_report < 0) {
    echo json_encode(array('success' => false, 'response' => 'Missing or invalid excel_report (0 or 1).'));
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

$col = getRow(getRs(
    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'blaze1' AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'excel_report'"
));
if (!$col) {
    echo json_encode(array('success' => false, 'response' => 'Column blaze1.brand.excel_report not found. Run migration.'));
    exit;
}

setRs("UPDATE blaze1.brand SET excel_report = ? WHERE brand_id = ?", array($excel_report, $brand_id));
echo json_encode(array('success' => true, 'response' => 'Format saved.'));
exit;
