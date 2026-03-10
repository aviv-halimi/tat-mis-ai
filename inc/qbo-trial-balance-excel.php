<?php
/**
 * Shared Trial Balance Excel styles and writer.
 * Used by module/qbo-trial-balance-download.php and ajax/qbo-trial-balance-download-one.php.
 */
if (!defined('BASE_PATH')) {
    return;
}
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet', false)) {
    require_once(BASE_PATH . 'class/PhpSpreadsheet/vendor/autoload.php');
}
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$qbo_tb_style_title = array(
    'font'      => array('bold' => true, 'size' => 18, 'color' => array('rgb' => '116066')),
    'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER),
    'borders'   => array('bottom' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF116066'))),
);
$qbo_tb_style_subtitle = array(
    'font'      => array('bold' => true, 'size' => 11),
    'alignment' => array('horizontal' => Alignment::HORIZONTAL_LEFT),
);
$qbo_tb_style_col_headers = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF116066')),
    'borders' => array(
        'bottom' => array('borderStyle' => 'medium', 'color' => array('argb' => 'FF116066')),
    ),
);
$qbo_tb_style_section_l0 = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF1e7e85')),
);
$qbo_tb_style_section_l1 = array(
    'font' => array('bold' => true, 'color' => array('rgb' => '116066')),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFd8eff1')),
);
$qbo_tb_style_summary_l0 = array(
    'font' => array('bold' => true),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFe0f0f1')),
    'borders' => array('top' => array('borderStyle' => 'thin', 'color' => array('argb' => 'FF116066'))),
);
$qbo_tb_style_summary_l1 = array(
    'font' => array('bold' => true, 'italic' => true),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFf5f5f5')),
);
$qbo_tb_style_grand_total = array(
    'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF'), 'size' => 11),
    'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FF116066')),
    'borders' => array('top' => array('borderStyle' => 'medium', 'color' => array('argb' => 'FF116066'))),
);
$qbo_tb_currency_fmt = '$#,##0.00_-';

/**
 * Write Trial Balance Excel from parsed report.
 * @param array $columns
 * @param array $rows
 * @param array|null $header_row1
 * @param array|null $header_row2
 * @param string $store_name
 * @param string $start_date
 * @param string $end_date
 * @param string $filepath
 * @param array $style_title (etc.) - use $qbo_tb_style_* from this file
 * @return bool
 */
function qbo_tb_write_excel_from_parsed(
    $columns, $rows, $header_row1, $header_row2, $store_name, $start_date, $end_date, $filepath,
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
        $dataStartRow = $headerRow + 1;

        if (!empty($header_row1) && !empty($header_row2) && count($header_row1) === $num_cols && count($header_row2) === $num_cols) {
            for ($c = 0; $c < $num_cols; $c++) {
                $sheet->setCellValueByColumnAndRow($c + 1, $headerRow, isset($header_row1[$c]) ? $header_row1[$c] : '');
            }
            $sheet->getStyle('A' . $headerRow . ':' . $last_col_letter . $headerRow)->applyFromArray($style_col_headers);
            $sheet->getRowDimension($headerRow)->setRowHeight(18);
            for ($c = 0; $c < $num_cols; ) {
                $val = isset($header_row1[$c]) ? $header_row1[$c] : '';
                $end = $c;
                while ($end + 1 < $num_cols && (isset($header_row1[$end + 1]) ? $header_row1[$end + 1] : '') === $val) {
                    $end++;
                }
                if ($end > $c) {
                    $startCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . $headerRow;
                    $endCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($end + 1) . $headerRow;
                    $sheet->mergeCells($startCell . ':' . $endCell);
                }
                $c = $end + 1;
            }
            $headerRow++;
            $dataStartRow = $headerRow + 1;
            for ($c = 0; $c < $num_cols; $c++) {
                $sheet->setCellValueByColumnAndRow($c + 1, $headerRow, isset($header_row2[$c]) ? $header_row2[$c] : '');
            }
            $sheet->getStyle('A' . $headerRow . ':' . $last_col_letter . $headerRow)->applyFromArray($style_col_headers);
            $sheet->getRowDimension($headerRow)->setRowHeight(18);
        } else {
            foreach ($columns as $c => $title) {
                $sheet->setCellValueByColumnAndRow($c + 1, $headerRow, $title);
            }
            $sheet->getStyle('A' . $headerRow . ':' . $last_col_letter . $headerRow)->applyFromArray($style_col_headers);
            $sheet->getRowDimension($headerRow)->setRowHeight(18);
        }

        $sheet->freezePane('A' . $dataStartRow);

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
