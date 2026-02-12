<?php
require_once ('../_config.php');

$success = false;
$response = $html = '';

$product_id = getVarNum('id');

$rp = getRs("SELECT id, is_batch_updated FROM {$_Session->db}.product WHERE product_id = ?", array($product_id));
if ($p = getRow($rp)) {
  //if (!$p['is_batch_updated']) 
  $_Fulfillment->UpdateInventory($product_id, $p['id']);
  $rs = getRs("SELECT l.product_batch_location_name, b.qty, b.batchId, b.batchPurchaseDate FROM {$_Session->db}.product_batch_location l INNER JOIN {$_Session->db}.product_batch b ON b.product_batch_location_id = l.product_batch_location_id WHERE " . is_enabled('l') . " AND b.qty <> 0 AND b.product_id = ?", array($product_id));
  foreach($rs as $r) {
    $html .= '<div class="mt-2">' . yesNoFormat(1) . ' ' . $r['product_batch_location_name'] . ': <b>' . number_format($r['qty']) . '</b>' . iif($r['batchPurchaseDate'], ' <i>(' . date('j/n/Y g:i a', $r['batchPurchaseDate'] / 1000) . ')</i>') . '</div>';
  }
  if (!strlen($html)) {
    $html = '<div class="mt-2">' . yesNoFormat(0) . ' No inventory found for this item</div>';
  }
  $success = true;
}
else {
  $response = 'Invalid product';
}
header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'html' => $html));
exit();
					
?>