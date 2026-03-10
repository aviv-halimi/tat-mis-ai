<?php
/**
 * QBO Trial Balance - ZIP download module.
 * Loops through all enabled stores, fetches each Trial Balance from QBO,
 * generates an Excel file per store, and streams the whole set as a ZIP.
 *
 * Access via: /?_module_code=qbo-trial-balance-download&end_date=YYYY-MM-DD
 */
require_once('_config.php');
require_once(BASE_PATH . 'inc/qbo.php');
require_once(BASE_PATH . 'inc/qbo-trial-balance-excel.php');

$style_title = $qbo_tb_style_title;
$style_subtitle = $qbo_tb_style_subtitle;
$style_col_headers = $qbo_tb_style_col_headers;
$style_section_l0 = $qbo_tb_style_section_l0;
$style_section_l1 = $qbo_tb_style_section_l1;
$style_summary_l0 = $qbo_tb_style_summary_l0;
$style_summary_l1 = $qbo_tb_style_summary_l1;
$style_grand_total = $qbo_tb_style_grand_total;
$currency_fmt = $qbo_tb_currency_fmt;

// ── Validate end_date ────────────────────────────────────────────────────────
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
if (!$end_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    http_response_code(400);
    die('Missing or invalid end_date parameter (expected YYYY-MM-DD).');
}

// ── Load stores ──────────────────────────────────────────────────────────────
$stores_rs = getRs(
    "SELECT store_id, store_name, qbo_tb_start_date
       FROM store
      WHERE " . is_enabled() . "
      ORDER BY store_name"
);
if (empty($stores_rs)) {
    die('No stores found.');
}

// ── Temp directory ───────────────────────────────────────────────────────────
$tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_' . time() . '_' . mt_rand(1000, 9999);
if (!mkdir($tmp_dir, 0755, true)) {
    die('Failed to create temporary directory.');
}

$generated = array();
$errors    = array();

// ── Loop through stores: one TB call per store (API returns monthly columns via summarize_column_by=Month) ──
foreach ($stores_rs as $store) {
    $store_id   = (int)$store['store_id'];
    $store_name = $store['store_name'];
    $start_date = isset($store['qbo_tb_start_date']) ? trim($store['qbo_tb_start_date']) : '';

    if ($start_date === '' || $start_date === null) {
        $errors[] = $store_name . ': No TB Start Date configured — skipped.';
        continue;
    }

    $result = qbo_get_trial_balance($store_id, $start_date, $end_date);
    if (!$result['success']) {
        $errors[] = $store_name . ': ' . (isset($result['error']) ? $result['error'] : 'Unknown QBO error');
        continue;
    }

    $parsed = qbo_tb_parse_report_to_flat($result['data']);
    $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $store_name);
    $filename  = $safe_name . '_TB_' . $start_date . '_to_' . $end_date . '.xlsx';
    $filepath  = $tmp_dir . DIRECTORY_SEPARATOR . $filename;

    $ok = qbo_tb_write_excel_from_parsed(
        $parsed['columns'],
        $parsed['rows'],
        isset($parsed['header_row1']) ? $parsed['header_row1'] : null,
        isset($parsed['header_row2']) ? $parsed['header_row2'] : null,
        $store_name,
        $start_date,
        $end_date,
        $filepath,
        $style_title, $style_subtitle, $style_col_headers,
        $style_section_l0, $style_section_l1,
        $style_summary_l0, $style_summary_l1,
        $style_grand_total, $currency_fmt
    );

    if ($ok) {
        $generated[] = array('path' => $filepath, 'name' => $filename);
    } else {
        $errors[] = $store_name . ': Failed to write Excel file.';
    }
}

// ── Nothing generated ────────────────────────────────────────────────────────
if (empty($generated)) {
    // Clean up
    @rmdir($tmp_dir);
    http_response_code(422);
    $msg = "No Trial Balance files were generated.\n\n";
    if ($errors) {
        $msg .= "Issues:\n" . implode("\n", $errors);
    }
    header('Content-Type: text/plain');
    die($msg);
}

// ── Build ZIP ────────────────────────────────────────────────────────────────
$zip_path = $tmp_dir . DIRECTORY_SEPARATOR . 'TrialBalances_' . $end_date . '.zip';
$zip      = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
    die('Failed to create ZIP archive.');
}
foreach ($generated as $f) {
    $zip->addFile($f['path'], $f['name']);
}
if (!empty($errors)) {
    $zip->addFromString('_skipped_stores.txt', implode("\n", $errors));
}
$zip->close();

// ── Stream ZIP to browser ────────────────────────────────────────────────────
$zip_filename = 'TrialBalances_' . $end_date . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');
readfile($zip_path);

// ── Cleanup ──────────────────────────────────────────────────────────────────
foreach ($generated as $f) {
    @unlink($f['path']);
}
@unlink($zip_path);
@rmdir($tmp_dir);
exit;
