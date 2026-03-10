<?php
/**
 * Download a single store's Trial Balance as one Excel file.
 * Use: /ajax/qbo-trial-balance-download-one.php?store_id=N&end_date=YYYY-MM-DD
 * Opens in same tab or new tab; browser will download the .xlsx file.
 * Reduces timeout risk vs. downloading all stores at once.
 */
require_once(dirname(__DIR__) . '/_config.php');
require_once(BASE_PATH . 'inc/qbo.php');
require_once(BASE_PATH . 'inc/qbo-trial-balance-excel.php');

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if (!$store_id || !$end_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('Missing or invalid store_id or end_date (use YYYY-MM-DD).');
}

$stores_rs = getRs(
    "SELECT store_id, store_name, qbo_tb_start_date
       FROM store
      WHERE store_id = ? AND " . is_enabled(),
    array($store_id)
);
if (empty($stores_rs) || !isset($stores_rs[0])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    die('Store not found or not enabled.');
}
$store = $stores_rs[0];
$store_name = $store['store_name'];
$start_date = isset($store['qbo_tb_start_date']) ? trim($store['qbo_tb_start_date']) : '';

if ($start_date === '' || $start_date === null) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('This store has no TB Start Date configured. Set it in the table above and try again.');
}

$result = qbo_get_trial_balance($store_id, $start_date, $end_date);
if (!$result['success']) {
    http_response_code(502);
    header('Content-Type: text/plain');
    die('QBO error: ' . (isset($result['error']) ? $result['error'] : 'Unknown error'));
}

$parsed = qbo_tb_parse_report_to_flat($result['data']);
$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $store_name);
$filename = $safe_name . '_TB_' . $start_date . '_to_' . $end_date . '.xlsx';
$filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_one_' . time() . '_' . mt_rand(1000, 9999) . '.xlsx';

$ok = qbo_tb_write_excel_from_parsed(
    $parsed['columns'],
    $parsed['rows'],
    isset($parsed['header_row1']) ? $parsed['header_row1'] : null,
    isset($parsed['header_row2']) ? $parsed['header_row2'] : null,
    $store_name,
    $start_date,
    $end_date,
    $filepath,
    $qbo_tb_style_title,
    $qbo_tb_style_subtitle,
    $qbo_tb_style_col_headers,
    $qbo_tb_style_section_l0,
    $qbo_tb_style_section_l1,
    $qbo_tb_style_summary_l0,
    $qbo_tb_style_summary_l1,
    $qbo_tb_style_grand_total,
    $qbo_tb_currency_fmt
);

if (!$ok || !is_file($filepath)) {
    @unlink($filepath);
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to generate Excel file.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');
readfile($filepath);
@unlink($filepath);
exit;
