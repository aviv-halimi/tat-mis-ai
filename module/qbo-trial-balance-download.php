<?php
/**
 * QBO Trial Balance - Download All as one Excel file (one sheet per store).
 * Loops through all enabled stores, fetches each Trial Balance from QBO,
 * and builds a single workbook with one tab per store (tab name = store name).
 *
 * Access via: /?_module_code=qbo-trial-balance-download&end_date=YYYY-MM-DD
 */
require_once('_config.php');
require_once(BASE_PATH . 'inc/qbo.php');
require_once(BASE_PATH . 'inc/qbo-trial-balance-excel.php');

// Allow up to 10 minutes for multiple QBO calls and Excel build
set_time_limit(600);

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

$errors = array();
$sheets_added = 0;
$spreadsheet = null;
$used_sheet_titles = array();

// ── Loop through stores: one TB call per store, add one sheet per store ──
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

    if ($spreadsheet === null) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('The Artist Tree')
            ->setTitle('Trial Balances — ' . $end_date);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet_title = qbo_tb_sheet_title($store_name, $used_sheet_titles);
        $sheet->setTitle($sheet_title);
    } else {
        $sheet_title = qbo_tb_sheet_title($store_name, $used_sheet_titles);
        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheet_title);
        $spreadsheet->addSheet($sheet);
    }

    qbo_tb_fill_sheet_from_parsed(
        $sheet,
        $parsed['columns'],
        $parsed['rows'],
        isset($parsed['header_row1']) ? $parsed['header_row1'] : null,
        isset($parsed['header_row2']) ? $parsed['header_row2'] : null,
        $store_name,
        $start_date,
        $end_date,
        $style_title, $style_subtitle, $style_col_headers,
        $style_section_l0, $style_section_l1,
        $style_summary_l0, $style_summary_l1,
        $style_grand_total, $currency_fmt
    );
    $sheets_added++;
}

// ── Nothing generated ────────────────────────────────────────────────────────
if ($sheets_added === 0) {
    http_response_code(422);
    $msg = "No Trial Balance sheets were generated.\n\n";
    if ($errors) {
        $msg .= "Issues:\n" . implode("\n", $errors);
    }
    header('Content-Type: text/plain');
    die($msg);
}

// ── Save to temp file and stream ─────────────────────────────────────────────
$tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_' . time() . '_' . mt_rand(1000, 9999);
if (!mkdir($tmp_dir, 0755, true)) {
    die('Failed to create temporary directory.');
}
$filepath = $tmp_dir . DIRECTORY_SEPARATOR . 'TrialBalances_' . $end_date . '.xlsx';

try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filepath);
} catch (Exception $e) {
    @rmdir($tmp_dir);
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Failed to write Excel file.');
}

$filename = 'TrialBalances_' . $end_date . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');
readfile($filepath);

@unlink($filepath);
@rmdir($tmp_dir);
exit;
