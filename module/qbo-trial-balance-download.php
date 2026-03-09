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

if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet', false)) {
    require_once(BASE_PATH . 'class/PhpSpreadsheet/vendor/autoload.php');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

// ── Shared Excel styles ──────────────────────────────────────────────────────
$style_title = array(
    'font'      => array('bold' => true, 'size' => 18, 'color' => array('rgb' => '116066')),
    'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER),
    'borders'   => array('bottom' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF116066'))),
);
$style_subtitle = array(
    'font'      => array('bold' => true, 'size' => 11),
    'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT),
);
$style_col_headers = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF116066')),
    'borders' => array(
        'bottom' => array('borderStyle' => 'medium', 'color' => array('argb' => 'FF116066')),
    ),
);
$style_section_l0 = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF1e7e85')),
);
$style_section_l1 = array(
    'font' => array('bold' => true, 'color' => array('rgb' => '116066')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFd8eff1')),
);
$style_summary_l0 = array(
    'font' => array('bold' => true),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFe0f0f1')),
    'borders' => array('top' => array('borderStyle' => 'thin', 'color' => array('argb' => 'FF116066'))),
);
$style_summary_l1 = array(
    'font' => array('bold' => true, 'italic' => true),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFf5f5f5')),
);
$style_grand_total = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF'), 'size' => 11),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF116066')),
    'borders' => array('top' => array('borderStyle' => 'medium', 'color' => array('argb' => 'FF116066'))),
);
$currency_fmt = '$#,##0.00_-';

// ── Temp directory ───────────────────────────────────────────────────────────
$tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_' . time() . '_' . mt_rand(1000, 9999);
if (!mkdir($tmp_dir, 0755, true)) {
    die('Failed to create temporary directory.');
}

$generated = array();
$errors    = array();

// ── Loop through stores ──────────────────────────────────────────────────────
foreach ($stores_rs as $store) {
    $store_id   = (int)$store['store_id'];
    $store_name = $store['store_name'];
    $start_date = isset($store['qbo_tb_start_date']) ? trim($store['qbo_tb_start_date']) : '';

    if ($start_date === '' || $start_date === null) {
        $errors[] = $store_name . ': No TB Start Date configured — skipped.';
        continue;
    }

    $months = qbo_tb_months_in_range($start_date, $end_date);
    if (empty($months)) {
        $errors[] = $store_name . ': Invalid date range.';
        continue;
    }

    // Fetch Trial Balance for each month
    $by_month = array();
    $first_month_accounts = array(); // preserve order from first report
    $first_label = $months[0]['label'];
    foreach ($months as $m) {
        $result = qbo_get_trial_balance($store_id, $m['start'], $m['end']);
        if (!$result['success']) {
            $errors[] = $store_name . ' (' . $m['label'] . '): ' . (isset($result['error']) ? $result['error'] : 'Unknown QBO error');
            continue 2;
        }
        $rows = qbo_tb_extract_account_rows($result['data']);
        $by_month[$m['label']] = array();
        foreach ($rows as $r) {
            $key = $r['id'] !== null ? $r['id'] : $r['name'];
            $by_month[$m['label']][$key] = array('name' => $r['name'], 'debit' => $r['debit'], 'credit' => $r['credit']);
            if ($m['label'] === $first_label) {
                $first_month_accounts[$key] = $r['name'];
            }
        }
    }

    // Full GL account list so we have a row for every account (including zero balance)
    $all_accounts = array();
    $acc_result = qbo_list_accounts($store_id, '');
    if ($acc_result['success'] && !empty($acc_result['accounts'])) {
        foreach ($acc_result['accounts'] as $a) {
            $all_accounts[$a['id']] = array('name' => $a['Name'], 'type' => isset($a['AccountType']) ? $a['AccountType'] : '');
        }
    }

    // Ordered list: first as in first month's TB, then any from full list (sorted by type, name)
    $ordered = array();
    foreach (array_keys($first_month_accounts) as $key) {
        $ordered[$key] = isset($all_accounts[$key]) ? $all_accounts[$key]['name'] : $first_month_accounts[$key];
    }
    $from_tb_keys = array_flip(array_keys($first_month_accounts));
    $only_list_ids = array_keys(array_diff_key($all_accounts, $from_tb_keys));
    if (!empty($only_list_ids)) {
        usort($only_list_ids, function ($a, $b) use ($all_accounts) {
            $ta = isset($all_accounts[$a]['type']) ? $all_accounts[$a]['type'] : '';
            $tb = isset($all_accounts[$b]['type']) ? $all_accounts[$b]['type'] : '';
            if ($ta !== $tb) return strcmp($ta, $tb);
            return strcmp($all_accounts[$a]['name'], $all_accounts[$b]['name']);
        });
        foreach ($only_list_ids as $id) {
            $ordered[$id] = $all_accounts[$id]['name'];
        }
    }

    // Generate Excel (multi-month: one row per account, Debit/Credit columns per month)
    $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $store_name);
    $filename  = $safe_name . '_TB_' . $start_date . '_to_' . $end_date . '.xlsx';
    $filepath  = $tmp_dir . DIRECTORY_SEPARATOR . $filename;

    $ok = qbo_tb_write_excel_multimonths(
        $ordered,
        $by_month,
        $months,
        $store_name,
        $start_date,
        $end_date,
        $filepath,
        $style_title, $style_subtitle, $style_col_headers,
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

// ════════════════════════════════════════════════════════════════════════════
// Helper: write Trial Balance Excel with one row per GL account, Debit/Credit per month.
// $ordered = [ account_key => account_name ] in display order
// $by_month = [ month_label => [ account_key => [ name, debit, credit ], ... ], ... ]
// ════════════════════════════════════════════════════════════════════════════
function qbo_tb_write_excel_multimonths(
    $ordered, $by_month, $months, $store_name, $start_date, $end_date, $filepath,
    $style_title, $style_subtitle, $style_col_headers, $style_grand_total, $currency_fmt
) {
    try {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('The Artist Tree')
            ->setTitle($store_name . ' — Trial Balance');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trial Balance');

        $start_fmt = date('F j, Y', strtotime($start_date));
        $end_fmt   = date('F j, Y', strtotime($end_date));

        $sheet->setCellValue('A1', $store_name . ' — Trial Balance');
        $sheet->getStyle('A1')->applyFromArray($style_title);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->setCellValue('A2', 'Period: ' . $start_fmt . ' — ' . $end_fmt);
        $sheet->getStyle('A2')->applyFromArray($style_subtitle);
        $sheet->getRowDimension(2)->setRowHeight(18);

        $num_months = count($months);
        $last_col = 1 + $num_months * 2 + 2; // Account + (Debit,Credit)*months + Total Debit + Total Credit
        $sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last_col) . '1');
        $sheet->mergeCells('A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last_col) . '2');

        $headerRow = 4;
        $col = 0;
        $sheet->setCellValueByColumnAndRow(++$col, $headerRow, 'Account');
        foreach ($months as $m) {
            $sheet->setCellValueByColumnAndRow(++$col, $headerRow, $m['label'] . ' Debit');
            $sheet->setCellValueByColumnAndRow(++$col, $headerRow, $m['label'] . ' Credit');
        }
        $sheet->setCellValueByColumnAndRow(++$col, $headerRow, 'Total Debit');
        $sheet->setCellValueByColumnAndRow(++$col, $headerRow, 'Total Credit');

        $sheet->getStyle('A' . $headerRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last_col) . $headerRow)
            ->applyFromArray($style_col_headers);
        $sheet->getRowDimension($headerRow)->setRowHeight(18);
        $sheet->freezePane('A' . ($headerRow + 1));

        $dataRow = $headerRow;
        $grand_debit = 0;
        $grand_credit = 0;
        $month_totals = array();
        foreach (array_keys($months) as $i) {
            $month_totals[$i] = array('debit' => 0, 'credit' => 0);
        }

        foreach ($ordered as $account_key => $account_name) {
            $dataRow++;
            $col = 0;
            $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $account_name);
            $row_debit = 0;
            $row_credit = 0;
            $mi = 0;
            foreach ($months as $m) {
                $label = $m['label'];
                $debit = 0;
                $credit = 0;
                if (isset($by_month[$label][$account_key])) {
                    $debit  = (float)$by_month[$label][$account_key]['debit'];
                    $credit = (float)$by_month[$label][$account_key]['credit'];
                }
                $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $debit);
                $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $credit);
                $row_debit += $debit;
                $row_credit += $credit;
                $month_totals[$mi]['debit'] += $debit;
                $month_totals[$mi]['credit'] += $credit;
                $mi++;
            }
            $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $row_debit);
            $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $row_credit);
            $grand_debit += $row_debit;
            $grand_credit += $row_credit;

            $numCols = $last_col;
            $range = 'B' . $dataRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols) . $dataRow;
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode($currency_fmt);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $dataRow++;
        $col = 0;
        $sheet->setCellValueByColumnAndRow(++$col, $dataRow, 'Total');
        foreach ($month_totals as $tot) {
            $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $tot['debit']);
            $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $tot['credit']);
        }
        $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $grand_debit);
        $sheet->setCellValueByColumnAndRow(++$col, $dataRow, $grand_credit);
        $sheet->getStyle('A' . $dataRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last_col) . $dataRow)
            ->applyFromArray($style_grand_total);
        $sheet->getStyle('B' . $dataRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last_col) . $dataRow)
            ->getNumberFormat()->setFormatCode($currency_fmt);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        for ($c = 2; $c <= $last_col; $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setWidth(14);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
