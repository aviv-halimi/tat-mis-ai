<?php
require_once ('../_config.php');

$success = false;
$response = $redirect = $swal = null;

$product_id = getVarNum('product_id');
$qty = getVarNum('qty');
$from_product_batch_location_id = getVarNum('from_product_batch_location_id');
$to_product_batch_location_id = getVarNum('to_product_batch_location_id');
$description = getVar('description');

$product_name = $from_product_batch_location_name = $to_product_batch_location_name = $productId = $from_inventoryId = $to_inventoryId = null;

$rs = getRs("SELECT id, name, sku FROM {$_Session->db}.product WHERE product_id = ?", array($product_id));
if ($r = getRow($rs)) {
  $product_name = $r['name'] . ' (' . $r['sku'] . ')';
  $productId = $r['id'];
}

$rs = getRs("SELECT * FROM {$_Session->db}.product_batch_location WHERE product_batch_location_id = ? OR product_batch_location_id = ? AND " . is_active(), array($from_product_batch_location_id, $to_product_batch_location_id));
foreach($rs as $r) {
  if ($r['product_batch_location_id'] == $from_product_batch_location_id) {
    $from_inventoryId = $r['inventoryId'];
    $from_product_batch_location_name = $r['product_batch_location_name'];
  }
  if ($r['product_batch_location_id'] == $to_product_batch_location_id) {
    $to_inventoryId = $r['inventoryId'];
    $to_product_batch_location_name = $r['product_batch_location_name'];
  }
}

if ($from_inventoryId == $to_inventoryId) {
  $response = 'You cannot move inventory to the same location';
}
if (!$from_inventoryId || !$to_inventoryId) {
  $response = 'You must select from and to inventory locations';
}
if (!$qty) {
  $response = 'Transfer amount is required';
}
if (!$productId) {
  $response = 'You must select a product';
}

if (!strlen($response)) {
  $transfer_product_id = dbPut('transfer_product', array('product_id' => $product_id, 'product_name' => $product_name, 'admin_id' => $_Session->admin_id, 'store_id' => $_Session->store_id, 'qty' => $qty, 'from_product_batch_location_id' => $from_product_batch_location_id, 'to_product_batch_location_id' => $to_product_batch_location_id, 'from_product_batch_location_name' => $from_product_batch_location_name, 'to_product_batch_location_name' => $to_product_batch_location_name, 'description' => $description));

  $a = $_Fulfillment->TransferProduct($product_id, $productId, $qty, $from_inventoryId, $to_inventoryId);

  $success = $a['success'];
  $response = $a['response'];
  $json = $a['json'];
  $swal = $a['swal'];
  $params = $a['params'];

  dbUpdate('transfer_product', array('response' => $response, 'api_success' => ($success)?1:0, 'api_response' => $json, 'params' => json_encode($params)), $transfer_product_id);

  if ($success) $redirect = '{refresh}';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'swal' => $swal));
exit();
					
?>