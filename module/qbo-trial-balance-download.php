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

// ════════════════════════════════════════════════════════════════════════════
// Helper: write Trial Balance Excel from parsed report (columns + rows from QBO).
// $columns = [ 'Account', 'Jan 2026', ... ], $rows = [ [ 'type' => 'data', 'depth' => 1, 'values' => [...] ], ... ]
// ════════════════════════════════════════════════════════════════════════════
function qbo_tb_write_excel_from_parsed(
    $columns, $rows, $store_name, $start_date, $end_date, $filepath,
    $style_title, $style_subtitle, $style_col_headers,
    $style_section_l0, $style_section_l1,
    $style_summary_l0, $style_summary_l1,
    $style_grand_total, $currency_fmt
) {
    try {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('The Artist Tree')
            ->setTitle($store_name . ' — Trial Balance');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trial Balance');

        $num_cols = max(count($columns), 1);
        $last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($num_cols);

        $start_fmt = date('F j, Y', strtotime($start_date));
        $end_fmt   = date('F j, Y', strtotime($end_date));

        $sheet->setCellValue('A1', $store_name . ' — Trial Balance');
        $sheet->mergeCells('A1:' . $last_col_letter . '1');
        $sheet->getStyle('A1')->applyFromArray($style_title);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->setCellValue('A2', 'Period: ' . $start_fmt . ' — ' . $end_fmt);
        $sheet->mergeCells('A2:' . $last_col_letter . '2');
        $sheet->getStyle('A2')->applyFromArray($style_subtitle);
        $sheet->getRowDimension(2)->setRowHeight(18);

        $headerRow = 4;
        foreach ($columns as $c => $title) {
            $sheet->setCellValueByColumnAndRow($c + 1, $headerRow, $title);
        }
        $sheet->getStyle('A' . $headerRow . ':' . $last_col_letter . $headerRow)->applyFromArray($style_col_headers);
        $sheet->getRowDimension($headerRow)->setRowHeight(18);
        $sheet->freezePane('A' . ($headerRow + 1));

        $excelRow = $headerRow;
        foreach ($rows as $r) {
            $excelRow++;
            $values = $r['values'];
            $type = isset($r['type']) ? $r['type'] : 'data';
            $depth = isset($r['depth']) ? $r['depth'] : 0;
            for ($col = 0; $col < $num_cols; $col++) {
                $val = isset($values[$col]) ? $values[$col] : '';
                $cell = $sheet->getCellByColumnAndRow($col + 1, $excelRow);
                if (is_numeric($val) && $col > 0) {
                    $cell->setValue((float)$val);
                    $cell->getStyle()->getNumberFormat()->setFormatCode($currency_fmt);
                } else {
                    $cell->setValue($val);
                }
            }
            $range = 'A' . $excelRow . ':' . $last_col_letter . $excelRow;
            $sheet->getStyle('B' . $excelRow . ':' . $last_col_letter . $excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            switch ($type) {
                case 'section_header':
                    $sty = ($depth === 0) ? $style_section_l0 : $style_section_l1;
                    $sheet->getStyle($range)->applyFromArray($sty);
                    $sheet->mergeCells($range);
                    break;
                case 'section_summary':
                    $sty = ($depth === 0) ? $style_summary_l0 : $style_summary_l1;
                    $sheet->getStyle($range)->applyFromArray($sty);
                    break;
                case 'grand_total':
                    $sheet->getStyle($range)->applyFromArray($style_grand_total);
                    $sheet->getRowDimension($excelRow)->setRowHeight(20);
                    break;
            }
        }

        $sheet->getColumnDimension('A')->setAutoSize(true);
        for ($c = 2; $c <= $num_cols; $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setWidth(14);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
