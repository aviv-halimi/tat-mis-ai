<?php
define('SkipAuth', true);
require_once('../_config.php');
$start = time();
$store_id = getVarNum('id', 3);
$product_id = getVarNum('p', 1);

$rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id DESC");
foreach($rs as $s) {
  $rr = getRs("SELECT product_id, id FROM {$s['db']}.product" . iif($product_id, " WHERE product_id > {$product_id}") . " ORDER BY product_id");
  foreach($rr as $r) {
    $api_post = array(
      'currentEmployeeId' => '5e8217676218d17a57b166cf',
      'createByEmployeeId' => '5e8217676218d17a57b166cf',
      'acceptByEmployeeId' =>'5e8217676218d17a57b166cf',
      //'fromInventoryId' => '5e8217676218d17a57b166ca', //5e8217676218d17a57b166c7',
      'toInventoryId' => '5e8217676218d17a57b166c9', //5e8217676218d17a57b166c8',
      'completeTransfer' => true,
      'transferLogs' => array(
        array(
          'productId' => $r['id'],
          'transferAmount' => 4,
          'fromBatchId' => '5faf70976218d178452f3fd0'
          //'fromProductBatch' => array('productId' => $r['id'], 'quantity' => 9),
          //'toProductBatch' => array('productId' => $r['id'], 'quantity' => 9)
        )
      )
    );
    print_r(json_encode($api_post));
    $json = fetchApi('store/batches/transferInventory', $s['api_url'], $s['auth_code'], $s['partner_key'], null, $api_post);
    echo '<li>---</li>';
    echo $json;
    $a = json_decode($json, true);

    foreach($a as $b) {
      if (true) { //$b['quantity'] > 0) {
        //dbPut($s['db'] . '.product_batch', array('product_id' => $r['product_id'], 'batchId' => $b['id'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity']));
      }
    }
  }
}
echo '<li>Done</li>';
echo '<li> Duration ' . (time() - $start) . ' secs</li>';

?>