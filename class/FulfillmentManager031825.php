<?php

class FulfillmentManager extends SessionManager {
  
  function NewTransferReport($_p) {
    $restock_type_id = getVarANum('restock_type_id', $_p);
    $rs = $this->GetTransfers($restock_type_id);
    $transfer_report_id = dbPut('transfer_report', array('store_id' => $this->store_id, 'transfer_report_status_id' => 1, 'admin_id' => $this->admin_id, 'restock_type_id' => $restock_type_id, 'num_products' => sizeof($rs), 'params' => json_encode($_p)));
    $num_products = 0;
    $re = getRs("SELECT exclude_product_name FROM exclude_product WHERE " . is_enabled());
    foreach($rs as $r) {
      $add = true;
      if ($add) {
        foreach($re as $e) {
          if (strpos(strtolower($r['product_name']), strtolower($e['exclude_product_name'])) !== false) {
            $add = false;
            break;
          }
        }
      }
      if ($add) {
        $num_products++;
        dbPut('transfer_report_product', array('transfer_report_id' => $transfer_report_id, 'product_id' => $r['product_id'], 'par_level' => $r['par_level'], 'fulfillment_level' => $r['fulfillment_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'suggested_qty' => $r['suggested_qty']));
      }
    }
    dbUpdate('transfer_report', array('num_products' => $num_products), $transfer_report_id);
    return $transfer_report_id;
  }

  function UpdateTransferReport($_p) {
    $transfer_report_id = getVarANum('transfer_report_id', $_p);
    $restock_type_id = getVarANum('restock_type_id', $_p);
    dbUpdate('transfer_report', array('restock_type_id' => $restock_type_id, 'params' => json_encode($_p)), $transfer_report_id);
    $progress = $this->TransferReportProgress($transfer_report_id);
    if (!$progress['num_products']) {
      $rs = $this->GetTransfers($restock_type_id);
      $num_products = 0;
      $re = getRs("SELECT exclude_product_name FROM exclude_product WHERE " . is_enabled());
      foreach($rs as $r) {
        $add = true;
        if ($add) {
          foreach($re as $e) {
            if (strpos(strtolower($r['product_name']), strtolower($e['exclude_product_name'])) !== false) {
              $add = false;
              break;
            }
          }
        }
        if ($add) {
          $num_products++;
          dbPut('transfer_report_product', array('transfer_report_id' => $transfer_report_id, 'product_id' => $r['product_id'], 'par_level' => $r['par_level'], 'fulfillment_level' => $r['fulfillment_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'suggested_qty' => $r['suggested_qty']));
        }
      }
      dbUpdate('transfer_report', array('num_products' => $num_products), $transfer_report_id);
    }
  }

  function GetTransfers($_restock_type_id = null, $_category_id = null, $_brand_id = null, $disaggregate_ids = array(), $_sort_by = null) {

    $this->UpdateLatestInventory();

    $rs = array();
    $rt = getRs("SELECT restock_type_id, restock_type_name, field_level FROM restock_type WHERE restock_type_id = ?", array($_restock_type_id));
    if ($t = getRow($rt)) {
    
      $field_level = $t['field_level'];
      //,int', 'restock_level,int', 'emergency_restock
      $max_daily_sales = null;
      $rs = getRs("SELECT MAX(daily_sales) AS max_daily_sales FROM {$this->db}.par_lookup");
      if ($r = getRow($rs)) {
        $max_daily_sales = $r['max_daily_sales'];
      }
      $params = array();
      if ($_brand_id) array_push($params, $_brand_id);
      if ($_category_id) array_push($params, $_category_id);
      
      $a_sql_order = array();
      if(in_array(1, $disaggregate_ids)) {
        array_push($a_sql_order, 'c.name');
      }
      if (in_array(2, $disaggregate_ids)) {
        array_push($a_sql_order, 'b.name');
      }
      array_push($a_sql_order, iif($_sort_by == 2, 'p.sku', 'p.name'));

      $rs = getRs("SELECT c.category_id, c.name AS category_name, b.brand_id, b.name AS brand_name, p.product_id, p.name AS product_name, p.sku, p.inv_1, p.inv_2, COALESCE(_s.suggested_par_level, _b.suggested_par_level, _p.suggested_par_level, 0) AS par_level, COALESCE(_s.par_level, _b.par_level, _p.par_level, 0) AS fulfillment_level, CASE WHEN COALESCE(_s.suggested_par_level, _b.suggested_par_level, _p.suggested_par_level, 0) - p.inv_2 < p.inv_1 THEN COALESCE(_s.suggested_par_level, _b.suggested_par_level, _p.suggested_par_level, 0) - p.inv_2 ELSE p.inv_1 END AS suggested_qty FROM 
      {$this->db}.category c RIGHT JOIN (
        {$this->db}.brand b RIGHT JOIN (
          (SELECT daily_sales, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.par_lookup WHERE " . is_enabled() . ") AS _p RIGHT JOIN (
            (SELECT brand_id, category_id, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.brand_override WHERE " . is_enabled() . ") AS _b RIGHT JOIN (
              (SELECT product_id, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.sku_override WHERE " . is_enabled() . ") AS _s RIGHT JOIN (
                {$this->db}.product_sale p
              ) ON _s.product_id = p.product_id
            ) ON ((_b.brand_id = p.brand_id AND _b.category_id = p.category_id) OR (_b.brand_id IS NULL AND _b.category_id = p.category_id) OR (_b.brand_id = p.brand_id AND _b.category_id IS NULL))
          ) ON ((_p.daily_sales = p.daily_sales AND p.daily_sales > 0) OR (_p.daily_sales = p.daily_avg_sales AND p.daily_avg_sales > 0) OR (_p.daily_sales = {$max_daily_sales} AND p.daily_sales > {$max_daily_sales} AND p.daily_avg_sales = 0) OR (_p.daily_sales = {$max_daily_sales} AND p.daily_avg_sales > {$max_daily_sales} AND p.daily_sales = 0))
        ) ON b.brand_id = p.brand_id
      ) ON c.category_id = p.category_id
      WHERE " . is_enabled('p') . " AND p.inv_1 > 0 AND COALESCE(_s.par_level, _b.par_level, _p.par_level, 0) > p.inv_2"  . iif($_category_id, " AND p.category_id = ?") . iif($_brand_id, " AND p.brand_id = ?") . " ORDER BY " . implode(", ", $a_sql_order), $params);
      //
    }
    return $rs;
  }

  function GetTransferReportProducts($transfer_report_id, $_category_id = null, $_brand_id = null, $disaggregate_ids = array(), $_sort_by = null) {
      
    $params = array($transfer_report_id);
    if ($_brand_id) array_push($params, $_brand_id);
    if ($_category_id) array_push($params, $_category_id);

    $a_sql_order = array();
    if(in_array(1, $disaggregate_ids)) {
      array_push($a_sql_order, 'c.name');
    }
    if (in_array(2, $disaggregate_ids)) {
      array_push($a_sql_order, 'b.name');
    }
    array_push($a_sql_order, iif($_sort_by == 2, 'p.sku', 'p.name'));

    return getRs("SELECT t.transfer_report_product_id, t.transfer_report_product_code, t.api_success, c.category_id, c.name AS category_name, b.brand_id, b.name AS brand_name, p.product_id, p.name AS product_name, p.sku, p.id, t.inv_1, t.inv_2, t.par_level, t.fulfillment_level, t.suggested_qty, t.transfer_qty FROM 
    {$this->db}.category c RIGHT JOIN (
      {$this->db}.brand b RIGHT JOIN (
        {$this->db}.product_sale p INNER JOIN transfer_report_product t ON t.product_id = p.product_id
      ) ON b.brand_id = p.brand_id
    ) ON c.category_id = p.category_id
    WHERE t.transfer_report_id = ? AND " . is_enabled('t') . iif($_brand_id, " AND p.brand_id = ?") . iif($_category_id, " AND p.category_id = ?") . " ORDER BY " . implode(", ", $a_sql_order), $params);
  }

  function SaveTransferReportProduct($_p) {
    $success = false;
    $response = $percent = null;

    $transfer_report_code = getVarA('c' , $_p);
    $transfer_report_product_code = getVarA('p' , $_p);
    $transfer_qty = getVarANum('qty' , $_p);

    $transfer_report_id = $this->GetCodeId('transfer_report', $transfer_report_code);

    if (str_len($transfer_report_product_code)) {
      setRs("UPDATE transfer_report_product SET transfer_qty = ? WHERE transfer_report_id = ? AND transfer_report_product_code = ?", array($transfer_qty, $transfer_report_id, $transfer_report_product_code));
      $success = true;
      $response = 'Completed successfully';
      $_tp = $this->TransferReportProgress($transfer_report_id);
      $progress = $_tp['progress'];
      $percent = $_tp['percent'];
    }
    else {
      $response = 'Product not defined';
    }

    return array('success' => $success, 'response' => $response, 'progress' => $progress, 'percent' => $percent);
  }

  function TransferReportProgress($transfer_report_id) {
    $success = false;
    $response = null;
    $num_products = $num_transfers = $percent = 0;

    $rt = getRs("SELECT COUNT(p.product_id) AS num_products, SUM(CASE WHEN p.transfer_qty > 0 THEN 1 ELSE 0 END) AS num_transfers FROM transfer_report_product p WHERE " . is_enabled('p') . " AND p.transfer_report_id = ?", array($transfer_report_id));
    if ($t = getRow($rt)) {
      $success = true;
      $num_products = $t['num_products'];
      $num_transfers = $t['num_transfers'];
      $response = $t['num_products'] . ' product' . iif($t['num_products'] != 1, 's');
      if ($t['num_transfers']) {
        $response .= ' / ' . $t['num_transfers'] . ' transfer'. iif($t['num_transfers'] != 1, 's') . ' completed';
        if ($t['num_products']) $percent = $t['num_transfers'] / $t['num_products'] * 100;
      }
      $transfer_report_status_id = ($num_transfers)?2:1;
      dbUpdate('transfer_report', array('num_transfers' => $t['num_transfers'], 'transfer_report_status_id' => $transfer_report_status_id), $transfer_report_id);
    }
    return array('success' => $success, 'response' => $response, 'progress' => $response, 'percent' => number_format($percent, 1), 'num_products' => $num_products, 'num_transfers' => $num_transfers);
  }

  function SaveTransferReportAPI($_p) {
    $success = false;
    $response = null;
    $transfer_report_api_status_id = null;

    $transfer_report_code = getVarA('c' , $_p);
    $product_id = getVarA('product_id' , $_p);
    $rt = getRs("SELECT * FROM transfer_report p WHERE " . is_enabled('p') . " AND p.transfer_report_code = ?", array($transfer_report_code));
    if ($t = getRow($rt)) {
      $transfer_report_id = $t['transfer_report_id'];
      $from_inventoryId = $to_inventoryId = null;

      $_rl = getRs("SELECT product_batch_location_type_id, inventoryId FROM {$this->db}.product_batch_location WHERE product_batch_location_type_id = 1 OR product_batch_location_type_id = 2");
      foreach($_rl as $_l) {
        if ($_l['product_batch_location_type_id'] == 1) $from_inventoryId = $_l['inventoryId'];
        if ($_l['product_batch_location_type_id'] == 2) $to_inventoryId = $_l['inventoryId'];
      }

      $rp = $this->GetTransferReportProducts($transfer_report_id);
      foreach($rp as $p) {
        if ($p['transfer_qty'] > 0 and !$p['api_success'] and (!$product_id || $p['product_id'] == $product_id)) {
          $api = $this->TransferProduct($p['product_id'], $p['id'], $p['transfer_qty'], $from_inventoryId, $to_inventoryId, true);
          if ($api['success']) $transfer_report_api_status_id = 1;
          $response = $api['response'];
          dbUpdate('transfer_report_product', array('api_success' => $api['success']?1:0, 'api_response' => $api['response']), $p['transfer_report_product_id']);
        }
      }
      if ($transfer_report_api_status_id == 1) {
        $success = true;
        if (!$response) $api_response = $response = 'API success';
      }
      dbUpdate('transfer_report', array('transfer_report_status_id' => $success?3:2, 'transfer_report_api_status_id' => $transfer_report_api_status_id, 'api_response' => $response), $transfer_report_id);
    }
    return array('success' => $success, 'response' => $response);
  }

  function SetEmployeeId() {
    if (!$this->employeeId) {
      $re = getRs("SELECT id FROM {$this->db}.employee WHERE employee_id = ?", array($this->employee_id));
      if ($e = getRow($re)) {
        $this->employeeId = $e['id'];
      }
    }
  }

  function TransferProduct($product_id, $productId, $qty, $from_inventoryId, $to_inventoryId, $updateInventory = false) {
    $success = false;
    $response = $swal = $json = null;
    $api_post = array();

    $this->SetEmployeeId();

    $transferLogs = array();
    $rs = getRs("SELECT batchId, qty FROM {$this->db}.product_batch WHERE qty > 0 AND product_id = ? AND inventoryId = ? ORDER BY batchPurchaseDate, product_batch_id", array($product_id, $from_inventoryId));
    foreach($rs as $r) {
      $_qty = ($qty > $r['qty'])?$r['qty']:$qty;
      array_push($transferLogs, 
        array(
          'productId' => $productId,
          'transferAmount' => $_qty,
          'fromBatchId' => $r['batchId']
        )
      );
      $qty -= $r['qty'];
      if ($qty <= 0) {
        break;
      }
    }
    if (!sizeof($transferLogs)) {
      $response = 'No items found in selected location.';
    }
    if ($qty > 0) {
      $response = 'There are not enough items in selected inventory location to complete this request.';
    }
    if (!str_len($response)) {
      $api_post = array(
        'currentEmployeeId' => $this->employeeId,
        'createByEmployeeId' => $this->employeeId,
        'acceptByEmployeeId' => $this->employeeId,
        'status' => 'ACCEPTED',
        'fromInventoryId' => $from_inventoryId,
        'toInventoryId' => $to_inventoryId,
        'completeTransfer' => true,
        'transferLogs' => $transferLogs
      );
      $json = fetchApi('store/batches/transferInventory', $this->api_url, $this->auth_code, $this->partner_key, null, $api_post);
      if (isJson($json)) {
        $a_json = json_decode($json, true);
        /*
        {"field":"Transfer Request","message":"Transfer amount must be greater than 0.","errorType":"com.fourtwenty.core.exceptions.BlazeInvalidArgException","references":null}

        {"id":"5fd946d45b5b1109088171d9","created":1608074964231,"modified":1608074964231,"deleted":false,"updated":false,"companyId":"5dae60917c3a500845335a84","shopId":"5dae60917c3a500845335a9c","dirty":false,"status":"PENDING","createByEmployeeId":"5ef2ae88c6e58008ca3381ad","acceptByEmployeeId":null,"declineByEmployeeId":null,"fromShopId":"5dae60917c3a500845335a9c","toShopId":"5dae60917c3a500845335a9c","fromInventoryId":"5dae60917c3a500845335ac1","toInventoryId":"5dd33347635fc30871d1b59d","transferLogs":[{"productId":"5f84c193fa9dea08f63a41bd","prepackageItemId":null,"transferAmount":5.0,"finalInventory":null,"origFromQty":null,"finalFromQty":null,"origToQty":null,"prevTransferAmt":null,"fromBatchId":"5fa9b78de6d4be08ca41f01f","fromBatchInfo":null,"prepackageName":null,"toBatchId":null,"fromProductBatch":null,"toProductBatch":null,"batchQuantityMap":{},"product":null}],"batchQuantityInfoMap":{},"declinedDate":null,"acceptedDate":null,"transferNo":"16490","completeTransfer":false,"transferByBatch":false,"processedTime":null,"processing":false,"driverId":null}
        */
        if (isset($a_json['transferNo'])) {
          $success = true;
          $response = 'API call completed successfully (transferNo: ' . $a_json['transferNo'] . ')';
          if ($updateInventory) {
            $this->UpdateInventory($product_id, $productId);
          }
        }
        else {
          $response = isset($a_json['message'])?$a_json['message']:'API called failed with an unspecified message';
        }
      }
      else {
        $response = 'API call failed with a fatal error.';
      }
    }
    if (!$success) {
      $swal = 'API Error';
    }
    //$response .= print_r(json_encode($transferLogs), true);
    return array('success' => $success, 'response' => $response, 'swal' => $swal, 'params' => $api_post, 'json' => $json);
  }

  function UpdateLatestProductInventory($_p) {
    $transfer_report_id = getVarA('transfer_report_id', $_p);
    $product_id = getVarA('product_id', $_p);
    $inv_1 = $inv_2 = null;
    $rs = getRs("SELECT product_id, name, id, sku, category_id, brand_id, vendor_id FROM {$this->db}.product WHERE " . is_active() . " AND product_id = ?", array($product_id));
    foreach($rs as $r) {
      $this->UpdateInventory($r['product_id'], $r['id']);
      $ri = getRs("SELECT inv_1, inv_2 FROM {$this->db}.product_sale WHERE product_id = ?", array($product_id));
      if ($i = getRow($ri)) {
        $inv_1 = $i['inv_1'];
        $inv_2 = $i['inv_2'];
        setRs("UPDATE transfer_report_product SET inv_1 = ?, inv_2 = ? WHERE product_id = ? AND transfer_report_id = ?", array($inv_1, $inv_2, $product_id, $transfer_report_id));
      }
    }
    return array('success' => true, 'response' => 'Done', 'inv_1' => $inv_1, 'inv_2' => $inv_2);
  }

  function UpdateLatestInventory() {
    $rs = getRs("SELECT product_id, name, id, sku, category_id, brand_id, vendor_id FROM {$this->db}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' AND is_batch_updated = 0");
    foreach($rs as $r) {
      //$this->UpdateSales($r['product_id'], $r['sku'], $r['category_id'], $r['brand_id']);
      $this->UpdateInventory($r['product_id'], $r['id']);
    }
  }

  function UpdateInventory($product_id, $productId) {
    $_rp = getRs("SELECT product_id, id, sku, category_id, brand_id, name, vendor_id, unitPrice FROM {$this->db}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' AND product_id = ?", array($product_id));
  
    foreach($_rp as $_p) {
  
  
      $product_id = $_p['product_id'];
      $productId = $_p['id'];
      $brand_id = $_p['brand_id'];
      $category_id = $_p['category_id'];
      $vendor_id = $_p['vendor_id'];    
    
      setRs("DELETE FROM {$this->db}.product_sale WHERE product_id = {$product_id}");
      $rp = getRs("SELECT
      p.product_id, p.id, p.sku AS productSku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice,
      SUM(COALESCE(i.quantity, 0)) AS sales, 
      FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
      FROM_UNIXTIME(MAX(t.created)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
      CASE WHEN MAX(t.created)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
        SUM(CASE WHEN t.created/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN i.quantity ELSE 0 END)
      ELSE 0 END AS valid_sales,
      DATEDIFF(NOW(), (CASE WHEN MIN(t.created)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.created)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days
    
      FROM 
      {$this->db}.product p LEFT JOIN (
        {$this->db}.items i LEFT JOIN (
          {$this->db}.cart c LEFT JOIN (
              {$this->db}.`transaction` t 
          ) ON t.transaction_id = c.transaction_id
        ) ON c.cart_id = i.cart_id
      ) ON i.product_id = p.product_id
    
      WHERE p.sku = ? GROUP BY p.product_id, p.id, p.sku, p.name, p.category_id, p.brand_id, p.vendor_id, p.unitPrice", array($_p['sku']));
      
      foreach($rp as $p) {
        $inv_1 = 0; //rand(30,40);
        $inv_2 = 0; //rand(30,40);
        dbPut($this->db . '.product_sale', array('id' => $p['id'], 'name' => TRIM($_p['name']), 'product_id' => $product_id, 'category_id' => $_p['category_id'], 'brand_id' => $_p['brand_id'], 'vendor_id' => $_p['vendor_id'], 'price' => $_p['unitPrice'], 'sku' => $p['productSku'], 'sales' => $p['sales'], 'valid_sales' => $p['valid_sales'], 'date_first_sale' => $p['date_first_sale'], 'date_last_sale' => $p['date_last_sale'], 'sale_days' => $p['sale_days'], 'daily_sales' => ($p['sale_days'])?ceil($p['valid_sales'] / $p['sale_days']):0, 'inv_1' => $inv_1, 'inv_2' => $inv_2));
      }
    
      $ra = getRs("SELECT category_id, brand_id, AVG(daily_sales) AS daily_sales FROM {$this->db}.product_sale WHERE daily_sales > 0 AND category_id = ? AND brand_id = ? GROUP BY category_id, brand_id", array($category_id, $brand_id));
      foreach($ra as $a) {
        setRs("UPDATE {$this->db}.product_avg_sale SET daily_sales = ? WHERE category_id = ? AND brand_id = ?", array($a['daily_sales'], $a['category_id'], $a['brand_id']));
      }
    
      setRs("UPDATE {$this->db}.product_sale s INNER JOIN {$this->db}.product_avg_sale a ON s.category_id = a.category_id AND s.brand_id = a.brand_id
      SET s.daily_avg_sales = a.daily_sales WHERE COALESCE(s.daily_sales, 0) = 0 AND s.product_id = ?", array($product_id));
    
      // set nulls to zero for quick report comparisons
      setRs("UPDATE {$this->db}.product_sale SET daily_sales = 0 WHERE daily_sales IS NULL AND product_id = ?", array($product_id));
      setRs("UPDATE {$this->db}.product_sale SET daily_avg_sales = 0 WHERE daily_avg_sales IS NULL AND product_id = ?", array($product_id));
    
      setRs("DELETE FROM {$this->db}.product_batch WHERE product_id = {$product_id}");
      $update = fetchApi('store/batches/' . $productId . '/batchQuantityInfo', $this->api_url, $this->auth_code, $this->partner_key);
      $a = json_decode($update, true);
      foreach($a as $b) {
        if ($b['quantity'] != 0) {
          dbPut($this->db . '.product_batch', array('product_id' => $product_id, 'batchId' => $b['batchId'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity'], 'created' => $b['created'], 'modified' => $b['modified'], 'batchPurchaseDate' => $b['batchPurchaseDate']));
        }
      }
      setRs("UPDATE {$this->db}.product SET is_batch_updated = 1 WHERE product_id = ?", array($product_id));
      
      setRs("UPDATE {$this->db}.product_batch b INNER JOIN {$this->db}.product_batch_location l ON l.inventoryId = b.inventoryId
      SET b.product_batch_location_id = l.product_batch_location_id WHERE b.product_id = {$product_id}");
    
      setRs("UPDATE {$this->db}.product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM {$this->db}.product_batch b INNER JOIN {$this->db}.product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id WHERE b.product_id = {$product_id} GROUP BY b.product_id) i ON i.product_id = p.product_id
      SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2 WHERE p.product_id = {$product_id}");
  
    }
  
  }

  function UpdateInventory2($product_id, $productId) {

    setRs("DELETE FROM {$this->db}.product_batch WHERE product_id = {$product_id}");
    $update = fetchApi('store/batches/' . $productId . '/batchQuantityInfo', $this->api_url, $this->auth_code, $this->partner_key);
    $a = json_decode($update, true);
    foreach($a as $b) {
      if ($b['quantity'] != 0) {
        $cost = $costPerUnit = null;
        /*
        if (str_len($b['batchId'])) {
          $json_batch = fetchApi('store/batches', $s['api_url'], $s['auth_code'], $s['partner_key'], 'batchId=' . $b['batchId']);
          $batches = json_decode($json_batch, true);
          if (isset($batches['values'])) {
            foreach($batches['values'] as $batch) {
              if (isset($batch['cost']) and isset($batch['costPerUnit'])) {
                $cost = numFormat($batch['cost']);
                $costPerUnit = numFormat($batch['costPerUnit']);
                break;
              }
            }
          }
        }
        */
        dbPut($this->db . '.product_batch', array('product_id' => $product_id, 'batchId' => $b['batchId'], 'inventoryId' => $b['inventoryId'], 'qty' => $b['quantity'], 'created' => $b['created'], 'modified' => $b['modified'], 'batchPurchaseDate' => $b['batchPurchaseDate']));
      }
    }
    setRs("UPDATE {$this->db}.product SET is_batch_updated = 1 WHERE product_id = ?", array($product_id));
    
    setRs("UPDATE {$this->db}.product_batch b INNER JOIN {$this->db}.product_batch_location l ON l.inventoryId = b.inventoryId
    SET b.product_batch_location_id = l.product_batch_location_id WHERE b.product_id = {$product_id}");

    $rs = getRs("SELECT product_sale_id FROM {$this->db}.product_sale WHERE product_id = ? AND " . is_enabled(), array($product_id));
    if (sizeof($rs)) {  
      setRs("UPDATE {$this->db}.product_sale p INNER JOIN (SELECT b.product_id, SUM(CASE WHEN l.product_batch_location_type_id = 1 THEN b.qty ELSE 0 END) AS inv_1, SUM(CASE WHEN l.product_batch_location_type_id = 2 THEN b.qty ELSE 0 END) AS inv_2 FROM {$this->db}.product_batch b INNER JOIN {$this->db}.product_batch_location l ON l.product_batch_location_id = b.product_batch_location_id WHERE b.product_id = {$product_id} GROUP BY b.product_id) i ON i.product_id = p.product_id
      SET p.inv_1 = i.inv_1, p.inv_2 = i.inv_2 WHERE p.product_id = {$product_id}");
    }
    else {

    }
  }

  function UpdateSales($product_id, $sku, $category_id, $brand_id) {
    setRs("DELETE FROM {$this->db}.product_sale WHERE product_id = {$product_id}");
    $rp = getRs("SELECT
    t.productSku, 
    SUM(t.quantity) AS sales, 
    FROM_UNIXTIME(MIN(t.ts_dateCreated)/1000, '%Y-%m-%d %H:%i:%s') AS date_first_sale,
    FROM_UNIXTIME(MAX(t.ts_dateCreated)/1000, '%Y-%m-%d %H:%i:%s') AS date_last_sale,
    CASE WHEN MAX(t.ts_dateCreated)/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 day)) THEN
      SUM(CASE WHEN t.ts_dateCreated/1000 >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN t.quantity ELSE 0 END)
    ELSE 0 END AS valid_sales,
    DATEDIFF(NOW(), (CASE WHEN MIN(t.ts_dateCreated)/1000 < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 28 day)) THEN DATE_SUB(NOW(), INTERVAL 28 day) ELSE FROM_UNIXTIME(MIN(t.ts_dateCreated)/1000, '%Y-%m-%d %H:%i:%s') END)) AS sale_days
  
    FROM {$this->db}._transactions t WHERE t.productSku = ? GROUP BY t.productSku", array($sku));
    
    foreach($rp as $p) {
      dbPut($this->db . '.product_sale', array('sku' => $p['productSku'], 'sales' => $p['sales'], 'valid_sales' => $p['valid_sales'], 'date_first_sale' => $p['date_first_sale'], 'date_last_sale' => $p['date_last_sale'], 'sale_days' => $p['sale_days'], 'daily_sales' => ($p['sale_days'])?ceil($p['valid_sales'] / $p['sale_days']):0));
    }
  
    setRs("UPDATE {$this->db}.product_sale s INNER JOIN {$this->db}.product p ON p.sku = s.sku
    SET s.id = p.id, s.name = TRIM(p.name), s.product_id = p.product_id, s.category_id = p.category_id, s.brand_id = p.brand_id, s.vendor_id = p.vendor_id, s.price = p.unitPrice WHERE p.product_id = ?", array($product_id));
  
    $ra = getRs("SELECT category_id, brand_id, AVG(daily_sales) AS daily_sales FROM {$this->db}.product_sale WHERE daily_sales > 0 AND category_id = ? AND brand_id = ? GROUP BY category_id, brand_id", array($category_id, $brand_id));
    foreach($ra as $a) {
      setRs($this->db . '.product_avg_sale', array('category_id' => $a['category_id'], 'brand_id' => $a['brand_id'], 'daily_sales' => $a['daily_sales']));
    }
  
    setRs("UPDATE {$this->db}.product_sale s INNER JOIN {$this->db}.product_avg_sale a ON s.category_id = a.category_id AND s.brand_id = a.brand_id
    SET s.daily_avg_sales = a.daily_sales WHERE COALESCE(s.daily_sales, 0) = 0 AND s.product_id = ?", array($product_id));
  
    // set nulls to zero for quick report comparisons
    setRs("UPDATE {$this->db}.product_sale SET daily_sales = 0 WHERE daily_sales IS NULL AND product_id = ?", array($product_id));
    setRs("UPDATE {$this->db}.product_sale SET daily_avg_sales = 0 WHERE daily_avg_sales IS NULL AND product_id = ?", array($product_id));
  }

}

?>