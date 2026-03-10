<?php
/**
 * CLI: Generate "Download All" Trial Balances (one Excel, one sheet per store).
 * No time limit; run from command line to avoid web timeout.
 * Writes progress to --log= path so the UI can show live status.
 *
 * Usage: php cli/qbo-trial-balance-download-all.php --end_date=YYYY-MM-DD --output=/path/to/file.xlsx [--log=/path/to/file.log]
 *
 * Exit 0 on success, 1 on failure.
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// Parse args and write to log immediately so we know the process started (before any require)
$end_date = null;
$output = null;
$log_path = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--end_date=(.+)$/', $arg, $m)) {
        $end_date = trim($m[1]);
    } elseif (preg_match('/^--output=(.+)$/', $arg, $m)) {
        $output = trim($m[1]);
    } elseif (preg_match('/^--log=(.+)$/', $arg, $m)) {
        $log_path = trim($m[1]);
    }
}

if ($log_path) {
    @file_put_contents($log_path, '[' . date('H:i:s') . '] CLI process started.' . "\n", FILE_APPEND | LOCK_EX);
}

if (!$end_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    if ($log_path) {
        @file_put_contents($log_path, '[' . date('H:i:s') . '] Error: missing or invalid --end_date.' . "\n", FILE_APPEND | LOCK_EX);
    }
    fwrite(STDERR, "Missing or invalid --end_date (use YYYY-MM-DD).\n");
    exit(1);
}
if (!$output) {
    if ($log_path) {
        @file_put_contents($log_path, '[' . date('H:i:s') . '] Error: missing --output.' . "\n", FILE_APPEND | LOCK_EX);
    }
    fwrite(STDERR, "Missing --output path.\n");
    exit(1);
}

$base = dirname(__DIR__);
require_once $base . DIRECTORY_SEPARATOR . '_config.php';
require_once BASE_PATH . 'inc/qbo.php';
require_once BASE_PATH . 'inc/qbo-trial-balance-excel.php';

function qbo_tb_log($log_path, $msg) {
    if ($log_path) {
        @file_put_contents($log_path, '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
    }
}

$style_title = $qbo_tb_style_title;
$style_subtitle = $qbo_tb_style_subtitle;
$style_col_headers = $qbo_tb_style_col_headers;
$style_section_l0 = $qbo_tb_style_section_l0;
$style_section_l1 = $qbo_tb_style_section_l1;
$style_summary_l0 = $qbo_tb_style_summary_l0;
$style_summary_l1 = $qbo_tb_style_summary_l1;
$style_grand_total = $qbo_tb_style_grand_total;
$currency_fmt = $qbo_tb_currency_fmt;

$stores_rs = getRs(
    "SELECT store_id, store_name, qbo_tb_start_date
       FROM store
      WHERE " . is_enabled() . "
      ORDER BY store_name"
);
if (empty($stores_rs)) {
    fwrite(STDERR, "No stores found.\n");
    exit(1);
}

qbo_tb_log($log_path, 'Started. End date: ' . $end_date . ', stores: ' . count($stores_rs));

$errors = array();
$sheets_added = 0;
$spreadsheet = null;
$used_sheet_titles = array();
$store_index = 0;

foreach ($stores_rs as $store) {
    $store_index++;
    $store_id   = (int)$store['store_id'];
    $store_name = $store['store_name'];
    $start_date = isset($store['qbo_tb_start_date']) ? trim($store['qbo_tb_start_date']) : '';

    if ($start_date === '' || $start_date === null) {
        qbo_tb_log($log_path, $store_index . '/' . count($stores_rs) . ' ' . $store_name . ' — skipped (no TB start date)');
        $errors[] = $store_name . ': No TB Start Date — skipped.';
        continue;
    }

    qbo_tb_log($log_path, $store_index . '/' . count($stores_rs) . ' ' . $store_name . ' — calling QBO...');
    $result = qbo_get_trial_balance($store_id, $start_date, $end_date);
    if (!$result['success']) {
        $err = isset($result['error']) ? $result['error'] : 'QBO error';
        qbo_tb_log($log_path, '  → QBO error: ' . $err);
        $errors[] = $store_name . ': ' . $err;
        continue;
    }
    qbo_tb_log($log_path, '  → Got response, adding sheet to workbook.');

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

if ($sheets_added === 0) {
    qbo_tb_log($log_path, 'Aborted: no sheets generated.');
    fwrite(STDERR, "No sheets generated. " . implode(' ', $errors) . "\n");
    exit(1);
}

$out_dir = dirname($output);
if (!is_dir($out_dir)) {
    if (!@mkdir($out_dir, 0755, true)) {
        fwrite(STDERR, "Could not create directory: " . $out_dir . "\n");
        exit(1);
    }
}

qbo_tb_log($log_path, 'Writing Excel file...');
try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($output);
} catch (Exception $e) {
    qbo_tb_log($log_path, 'Failed: ' . $e->getMessage());
    fwrite(STDERR, "Failed to write Excel: " . $e->getMessage() . "\n");
    exit(1);
}
qbo_tb_log($log_path, 'Complete. File: ' . $output);

exit(0);
