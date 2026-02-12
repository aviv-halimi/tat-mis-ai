<?php
require_once ('_config.php');
require_once ('class/PhpSpreadsheet/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$success = false;
$response = '';

$daily_discount_report_code = null;
$daily_discount_report_brand_code = getVar('c');
$showGrossSales = null;
// styling

$titleStyle = [
    'font' => array(
        'color' => array('rgb' => '116066'),
        'size' => 20,
        'bold' => true
    ),
    'alignment' => array(
        'vertical' => 1
    ),
    'borders' => [
        'bottom' => ['borderStyle' => 'thick', 'color' => ['argb' => 'FF116066']],
        'top' => ['borderStyle' => 'thick', 'color' => ['argb' => 'FF116066']]
    ]
];

$theadStyle = [
    'font' => array(
        'color' => array('rgb' => '000000'),
        'bold' => true
    ),
    'fill' => array(
        'fillType' => 'solid',
        'startColor' => array('argb' => 'FFc8e5e7')
    ),
    'borders' => [
        'bottom' => ['borderStyle' => 'thick', 'color' => ['argb' => 'FF116066']],
        'top' => ['borderStyle' => 'thin', 'color' => ['argb' => 'FF116066']]
    ]
];

$tfootStyle = [
    'font' => array(
        'color' => array('rgb' => '000000'),
        'bold' => true
    ),
    'fill' => array(
        'fillType' => 'solid',
        'startColor' => array('argb' => 'FFF3F3F3')
    ),
    'borders' => [
        'bottom' => ['borderStyle' => 'thin', 'color' => ['argb' => 'FF000000']],
        'top' => ['borderStyle' => 'thick', 'color' => ['argb' => 'FF000000']]
    ]
];
$currency_mask = '$#,##0.00_-';

if ($daily_discount_report_brand_code) {   
    $rd = getRs("SELECT r.*, b.name AS brand_name, b.brand_id, rb.filename, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE rb.daily_discount_report_brand_code = ?", $daily_discount_report_brand_code);
}
else {
    $rd = getRs("SELECT r.*, b.name AS brand_name, b.brand_id, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE r.daily_discount_report_code = ?", $daily_discount_report_code);
}

if ($d = getRow($rd)) {

    $filename = getFilename($d['filename']) . '.xlsx';
	$showGrossSales = ($d['brand_id'] == 91);
    $spreadsheet = new Spreadsheet();    
        
    $spreadsheet->getProperties()
        ->setCreator('The Artist Tree')
        ->setLastModifiedBy('The Artist Tree')
        ->setTitle('Summary')
        ->setSubject('Summary')
        ->setDescription('Summary')
        ->setKeywords('Summary');

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

	$sheet->SetCellValue('A2', $d['brand_name'] . iif($d['brand_name'], ': ') . date('F jS, Y', strtotime($d['date_start'])) . ' - ' . date('F jS, Y', strtotime($d['date_end'])));
    $sheet->getStyle('A2')->getFont()->setBold(true);
    $sheet->mergeCells('A2:D2');

    $sheet->SetCellValue('A4', 'Store Location');
    $sheet->SetCellValue('B4', 'Total Rebate Due');

    $sheet->getStyle('A4:B4')->applyFromArray($theadStyle);
    $_row = 4;
    $g_total = 0;
    
    $rs = getRs("SELECT s.store_name, d.* FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s')  . " ORDER BY d.daily_discount_report_store_id", array($d['daily_discount_report_brand_id']));
    foreach($rs as $r) {
        $rp = json_decode($r['params'], true);
        $row = 0;
        if (sizeof($rp)) {
            $sheetId++;
            $spreadsheet->createSheet($sheetId);
            $spreadsheet->setActiveSheetIndex($sheetId);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($r['store_name']);
            
            $col = -1;
            $row++;
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Date');
			$sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Weekday');
            if(!$d['brand_id']) $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Brand');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Category');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Product Name');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Rebate Type');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Rebate Percentage');
			if($showGrossSales) $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Gross Sales');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Quantity Sold');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Cogs / Unit Price');
            $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, 'Total Rebate Due');

            $sheet->getStyle('A1:' . getLetterFromNumber($col) . $row)->applyFromArray($theadStyle);

            
            $qty = 0;
            $total = 0;
            $max_col = 0;
            foreach($rp as $p) {
                $col = -1;
                $row++;
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['TransactionDate']);
				$sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['weekday_name']);
                if(!$d['brand_id']) $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['brand_name']);
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['category_name']);
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['product_name']);
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, $p['daily_discount_type_name']);
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, number_format($p['rebate_percent'], 2));
				if ($showGrossSales) $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, number_format($p['GrossSales'], 2));
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, number_format($p['quantity'], 0, '.', ''));
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, number_format($p['unit_price'], 2, '.', ''));
                $sheet->SetCellValue(getLetterFromNumber(++$col) . $row, number_format($p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'], 2, '.', ''));
                $qty += $p['quantity'];
                $total += $p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'];
                $max_col = $col;
            }
            $row++;
            $sheet->SetCellValue(getLetterFromNumber($col - 2) . $row, number_format($qty, 0, '.', ''));
            $sheet->SetCellValue(getLetterFromNumber($col) . $row, number_format($total, 2, '.', ''));
            $sheet->getStyle('A' . $row . ':'. getLetterFromNumber($col) . $row)->applyFromArray($tfootStyle);
            $sheet->getStyle(getLetterFromNumber($col-1))->getNumberFormat()->setFormatCode($currency_mask);
            $sheet->getStyle(getLetterFromNumber($col))->getNumberFormat()->setFormatCode($currency_mask);
 
            for($_c = 0; $_c <= $max_col; $_c++) {
                $sheet->getColumnDimension(getLetterFromNumber($_c))->setAutoSize(true);
            }
            
            $spreadsheet->setActiveSheetIndex(0);
            $sheet = $spreadsheet->getActiveSheet();
            $_row++;
            $sheet->SetCellValue('A' . $_row, $r['store_name']);
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
    //$sheet->getStyle('A' . $_row . ':B' . $_row)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->setColor(new Color('333333'));
    $sheet->getStyle('B')->getNumberFormat()->setFormatCode($currency_mask);
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $spreadsheet->setActiveSheetIndex(0);
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
else {
    exit('Not found');
}

function getLetterFromNumber($n) {
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x41) . $r;
    return $r;
}
?>