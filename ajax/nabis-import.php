<?php
require_once('../_config.php');
require_once ('../class/PhpSpreadsheet/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\IOFactory;

$start = time();

set_time_limit (6000);
ini_set('memory_limit', '-1');

$success = false;
$response = '';
$redirect = null;
$data = $errors = array();

$filename = getVar('filename');
$sheet_id = 0;
$nabis_id = null;
$num_dups = $num_orders = $num_products = $_num_products = 0;
$a_skip = array();
$last_nabis_id = 0;
$min_nabis_id = $max_nabis_id = null;
$D_quantity = 0;
$D_pricePerUnit = 0;

if (!$response) {
    if ($filename) {
        $inputFileName = MEDIA_PATH . 'nabis/' . $filename;
        $spreadsheet = IOFactory::load($inputFileName);
        $sheet = 0;
        $sheets = $spreadsheet->getSheetNames();
        $i = $j = 0;
        foreach($sheets as $s) {
            $spreadsheet->setActiveSheetIndex($i);
            $sheet = $spreadsheet->getActiveSheet();
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            if ($i == $sheet_id) {
                $r = 1;
                $nabis_id = $_orderNumber = null;
                foreach($sheetData as $s) {
                    $j++;
                    if ($j < 2) continue;

                    //'brandDoingBusinessAs', 'orderNumber', 'orderName', 'daysTillPaymentDue', 'orderCreationDate', 'deliveryDate', 'gmv', 'orderDiscount', 'creatorEmail', 'status', 'paymentStatus', 'batchCode', 'manufacturerLicenseNumber', 'manufacturerLegalEntityName', 'lineItemSkuCode', 'lineItemSkuName', 'lineItemDiscount', 'unit', 'quantity', 'pricePerUnit', 'standardPricePerUnit', 'sampleType', 'skuBatchId', 'organization', 'overrideQuantityPerUnitOfMeasure', 'lineItemManifestNotes', 'destinationSkuBatchId', 'additionalDiscount', 'promotionsDiscount') as $f) {
                    
                    $brandDoingBusinessAs = trim($s['A'] ?? '');
                    $orderNumber = trim($s['B'] ?? '');
                    $orderName = trim($s['C'] ?? '');
                    $paymentTerms = trim($s['D'] ?? '');
                    $orderCreationDate = trim($s['E'] ?? '');
                    $deliveryDate = trim($s['F'] ?? '');
                    $gmv = trim($s['G'] ?? '');
                    $orderDiscount = trim($s['H'] ?? '');
                    $creatorEmail = trim($s['I'] ?? '');
                    $status = trim($s['J'] ?? '');
                    $paymentStatus = trim($s['K'] ?? '');
                    $batchCode = trim($s['L'] ?? '');
                    $manufacturerLicenseNumber = trim($s['M'] ?? '');
                    $manufacturerLegalEntityName = trim($s['N'] ?? '');
                    //$lineItemSkuCode = trim($s['O'] ?? '');
					$lineItemSkuCode = trim(
						in_array($brandDoingBusinessAs, ['Pabst Labs', '#hashtag, Hella Dank', 'Micro Greenz']) &&
    					floatval(trim($s['S'] ?? '')) > 0.02
							? ($s['P'] ?? '')
							: ($s['O'] ?? '')
					);
                    $lineItemSkuName = trim($s['P'] ?? '');
                    $unit = trim($s['Q'] ?? '');
                    $quantity = trim($s['R'] ?? '');
                    $pricePerUnit = trim($s['S'] ?? '');
                    $sampleType = trim($s['T'] ?? '');
                    $skuBatchId = trim($s['U'] ?? '');
                    $additionalDiscount = trim($s['V'] ?? '');
                    $promotionsDiscount = trim($s['W'] ?? '');
                    $lineItemDiscount = trim($s['X'] ?? '');
                    $lineItemManifestNotes = trim($s['Y'] ?? '');

                    $daysTillPaymentDue = ''; //trim($s['D'] ?? '');
                    $standardPricePerUnit = ''; //trim($s['U'] ?? '');
                    $organization = ''; //trim($s['X'] ?? '');
                    $overrideQuantityPerUnitOfMeasure = ''; //trim($s['Y'] ?? '');
                    $destinationSkuBatchId = ''; //trim($s['AA'] ?? '');
					
					if ($pricePerUnit <= 0.01) {
						$lineItemSkuCode = $lineItemSkuCode . "-promo";
						}
					
                    if ($_orderNumber != $orderNumber) {
                        if (!in_array($orderNumber, $a_skip)) {
                            $rs = getRs("SELECT nabis_id FROM nabis WHERE filename <> ? AND orderNumber = ? AND " . is_enabled(), array($filename, $orderNumber));
                            if ($r = getRow($rs)) {
                                array_push($a_skip, $orderNumber);
                            }
                        }
                        if (!in_array($orderNumber, $a_skip)) {
                            $rs = getRs("SELECT nabis_id FROM nabis WHERE filename = ? AND orderNumber = ? AND " . is_enabled(), array($filename, $orderNumber));
                            if ($r = getRow($rs)) {
                                $nabis_id = $r['nabis_id'];
                            }
                            else {
                                $nabis_id = dbPut('nabis', array('filename' => $filename, 'store_id' => $_Session->store_id, 'admin_id' => $_Session->admin_id, 'orderNumber' => $orderNumber, 'orderName' => $orderName, 'orderCreationDate' => $orderCreationDate, 'brandDoingBusinessAs' => $brandDoingBusinessAs));
                                $num_orders++;
                                if (!$min_nabis_id) $min_nabis_id = $nabis_id;
                                $max_nabis_id = $nabis_id;
                            }
                        }
                        else {
                            $nabis_id = null;
                        }
                        $_orderNumber = $orderNumber;
                    }

                    if ($nabis_id) {
						dbPut('nabis_product', array('nabis_id' => $nabis_id, 'brandDoingBusinessAs' => $brandDoingBusinessAs, 'orderNumber' => $orderNumber, 'orderName' => $orderName, 'daysTillPaymentDue' => $daysTillPaymentDue, 'orderCreationDate' => $orderCreationDate, 'deliveryDate' => $deliveryDate, 'gmv' => $gmv, 'orderDiscount' => $orderDiscount, 'creatorEmail' => $creatorEmail, 'status' => $status, 'paymentStatus' => $paymentStatus, 'batchCode' => $batchCode, 'manufacturerLicenseNumber' => $manufacturerLicenseNumber, 'manufacturerLegalEntityName' => $manufacturerLegalEntityName, 'lineItemSkuCode' => $lineItemSkuCode, 'lineItemSkuName' => $lineItemSkuName, 'lineItemDiscount' => $lineItemDiscount, 'unit' => $unit, 'quantity' => $quantity, 'pricePerUnit' => $pricePerUnit, 'standardPricePerUnit' => $standardPricePerUnit, 'sampleType' => $sampleType, 'skuBatchId' => $skuBatchId, 'organization' => $organization, 'overrideQuantityPerUnitOfMeasure' => $overrideQuantityPerUnitOfMeasure, 'lineItemManifestNotes' => $lineItemManifestNotes, 'destinationSkuBatchId' => $destinationSkuBatchId, 'additionalDiscount' => $additionalDiscount, 'promotionsDiscount' => $promotionsDiscount));
						}
						/*if (($pricePerUnit * $quantity) > 0.01) {
                        dbPut('nabis_product', array('nabis_id' => $nabis_id, 'brandDoingBusinessAs' => $brandDoingBusinessAs, 'orderNumber' => $orderNumber, 'orderName' => $orderName, 'daysTillPaymentDue' => $daysTillPaymentDue, 'orderCreationDate' => $orderCreationDate, 'deliveryDate' => $deliveryDate, 'gmv' => $gmv, 'orderDiscount' => $orderDiscount, 'creatorEmail' => $creatorEmail, 'status' => $status, 'paymentStatus' => $paymentStatus, 'batchCode' => $batchCode, 'manufacturerLicenseNumber' => $manufacturerLicenseNumber, 'manufacturerLegalEntityName' => $manufacturerLegalEntityName, 'lineItemSkuCode' => $lineItemSkuCode, 'lineItemSkuName' => $lineItemSkuName, 'lineItemDiscount' => $lineItemDiscount, 'unit' => $unit, 'quantity' => $quantity, 'pricePerUnit' => $pricePerUnit, 'standardPricePerUnit' => $standardPricePerUnit, 'sampleType' => $sampleType, 'skuBatchId' => $skuBatchId, 'organization' => $organization, 'overrideQuantityPerUnitOfMeasure' => $overrideQuantityPerUnitOfMeasure, 'lineItemManifestNotes' => $lineItemManifestNotes, 'destinationSkuBatchId' => $destinationSkuBatchId, 'additionalDiscount' => $additionalDiscount, 'promotionsDiscount' => $promotionsDiscount));
						}
						if ($pricePerUnit = 0.01 AND $quantity = 1) {
								$D_brandDoingBusinessAs = 'Display Units';
								$D_orderNumber = $orderNumber;
								$D_orderName = $orderName;
								$D_paymentTerms = $paymentTerms;
								$D_orderCreationDate = $orderCreationDate;
								$D_deliveryDate = $deliveryDate;
								$D_gmv = $gmv;
								$D_orderDiscount = $orderDiscount;
								$D_creatorEmail = $creatorEmail;
								$D_status = $status;
								$D_paymentStatus = $paymentStatus;
								$D_batchCode = '9999';
								$D_manufacturerLicenseNumber = $manufacturerLicenseNumber;
								$D_manufacturerLegalEntityName = $manufacturerLegalEntityName;
								$D_lineItemSkuCode = 'DISPLAY-9999999';
								$D_lineItemSkuName = '**Display Units**';
								$D_unit = 'Each';
								$D_quantity += $quantity;
								$D_pricePerUnit = $pricePerUnit;
								$D_sampleType = $sampleType;
								$D_skuBatchId = 'DISPLAY';
								$D_additionalDiscount = $additionalDiscount;
								$D_promotionsDiscount = $promotionsDiscount;
								$D_lineItemDiscount = $lineItemDiscount;
								$D_lineItemManifestNotes = $lineItemManifestNotes;
							 } 
						}*/
                }
				//insert aggregated display units
				/*if ($nabis_id and $D_quantity > 0) {
					dbPut('nabis_product', array('nabis_id' => $nabis_id, 'brandDoingBusinessAs' => $D_brandDoingBusinessAs, 'orderNumber' => $D_orderNumber, 'orderName' => $D_orderName, 'daysTillPaymentDue' => $D_daysTillPaymentDue, 'orderCreationDate' => $D_orderCreationDate, 'deliveryDate' => $D_deliveryDate, 'gmv' => $D_gmv, 'orderDiscount' => $D_orderDiscount, 'creatorEmail' => $D_creatorEmail, 'status' => $D_status, 'paymentStatus' => $D_paymentStatus, 'batchCode' => $D_batchCode, 'manufacturerLicenseNumber' => $D_manufacturerLicenseNumber, 'manufacturerLegalEntityName' => $D_manufacturerLegalEntityName, 'lineItemSkuCode' => $D_lineItemSkuCode, 'lineItemSkuName' => $D_lineItemSkuName, 'lineItemDiscount' => $D_lineItemDiscount, 'unit' => $D_unit, 'quantity' => $D_quantity, 'pricePerUnit' => $D_pricePerUnit, 'standardPricePerUnit' => $D_standardPricePerUnit, 'sampleType' => $D_sampleType, 'skuBatchId' => $D_skuBatchId, 'organization' => $D_organization, 'overrideQuantityPerUnitOfMeasure' => $D_overrideQuantityPerUnitOfMeasure, 'lineItemManifestNotes' => $D_lineItemManifestNotes, 'destinationSkuBatchId' => $D_destinationSkuBatchId, 'additionalDiscount' => $D_additionalDiscount, 'promotionsDiscount' => $D_promotionsDiscount));
				}*/
            }
            $i++;
        }
    }
    else {
        $response = 'File not found';
    }
}

if ($num_orders) {
    for($_nabis_order_id = $min_nabis_id; $_nabis_order_id <= $max_nabis_id; $_nabis_order_id++) {
        $_PO->NabisSummary($_nabis_order_id);
    }
    $response = $num_orders . ' order(s) imported. ';
    $redirect = '/nabis-orders';
    $success = true;
}
else {
    $response = 'No orders found. ';
}
if (sizeof($a_skip)) {
    $success = false;
    $response .= sizeof($a_skip) . ' order' . iif(sizeof($a_skip) != 1, 's skipped because they were', 'skipped because it was') . ' previously imported: ' . implode(', ', $a_skip);
    if ($redirect) {
        $response .= ' <a href="' . $redirect . '">Reload page</a>';
        $redirect = null;
    }
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
