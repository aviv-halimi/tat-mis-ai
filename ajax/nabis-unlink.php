<?php
require_once('../_config.php');

$success = false;
$response = $redirect = $swal = $nabis_id = null;

$nabis_product_id = getVarNum('n');

$rs = getRs("SELECT nabis_id, lineItemSkuCode FROM nabis_product WHERE nabis_product_id = ?", $nabis_product_id);
if ($r = getRow($rs)) {
    $nabis_id = $r['nabis_id'];
    $nabis_code = $r['lineItemSkuCode'];
    setRs("UPDATE nabis_product SET product_id = NULL, product_name = NULL, category_id = NULL, brand_id = NULL, flower_type = NULL, is_tax = 0, price = null WHERE nabis_product_id = ?", array($nabis_product_id));
    setRs("UPDATE {$_Session->db}.product SET nabis_code = NULL WHERE nabis_code = ?", array($nabis_code));
    $success = true;
    $response = 'Unmapped successfully';
}
else {
    $swal = 'Error';
    $response = 'Product not found';
}


$subtotal = $po_subtotal = $discount = $po_discount = $total = $po_total = 0;

$rs = getRs("SELECT p.*, t.product_id, t.sku, t.name, t.unitPrice FROM {$_Session->db}.product t RIGHT JOIN (nabis_product p INNER JOIN nabis n ON n.nabis_id = p.nabis_id) ON t.nabis_code = p.lineItemSkuCode WHERE n.nabis_id = ? AND " . is_active('n,p') . " ORDER BY CASE WHEN t.product_id THEN 1 ELSE 0 END, p.nabis_product_id", $nabis_id);
$linked = $unlinked = 0;
foreach($rs as $r) {    
    if ($r['product_id'] || $r['product_name']) {
        $linked++;
        $po_subtotal += ($r['quantity'] * $r['price']);
    }
    else {
        $unlinked++;
    }
    $discount = $r['orderDiscount'];
    $subtotal += ($r['quantity'] * $r['pricePerUnit']);
}
$total = $subtotal - $discount;
$po_total = $po_subtotal - $po_discount;

dbUpdate('nabis', array('subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'po_subtotal' => $po_subtotal, 'po_discount' => $po_discount, 'po_total' => $po_total), $nabis_id);

$mapping = iif($unlinked, '<i class="fa fa-exclamation-triangle text-danger"></i> ' . $unlinked . ' product' . iif($unlinked != 1, 's') . ' not yet mapped. ') . iif($linked and sizeof($rs) > $linked, '<i class="fa fa-check-circle"></i> ' . $linked . ' product' . iif($linked != 1, 's') . ' already mapped. ') . iif(sizeof($rs) == $linked, '<i class="fa fa-check-circle text-success"></i> All products mapped. ');

$foot = '<tr><th colspan="5">Subtotal</th><th>' . currency_format($subtotal) . '</th><th colspan="3"></th><th>' . currency_format($po_subtotal) . '</th></tr>
<tr><th colspan="5">Discount</th><th>' . currency_format($discount) . '</th><th colspan="3"></th><th>' . currency_format($po_discount) . '</th></tr>
<tr><th colspan="5">Total</th><th>' . currency_format($total) . '</th><th colspan="3"></th><th>' . currency_format($po_total) . '</th></tr>';

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'swal' => $swal, 'mapping' => $mapping, 'foot' => $foot));
exit();
