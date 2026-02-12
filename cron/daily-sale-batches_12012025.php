<?php
require_once('../_config.php');
$success = false;
$response = '';

$start = time();
$store_id = getVarNum('store_id', null);
$product_id = null;

$rs = getRs("SELECT store_name, store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id DESC");
foreach($rs as $s) {
  $t_store_id = $s['store_id'];
  $_rs = getRs("SELECT * FROM {$s['db']}._sys_import_status WHERE _sys_import_status_id = 1 AND is_running = 1");
  if ($_r = getRow($_rs)) {
    $response .= '<li> ' . $s['store_name'] . ': Already running. Skipping ...</li>';
    continue;
  }

  
  $response .= '<li> -- Starting: ' . $s['store_name']  . ' --<ul>';

  $_sys_log_id = dbPut($s['db'] . '._sys_log', array('tbl' => 'daily sales (cron)'));
  
  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 1, import_start = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > daily sales (cron)'));

  $response .= '<li> Truncating Sales Data...';

  setRs("TRUNCATE TABLE {$s['db']}.product_sale");
  setRs("TRUNCATE TABLE {$s['db']}.product_avg_sale");

  $response .= 'Done</li>';
  $response .= '<li> Inserting New Sales Data...';

  // $rp = getRs("SELECT
  // p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,
  // SUM(COALESCE(i.quantity, 0)) AS sales, 
  // FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
 //  FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
  // CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
  //   SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
  // ELSE 0 END AS valid_sales,
  // DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) // ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days

  // FROM 
  // {$s['db']}.product p LEFT JOIN (
  //   {$s['db']}.items i LEFT JOIN (
  //     {$s['db']}.cart c LEFT JOIN (
   //        {$s['db']}.`transaction` t 
  //     ) ON t.transaction_id = c.transaction_id
  //   ) ON c.cart_id = i.cart_id
 //  ) ON i.product_id = p.product_id
// 
//   GROUP BY p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice");
	
$rp = getRs("SELECT
  p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,
  SUM(COALESCE(i.quantity, 0)) AS sales, 
  FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
  FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
  CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
    SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
  ELSE 0 END AS valid_sales_old,
  DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days_old,
IFNULL(SUM(id.DaysInInventory),0) sale_days,
SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 60 day)) THEN i.quantity ELSE 0 END) AS valid_sales
  

  FROM 
  {$s['db']}.product p LEFT JOIN (
    {$s['db']}.items i LEFT JOIN (
      {$s['db']}.cart c LEFT JOIN (
          {$s['db']}.`transaction` t 
      ) ON t.transaction_id = c.transaction_id
    ) ON c.cart_id = i.cart_id
  ) ON i.product_id = p.product_id
LEFT JOIN theartisttree._Days_Inventory id ON id.store_id = {$t_store_id} AND p.product_id = id.product_id
  GROUP BY p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice");
  
  foreach($rp as $p) {
    $inv_1 = 0; //rand(30,40);
    $inv_2 = 0; //rand(30,40);
    dbPut($s['db'] . '.product_sale', array('sku' => $p['sku'], 'sales' => $p['sales'], 'valid_sales' => $p['valid_sales'], 'date_first_sale' => $p['date_first_sale'], 'date_last_sale' => $p['date_last_sale'], 'sale_days' => $p['sale_days'], 'daily_sales' => ($p['sale_days'])?ceil($p['valid_sales'] / $p['sale_days']):0, 'inv_1' => $inv_1, 'inv_2' => $inv_2, 'id' => $p['id'], 'name' => $p['name'], 'product_id' => $p['product_id'], 'category_id' => $p['category_id'], 'brand_id' => $p['brand_id'], 'vendor_id' => $p['vendor_id'], 'price' => $p['unitPrice']));
  }

  $ra = getRs("SELECT category_id, brand_id, AVG(daily_sales) AS daily_sales FROM {$s['db']}.product_sale WHERE brand_id AND category_id GROUP BY category_id, brand_id");
  foreach($ra as $a) {
    $_ra = getRs("SELECT product_avg_sale_id FROM {$s['db']}.product_avg_sale WHERE category_id = {$a['category_id']} AND brand_id = {$a['brand_id']}");
    if (!sizeof($_ra)) {
      dbPut($s['db'] . '.product_avg_sale', array('category_id' => $a['category_id'], 'brand_id' => $a['brand_id'], 'daily_sales' => $a['daily_sales']));
    }
    else {
      setRs("UPDATE {$s['db']}.product_avg_sale SET daily_sales = ? WHERE category_id = ? AND brand_id = ?", array($a['daily_sales'], $a['category_id'], $a['brand_id']));
    }
  }

  setRs("UPDATE {$s['db']}.product_sale s INNER JOIN {$s['db']}.product_avg_sale a ON s.category_id = a.category_id AND s.brand_id = a.brand_id
  SET s.daily_avg_sales = a.daily_sales WHERE COALESCE(s.daily_sales, 0) = 0");

  // set nulls to zero for quick report comparisons
  setRs("UPDATE {$s['db']}.product_sale SET daily_sales = 0 WHERE daily_sales IS NULL");
  setRs("UPDATE {$s['db']}.product_sale SET daily_avg_sales = 0 WHERE daily_avg_sales IS NULL");

  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 0, import_end = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > daily sales (cron)'));
  setRs("UPDATE {$s['db']}._sys_log SET duration = ? WHERE _sys_log_id = ?", array((time() - $start), $_sys_log_id));
  $response .= 'Done</li>';

  ////////////////////////////////////////////////////////////

  $_sys_log_id = dbPut($s['db'] . '._sys_log', array('tbl' => 'batches'));
  
  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 1, import_start = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > batches (cron)'));

  $response .= '<li> Truncating Inventory Data...';

  if (!$product_id) setRs("TRUNCATE TABLE {$s['db']}.product_batch");

  $response .= 'Done</li>';
  $response .= '<li> Inserting New Inventory Data...';

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
    $a = json_decode($json, true);

    foreach($a as $b) {
      if ($b['quantity'] != 0) {
        $cost = $costPerUnit = null;
        dbPut($s['db'] . '.product_batch', array('product_id' => $r['product_id'], 'batchId' => $b['batchId'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity'], 'cost' => $cost, 'costPerUnit' => $costPerUnit, 'created' => $b['created'], 'modified' => $b['modified'], 'batchPurchaseDate' => $b['batchPurchaseDate']));
      }
    }
    setRs("UPDATE {$s['db']}.product SET is_batch_updated = 1 WHERE product_id = ?", array($r['product_id']));

    
  }
  $response .= 'Done</li>';
  $response .= '<li> Adding References ...';
  
  setRs("UPDATE {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.inventoryId = b.inventoryId
  SET b.product_batch_location_id = l.product_batch_location_id");

  setRs("UPDATE {$s['db']}.product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id GROUP BY b.product_id) i ON i.product_id = p.product_id
  SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2");

  $response .= 'Done</li></ul></li>';


  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 0, import_end = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > batches (cron)'));
  setRs("UPDATE {$s['db']}._sys_log SET duration = ? WHERE _sys_log_id = ?", array((time() - $start), $_sys_log_id));


}
$success = true;
$response = '<ul>' . $response . '<li> All Done -> Duration ' . (time() - $start) . ' secs</li></ul>';

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response));
?>
