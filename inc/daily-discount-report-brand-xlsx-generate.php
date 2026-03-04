<?php
/**
 * Generate daily discount report brand Excel file and save to disk.
 * Returns array('path' => full path, 'filename' => basename) or false on failure.
 * Requires _config.php; loads PhpSpreadsheet when needed.
 */
if (!function_exists('dd_report_brand_generate_xlsx')) {

function getLetterFromNumber_xlsx($n) {
    for ($r = ""; $n >= 0; $n = intval($n / 26) - 1) {
        $r = chr($n % 26 + 0x41) . $r;
    }
    return $r;
}

function dd_report_brand_generate_xlsx($daily_discount_report_brand_id, $save_dir = null) {
    if (!defined('MEDIA_PATH') || !$daily_discount_report_brand_id) {
        return false;
    }
    $save_dir = $save_dir !== null ? rtrim($save_dir, '/\\') . '/' : (MEDIA_PATH . 'daily_discount_report_brand/');
    if (!is_dir($save_dir)) {
        @mkdir($save_dir, 0755, true);
    }
    if (!is_dir($save_dir)) {
        return false;
    }

    $rd = getRs(
        "SELECT r.*, b.name AS brand_name, b.brand_id, rb.filename, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb,r'),
        array($daily_discount_report_brand_id)
    );
    $d = $rd ? getRow($rd) : null;
    if (!$d) {
        return false;
    }

    $brand_name_safe = trim(preg_replace('/[^a-zA-Z0-9 _\-\.]/', '', isset($d['brand_name']) ? $d['brand_name'] : ''));
    if ($brand_name_safe === '') $brand_name_safe = 'Report';
    $report_date_ts = !empty($d['date_end']) ? strtotime($d['date_end']) : (!empty($d['date_start']) ? strtotime($d['date_start']) : time());
    $base = $brand_name_safe . ' - ' . date('M j', $report_date_ts) . ' - Rebate Report';
    $filename = $base . '.xlsx';
    $full_path = $save_dir . $filename;

    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        require_once(BASE_PATH . 'class/PhpSpreadsheet/vendor/autoload.php');
    }
    $titleStyle = array(
        'font' => array('color' => array('rgb' => '116066'), 'size' => 20, 'bold' => true),
        'alignment' => array('vertical' => 1),
        'borders' => array('bottom' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF116066')), 'top' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF116066'))),
    );
    $theadStyle = array(
        'font' => array('color' => array('rgb' => '000000'), 'bold' => true),
        'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFc8e5e7')),
        'borders' => array('bottom' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF116066')), 'top' => array('borderStyle' => 'thin', 'color' => array('argb' => 'FF116066'))),
    );
    $tfootStyle = array(
        'font' => array('color' => array('rgb' => '000000'), 'bold' => true),
        'fill' => array('fillType' => 'solid', 'startColor' => array('argb' => 'FFF3F3F3')),
        'borders' => array('bottom' => array('borderStyle' => 'thin', 'color' => array('argb' => 'FF000000')), 'top' => array('borderStyle' => 'thick', 'color' => array('argb' => 'FF000000'))),
    );
    $currency_mask = '$#,##0.00_-';
    $showGrossSales = (isset($d['brand_id']) && (int)$d['brand_id'] === 91);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getProperties()->setCreator('The Artist Tree')->setLastModifiedBy('The Artist Tree')->setTitle('Summary')->setSubject('Summary')->setDescription('Summary')->setKeywords('Summary');

    $_row = 0;
    $sheetId = 0;
    $spreadsheet->createSheet($sheetId);
    $spreadsheet->setActiveSheetIndex($sheetId);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Summary');
    $sheet->SetCellValue('A1', 'Daily Deal Discount Report');
    $sheet->getStyle('A1:D1')->applyFromArray($titleStyle);
    $sheet->mergeCells('A1:D1');
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->SetCellValue('A2', (isset($d['brand_name']) ? $d['brand_name'] : '') . (isset($d['brand_name']) && $d['brand_name'] ? ': ' : '') . (isset($d['date_start']) ? date('F jS, Y', strtotime($d['date_start'])) : '') . ' - ' . (isset($d['date_end']) ? date('F jS, Y', strtotime($d['date_end'])) : ''));
    $sheet->getStyle('A2')->getFont()->setBold(true);
    $sheet->mergeCells('A2:D2');
    $sheet->SetCellValue('A4', 'Store Location');
    $sheet->SetCellValue('B4', 'Total Rebate Due');
    $sheet->getStyle('A4:B4')->applyFromArray($theadStyle);
    $_row = 4;
    $g_total = 0;

    $rs = getRs("SELECT s.store_name, d.* FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s') . " ORDER BY d.daily_discount_report_store_id", array($d['daily_discount_report_brand_id']));
    $store_rows = $rs ?: array();
    foreach ($store_rows as $r) {
        $rp = json_decode($r['params'], true);
        $row = 0;
        if (is_array($rp) && count($rp) > 0) {
            $sheetId++;
            $spreadsheet->createSheet($sheetId);
            $spreadsheet->setActiveSheetIndex($sheetId);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(isset($r['store_name']) ? $r['store_name'] : ('Store' . $sheetId));
            $col = -1;
            $row++;
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Date');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Weekday');
            if (empty($d['brand_id'])) {
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Brand');
            }
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Category');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Product Name');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Rebate Type');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Rebate Percentage');
            if ($showGrossSales) {
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Gross Sales');
            }
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Quantity Sold');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Cogs / Unit Price');
            $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, 'Total Rebate Due');
            $sheet->getStyle('A1:' . getLetterFromNumber_xlsx($col) . $row)->applyFromArray($theadStyle);
            $qty = 0;
            $total = 0;
            $max_col = 0;
            foreach ($rp as $p) {
                $col = -1;
                $row++;
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['TransactionDate']) ? $p['TransactionDate'] : '');
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['weekday_name']) ? $p['weekday_name'] : '');
                if (empty($d['brand_id'])) {
                    $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['brand_name']) ? $p['brand_name'] : '');
                }
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['category_name']) ? $p['category_name'] : '');
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['product_name']) ? $p['product_name'] : '');
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, isset($p['daily_discount_type_name']) ? $p['daily_discount_type_name'] : '');
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, number_format(isset($p['rebate_percent']) ? $p['rebate_percent'] : 0, 2));
                if ($showGrossSales) {
                    $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, number_format(isset($p['GrossSales']) ? $p['GrossSales'] : 0, 2));
                }
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, number_format(isset($p['quantity']) ? $p['quantity'] : 0, 0, '.', ''));
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, number_format(isset($p['unit_price']) ? $p['unit_price'] : 0, 2, '.', ''));
                $rebate_val = (isset($p['quantity']) ? $p['quantity'] : 0) * (isset($p['rebate_percent']) ? $p['rebate_percent'] : 0) / 100 * (isset($p['unit_price']) ? $p['unit_price'] : 0);
                $sheet->SetCellValue(getLetterFromNumber_xlsx(++$col) . $row, number_format($rebate_val, 2, '.', ''));
                $qty += isset($p['quantity']) ? $p['quantity'] : 0;
                $total += $rebate_val;
                $max_col = $col;
            }
            $row++;
            $sheet->SetCellValue(getLetterFromNumber_xlsx($col - 2) . $row, number_format($qty, 0, '.', ''));
            $sheet->SetCellValue(getLetterFromNumber_xlsx($col) . $row, number_format($total, 2, '.', ''));
            $sheet->getStyle('A' . $row . ':' . getLetterFromNumber_xlsx($col) . $row)->applyFromArray($tfootStyle);
            $sheet->getStyle(getLetterFromNumber_xlsx($col - 1))->getNumberFormat()->setFormatCode($currency_mask);
            $sheet->getStyle(getLetterFromNumber_xlsx($col))->getNumberFormat()->setFormatCode($currency_mask);
            for ($_c = 0; $_c <= $max_col; $_c++) {
                $sheet->getColumnDimension(getLetterFromNumber_xlsx($_c))->setAutoSize(true);
            }
            $spreadsheet->setActiveSheetIndex(0);
            $sheet = $spreadsheet->getActiveSheet();
            $_row++;
            $sheet->SetCellValue('A' . $_row, isset($r['store_name']) ? $r['store_name'] : '');
            $sheet->SetCellValue('B' . $_row, number_format($total, 2, '.', ''));
            $sheet->getStyle('B')->getNumberFormat()->setFormatCode($currency_mask);
            $g_total += $total;
        }
    }

    $spreadsheet->setActiveSheetIndex(0);
    $sheet = $spreadsheet->getActiveSheet();
    $_row++;
    $sheet->SetCellValue('B' . $_row, number_format($g_total, 2, '.', ''));
    $sheet->getStyle('A' . $_row . ':B' . $_row)->applyFromArray($tfootStyle);
    $sheet->getStyle('B')->getNumberFormat()->setFormatCode($currency_mask);
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($full_path);
    if (is_file($full_path)) {
        return array('path' => $full_path, 'filename' => $filename);
    }
    return false;
}

}
