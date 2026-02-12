<?php
define('SkipAuth', true);
require_once('/var/www/vhosts/wantadigital.com/www/theartisttree-mis/_config.php');
$start = time();
$args = $_SERVER['argv'];
$store_id = isset($args[1])?$args[1]:null;
$store_id=4;

$rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id DESC");
foreach($rs as $s) {
  $t_store_id = $s['store_id'];
  $_rs = getRs("SELECT * FROM {$s['db']}._sys_import_status WHERE _sys_import_status_id = 1 AND is_running = 1");
  if ($_r = getRow($_rs)) {
	echo '<li> Already running. Skipping ...</li>';
    continue;
  }
  
  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 1, import_start = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > daily sale batches'));
  $lookback = 60;
	$params = json_decode($s['params'], true);
	if (is_numeric($params['daily_sales_lookback_period'])) {
		$lookback = $params['daily_sales_lookback_period'];
	}
  
  $json = fetchApi('store/inventories', $s['api_url'], $s['auth_code'], $s['partner_key']);
  $a = json_decode($json, true);
  if (isset($a['values'])) {
    $a = $a['values'];
    if (is_array($a)) {
      echo '<li>' . $s['store_id'] . ' > ' . sizeof($a) . ' batch locations found</li>';
      foreach($a as $b) {
        $id = $b['id'];
        $type = $b['type'];
        $name = $b['name'];
        $product_batch_location_type_id = null;
        if (in_array($name, array('Safe'))) $product_batch_location_type_id = 1;
        if (in_array($name, array('Fulfillment', 'Fulfillment Vault', 'Showroom / Sales floor', 'Sample - Showroom'))) $product_batch_location_type_id = 2;
        $rr = getRs("SELECT product_batch_location_id FROM {$s['db']}.product_batch_location WHERE inventoryId = ?", array($id));
        if ($r = getRow($rr)) {
          setRs("UPDATE {$s['db']}.product_batch_location SET product_batch_location_name = ?, type = ?, date_modified = CURRENT_TIMESTAMP WHERE product_batch_location_id = ?", array($name, $type, $r['product_batch_location_id']));
        }
        else {    
          dbPut($s['db'] . '.product_batch_location', array('product_batch_location_name' => $name, 'type' => $type, 'inventoryId' => $id, 'product_batch_location_type_id' => $product_batch_location_type_id));
        }
      }
    }
  }


  $_rp = getRs("SELECT product_id, id, sku, category_id, brand_id, name, vendor_id, unitPrice FROM {$s['db']}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' AND is_batch_updated = 0");

  $_sys_log_id = dbPut($s['db'] . '._sys_log', array('tbl' => 'daily sale batches', 'notes' => sizeof($_rp) . ' product' . iif(sizeof($_rp) != 1, 's') . ' found'));

  foreach($_rp as $_p) {


  $product_id = $_p['product_id'];
  $productId = $_p['id'];
  $brand_id = $_p['brand_id'];
  $category_id = $_p['category_id'];
  $vendor_id = $_p['vendor_id'];




  setRs("DELETE FROM {$s['db']}.product_sale WHERE product_id = {$product_id}");
  /*
 AVIV REMOVE AND REPLACE WITH DAILY SALE BATCH RESET CODE 12.7.23
  
  $rp = getRs("SELECT
  p.sku, 
  SUM(COALESCE(i.quantity, 0)) AS sales, 
  FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
  FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
  CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
    SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
  ELSE 0 END AS valid_sales,
  DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days

  FROM 
  {$s['db']}.product p LEFT JOIN (
    {$s['db']}.items i LEFT JOIN (
      {$s['db']}.cart c LEFT JOIN (
          {$s['db']}.`transaction` t 
      ) ON t.transaction_id = c.transaction_id
    ) ON c.cart_id = i.cart_id
  ) ON i.product_id = p.product_id
  
  WHERE p.sku = ? 
  
  GROUP BY p.sku", array($_p['sku']));
  
  
  foreach($rp as $p) {
    $inv_1 = 0; //rand(30,40);
    $inv_2 = 0; //rand(30,40);
    
	dbPut($s['db'] . '.product_sale', array('id' => $_p['id'], 'name' => TRIM($_p['name']), 'product_id' => $product_id, 'category_id' => $category_id, 'brand_id' => $brand_id, 'vendor_id' => $vendor_id, 'price' => $_p['unitPrice'], 'sku' => $p['sku'], 'sales' => $p['sales'], 'valid_sales' => $p['valid_sales'], 'date_first_sale' => $p['date_first_sale'], 'date_last_sale' => $p['date_last_sale'], 'sale_days' => $p['sale_days'], 'daily_sales' => ($p['sale_days'])?ceil($p['valid_sales'] / $p['sale_days']):0, 'inv_1' => $inv_1, 'inv_2' => $inv_2));
  }
  
	  
$rp = getRs("SELECT
  p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,
  SUM(COALESCE(i.quantity, 0)) AS sales, 
  FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
  FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
  CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
    SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
  ELSE 0 END AS valid_sales_old,
  DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days_old,
IFNULL(id.DaysInInventory,0) sale_days,
SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 60 DAY)) 
	AND	((i.FinalPrice
	   - ((c.afterTaxDiscount / (c.SubTotal - c.TotalDiscount  + c.calccartdiscount)) * i.finalPrice)
		- ((c.calcCartDiscount / (c.SubTotal - c.totaldiscount + c.calccartdiscount)) * i.finalPrice))
	/ i.quantity > 1 ) 
	THEN i.quantity ELSE 0 END) AS valid_sales
  

  FROM 
  {$s['db']}.product p LEFT JOIN (
    {$s['db']}.items i LEFT JOIN (
      {$s['db']}.cart c LEFT JOIN (
          {$s['db']}.`transaction` t 
      ) ON t.transaction_id = c.transaction_id
    ) ON c.cart_id = i.cart_id
  ) ON i.product_id = p.product_id
LEFT JOIN theartisttree._Days_Inventory id 


ON id.store_id = {$t_store_id} AND p.product_id = id.product_id
  WHERE p.product_id = ? 
  GROUP BY p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,id.DaysInInventory", array($product_id));
*/
	  
$rp = getRs("SELECT
  p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,
  SUM(COALESCE(i.quantity, 0)) AS sales, 
  FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
  FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
  CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
    SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
  ELSE 0 END AS valid_sales_old,
  DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days_old,
IFNULL(id.DaysInInventory,0) sale_days,
SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL {$lookback} DAY)) 
	AND	((i.FinalPrice
	   - ((c.afterTaxDiscount / (c.SubTotal - c.TotalDiscount  + c.calccartdiscount)) * i.finalPrice)
		- ((c.calcCartDiscount / (c.SubTotal - c.totaldiscount + c.calccartdiscount)) * i.finalPrice))
	/ i.quantity > 1 ) 
	THEN i.quantity ELSE 0 END) AS valid_sales
  

  FROM 
  {$s['db']}.product p LEFT JOIN (
    {$s['db']}.items i LEFT JOIN (
      {$s['db']}.cart c LEFT JOIN (
          {$s['db']}.`transaction` t 
      ) ON t.transaction_id = c.transaction_id
    ) ON c.cart_id = i.cart_id
  ) ON i.product_id = p.product_id
LEFT JOIN (
			SELECT 
				i.product_id AS product_id,i.store_id AS store_id, 
				ifnull(COUNT(*),0) AS DaysInInventory
			FROM inventory i
			WHERE i.qty > 2 AND i.date_inventory > current_timestamp() - INTERVAL {$lookback} DAY
			GROUP BY i.product_id,i.store_id) as id ON id.store_id = {$t_store_id} AND p.product_id = id.product_id
  WHERE p.product_id = ? 
  GROUP BY p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,id.DaysInInventory", array($product_id));
  
  foreach($rp as $p) {
    $inv_1 = 0; //rand(30,40);
    $inv_2 = 0; //rand(30,40);
    dbPut($s['db'] . '.product_sale', array('sku' => $p['sku'], 'sales' => $p['sales'], 'valid_sales' => $p['valid_sales'], 'date_first_sale' => $p['date_first_sale'], 'date_last_sale' => $p['date_last_sale'], 'sale_days' => $p['sale_days'], 'daily_sales' => ($p['sale_days'])?($p['valid_sales'] / $p['sale_days']):0, 'inv_1' => $inv_1, 'inv_2' => $inv_2, 'id' => $p['id'], 'name' => $p['name'], 'product_id' => $p['product_id'], 'category_id' => $p['category_id'], 'brand_id' => $p['brand_id'], 'vendor_id' => $p['vendor_id'], 'price' => $p['unitPrice']));
  }
	  

  $ra = getRs("SELECT category_id, brand_id, AVG(daily_sales) AS daily_sales FROM {$s['db']}.product_sale WHERE category_id = ? AND brand_id = ? GROUP BY category_id, brand_id", array($category_id, $brand_id));
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
  SET s.daily_avg_sales = a.daily_sales WHERE COALESCE(s.daily_sales, 0) = 0 AND s.product_id = ?", array($product_id));

  // set nulls to zero for quick report comparisons
  setRs("UPDATE {$s['db']}.product_sale SET daily_sales = 0 WHERE daily_sales IS NULL AND product_id = ?", array($product_id));
  setRs("UPDATE {$s['db']}.product_sale SET daily_avg_sales = 0 WHERE daily_avg_sales IS NULL AND product_id = ?", array($product_id));






  setRs("DELETE FROM {$s['db']}.product_batch WHERE product_id = {$product_id}");
  $update = fetchApi('store/batches/' . $productId . '/batchQuantityInfo', $s['api_url'], $s['auth_code'], $s['partner_key']);
  $a = json_decode($update, true);
  if (is_array($a)) {
    foreach($a as $b) {
      if ($b['quantity'] != 0) {
        dbPut($s['db'] . '.product_batch', array('product_id' => $product_id, 'batchId' => $b['batchId'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity'], 'created' => $b['created'], 'modified' => $b['modified'], 'batchPurchaseDate' => $b['batchPurchaseDate']));
      }
    }
  }
  setRs("UPDATE {$s['db']}.product SET is_batch_updated = 1 WHERE product_id = ?", array($product_id));
  
  setRs("UPDATE {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.inventoryId = b.inventoryId
  SET b.product_batch_location_id = l.product_batch_location_id WHERE b.product_id = {$product_id}");

  setRs("UPDATE {$s['db']}.product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM {$s['db']}.product_batch b INNER JOIN {$s['db']}.product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id WHERE b.product_id = {$product_id} GROUP BY b.product_id) i ON i.product_id = p.product_id
  SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2 WHERE p.product_id = {$product_id}");






  }



  setRs("UPDATE {$s['db']}._sys_import_status SET is_running = 0, import_end = ? WHERE _sys_import_status_id = 1", array(date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > daily sale batches'));
  setRs("UPDATE {$s['db']}._sys_log SET duration = ? WHERE _sys_log_id = ?", array((time() - $start), $_sys_log_id));
}
echo '<li>Done</li>';
echo '<li> Duration ' . (time() - $start) . ' secs</li>';
?>
