<?php
require_once ('../_config.php');


$success = false;
$response = '';
$product = $mapping = $foot = $nabis_id = null;

$nabis_product_id = getVarNum('nabis_product_id');
$product_name = getVar('product_name');
$category_id = getVarNum('category_id');
$brand_id = getVarNum('brand_id');
$flower_type = getVar('flower_type');
$is_tax = getVarInt('is_tax');
$price = getVarNum('price');
$_subtotal = null;

if (!$product_name) $response .= 'Product name is required. ';
if (!$category_id) $response .= 'Product category is required. ';
if (!$brand_id) $response .= 'Product brand is required. ';
if (!$price) $response .= 'Product price is required. ';

if (!$response) {
    $rs = getRs("SELECT * FROM nabis_product WHERE nabis_product_id = ?", $nabis_product_id);
    if ($r = getRow($rs)) {
        $nabis_id = $r['nabis_id'];
        dbUpdate('nabis_product', array('product_id' => null, 'price' => $price, 'product_name' => $product_name, 'category_id' => $category_id, 'brand_id' => $brand_id, 'flower_type' => $flower_type, 'is_tax' => $is_tax), $nabis_product_id);
        $product = $product_name . ' - ' .  getDisplayName('category', $category_id, 'name', 'category_id', false, $_Session->db . '.') . ', ' .  getDisplayName('brand', $brand_id, 'name', 'brand_id', false, $_Session->db . '.') . ', ' . $flower_type . ' <a href="" class="btn-dialog" data-url="nabis-custom-product" data-a="' . $r['nabis_product_id'] . '"><i class="fa fa-pen"></i></a>';
        $_subtotal = currency_format($price * $r['quantity']);
        $price = number_format($price, 2);
        $success = true;
        $response = 'Custom product updated successfully';
    }
}

if ($success) {
    $_summary = $_PO->NabisSummary($nabis_id);
    $foot = $_summary['foot'];
    $mapping = $_summary['mapping'];
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'id' => $nabis_product_id, 'product' => $product, 'price' => $price, 'subtotal' => $_subtotal, 'nabis' => $success, 'mapping' => $mapping, 'foot' => $foot));
exit();
					
?>