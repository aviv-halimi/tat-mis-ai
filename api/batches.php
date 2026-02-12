<?php
define('SkipAuth', true);
require_once('../_config.php');
$start = time();
$store_id = getVarNum('id');
$product_id = getVarNum('p');

$rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id DESC");
foreach($rs as $s) {
  if (!$product_id) setRs("TRUNCATE TABLE {$s['db']}.product_batch");

  $json = fetchApi('store/inventories', $s['api_url'], $s['auth_code'], $s['partner_key']);
  $a = json_decode($json, true);
  $a = $a['values'];
  foreach($a as $b) {
    $id = $b['id'];
    $type = $b['type'];
    $name = $b['name'];
    $product_batch_location_type_id = null;
    if (in_array($name, array('Safe'))) $product_batch_location_type_id = 1;
    if (in_array($name, array('Fulfillment Vault', 'Showroom / Sales floor'))) $product_batch_location_type_id = 2;
    $rr = getRs("SELECT product_batch_location_id FROM {$s['db']}.product_batch_location WHERE inventoryId = ?", array($id));
    if ($r = getRow($rr)) {
      setRs("UPDATE {$s['db']}.product_batch_location SET product_batch_location_name = ?, type = ? WHERE product_batch_location_id = ?", array($name, $type, $r['product_batch_location_id']));
    }
    else {    
      dbPut($s['db'] . '.product_batch_location', array('product_batch_location_name' => $name, 'type' => $type, 'inventoryId' => $id, 'product_batch_location_type_id' => $product_batch_location_type_id));
    }
  }

  ///

  $rr = getRs("SELECT product_id, id FROM {$s['db']}.product WHERE " . is_active() . " AND active = '1' AND deleted = ''" . iif($product_id, " AND product_id > {$product_id}") . " ORDER BY product_id");
  foreach($rr as $r) {
    $json = fetchApi('store/batches/' . $r['id'] . '/batchQuantityInfo', $s['api_url'], $s['auth_code'], $s['partner_key']);
    //echo '<li>---</li>';
    //echo $json;
    $a = json_decode($json, true);

    foreach($a as $b) {
      if ($b['quantity'] != 0) {
        dbPut($s['db'] . '.product_batch', array('product_id' => $r['product_id'], 'batchId' => $b['batchId'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity'], 'created' => $b['created'], 'modified' => $b['modified'], 'batchPurchaseDate' => $b['batchPurchaseDate']));
      }
    }
  }
  
  setRs("UPDATE {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.inventoryId = b.inventoryId
  SET b.product_batch_location_id = l.product_batch_location_id");

  setRs("UPDATE {$s['db']}.product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id GROUP BY b.product_id) i ON i.product_id = p.product_id
  SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2");

}
echo '<li>Done</li>';
echo '<li> Duration ' . (time() - $start) . ' secs</li>';

/*
UPDATE product_batch b INNER JOIN product_batch_location l ON l.inventoryId = b.inventoryId SET b.product_batch_location_id = l.product_batch_location_id

UPDATE product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM product_batch b INNER JOIN product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id GROUP BY b.product_id) i ON i.product_id = p.product_id
  SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2

*/
?>