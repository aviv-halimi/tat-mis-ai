<?php

class FullfillmentManager extends SessionManager {
  
  function NewTransferReport($_p, $store_id, $admin_id) {
    $restock_type_id = getVarANum('restock_type_id', $_p);
    $rs = $this->GetTransfers($restock_type_id);
    $transfer_report_id = dbPut('transfer_report', array('store_id' => $store_id, 'transfer_report_status_id' => 1, 'admin_id' => $admin_id, 'restock_type_id' => $restock_type_id, 'num_products' => sizeof($rs), 'params' => json_encode($_p)));
    foreach($rs as $r) {
      dbPut('transfer_report_product', array('transfer_report_id' => $transfer_report_id, 'product_id' => $r['product_id'], 'fullfillment_level' => $r['fullfillment_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'suggested_qty' => $r['suggested_qty']));
    }
    return $transfer_report_id;
  }

  function UpdateTransferReport($_p) {
    $transfer_report_id = getVarANum('transfer_report_id', $_p);
    $restock_type_id = getVarANum('restock_type_id', $_p);
    dbUpdate('transfer_report', array('restock_type_id' => $restock_type_id, 'params' => json_encode($_p)), $transfer_report_id);
    $progress = $this->TransferReportProgress($transfer_report_id);
    if (!$progress['num_products']) {
      $rs = $this->GetTransfers($restock_type_id);
      foreach($rs as $r) {
        dbPut('transfer_report_product', array('transfer_report_id' => $transfer_report_id, 'product_id' => $r['product_id'], 'fullfillment_level' => $r['fullfillment_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'suggested_qty' => $r['suggested_qty']));
      }
    }
  }

  function GetTransfers($_restock_type_id = null, $_category_id = null, $_brand_id = null, $disaggregate_ids = array(), $_sort_by = null) {
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

      $rs = getRs("SELECT c.category_id, c.name AS category_name, b.brand_id, b.name AS brand_name, p.product_id, p.name AS product_name, p.sku, p.inv_1, p.inv_2, COALESCE(_s.par_level, _b.par_level, _p.par_level, 0) AS fullfillment_level, CASE WHEN COALESCE(_s.suggested_par_level, _b.suggested_par_level, _p.suggested_par_level, 0) - p.inv_2 < p.inv_1 THEN COALESCE(_s.suggested_par_level, _b.suggested_par_level, _p.suggested_par_level, 0) - p.inv_2 ELSE p.inv_1 END AS suggested_qty FROM 
      {$this->db}.category c RIGHT JOIN (
        {$this->db}.brand b RIGHT JOIN (
          (SELECT daily_sales, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.par_lookup) AS _p RIGHT JOIN (
            (SELECT brand_id, category_id, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.brand_override) AS _b RIGHT JOIN (
              (SELECT product_id, par_level AS suggested_par_level, {$field_level} AS par_level FROM {$this->db}.sku_override) AS _s RIGHT JOIN (
                {$this->db}.product_sale p
              ) ON _s.product_id = p.product_id
            ) ON ((_b.brand_id = p.brand_id AND _b.category_id = p.category_id) OR (_b.brand_id IS NULL AND _b.category_id = p.category_id) OR (_b.brand_id = p.brand_id AND _b.category_id IS NULL))
          ) ON ((_p.daily_sales = p.daily_sales AND p.daily_sales > 0) OR (_p.daily_sales = p.daily_avg_sales AND p.daily_avg_sales > 0) OR (_p.daily_sales = p.daily_avg_sales AND _p.daily_sales = p.daily_avg_sales AND p.daily_sales = 0 AND p.daily_avg_sales = 0) OR (_p.daily_sales = {$max_daily_sales} AND p.daily_sales > {$max_daily_sales} AND p.daily_avg_sales = 0) OR (_p.daily_sales = {$max_daily_sales} AND p.daily_avg_sales > {$max_daily_sales} AND p.daily_sales = 0))
        ) ON b.brand_id = p.brand_id
      ) ON c.category_id = p.category_id
      WHERE " . is_enabled('p') . " AND p.inv_1 > 0 AND COALESCE(_s.par_level, _b.par_level, _p.par_level, 0) > p.inv_2" . iif($_category_id, " AND p.category_id = ?") . iif($_brand_id, " AND p.brand_id = ?") . " ORDER BY " . implode(", ", $a_sql_order) . " LIMIT 500", $params);
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

    return getRs("SELECT t.transfer_report_product_id, t.transfer_report_product_code, c.category_id, c.name AS category_name, b.brand_id, b.name AS brand_name, p.product_id, p.name AS product_name, p.sku, t.inv_1, t.inv_2, t.fullfillment_level, t.suggested_qty, t.transfer_qty FROM 
    {$this->db}.category c RIGHT JOIN (
      {$this->db}.brand b RIGHT JOIN (
        {$this->db}.product_sale p INNER JOIN transfer_report_product t ON t.product_id = p.product_id
      ) ON b.brand_id = p.brand_id
    ) ON c.category_id = p.category_id
    WHERE t.transfer_report_id = ? AND " . is_enabled('t') . iif($_category_id, " AND p.category_id = ?") . iif($_brand_id, " AND p.brand_id = ?") . " ORDER BY " . implode(", ", $a_sql_order), $params);
  }

  function SaveTransferReportProduct($_p) {
    $success = false;
    $response = $percent = null;

    $transfer_report_id = getVarANum('id' , $_p);
    $transfer_report_product_code = getVarA('c' , $_p);
    $transfer_qty = getVarANum('qty' , $_p);

    if (strlen($transfer_report_product_code)) {
      setRs("UPDATE transfer_report_product SET transfer_qty = ? WHERE transfer_report_product_code = ?", array($transfer_qty, $transfer_report_product_code));
      $success = true;
      $progress = $this->TransferReportProgress($transfer_report_id);
      $response = $progress['response'];
      $percent = $progress['percent'];
    }
    else {
      $response = 'Product not defined';
    }

    return array('success' => $success, 'response' => $response, 'percent' => $percent);
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
      dbUpdate('transfer_report', array('num_transfers' => $t['num_transfers']), $transfer_report_id);
    }
    return array('success' => $success, 'response' => $response, 'percent' => number_format($percent, 1), 'num_products' => $num_products, 'num_transfers' => $num_transfers);
  }

}

?>