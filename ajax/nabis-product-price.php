<?php
require_once ('../_config.php');

$success = false;
$response = $product = $price = $subtotal = $nabis_id = $nabis_id = $nabis_code = $foot = null;

$nabis_product_id = getVarNum('n');
$price = getVarNum('price');

$rs = getRs("SELECT nabis_id, quantity FROM nabis_product WHERE nabis_product_id = ?", $nabis_product_id);
if ($r = getRow($rs)) {
    $nabis_id = $r['nabis_id'];
    dbUpdate('nabis_product', array('price' => $price), $nabis_product_id);
    $subtotal = currency_format($price * $r['quantity']);
    $price = number_format($price, 2);
    $success = true;
    $response = 'Price updated';
}
else {
    $response = 'Product not found';
}

if ($success) {
    $_summary = $_PO->NabisSummary($nabis_id);
    $foot = $_summary['foot'];
}


header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'price' => $price, 'subtotal' => $subtotal, 'foot' => $foot));
exit();
					
?>