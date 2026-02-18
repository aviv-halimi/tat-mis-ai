<?php

class POManager extends SessionManager {
    function NewPO($_p) {
      $success = false;
      $response = $swal = null;
      $po_name = getVarA('po_name', $_p);
      $vendor_id = getVarANum('vendor_id', $_p);
      $email = getVarA('email', $_p);
      $po_type_id = getVarANum('po_type_id', $_p);
	    $copy_po = getVarA('copy_po', $_p);
      $po_reorder_type_id = getVarANum('po_reorder_type_id', $_p);
      $date_schedule_delivery = getVarA('date_schedule_delivery', $_p);
      $unit_count = 0;
	    $po_id = null;
      if ($vendor_id and $po_reorder_type_id) {
        $rv = getRs("SELECT vendor_id, name, is_suspended FROM {$this->db}.vendor WHERE " . is_enabled() . " AND vendor_id = ?", array($vendor_id));
        if ($v = getRow($rv)) {
          if ($v['is_suspended']) {
            $response = 'This Vendor (' . $v['name'] . ') is currently suspended so you cannot place any new POs or Credit Requests.';
            $swal = 'Vendor Suspended';
            $vendor_id = null;
          }
        }
        if ($vendor_id) {
          
			
          if ($po_reorder_type_id == 4) $rs = array();
          else $rs = $this->GetPOProducts($vendor_id);
		  
			
          $po_id = dbPut('po', array('store_id' => $this->store_id, 'po_event_status_id' => 6, 'po_status_id' => 1, 'admin_id' => $this->admin_id, 'po_name' => $po_name, 'vendor_id' => $vendor_id, 'email' => $email, 'po_type_id' => $po_type_id, 'vendor_name' => dbFieldName('vendor', $vendor_id, 'name', $this->db), 'po_reorder_type_id' => $po_reorder_type_id, 'num_products' => sizeof($rs), 'tax_rate' => $this->GetSetting('tax'), 'date_schedule_delivery' => toMySqlDT($date_schedule_delivery), 'params' => json_encode($_p)));

			//COPY FROM OTHER PO
			if ($copy_po) {
				$rs = array();
				$rPO = getRs("SELECT * FROM po WHERE po.po_number = '{$copy_po}'");
				$this->SavePONote($po_id, 'PO copied from PO  ' . $copy_po . '.');
				if ($PO = getRow($rPO)) { 
					$rPP = getRs("SELECT pp.*, p.*, coalesce (pp.price, pp.cost) as nprice, coalesce(np.product_id,np2.product_id) as n_product_id, b.name as brand, coalesce(c.name, c2.name) as category, coalesce(pp.flower_type,p.flowerType) n_flowertype
							FROM po_product pp 
							LEFT JOIN blaze{$PO['store_id']}.product p ON p.product_id = pp.product_id 
							LEFT JOIN blaze{$PO['store_id']}.category c on c.category_id = p.category_id
							LEFT JOIN blaze{$PO['store_id']}.category c2 on c2.category_id = pp.category_id
							LEFT JOIN blaze{$PO['store_id']}.brand b on b.brand_id = pp.brand_id
							LEFT JOIN {$this->db}.product np on np.name = p.name and np.vendor_id = {$vendor_id} and np.is_enabled = 1 and np.is_active = 1
							LEFT JOIN {$this->db}.product np2 on np2.name = pp.po_product_name and np2.vendor_id = {$vendor_id} and np2.is_enabled = 1 and np2.is_active = 1
							WHERE po_id = {$PO['po_id']} AND order_qty > 0
							ORDER BY COALESCE(p.name, pp.po_product_name)");
					
					foreach($rPP as $pp) {
						if ($pp['n_product_id']) {
							$rp = $this->GetPOProducts($vendor_id, $pp['n_product_id']);
                      		$this->AddPOProducts($po_id, $rp, 1, 1, 1, $pp['nprice']);
						}
						else {
							$tCat = null;
							$tBrand = null;
							$tname = ($pp['product_id'])?'(e) ' . $pp['name']:$pp['po_product_name'];
							
							$rC = getRs("SELECT * FROM {$this->db}.category c WHERE c.name like '{$pp['category']}'");
							if ($c = getRow($rC)) { 
								$tCat = $c['category_id']; 
							}
							$rB = getRs("SELECT * FROM {$this->db}.brand b WHERE b.name like '{$pp['brand']}'");
							if ($b = getRow($rB)) { 
								$tBrand = $b['brand_id']; 
							}
										
							$params = array('po_id' => $po_id, 'po_product_name' => $tname, 'is_editable' => 1, 'is_tax' => 1, 'category_id' => $tCat, 'brand_id' => $tBrand, 'flower_type' => $pp['n_flowertype'], 'is_created' => 0, 'is_transferred' => 0);
                    		$po_product_id = dbPut('po_product', $params);
							dbUpdate('po_product', array('price' => $pp['nprice']), $po_product_id);
						}
					}
				}
			 }		
			
			
			
		  if ($po_reorder_type_id == 4) $rs = array();
          else $rs = $this->GetPOProducts($vendor_id);
		  $num_products = $this->AddPOProducts($po_id, $rs, $po_reorder_type_id);
          dbUpdate('po', array('po_number' => (10000 + $po_id), 'num_products' => $num_products), $po_id);
          $this->SavePONote($po_id, 'PO initialized. ' . $num_products . ' product' . iif($num_products != 1, 's') . ' fetched');

          // check and apply vendor autodiscount
          $rd = getRs("SELECT vendor_discount_id, vendor_discount_name, discount_rate, is_after_tax FROM {$this->db}.vendor_discount WHERE " . is_enabled() . " AND vendor_id = ? AND COALESCE(discount_rate, 0) > 0 ORDER BY vendor_discount_id", array($vendor_id));
          if (sizeof($rd)) {
            $po_code = $this->GetIdCode('po', $po_id);
            foreach($rd as $d) {
              if ($d['is_after_tax']) {
                $this->SavePOATDiscount(array('po_code' => $po_code, 'po_discount_name' => $d['vendor_discount_name'], 'discount_type' => 1, 'discount_rate' => $d['discount_rate']));
              }
              else {
                dbUpdate('po', array('discount_name' => $d['vendor_discount_name'], 'discount_rate' => $d['discount_rate']), $po_id);
              }
            }
          }
          $success = true;
        }
      }
      else {
        $response = 'You must specify vendor and reorder type';
      }
      return array('success' => $success, 'response' => $response, 'po_id' => $po_id, 'swal' => $swal);
    }

    function UpdatePO($_p) {
      $po_id = getVarANum('po_id', $_p);
      $po_name = getVarA('po_name', $_p);
      $date_schedule_delivery = getVarA('date_schedule_delivery', $_p);
      $rs = getRs("SELECT po_name, date_schedule_delivery FROM po WHERE " . is_enabled() . " AND FIND_IN_SET(po_status_id, '1,2,3') AND po_id = ?", array($po_id));
      if ($r = getRow($rs)) {
        $a_description = array();
        if ($po_name != $r['po_name']) {
          array_push($a_description, ($po_name)?'PO name updated: ' . $po_name:'PO name removed');
        }
        if (toMySqlDT($date_schedule_delivery) != $r['date_schedule_delivery']) {
          array_push($a_description, ($date_schedule_delivery)?'Latest schedule delivery date updated: ' . $date_schedule_delivery:'Latest schedule delivery date removed');
        }
        dbUpdate('po', array('po_name' => $po_name, 'date_schedule_delivery' => toMySqlDT($date_schedule_delivery)), $po_id);
        if (sizeof($a_description)) {
          $this->SavePONote($po_id, implode('. ', $a_description));
        }
      }
      return $po_id;
    }

    function RecalculateDiscounts($po_id, $footer = true, $db = null) {
	  $db = $db ?? $this->db;
      $po_code = null;
      $rs = getRs("SELECT po_code, vendor_id, po_status_id FROM po WHERE po_id = ?", array($po_id));
      if ($r = getRow($rs)) {
        $po_code = $r['po_code'];
        // check and apply vendor autodiscount
        $rd = getRs("SELECT vendor_discount_id, vendor_discount_name, discount_rate, is_after_tax FROM {$db}.vendor_discount WHERE " . is_enabled() . " AND vendor_id = ? AND COALESCE(discount_rate, 0) > 0 ORDER BY vendor_discount_id", array($r['vendor_id']));
        if (sizeof($rd)) {
          foreach($rd as $d) {
            $po_discount_code = null;
            $_rd = getRs("SELECT po_discount_code FROM po_discount WHERE vendor_id = ? AND po_id = ? AND is_enabled = 1", array($r['vendor_id'], $po_id));
            if ($_d = getRow($_rd)) {
              $po_discount_code = $_d['po_discount_code'];
            }
            if ($d['is_after_tax']) {
              $this->SavePOATDiscount(array('po_code' => $po_code, 'po_discount_code' => $po_discount_code, 'po_discount_name' => $d['vendor_discount_name'], 'discount_type' => 1, 'discount_rate' => $d['discount_rate'], 'is_enabled' => 1, 'vendor_id' => $r['vendor_id'], 'is_after_tax' => $d['is_after_tax']), $footer);
            }
            else {
              dbUpdate('po', array('discount_name' => $d['vendor_discount_name'], 'discount_rate' => $d['discount_rate']), $po_id);
            }
          }
        }
        
        if ($r['po_status_id'] == 1 || $r['po_status_id'] == 3) {
          // check and apply brand autodiscount
		  $rp = null;
          $is_receiving = ($r['po_status_id'] == 3)?1:0;
          setRs("UPDATE po_discount SET is_enabled = 0 WHERE is_receiving = ? AND po_id = ? AND brand_id IS NOT NULL", array($is_receiving, $po_id));
          $rd = getRs("SELECT brand_discount_id, brand_discount_name, brand_id, category_id, discount_rate, is_after_tax FROM {$db}.brand_discount WHERE " . is_enabled() . " AND brand_id IS NOT NULL AND COALESCE(discount_rate, 0) > 0 AND is_rebate = 0 ORDER BY brand_discount_id");
          foreach($rd as $d) {
            if ($is_receiving) {
			  $eDisc = getRs("SELECT COUNT(*) as count FROM po_discount p WHERE " . is_enabled() . " AND p.po_id = ? AND is_receiving = 0 AND brand_id = ?" . iif(!empty($d['category_id'])," AND category_id = '{$d['category_id']}'"), array($po_id, $d['brand_id']));
              if ($e = getRow($eDisc)) {
				  $rp = getRs("SELECT SUM(po.received_qty * COALESCE(po.paid, po.price, po.cost, 0)) AS subtotal FROM po_product po WHERE " . is_enabled('po') . " AND po.po_id = ? AND po.brand_id = ?" . iif(!empty($d['category_id'])," AND JSON_CONTAINS('{$d['category_id']}', CAST(po.category_id AS CHAR), '$')") . iif($e['count'] == 0," AND 1=2"), array($po_id, $d['brand_id']));
			  }
            }
            else {
              $rp = getRs("SELECT SUM(po.order_qty * COALESCE(po.price, po.cost, 0)) AS subtotal FROM po_product po WHERE " . is_enabled('po') . " AND po.po_id = ? AND po.brand_id = ?" . iif(!empty($d['category_id'])," AND JSON_CONTAINS('{$d['category_id']}', CAST(po.category_id AS CHAR), '$')"), array($po_id, $d['brand_id']));
            }
            $po_discount_code = null;
            if ($p = getRow($rp)) {
              if ($p['subtotal']) {
                $_rd = getRs("SELECT po_discount_code FROM po_discount WHERE is_receiving = ? AND brand_id = ? AND category_id = ? AND po_id = ? AND is_active = 1", array($is_receiving, $d['brand_id'], $d['category_id'], $po_id));
                if ($_d = getRow($_rd)) {
                  $po_discount_code = $_d['po_discount_code'];
                }
                if (true) { //) { //$d['is_after_tax']) {
                  $this->SavePOATDiscount(array('po_code' => $po_code, 'po_discount_code' => $po_discount_code, 'po_discount_name' => $d['brand_discount_name'], 'discount_type' => 2, 'discount_amount' => $p['subtotal'] * $d['discount_rate'] / 100, 'is_enabled' => 1, 'brand_id' => $d['brand_id'],'category_id' => $d['category_id'], 'is_after_tax' => $d['is_after_tax']), $footer);
                }
                else {
                  //dbUpdate('po', array('discount_name' => $d['vendor_discount_name'], 'discount_rate' => $d['discount_rate']), $po_id);
                }
              }
            }
          }
        }
      }
      return array('success' => true, 'response' => 'Done', 'redirect' => '/po/' . $po_code);
    }

    function AddPOProducts($po_id, $rs, $po_reorder_type_id = null, $is_editable = 0, $is_non_conforming = 0, $oPrice = null) {

      $_rs = getRs("SELECT params FROM store WHERE store_id = ?", array($this->store_id));
      $target_days_of_inventory = 15;
      if ($_s = getRow($_rs)) {
        $params = json_decode($_s['params'], true);
        if (is_numeric($params['target_days_of_inventory'])) {
          $target_days_of_inventory = $params['target_days_of_inventory'];
        }
      }

      $product_ids = array();
      foreach($rs as $r) {
        array_push($product_ids, $r['product_id']);
      }

      /*$_ri = getRs("SELECT i.product_id, CASE WHEN COUNT(v.inventory_id) THEN SUM(i.quantity) / COUNT(v.inventory_id) ELSE NULL END AS daily_avg_sales
      FROM inventory v RIGHT JOIN (
      {$this->db}.items i
      INNER JOIN {$this->db}.cart c ON c.cart_id = i.cart_id
      INNER JOIN {$this->db}.transaction t ON t.transaction_id = c.transaction_id
      ) ON v.product_id = i.product_id AND v.qty >= 3 AND v.date_inventory >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND v.store_id = ?
      WHERE FROM_UNIXTIME(t.completedtime/1000) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND FIND_IN_SET(i.product_id, ?)
      GROUP BY i.product_id
      LIMIT 10000", array($this->store_id, implode(',', $product_ids)));
      

		AVIV REMOVED THIS QUERY 2.6.24

      $_ri = getRs("SELECT i.product_id, CASE WHEN v.inv_days THEN SUM(i.quantity) / v.inv_days ELSE NULL END AS daily_avg_sales
        FROM (SELECT product_id, COUNT(inventory_id) AS inv_days FROM inventory WHERE qty >= 3 AND date_inventory >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND store_id = ? GROUP BY product_id) AS v RIGHT JOIN (
        {$this->db}.items i
        INNER JOIN {$this->db}.cart c ON c.cart_id = i.cart_id
        INNER JOIN {$this->db}.transaction t ON t.transaction_id = c.transaction_id
        ) ON v.product_id = i.product_id
        WHERE FROM_UNIXTIME(t.completedtime/1000) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND FIND_IN_SET(i.product_id, ?)
        GROUP BY i.product_id
      LIMIT 10000", array($this->store_id, implode(',', $product_ids)));
		*/
		
      $num_products = 0;
      $re = getRs("SELECT exclude_product_name FROM exclude_product WHERE " . is_enabled());
      foreach($rs as $r) {

        $_rs = getRs("SELECT target_days_on_hand FROM {$this->db}.vendor WHERE vendor_id = ?", array($r['vendor_id']));
        if ($_r = getRow($_rs)) {
          if ($_r['target_days_on_hand']) {          
            $target_days_of_inventory = $_r['target_days_on_hand'];
          }
        }

        $vendor_leadtime = $r['vendor_leadtime'];
        $vendor_target_days_on_hand = ($r['vendor_target_days_on_hand'])?$r['vendor_target_days_on_hand']:$this->GetSetting('target-days-on-hand');
        if (!$vendor_target_days_on_hand) $vendor_target_days_on_hand = 15;
        $suggested_qty = ($r['daily_sales'] * ($vendor_leadtime + $vendor_target_days_on_hand)) - $r['on_order_qty'] - ($r['inv_1'] + $r['inv_2']);

        if ($suggested_qty < 0) $suggested_qty = 0;
        $add = false;
        if ($po_reorder_type_id == 1) {
          $add = true;
        }
        else if ($po_reorder_type_id == 2) {
          if (($r['inv_1'] + $r['inv_2']) < ($r['par_level'] + ($vendor_leadtime * $r['daily_sales']) - $r['on_order_qty'])) $add = true;
        }
        else if ($po_reorder_type_id == 3) {
          if (($r['inv_1'] + $r['inv_2']) < ($r['reorder_level'] + ($vendor_leadtime * $r['daily_sales']) - $r['on_order_qty'])) $add = true;
        }
        if (!$is_editable and $add) {
          foreach($re as $e) {
            if (strpos(strtolower($r['product_name']), strtolower($e['exclude_product_name'])) !== false) {
              $add = false;
              break;
            }
          }
        }
        if ($add) {
          $num_products++;


          $daily_sales = null;
          $suggested_qty = null;
          /*  AVIV REMOVED THIS 2.6.24
		  
		  foreach ($_ri as $_i) {
            if ($_i['product_id'] == $r['product_id']) {
              $daily_sales = $_i['daily_avg_sales'];
              $suggested_qty = ($daily_sales * $target_days_of_inventory) - ($r['inv_1'] +  $r['inv_2']) - $r['on_order_qty'];
            }
          }
		*/
			//AVIV ADDED THIS 2.6.24
			  $daily_sales = $r['daily_sales'];
              $suggested_qty = MAX(($daily_sales * $target_days_of_inventory) - ($r['inv_1'] +  $r['inv_2']) - $r['on_order_qty'],0);
			  $tcost = ($oPrice)?$oPrice:$r['cost'];
          dbPut('po_product', array('po_id' => $po_id, 'product_id' => $r['product_id'], 'blaze_id' => $r['id'], 'product_name' => $r['product_name'], 'category_id' => $r['category_id'], 'brand_id' => $r['brand_id'], 'par_level' => $r['par_level'], 'reorder_level' => $r['reorder_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'on_order_qty' => $r['on_order_qty'], 'daily_sales' => $daily_sales, 'cost' => $tcost, 'weight_per_unit' => nicefy($r['weightPerUnit']), 'cannabis_type' => nicefy($r['cannabisType']), 'flower_type' => nicefy($r['flowerType']), 'suggested_qty' => $suggested_qty, 'is_editable' => $is_editable, 'is_non_conforming' => $is_non_conforming, 'is_tax' => ((strtolower($r['cannabisType']) != 'non_cannabis')?1:0), 'date_last_purchased' => $r['date_last_purchased']));
        }
      }
      return $num_products;
    }

    function GetPOProducts($vendor_id, $product_id = null) {
      $rs = getRs("SELECT params FROM store WHERE store_id = ?", array($this->store_id));
      $target_days_of_inventory = 15;
      if ($s = getRow($rs)) {
        $params = json_decode($s['params'], true);
        if (is_numeric($params['target_days_of_inventory'])) {
          $target_days_of_inventory = $params['target_days_of_inventory'];
        }
      }
	  $vid = 'NA';
      $rs = getRs("SELECT target_days_on_hand, id FROM {$this->db}.vendor WHERE vendor_id = ?", array($vendor_id));
      if ($r = getRow($rs)) {
		$vid = $r['id'];
        if ($r['target_days_on_hand']) {          
          $target_days_of_inventory = $r['target_days_on_hand'];
        }
      }
      $params = array($this->store_id, $vendor_id);
      if ($product_id) array_push($params, $product_id);
      return getRs("SELECT c.category_id, c.name AS category_name, b.brand_id, b.name AS brand_name, b.is_suspended, s.product_id, s.id, s.weightPerUnit, s.cannabisType, s.flowerType, s.name AS product_name, s.sku, s.is_batch_updated, p.inv_1, p.inv_2, case when p.sale_days > 4 THEN ifnull((p.valid_sales / p.sale_days),0) ELSE 0 END AS daily_sales, CASE WHEN p.sale_days > 4 THEN (daily_sales * ifnull(nullif(b.targetIDOH,0),{$target_days_of_inventory})) - p.inv_1 + p.inv_2 ELSE 0 END AS suggested_qty, COALESCE(_o.order_qty, 0) AS on_order_qty, COALESCE(_s.par_level, _b.par_days * (p.valid_sales / p.sale_days), 0) AS par_level, COALESCE(_s.reorder_level, _b.reorder_days * (p.valid_sales / p.sale_days), 0) AS reorder_level, COALESCE(s.po_cogs, s.cogs) AS cost, v.vendor_id, COALESCE(v.leadtime, 0) AS vendor_leadtime, COALESCE(v.target_days_on_hand, 0) AS vendor_target_days_on_hand, _batch.date_last_purchased FROM 
      (SELECT CAST(FROM_UNIXTIME(MAX(batchPurchaseDate)/1000, '%Y-%m-%d') AS DATE) AS date_last_purchased, product_id FROM {$this->db}.product_batch GROUP BY product_id) AS _batch RIGHT JOIN (
        (SELECT pop.product_id, SUM(pop.order_qty) AS order_qty FROM po_product pop INNER JOIN po ON po.po_id = pop.po_id AND po.po_status_id = 3 WHERE po.store_id = ? AND " . is_enabled('po,pop') . " GROUP BY pop.product_id) _o RIGHT JOIN (
          {$this->db}.category c RIGHT JOIN (
            {$this->db}.brand b RIGHT JOIN (
              (SELECT brand_id, category_id, par_days, reorder_days FROM {$this->db}.po_brand_override WHERE " . is_enabled() . ") AS _b RIGHT JOIN (
                (SELECT product_id, par_level, reorder_level FROM {$this->db}.po_sku_override WHERE " . is_enabled() . ") AS _s RIGHT JOIN (
                  ({$this->db}.product_sale p RIGHT JOIN ($this->db.vendor v INNER JOIN {$this->db}.product s ON s.vendor_id = v.vendor_id OR s.secondaryVendors LIKE '%{$vid}%') ON s.product_id = p.product_id AND " . is_enabled('p') . ")
                ) ON _s.product_id = s.product_id
              ) ON ((_b.brand_id = s.brand_id AND _b.category_id = s.category_id) OR (_b.brand_id IS NULL AND _b.category_id = s.category_id) OR (_b.brand_id = s.brand_id AND _b.category_id IS NULL))
            ) ON b.brand_id = s.brand_id
          ) ON c.category_id = s.category_id
        ) ON _o.product_id = s.product_id
      ) ON _batch.product_id = s.product_id
      WHERE " . is_enabled('s') . " AND s.active = '1' AND s.deleted = '' AND v.vendor_id = ?" . iif($product_id, " AND s.product_id = ?
	  GROUP BY product_id"), $params);
    }

    function GetSavedPOProducts($po_id, $_brand_id = null, $_category_id = null, $_date_last_purchased = null, $_disaggregate_ids = array(), $_sort_by = null, $_po_product_id = null) {
      $po_status_id = null;
      $rs = getRs("SELECT po_status_id FROM po WHERE po_id = ?", array($po_id));
      if ($r = getRow($rs)) {
        $po_status_id = $r['po_status_id'];
      }

      $params = array($po_id);
      if ($_brand_id) array_push($params, $_brand_id);
      if ($_category_id) array_push($params, $_category_id);
      if ($_po_product_id) array_push($params, $_po_product_id);
      if ($_date_last_purchased) array_push($params, toMySqlDT($_date_last_purchased));
  
      $a_sql_order = array();
      if (in_array(2, $_disaggregate_ids)) {
        array_push($a_sql_order, 'b.name');
      }
	  if(in_array(1, $_disaggregate_ids)) {
        array_push($a_sql_order, 'category_name');
      }
      if (in_array(3, $_disaggregate_ids)) {
        array_push($a_sql_order, 't.cannabis_type');
      }
      if (in_array(4, $_disaggregate_ids)) {
        array_push($a_sql_order, 't.weight_per_unit');
      }
      if (in_array(5, $_disaggregate_ids)) {
        array_push($a_sql_order, 't.flower_type');
      }
      //array_push($a_sql_order, iif($_sort_by == 2, 's.sku', 's.name'));
      	  
      return getRs("SELECT po.po_code, po.po_status_id, t.po_product_id, t.po_product_code, COALESCE(c.category_id,c2.category_id,'0') as category_id, COALESCE(c.name,c2.name,'(No Category Listed)') AS category_name, ifnull(b.brand_id,0) as brand_id, ifnull(b.name,'(No Brand Listed)') AS brand_name, CASE WHEN po.po_type_id = 1 THEN b.is_suspended else 0 end as is_suspended, s.product_id, CONCAT(case when json_contains(`b`.`suspended_categories`,json_array(`s`.`category_id`)) AND po.po_type_id = 1 then '**Suspended -- ' else '' end, case when s.tags LIKE '%Clearance%' AND po.po_type_id = 1 THEN 'CLEARANCE!! -- ' ELSE '' END,COALESCE(s.name, t.po_product_name)) AS product_name, COALESCE(s.sku, 'NEW*') AS sku, s.id, t.inv_1, t.inv_2, t.par_level, t.reorder_level, ROUND(t.daily_sales,1) as daily_sales, t.on_order_qty, t.suggested_qty, t.order_qty, t.received_qty, t.price, t.paid, t.cost, t.is_editable, t.is_non_conforming, t.weight_per_unit, t.cannabis_type, t.flower_type, t.is_tax, t.is_created, t.is_editable, t.is_transferred, t.is_included, (case when po.po_status_id = 1 then t.is_editable else 0 end) as sort2, case when po.po_status_id = 1 and t.is_editable then t.po_product_id else COALESCE(s.name, t.po_product_name) end as sort3 FROM 
      ({$this->db}.category c RIGHT JOIN (
        {$this->db}.brand b RIGHT JOIN (
          {$this->db}.product_sale p RIGHT JOIN ({$this->db}.product s RIGHT JOIN (po_product t INNER JOIN po ON po.po_id = t.po_id) ON s.product_id = t.product_id) ON t.product_id = p.product_id
        ) ON b.brand_id = s.brand_id OR b.brand_id = t.brand_id
      ) ON c.category_id = s.category_id OR c.category_id = t.category_id)
	  left join {$this->db}.category c2 on t.category_id = c2.category_id
      WHERE t.po_id = ? AND " . is_enabled('t') . iif($_brand_id, " AND s.brand_id = ?") . iif($_category_id, " AND s.category_id = ?") . iif($_po_product_id, " AND t.po_product_id = ?") . iif($po_status_id > 1, " AND (COALESCE(t.order_qty, 0) <> 0 OR COALESCE(t.received_qty, 0) <> 0 OR t.is_non_conforming = 1 OR is_included = 1)") . iif($_date_last_purchased, " AND (t.is_editable = 1 OR t.date_last_purchased >= CAST(? AS DATE))") . "
	  GROUP BY t.po_product_id
	  ORDER BY sort2 , "  . implode(", ", $a_sql_order) . iif($a_sql_order, ", ") . "sort3", $params);
    }

    function SavePOProduct($_p) {
        $success = false;
        $response = $percent = $foot = $subtotal = $subtotal_r = null;
    
        $po_code = getVarA('c' , $_p);
        $po_product_code = getVarA('p' , $_p);
        $order_qty = getVarANum('qty' , $_p);
        $received_qty = getVarANum('r_qty' , $_p);
        $price = getVarANum('price' , $_p);
        $paid = getVarANum('paid' , $_p);
        $cost = getVarANum('cost' , $_p);
    
        if ($price == 0) $price = null;

        $rs = getRs("SELECT po_id, po_type_id, po_status_id FROM po WHERE po_code = ?", array($po_code));
        if ($r = getRow($rs)) {
          $po_id = $r['po_id'];
          $params = array();
          if (str_len($po_product_code)) {
            if ($r['po_status_id'] == 1) {
              $sql = "order_qty = ?, price = ?";
              $params = array($order_qty, $price);
              if ($r['po_type_id'] == 2) {
                if ($order_qty > 0) {
                  $order_qty *= -1;
                }
                $sql = "order_qty = ?, price = ?, received_qty = ?, paid = ?";
                $params = array($order_qty, $price, $order_qty, $price);
              }
              $subtotal_r = $order_qty * ($price ?: $cost);
              $subtotal = currency_format($order_qty * ($price ?: $cost));
            }
            if ($r['po_status_id'] == 3) {
			  //$paid = round($paid,2);
              $sql = "received_qty = ?, paid = ?";
              $params = array($received_qty, $paid);
              $subtotal_r = $received_qty * ($paid ?: $price);
              $subtotal = currency_format($received_qty * ($paid ?: $price));
            }
            array_push($params, $po_id);
            array_push($params, $po_product_code);
            setRs("UPDATE po_product SET {$sql} WHERE po_id = ? AND po_product_code = ?", $params);
            $success = true;
            $response = 'Saved successfully';
            $_pp = $this->POProgress($po_id);
            $progress = $_pp['progress'];
            $percent = $_pp['percent'];
            $foot = $_pp['foot'];

          }
          else {
            $response = 'Product not defined';
          }
        }
        else {
          $response = 'PO not found';
        }
    
        return array('success' => $success, 'response' => $response, 'foot' => $foot, 'progress' => $progress, 'percent' => $percent, 'subtotal' => $subtotal, 'subtotal_r' => $subtotal_r);
      }

      function SavePOATDiscount($_p, $footer = true) {
          $success = false;
          $response = $progress = $percent = $foot = null;
      
          $po_code = getVarA('po_code' , $_p);
          $po_discount_code = getVarA('po_discount_code' , $_p);
          $po_discount_name = getVarA('po_discount_name' , $_p);
          $discount_type = getVarANum('discount_type' , $_p);
          $discount_amount = getVarANum('discount_amount' , $_p);
          $discount_rate = getVarANum('discount_rate' , $_p);
          $vendor_id = getVarANum('vendor_id', $_p);
          $brand_id = getVarANum('brand_id', $_p);
		  $category_id = getVarA('category_id', $_p);
          $is_after_tax = getVarAInt('is_after_tax', $_p, 1);
          $del = getVarAInt('del', $_p);

          if ($discount_type == 1) {
            $discount_amount = null;
          }
          else if ($discount_type == 2) {
            $discount_rate = null;
          }
          else {
            $discount_amount = $discount_rate = null;
          }

          if ($discount_rate > 100) {
            $response = 'Discount rate cannot be greater than 100%';
          }

          if (!str_len($response)) {
  
            $rs = getRs("SELECT po_id, po_status_id FROM po WHERE po_code = ?", array($po_code));
        
            if ($r = getRow($rs)) {
              $po_id = $r['po_id'];              
              $po_discount_id = $this->GetCodeId('po_discount', $po_discount_code);
              $is_receiving = getVarAInt('is_receiving', $_p, null);
              if ($is_receiving === null) {
                $is_receiving = ($r['po_status_id'] == 3) ? 1 : 0;
              }
              $params = array('po_id' => $po_id, 'vendor_id' => $vendor_id, 'brand_id' => $brand_id, 'category_id' => $category_id, 'po_discount_name' => $po_discount_name, 'discount_amount' => $discount_amount, 'discount_rate' => $discount_rate, 'is_after_tax' => $is_after_tax, 'is_enabled' => 1, 'is_receiving' => $is_receiving);
              if ($po_discount_id) {
                $response = 'Updated successfully';
                if ($del) {
                  $params = array('is_active' => 0);
                  $response = 'Deleted successfully';
                }
                dbUpdate('po_discount', $params, $po_discount_id);
                $success = true;
              }
              else {
                dbPut('po_discount', $params);
                $success = true;
                $response = 'Saved successfully';
              }
              if ($footer) {
                $_pp = $this->POProgress($po_id);
                $progress = $_pp['progress'];
                $percent = $_pp['percent'];
                $foot = $_pp['foot'];
              }
            }
            else {
              $response = 'PO not defined';
            }
          }
      
          return array('success' => $success, 'response' => $response, 'foot' => $foot, 'progress' => $progress, 'percent' => $percent);
        }

      function SavePODiscount($_p) {
          $success = false;
          $response = $progress = $percent = $foot = null;
      
          $po_code = getVarA('po_code' , $_p);
          $discount_name = getVarA('discount_name' , $_p);
          $discount_type = getVarANum('discount_type' , $_p);
          $discount_amount = getVarANum('discount_amount' , $_p);
          $discount_rate = getVarANum('discount_rate' , $_p);

          if ($discount_type == 1) {
            $discount_amount = null;
          }
          else if ($discount_type == 2) {
            $discount_rate = null;
          }
          else {
            $discount_amount = $discount_rate = null;
          }

          if ($discount_rate > 100) {
            $response = 'Discount rate cannot be greater than 100%';
          }

          if (!str_len($response)) {
  
            $rs = getRs("SELECT po_id, po_status_id, discount_amount, discount_rate, discount_name FROM po WHERE po_code = ?", array($po_code));
        
            if ($r = getRow($rs)) {
              $po_id = $r['po_id'];
              $r_ = iif($r['po_status_id'] != 1, "r_");
              setRs("UPDATE po SET {$r_}discount_name = ?, {$r_}discount_amount = ?, {$r_}discount_rate = ? WHERE po_id = ?", array($discount_name, $discount_amount, $discount_rate, $po_id));
              $success = true;
              $response = 'Saved successfully';
              $_pp = $this->POProgress($po_id);
              $progress = $_pp['progress'];
              $percent = $_pp['percent'];
              $foot = $_pp['foot'];
            }
            else {
              $response = 'PO not defined';
            }
          }
      
          return array('success' => $success, 'response' => $response, 'foot' => $foot, 'progress' => $progress, 'percent' => $percent);
        }

        function SavePOTax($_p) {
            $success = false;
            $response = $progress = $percent = $foot = null;
        
            $po_code = getVarA('po_code' , $_p);
            $tax_type = getVarANum('tax_type' , $_p);
            $tax_amount = getVarANum('tax_amount' , $_p);
  
            if ($tax_type == 1) {
              $tax_amount = null;
            }
    
            $rs = getRs("SELECT po_id, po_status_id, tax_rate, tax_amount FROM po WHERE po_code = ?", array($po_code));
        
            if ($r = getRow($rs)) {
              $po_id = $r['po_id'];
              setRs("UPDATE po SET " . iif($r['po_status_id'] != 1, "r_") . "tax_amount = ? WHERE po_id = ?", array($tax_amount, $po_id));
              $success = true;
              $response = 'Saved successfully';
              $_pp = $this->POProgress($po_id);
              $progress = $_pp['progress'];
              $percent = $_pp['percent'];
              $foot = $_pp['foot'];
            }
            else {
              $response = 'PO not defined';
            }
        
            return array('success' => $success, 'response' => $response, 'foot' => $foot, 'progress' => $progress, 'percent' => $percent);
          }
    
          function SavePOCustomProduct($_p) {
            $success = false;
            $response = $progress = $percent = $foot = $tr = $tbody = $po_product_id = $include_po_product_id = null;
        
            $po_code = getVarA('po_code' , $_p);
            $po_product_code = getVarA('po_product_code' , $_p);
            $po_product_name = getVarA('po_product_name' , $_p);
            $product_id = getVarANum('product_id' , $_p);
            $category_id = getVarANum('category_id' , $_p);
            $brand_id = getVarANum('brand_id' , $_p);
            $flower_type = getVarA('flower_type' , $_p);
            $is_tax = getVarAInt('is_tax' , $_p);
            $is_existing_product = getVarAInt('is_existing_product' , $_p);
            $del = getVarAInt('del', $_p);
            $qty = getVarANum('qty' , $_p);
            $price = getVarANum('price' , $_p);
            $po_product_name = trim($po_product_name);
            if (!$del) {
              if ($is_existing_product) {
                if (!$product_id) {
                  $response = 'Please select product';
                }
                else {
                  $rs = getRs("SELECT p.po_status_id, t.po_product_id, t.order_qty FROM po p INNER JOIN po_product t ON t.po_id = p.po_id WHERE " . is_enabled('p,t') . " AND p.po_code = ? AND t.product_id = ?", array($po_code, $product_id));
                  if ($r = getRow($rs)) {
                    if ($r['po_status_id'] == 1) {
                      $response = 'This product is already listed on the PO';
                    }
                    else {
                      if (!$r['order_qty']) {
                        $include_po_product_id = $r['po_product_id'];
                      }
                      else {
                        $response = 'This product is already listed on the PO';
                      }
                    }
                  }
                }
              }
              else {
                if (!$po_product_name) {
                  $response = 'Product name is required';
                }
                else {
                  $rs = getRs("SELECT t.po_product_id FROM po p INNER JOIN po_product t ON t.po_id = p.po_id WHERE " . is_enabled('p,t') . " AND p.po_code = ? AND t.po_product_name = ? AND po_product_code <> ?", array($po_code, $po_product_name, $po_product_code));
                  if (sizeof($rs)) {
                    $response = 'This product is already listed on the PO';
                  }
                }
              }
            }
    
            if (!str_len($response)) {
              $rs = getRs("SELECT po_id, po_status_id, vendor_id FROM po WHERE po_code = ?", array($po_code));
              $po_product_id = $this->GetCodeId('po_product', $po_product_code);
          
              if ($r = getRow($rs)) {
                $po_id = $r['po_id'];
                if ($po_product_id) {
                  if ($del) {
                    setRs("UPDATE po_product SET is_active = 0 WHERE po_id = ? AND po_product_id = ?", array($po_id, $po_product_id));
                    $success = true;
                    $response = 'Product deleted succesfully';
                  }
                  else {
                    setRs("UPDATE po_product SET po_product_name = ?, is_tax = ?, category_id = ?, brand_id = ?, flower_type = ? WHERE po_id = ? AND po_product_id = ?", array($po_product_name, $is_tax, $category_id, $brand_id, $flower_type, $po_id, $po_product_id));
                    $success = true;
                    $response = 'Product updated succesfully';
                  }
                }
                else {
                  if (!$product_id) {
                    $params = array('po_id' => $po_id, 'po_product_name' => $po_product_name, 'is_editable' => 1, 'is_tax' => $is_tax, 'category_id' => $category_id, 'brand_id' => $brand_id, 'flower_type' => $flower_type, 'is_created' => 0, 'is_transferred' => 0);
                    if ($r['po_status_id'] > 2) $params['is_non_conforming'] = 1;
                    $po_product_id = dbPut('po_product', $params);
                    if ($qty || $price) {
                      dbUpdate('po_product', array('order_qty' => $qty, 'price' => $price), $po_product_id);
                    }
                    $response = 'Custom Product added succesfully';
                  }
                  else {
                    if ($include_po_product_id) {
                      setRs("UPDATE po_product SET is_included = 1 WHERE po_product_id = ?", array($include_po_product_id));
                      $response = 'Existing Product updated succesfully';
                    }
                    else {
                      $rp = $this->GetPOProducts($r['vendor_id'], $product_id);
                      $this->AddPOProducts($r['po_id'], $rp, 1, 1, ($r['po_status_id'] > 2)?1:0);
                      $response = 'Existing Product added succesfully';
                    }
                  }
                  $success = true;
                }
                $_pp = $this->POProgress($po_id);
                $progress = $_pp['progress'];
                $percent = $_pp['percent'];
                $foot = $_pp['foot'];
                if (false) { //$po_product_id) {
                  $rs = $this->GetSavedPOProducts($po_id, null, null, null, array(), null, $po_product_id);
                  if ($r = getRow($rs)) {
                    $_tr = $this->ProductRow($r);
                    $tr = $_tr[1];
                  }
                }
                else {
                  $_ds = $this->GetTableDisplaySettings('po');
                  $_category_id = (isset($_ds['category_id']))?$_ds['category_id']:null;
                  $_brand_id = (isset($_ds['brand_id']))?$_ds['brand_id']:null;
                  $_disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:array();
                  $_date_last_purchased = (isset($_ds['date_last_purchased']))?$_ds['date_last_purchased']:null;
                  $_sort_by = (isset($_ds['sort_by']))?$_ds['sort_by']:null;
                  $rs = $this->GetSavedPOProducts($po_id, $_brand_id, $_category_id, $_date_last_purchased, $_disaggregate_ids, $_sort_by);
                  $tbody = $this->ProductRows($rs, $_disaggregate_ids);
                }
              }
              else {
                $response = 'PO not defined';
              }
            }
        
            return array('success' => $success, 'response' => $response, 'foot' => $foot, 'progress' => $progress, 'percent' => $percent, 'po_product_name' => $po_product_name, 'po_product_id' => $po_product_id, 'tr' => $tr, 'tbody' => $tbody);
          }

          function ProductRowSubtotal($totals, $category_id = null, $category_name = null, $brand_id = null, $brand_name = null, $cannabis_type = null, $weight_per_unit = null, $flower_type = null, $po_status_id = null) {
            if ($po_status_id > 1) return null;
            $ret = '<tr style="background:#' . iif($category_name, 'ddd', 'e5e5e5') . ';font-style:italic"><th colpsan="2">' . iif($category_name, $category_name, $brand_name) . ' Subtotal</th><th>';
            if ($category_name) {
              if (isset($totals['inv_category_' . $category_id])) {
                $ret .= $totals['inv_category_' . $category_id];
              }
              $ret .= '</th><th>';
              if (isset($totals['order_category_' . $category_id])) {
                $ret .= $totals['order_category_' . $category_id];
              }
              $ret .= '</th><th>';
              if (isset($totals['sales_category_' . $category_id])) {
                $ret .= number_format($totals['sales_category_' . $category_id], 1);
              }
              $ret .= '</th><th>';
              /*
				if (isset($totals['days_category_' . $category_id])) {
				$ret .= number_format($totals['days_category_' . $category_id], 1);
              }
			  */
				if (isset($totals['sales_category_' . $category_id]) & $totals['sales_category_' . $category_id] > 0 & isset($totals['inv_category_' . $category_id])){   
                	$ret .= number_format($totals['inv_category_' . $category_id] / $totals['sales_category_' . $category_id], 1);
				}
              $ret .= '</th>';
              if ($po_status_id > 2) {
                $ret .= '
                <th><span id="suggested_qty_category_' . $category_id . '"></span></th>
                <th class="text-right"><span id="order_qty_category_' . $category_id . '"></span></th>
                <th><span id="order_price_category_' . $category_id . '"></span></th>
                <th class="text-right"><span id="order_subtotal_category_' . $category_id . '"></span></th>';
              }
            }
            else {
              if (isset($totals['inv_brand_' . $brand_id . '_category_' . $category_id])) {
                $ret .= $totals['inv_brand_' . $brand_id . '_category_' . $category_id];
              }
              $ret .= '</th><th>';
              if (isset($totals['order_brand_' . $brand_id . '_category_' . $category_id])) {
                $ret .= $totals['order_brand_' . $brand_id . '_category_' . $category_id];
              }
              $ret .= '</th><th>';
              if (isset($totals['sales_brand_' . $brand_id . '_category_' . $category_id])) {
                $ret .= number_format($totals['sales_brand_' . $brand_id . '_category_' . $category_id], 1);
              }
              $ret .= '</th><th>';
              /*if (isset($totals['days_brand_' . $brand_id . '_category_' . $category_id])) {
				  $ret .= number_format($totals['days_brand_' . $brand_id . '_category_' . $category_id], 1);
			  }*/
                if (isset($totals['sales_brand_' . $brand_id . '_category_' . $category_id]) & isset($totals['inv_brand_' . $brand_id . '_category_' . $category_id]) & $totals['sales_brand_' . $brand_id . '_category_' . $category_id] > 0) {
                	$ret .= number_format($totals['inv_brand_' . $brand_id . '_category_' . $category_id] / $totals['sales_brand_' . $brand_id . '_category_' . $category_id], 1);
              	}
              
              $ret .= '</th>';
              if ($po_status_id > 2) {
                $ret .= '
                <th><span id="suggested_qty_brand_' . $brand_id . '_category_' . $category_id . '"></span></th>
                <th class="text-right"><span class="order-qty-brand-category" data-brand="' . $brand_id . '" data-category="' . $category_id . '"  id="order_qty_brand_' . $brand_id . '_category_' . $category_id . '"></span></th>
                <th><span id="order_price_brand_' . $brand_id . '_category_' . $category_id . '"></span></th>
                <th class="text-right"><span id="order_subtotal_brand_' . $brand_id . '_category_' . $category_id . '"></span></th>';
              }
            }
            $ret .= '</tr>';
            return $ret;
          }

  function ProductRows($rs, $_disaggregate_ids = array()) {
    $first_run = true;
    $brand_id = $category_id = $cannabis_type = $weight_per_unit = $flower_type = $brand_name = $category_name = null;
    $ret = '';
    $totals = array();
    foreach($rs as $r) {
      $show_header = false;
      if ($brand_id and $brand_id != $r['brand_id'] && in_array(2, $_disaggregate_ids)) {
        $ret .= $this->ProductRowSubtotal($totals, $category_id, null, $brand_id, $brand_name, null, null, null, $r['po_status_id']);
      }
      if ($category_id and $category_id != $r['category_id'] && in_array(1, $_disaggregate_ids)) {
        $ret .= $this->ProductRowSubtotal($totals, $category_id, $category_name, $brand_id, null, null, null, null, $r['po_status_id']);
      }
      
      if ($brand_id != $r['brand_id'] && in_array(2, $_disaggregate_ids)) {
        $brand_id = $r['brand_id'];
        $brand_name = $r['brand_name'];
        $ret.= '
        <tr data-id="' . $r['brand_id'] . '" style="background:#fef2e1;" onclick="$(\'.brand_\' + $(this).data(\'id\')).slideToggle();">
          <th' . iif(!$show_header, ' colspan="9"') . '>' . $r['brand_name'] . iif($r['is_suspended'], ' <span class="badge badge-danger">SUSPENDED<span>') . '</th>' . iif($show_header, iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>')) . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
	  if ($category_id != $r['category_id'] && in_array(1, $_disaggregate_ids)) {
        $category_id = $r['category_id'];
        $category_name = $r['category_name'];
        $ret.= '
        <tr style="background:#daf3f1;">
          <th>' . $r['category_name'] . '</th>' . iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>') . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
      
      if ($cannabis_type != $r['cannabis_type'] && in_array(3, $_disaggregate_ids)) {
        if ($cannabis_type) $ret .= $this->ProductRowSubtotal($totals, null, null, null, null, $cannabis_type, null, null, $r['po_status_id']);
        $cannabis_type = $r['cannabis_type'];
        $ret.= '
        <tr style="background:#d7edb2;">
          <th' . iif(!$show_header, ' colspan="9"') . '>' . $r['cannabis_type'] . '</th>' . iif($show_header, iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>')) . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
      if ($weight_per_unit != $r['weight_per_unit'] && in_array(4, $_disaggregate_ids)) {
        if ($weight_per_unit) $ret .= $this->ProductRowSubtotal($totals, null, null, null, null, null, $weight_per_unit, null, $r['po_status_id']);
        $weight_per_unit = $r['weight_per_unit'];
        $ret.= '
        <tr style="background:#e6d2ea;">
          <th' . iif(!$show_header, ' colspan="9"') . '>' . $r['weight_per_unit'] . '</th>' . iif($show_header, iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>')) . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
      if ($flower_type != $r['flower_type'] && in_array(5, $_disaggregate_ids)) {
        if ($flower_type) $ret .= $this->ProductRowSubtotal($totals, null, null, null, null, null, null, $flower_type, $r['po_status_id']);
        $flower_type = $r['flower_type'];
        $ret.= '
        <tr style="background:#e6d2ea;">
          <th' . iif(!$show_header, ' colspan="9"') . '>' . $r['flower_type'] . '</th>' . iif($show_header, iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>')) . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
      if ($first_run) {
        $ret.= '
        <tr class="inverse">
          <th></th>' . iif($r['po_status_id'] == 1, '
          <th class="hidden-sm">Current Inventory</th>
          <th class="hidden-sm">Qty on Order</th>
          <th class="hidden-sm">Daily Average Sales</th>
          <th class="hidden-sm">Days of Inventory</th>
          <th>Purchase Qty</th>') . '
          <th class="text-right">Order Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Subtotal</th>' . iif($r['po_status_id'] > 2, '
          <th><button class="hide btn btn-default btn-sm"><i class="ion-arrow-right-a"></i></button></th>
          <th class="text-right">Qty</th>
          <th class="text-right">Price</th>
          <th class="text-right">Subtotal</th>') . '
        </tr>';
        $first_run = false;
        $show_header = false;
      }
      $_ret = $this->ProductRow($r);
      $totals = $this->MergeTotals($totals, $_ret[0]);
      $ret .= $_ret[1];
    }
    return $ret;
  }
  
  function MergeTotals($totals, $total) {
    foreach($total as $k => $v) {
      if (isset($totals[$k])) {
        if (is_numeric($totals[$k]) and is_numeric($v)) $totals[$k] += $v;
      }
      else {
        $totals[$k] = $v;
      }
    }
    return $totals;
  }

  function ProductRow($r) {
    $css = $icon = $css_paid = $icon_paid = $flat_icon = $flat_icon_paid = null;
    $total = array();
    if ($r['po_status_id'] > 2) {
      if ($r['received_qty']) {
        if ($r['received_qty'] <= $r['order_qty']) {
          $css = 'has-success';
          $icon = '<span class="fa fa-check-circle form-control-feedback"></span>';
          $flat_icon = '<i class="fa fa-check-circle"></i>';
        }
        else {
          $css = 'has-warning';
          $icon = '<span class="fa fa-exclamation-triangle form-control-feedback"></span>';
          $flat_icon = '<i class="fa fa-exclamation-triangle"></i>';
        }
      }
      if ($r['paid']) {
        if ($r['paid'] <= ($r['price'] ?: $r['cost'])) {
          $css_paid = 'has-success';
          $icon_paid = '<span class="fa fa-check-circle form-control-feedback"></span>';
          $flat_icon_paid = '<i class="fa fa-check-circle"></i>';
        }
        else {
          $css_paid = 'has-warning';
          $icon_paid = '<span class="fa fa-exclamation-triangle form-control-feedback"></span>';
          $flat_icon_paid = '<i class="fa fa-exclamation-triangle"></i>';
        }
      }
    }
    else {
      if ($r['order_qty']) {
        $css = 'has-success';
        $icon = '<span class="fa fa-check-circle form-control-feedback"></span>';
      }      
    }
	
    $total['inv_brand_' . $r['brand_id']] = $r['inv_1'] + $r['inv_2'];
    $total['order_brand_' . $r['brand_id']] = $r['on_order_qty'];
    $total['sales_brand_' . $r['brand_id']] = $r['daily_sales'];
    $total['days_brand_' . $r['brand_id']] = (($r['daily_sales'] > 0)?number_format(($r['inv_1'] + $r['inv_2']) / $r['daily_sales'], 1):0);

    $total['inv_category_' . $r['category_id']] = $r['inv_1'] + $r['inv_2'];
    $total['order_category_' . $r['category_id']] = $r['on_order_qty'];
    $total['sales_category_' . $r['category_id']] = $r['daily_sales'];
    $total['days_category_' . $r['category_id']] = (($r['daily_sales'] > 0)?number_format(($r['inv_1'] + $r['inv_2']) / $r['daily_sales'], 1):0);
    //$total['days_category_' . $r['category_id']] = 0;
    
    $total['inv_brand_' . $r['brand_id'] . '_category_' . $r['category_id']] = $r['inv_1'] + $r['inv_2'];
    $total['order_brand_' . $r['brand_id'] . '_category_' . $r['category_id']] = $r['on_order_qty'];
    $total['sales_brand_' . $r['brand_id'] . '_category_' . $r['category_id']] = 0;
    $total['days_brand_' . $r['brand_id'] . '_category_' . $r['category_id']] = (($r['daily_sales'] > 0)?number_format(($r['inv_1'] + $r['inv_2']) / $r['daily_sales'], 1):0);


    if ($r['po_status_id'] == 1) {
    $ret = '
    <tr id="po_product_' . $r['po_product_id'] . '" class="brand_' . $r['brand_id'] . ' category_' . $r['category_id'] . '">
    <td><div><span class="hidden-sm"><span class="product-name">' . $r['product_name'] . '</span> (</span>' . $r['sku'] . '<span class="hidden-sm">)</span></div>' . iif($r['is_editable'], '<button class="btn btn-rounded btn-sm btn-danger px-2 py-0 mr-2 btn-po-custom-product-del" data-c="' . $r['po_code'] . '" data-a="' . $r['po_product_code'] . '" data-title="' . $r['product_name'] . '">Remove</button>' . iif(!$r['product_id'], '<button class="btn btn-rounded btn-sm btn-default px-2 py-0 mr-2 btn-dialog" data-c="' . $r['po_code'] . '" data-a="' . $r['po_product_code'] . '" data-url="po-custom-product" data-title="Edit Product: ' . $r['product_name'] . '">Edit</button>')) . '</td>
    <td class="hidden-sm">' . number_format($r['inv_1'] + $r['inv_2']) . '</td>
    <td class="hidden-sm">' . number_format($r['on_order_qty'] ?? 0) . '</td>
    <td class="hidden-sm">' . number_format($r['daily_sales'] ?? 0, 1) . '</td>
    <td class="hidden-sm">' . (($r['daily_sales'] > 0)?number_format(($r['inv_1'] + $r['inv_2']) / $r['daily_sales'], 1):'0.0') . '</td>
    <td id="suggested_qty_' . $r['po_product_id'] . '" data-sort="' . $r['suggested_qty'] . '"><button type="button" id="suggested_qty_' . $r['po_product_id'] . '" data-ref="order_qty_' . $r['po_product_id'] . '" class="btn btn-default' . iif(!$r['is_suspended'], ' suggested-qty') . ' tc-' . $r['category_id'] . ' tb-' . $r['brand_id'] . '"' . iif($r['is_suspended'], ' disabled="disabled" data-toggle="tooltip" data-title="Suspended"') . '>' . number_format($r['suggested_qty'] ?? 0) . '</button></td>
    <td style="width: 80px;">
      <div class="form-group has-feedback m-0 ' . $css . '">
        <input type="number" id="order_qty_' . $r['po_product_id'] . '" data-brand="' . $r['brand_id'] . '" data-category="' . $r['category_id'] . '" data-id="' . $r['po_product_id'] . '" data-code="' . $r['po_product_code'] . '" class="order-qty form-control" value="' . (($r['order_qty'] <> 0)?number_format($r['order_qty'] ?? 0):'') . '"' . iif($r['is_suspended'], ' disabled="disabled" data-toggle="tooltip" data-title="Suspended"') . ' />' . $icon . '
      </div>
    </td>
    <td><div class="input-group"><div class="input-group-prepend"><div class="input-group-text">$</div></div><input type="number" step=".01" id="order_price_' . $r['po_product_id'] . '" data-id="' . $r['po_product_id'] . '" data-code="' . $r['po_product_code'] . '" data-cost="' . number_format($r['cost'] ?? 0, 2) . '" data-brand="' . $r['brand_id'] . '" data-category="' . $r['category_id'] . '" class="order-price form-control" style="border-left:0;" value="' . iif($r['price'], number_format($r['price'] ?? 0, 2, '.', ''), '') . '" placeholder="' . number_format($r['cost'] ?? 0, 2) . '"' . iif($r['po_status_id'] != 1, ' disabled="disabled"') . ' /></div></td>
    <td id="order_subtotal_' . $r['po_product_id'] . '" data-brand="' . $r['brand_id'] . '" data-category="' . $r['category_id'] . '" class="order-subtotal text-right" data-subtotal="' . ($r['order_qty'] * ($r['price']?:$r['cost'])) . '">' . currency_format($r['order_qty'] * ($r['price']?:$r['cost'])) . '</td>
    </tr>';
    }
    else if ($r['po_status_id'] == 2) {

      $ret = '
      <tr id="po_product_' . $r['po_product_id'] . '">
      <td><div><span class="hidden-sm"><span class="product-name">' . $r['product_name'] . '</span> (</span>' . $r['sku'] . '<span class="hidden-sm">)</span></div></td>
      <td class="text-right">' . number_format($r['order_qty']) . '</td>
      <td class="text-right">' . currency_format($r['price']?:$r['cost']) . '</td>
      <td class="text-right">' . currency_format($r['order_qty'] * ($r['price']?:$r['cost'])) . '</td>
      </tr>';

    }
    else if ($r['po_status_id'] == 3) {

      $ret = '
      <tr id="po_product_' . $r['po_product_id'] . '">
      <td><div><span class="hidden-sm"><span class="product-name">' . $r['product_name'] . '</span> (</span>' . $r['sku'] . '<span class="hidden-sm">)</span></div>' . iif($r['is_non_conforming'], '<button class="btn btn-rounded btn-sm btn-danger px-2 py-0 mr-2 btn-po-custom-product-del" data-c="' . $r['po_code'] . '" data-a="' . $r['po_product_code'] . '" data-title="' . $r['product_name'] . '">Remove</button>' . iif(!$r['product_id'], '<button class="btn btn-rounded btn-sm btn-default px-2 py-0 mr-2 btn-dialog" data-c="' . $r['po_code'] . '" data-a="' . $r['po_product_code'] . '" data-url="po-custom-product" data-title="Edit Product: ' . $r['product_name'] . '">Edit</button>')) . '</td>
      <td class="text-right">' . number_format($r['order_qty'] ?? 0) . '</td>
      <td class="text-right">' . currency_format($r['price']?:$r['cost'] ?? 0) . '</td>
      <td class="text-right">' . currency_format(($r['order_qty'] ?? 0) * ($r['price']?:$r['cost'] ?? 0)) . '</td>
      <td id="r_suggested_qty_' . $r['po_product_id'] . '"><a href="" data-ref="' . $r['po_product_id'] . '" data-qty="'. $r['order_qty'] . '" data-price="' . number_format($r['price']?:$r['cost'] ?? 0, 2, '.', '') . '" class="btn btn-default btn-sm r-suggested-qty tc-' . $r['category_id'] . ' tb-' . $r['brand_id'] . '"><i class="ion-arrow-right-a"></i></a></td>
      <td style="width: 80px;">
        <div class="form-group has-feedback m-0 ' . $css . '">
          <input type="number" id="received_qty_' . $r['po_product_id'] . '" data-id="' . $r['po_product_id'] . '" data-code="' . $r['po_product_code'] . '" class="received-qty form-control" data-order="' . $r['order_qty'] . '" value="' . (($r['received_qty'] <> 0)?number_format($r['received_qty']):'') . '"' . iif($r['po_status_id'] != 3, ' disabled="disabled"') . ' />' . $icon . '
        </div>
      </td>
      <td><div class="input-group"><div class="input-group-prepend hidden-sm"><div class="input-group-text">$</div></div>
      
      <div class="form-group has-feedback m-0 ' . $css_paid . '">
      <input type="number" step=".01" min="0" id="order_paid_' . $r['po_product_id'] . '" data-id="' . $r['po_product_id'] . '" data-code="' . $r['po_product_code'] . '" class="order-paid form-control" style="border-left:0;" value="' . iif($r['paid'], number_format($r['paid'] ?? 0, 2, '.', ''), '') . '" placeholder="' . number_format($r['price']?:$r['cost'] ?? 0, 2) . '" data-price="' . ($r['price']?:$r['cost']) . '"' . iif($r['po_status_id'] != 3, ' disabled="disabled"') . ' />' . $icon_paid . '</div>
      
      </div></td>
      <td id="order_r_subtotal_' . $r['po_product_id'] . '" class="text-right">' . currency_format($r['received_qty'] * ($r['paid']?:$r['price']?:$r['cost'])) . '</td>
      </tr>
      </tr>';

    }
    else {

      $ret = '
      <tr id="po_product_' . $r['po_product_id'] . '">
      <td><div><span class="hidden-sm"><span class="product-name">' . $r['product_name'] . '</span> (</span>' . $r['sku'] . '<span class="hidden-sm">)</span></div></td>
      <td class="text-right">' . number_format($r['order_qty'] ?? 0) . '</td>
      <td class="text-right">' . currency_format($r['price']?:$r['cost'] ?? 0) . '</td>
      <td class="text-right">' . currency_format($r['order_qty'] * ($r['price']?:$r['cost'] ?? 0)) . '</td>
      <td></td>
      <td class="text-right"><span class="py-1 alert alert-' . str_replace('has-', '', $css ?? '') . '">' . number_format($r['received_qty'] ?? 0) . ' ' . $flat_icon . '</span></td>
      <td class="text-right"><span class="py-1 alert alert-' . str_replace('has-', '', $css_paid ?? '') . '">' . currency_format($r['paid']?:$r['price']?:$r['cost']) . ' ' . $flat_icon_paid . '</span></div></td>
      <td class="text-right">' . currency_format($r['received_qty'] * ($r['paid']?:$r['price']?:$r['cost'])) . '</td>
      </tr>
      </tr>';

    }

    return array($total, $ret);
  }

      function POProgress($po_id) {
        $success = false;
        $response = $foot = null;
        $num_products = $num_orders = $percent = 0;

        $_ds = $this->GetTableDisplaySettings('po');
        $_category_id = (isset($_ds['category_id']))?$_ds['category_id']:null;
        $_brand_id = (isset($_ds['brand_id']))?$_ds['brand_id']:null;
        $_disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:array();
        $_date_last_purchased = (isset($_ds['date_last_purchased']))?$_ds['date_last_purchased']:null;
        $_sort_by = (isset($_ds['sort_by']))?$_ds['sort_by']:null;
        $rs = $this->GetSavedPOProducts($po_id, $_brand_id, $_category_id, $_date_last_purchased, $_disaggregate_ids, $_sort_by);
        $num_filtered_products = sizeof($rs);

        $this->RecalculateDiscounts($po_id, false);
        
        $pt_discount = $r_pt_discount = 0;
        
        $rd_pt = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, discount_amount AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));

        $rrd_pt = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, discount_amount AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 1 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));

        foreach($rd_pt AS $d) {
          $pt_discount += $d['discount'];
        }
        $r_pt_discount = $pt_discount;
    
        $rt = getRs("SELECT COUNT(p.po_product_id) AS num_products,
        ifnull(SUM(p.order_qty),0) as tOrderQty,
		ifnull(SUM(p.received_qty),0) as rOrderQty,
        SUM(CASE WHEN (po.po_type_id = 1 AND p.order_qty > 0) THEN 1 WHEN (po.po_type_id = 2 AND p.order_qty < 0) THEN 1 ELSE 0 END) AS num_orders, 
        
        SUM(COALESCE(p.price, p.cost, 0) * p.order_qty) AS subtotal, 
        
        (CASE WHEN COALESCE(po.tax_amount, 0) > 0 THEN po.tax_amount ELSE ((SUM(COALESCE(p.price, p.cost, 0) * p.is_tax * p.order_qty) - (CASE WHEN COALESCE(po.discount_rate, 0) > 0 THEN (po.discount_rate / 100 * SUM((CASE WHEN p.price THEN p.price ELSE p.cost END) * p.is_tax * p.order_qty)) ELSE COALESCE(po.discount_amount, 0) END) - ({$pt_discount})) * po.tax_rate / 100) END) AS tax, 
        
        (CASE WHEN po.discount_rate THEN (po.discount_rate / 100 * SUM(COALESCE(p.price, p.cost, 0) * p.order_qty)) ELSE po.discount_amount END) AS discount, 
        
        po.tax_rate, po.tax_amount, po.discount_amount, po.discount_rate, po.discount_name,
        
        SUM(CASE WHEN p.received_qty > 0 THEN 1 ELSE 0 END) AS num_received, SUM(COALESCE(p.paid, p.price, p.cost, 0) * p.received_qty) AS r_subtotal, 
        
        (CASE WHEN COALESCE(po.r_tax_amount, 0) > 0 THEN po.r_tax_amount ELSE ((SUM(COALESCE(p.paid, p.price, p.cost, 0) * p.is_tax * p.received_qty) - (CASE WHEN COALESCE(po.r_discount_rate, 0) > 0 THEN (po.r_discount_rate / 100 * SUM(COALESCE(p.paid, p.price, p.cost, 0) * p.is_tax * p.received_qty)) ELSE COALESCE(po.r_discount_amount, 0) END) - ({$r_pt_discount})) * po.r_tax_rate / 100) END) AS r_tax, 
        
        (CASE WHEN po.r_discount_rate THEN (po.r_discount_rate / 100 * SUM(COALESCE(p.paid, p.price, p.cost, 0) * p.received_qty)) ELSE po.r_discount_amount END) AS r_discount, po.r_tax_rate, po.r_tax_amount, po.r_discount_amount, po.r_discount_rate, po.r_discount_name,
        
        po.po_type_id, po.po_number, po.po_code, po.po_status_id FROM po INNER JOIN po_product p ON p.po_id = po.po_id WHERE " . is_enabled('po,p') . " AND p.po_id = ? GROUP BY
        po.po_type_id, po.po_number, po.po_code, po.tax_rate, po.tax_amount, po.discount_amount, po.discount_rate, po.discount_name, po.r_tax_rate, po.r_tax_amount, po.r_discount_amount, po.r_discount_rate, po.r_discount_name", array($po_id));


        if ($t = getRow($rt)) {


          $pt_discount = $r_pt_discount = 0;

          $at_discount = $r_at_discount = 0;

          $rd_pt = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));

          $rd = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 1 ORDER BY po_discount_id", array($po_id));

          $rrd_pt = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['r_subtotal'] - $t['r_discount'] + $t['r_tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 1 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));

          $rrd = getRs("SELECT po_discount_id, brand_id, vendor_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['r_subtotal'] - $t['r_discount'] + $t['r_tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 1 AND po_id = ? AND is_after_tax = 1 ORDER BY po_discount_id", array($po_id));

          $success = true;
          $num_products = $t['num_products'];
          $num_orders = $t['num_orders'];
          $response = iif($num_filtered_products != $t['num_products'], '<b>' . $num_filtered_products . '</b> filtered from ') . $t['num_products'] . ' product' . iif($t['num_products'] != 1, 's');
          if ($t['num_orders']) {
            $response .= ' / ' . $t['num_orders'] . ' order'. iif($t['num_orders'] != 1, 's') . ' completed';
            if ($t['num_products']) $percent = $t['num_orders'] / $t['num_products'] * 100;
          }

          ////////////////////////////////

          if ($t['po_status_id'] == 1) {
          $foot = '<tr><th colspan="2">Subtotal</th><th colspan="4" class="hidden-sm"></th><th class="text-right">' . number_format($t['tOrderQty']) . '</th><th></th><th class="text-right">' . currency_format($t['subtotal']) . '</th></tr>
          <tr><th colspan="2">' . iif($t['discount'], iif(str_len($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '<button class="btn btn-sm btn-default ml-2 btn-dialog" data-url="po-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '">Edit</button></th><th colspan="6" class="hidden-sm"></th><th class="text-right">-' . currency_format($t['discount']) . '</th></tr>';

          foreach($rd_pt as $d) {
            $foot .= '
            <tr><th colspan="2"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</div>'. iif(!$d['brand_id'], '<button class="btn btn-rounded btn-sm btn-default px-2 py-0 mr-2 btn-dialog" data-url="po-at-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Edit</button><button class="btn btn-rounded btn-sm btn-danger px-2 py-0 btn-po-discount-del" data-title="Remove this discount: ' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Remove</button>') . '</th><th colspan="6" class="hidden-sm"></th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $pt_discount += $d['discount'];
          }

          $foot .= '<tr><th colspan="2">Subtotal after Discount</td><th colspan="6" class="hidden-sm"></th><th class="text-right">' . currency_format($t['subtotal'] - $t['discount'] - $pt_discount) . '</th></tr>';

          $foot .= '<tr><th colspan="2">Tax' . iif(!$t['tax_amount'], ' (' . (float)$t['tax_rate'] . '%)') . '<button class="btn btn-sm btn-default ml-2 btn-dialog" data-url="po-tax" data-title="Edit Tax for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '">Edit</button></th><th colspan="6" class="hidden-sm"></th><th class="text-right">' . currency_format($t['tax']) . '</th></tr>';
          foreach($rd as $d) {
            $foot .= '
            <tr><th colspan="2"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</div>'. iif(!$d['brand_id'], '<button class="btn btn-rounded btn-sm btn-default px-2 py-0 mr-2 btn-dialog" data-url="po-at-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Edit</button><button class="btn btn-rounded btn-sm btn-danger px-2 py-0 btn-po-discount-del" data-title="Remove this discount: ' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Remove</button>') . '</th><th colspan="6" class="hidden-sm"></th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $at_discount += $d['discount'];
          }
          $foot .= '<tr><th colspan="7"><button class="btn btn-sm btn-default btn-dialog" data-url="po-at-discount" data-title="Add After Tax Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '"><i class="fa fa-plus mr-1"></i> Add After Tax Discount</button></th></tr>
          <tr><th colspan="2">TOTAL</th><th colspan="6" class="hidden-sm"></th><th class="text-right">' . currency_format($t['subtotal'] + $t['tax'] - $t['discount'] - $pt_discount - $at_discount) . '</th></tr>';
          }

          ////////////////////

          else if ($t['po_status_id'] == 2) {
          $foot = '<tr><th>Subtotal</th><th class="text-right">' . number_format($t['tOrderQty']) . '</th><th></th><th class="text-right">' . currency_format($t['subtotal']) . '</th></tr>
          <tr><th colspan="3">' . iif($t['discount'], iif(str_len($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right">-' . currency_format($t['discount']) . ' </th></tr>';

          foreach($rd_pt as $d) {
            $foot .= '
            <tr><th colspan="3"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Pre tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Pre tax Discount') . '</div></th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $pt_discount += $d['discount'];
          }

          $foot .= '
          <tr><th colspan="3">Subtotal after Discount</th><th class="text-right">' . currency_format($t['subtotal'] - $t['discount'] - $pt_discount) . '</th></tr><tr><th colspan="3">Tax' . iif(!$t['tax_amount'], ' (' . (float)$t['tax_rate'] . '%)') . '</th><th class="text-right">' . currency_format($t['tax']) . '</th></tr>';

          foreach($rd as $d) {
            $foot .= '
            <tr><th colspan="3"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'After tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'After tax Discount') . '</div></th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $at_discount += $d['discount'];
          }
          $foot .= '<tr>
          <tr><th colspan="3">TOTAL</th><th class="text-right">' . currency_format($t['subtotal'] + $t['tax'] - $t['discount'] - $pt_discount - $at_discount) . '</th></tr>';
          }

          //////////////////////////////////////

          else {
          /*$foot = '<tr><th colspan="3">Subtotal</th><th class="text-right">' . currency_format($t['subtotal']) . '</th><th colspan="4" class="text-right">' . currency_format($t['r_subtotal']) . '</th></tr>
          <tr><th colspan="3">' . iif($t['discount'], iif(str_len($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right">-' . currency_format($t['discount']) . '</th><th colspan="3">' . iif($t['r_discount'], iif(str_len($t['r_discount_name']), $t['r_discount_name'], 'Discount') . iif($t['r_discount_rate'], ' (-' . (float)$t['r_discount_rate'] . '%)'), 'Discount') . iif($t['po_status_id'] == 3, '<button class="btn btn-sm btn-default ml-2 btn-dialog" data-url="po-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '">Edit</button>') . '</th><th class="text-right">-' . currency_format($t['r_discount']) . '</th></tr>';*/
		  $foot = '<tr><th>Subtotal</th><th class="text-right">' . number_format($t['tOrderQty']) . '</th><th></th><th class="text-right">' . currency_format($t['subtotal']) . '</th><th></th><th class="text-right">' . number_format($t['rOrderQty']) . '</th><th colspan="2" class="text-right">' . currency_format($t['r_subtotal']) . '</th></tr>
          <tr><th colspan="3">' . iif($t['discount'], iif(str_len($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right">-' . currency_format($t['discount']) . '</th><th colspan="3">' . iif($t['r_discount'], iif(str_len($t['r_discount_name']), $t['r_discount_name'], 'Discount') . iif($t['r_discount_rate'], ' (-' . (float)$t['r_discount_rate'] . '%)'), 'Discount') . iif($t['po_status_id'] == 3, '<button class="btn btn-sm btn-default ml-2 btn-dialog" data-url="po-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '">Edit</button>') . '</th><th class="text-right">-' . currency_format($t['r_discount']) . '</th></tr>';
          $foot .= '<tr><td colspan="4"><table class="table m-0">';
          
          foreach($rd_pt as $d) {
            $foot .= '
            <tr><td class="p-0" style="text-indent:30px"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Pre tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Pre tax Discount') . '</div></td><td class="p-0 text-right">-' . currency_format($d['discount']) . '</td></tr>';
            $pt_discount += $d['discount'];
			
          }
          $foot .= '</table></td><td colspan="4"><table class="table m-0">';

          foreach($rrd_pt as $d) {
            $foot .= '
            <tr><td class="p-0" style="text-indent:30px"><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Pre tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Pre tax Discount') . '</div></td><td class="p-0 text-right">-' . currency_format($d['discount']) . '</td></tr>';
           $r_pt_discount += $d['discount'];
		   
          }

          $foot .= '</table></td></tr>';

          $foot .= '<tr><th colspan="3">Discount Total</th><th class="text-right">' . currency_format($pt_discount) . '</th><th colspan="4" class="text-right">' . currency_format($r_pt_discount) . '</th></tr>';
	      $foot .= '<tr><th colspan="3">Subtotal after Discount</th><th class="text-right">' . currency_format($t['subtotal'] - $t['discount'] - $pt_discount) . '</th><th colspan="4" class="text-right">' . currency_format($t['r_subtotal'] - $t['r_discount'] - $r_pt_discount) . '</th></tr>';
          
          $foot .= '
          <tr><th colspan="3">Tax' . iif(!$t['tax_amount'], ' (' . (float)$t['tax_rate'] . '%)') . '</th><th class="text-right">' . currency_format($t['tax']) . '</th><th colspan="3">Tax' . iif(!$t['r_tax_amount'], ' (' . (float)$t['r_tax_rate'] . '%)') . iif($t['po_status_id'] == 3, '<button class="btn btn-sm btn-default ml-2 btn-dialog" data-url="po-tax" data-title="Edit Tax for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '">Edit</button>') . '</th><th class="text-right">' . currency_format($t['r_tax']) . '</th></tr>';
          $foot .= '
          <tr><td colspan="4">

          <table class="table m-0">';
          foreach($rd as $d) {
            $foot .= '
            <tr><th>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'After tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'After tax Discount') . '</th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $at_discount += $d['discount'];
          }
          $foot .='</table></td><td colspan="4"><table class="table m-0">';

          foreach($rrd as $d) {
            $foot .= '
            <tr><th><div>' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'After tax Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'After tax Discount') . '</div>' . iif($t['po_status_id'] == 3, '<button class="btn btn-rounded btn-sm btn-default px-2 py-0 mr-2 btn-dialog" data-url="po-at-discount" data-title="Edit Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Edit</button><button class="btn btn-rounded btn-sm btn-danger px-2 py-0 btn-po-discount-del" data-title="Remove this discount: ' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '" data-c="' . $t['po_code'] . '" data-d="' . $d['po_discount_code'] . '">Remove</button>') . '</th><th class="text-right">-' . currency_format($d['discount']) . '</th></tr>';
            $r_at_discount += $d['discount'];
          }
          $foot .= '</table>' . iif($t['po_status_id'] == 3, '<button class="btn btn-sm btn-default btn-dialog' . iif(sizeof($rrd), ' mt-2') . '" data-url="po-at-discount" data-title="Add After Tax Discount for PO: ' . $t['po_number'] . '" data-c="' . $t['po_code'] . '"><i class="fa fa-plus mr-1"></i> Add After Tax Discount</button>') . '</td></tr>';
          $foot .= '
          <tr><th colspan="3">TOTAL</th><th class="text-right">' . currency_format($t['subtotal'] + $t['tax'] - $t['discount'] - $pt_discount - $at_discount) . '</th><th colspan="4" class="text-right">' . currency_format($t['r_subtotal'] + $t['r_tax'] - $t['r_discount'] - $r_pt_discount - $r_at_discount) . '</th></tr>';
          }
          if ($t['po_status_id'] == 1) {
            dbUpdate('po', array('num_products' => $t['num_products'], 'num_orders' => $t['num_orders'], 'subtotal' => $t['subtotal'], 'tax' => $t['tax'], 'discount' => $t['discount'], 'at_discount' => $at_discount, 'total' => $t['subtotal'] + $t['tax'] - $t['discount'] - $pt_discount - $at_discount, 'order_qty' => $t['tOrderQty']), $po_id);
          }
          else if ($t['po_status_id'] == 3) {
            dbUpdate('po', array('num_received' => $t['num_received'], 'r_subtotal' => $t['r_subtotal'], 'r_tax' => $t['r_tax'], 'r_discount' => $t['r_discount'], 'r_at_discount' => $r_at_discount, 'r_total' => $t['r_subtotal'] + $t['r_tax'] - $t['r_discount'] - $r_at_discount, 'r_qty' => $t['rOrderQty']), $po_id);
          }
        }

        return array('success' => $success, 'response' => $response, 'progress' => $response, 'foot' => $foot, 'percent' => number_format($percent, 1), 'num_products' => $num_products, 'num_orders' => $num_orders);
      }
  
  function SavePOStatus($_p) {
    $success = false;
    $response = $redirect = $po_filename = $invoice_filename = $click = null;
    $is_non_conforming = false;

    $_po_type_id = null;
    $po_code = getVarA('po_code' , $_p);
    $_permission = getVarAInt('_permission' , $_p);
    $_email = getVarA('_email' , $_p);
    $_password = getVarA('_password' , $_p);
    $back = getVarAInt('back' , $_p);

    $_admin_id = $this->admin_id;
    $_first_name = $this->first_name;

    if ($_permission) {
      if (str_len($_email) and str_len($_password)) {
        $rs = getRs("SELECT a.admin_id, a.first_name, a.is_superadmin, a.store_ids, g.module_ids FROM store s RIGHT JOIN (admin a LEFT JOIN admin_group g ON g.admin_group_id = a.admin_group_id AND " . is_enabled('g') . ") ON s.store_id = a.store_id WHERE (a.is_superadmin = 1 OR ((a.date_start IS NULL OR a.date_start <= CURDATE()) AND (a.date_end IS NULL OR a.date_start >= CURDATE()))) AND " . is_enabled('a') . " AND a.email = ? AND a.password = ?", array($_email, formatPassword($_password)));
        if ($r = getRow($rs)) {
          $_admin_id = $r['admin_id'];
          $_first_name = $r['first_name'];
        }
        else {
          $response = 'Authorization failed. Invalid e-mail / password combination';
        }
      }
      else {
        $response = 'Please provide e-mail address and password';
      }
    }

    if (!str_len($response)) {
      $rs = getRs("SELECT po_id, vendor_id, po_type_id, po_status_id, num_products, num_orders, num_received, po_filename, invoice_filename, date_received, invoice_number, COALESCE(discount,0) AS discount, COALESCE(r_discount,0) AS r_discount, COALESCE(at_discount,0) AS at_discount, COALESCE(r_at_discount,0) AS r_at_discount FROM po WHERE " . is_enabled() . " AND po_code = ?", array($po_code));
      if ($r = getRow($rs)) {
        $po_id = $r['po_id'];
        $_po_status_id = $r['po_status_id'];
        $_po_type_id = $r['po_type_id'];
        if ($back) {
          $po_status_id = $r['po_status_id'] - 1;
          if ($r['po_type_id'] == 2 and $po_status_id == 3) {
            $po_status_id = 2;
          }
        }
        else {
          $po_status_id = $r['po_status_id'] + 1;
          if ($r['po_type_id'] == 2 and $po_status_id == 3) {
            setRs("UPDATE po SET r_discount_name = discount_name, r_discount_amount = discount_amount, r_discount_rate = discount_rate, r_discount = discount,r_tax_rate = tax_rate, r_tax_amount = tax_amount, r_tax = tax WHERE po_id = ?", array($po_id));
            $po_status_id = 4;
          }
          $_po_status_id = $po_status_id;
        }
        $rp = getRs("SELECT po_status_id, module_code, po_status_name, admin_field FROM po_status WHERE po_status_id = ?", array($_po_status_id));
        if ($p = getRow($rp)) {
          $permission_module_code = $p['module_code'];
          // error checking
          if ($_po_status_id == 2) {
            if (!($r['num_products'] and $r['num_orders'])) $response = 'You must specify order quantity for at least one product in order to continue. ' . $r['num_products'] . ' and ' . $r['num_orders'];
          }
          if ($_po_status_id == 4) {
			  if ($r['po_type_id'] == 1) {
              if (!($r['num_received'] and $r['num_orders'])) $response = 'You must specify quantity received for at least one product in order to continue. ';
              //if (!$r['invoice_number']) $response .= 'Invoice number is required. ';
              //if (!$r['invoice_filename']) $response .= 'Receiving document is required. ';
            }
            if ($r['r_discount'] < $r['discount']) $is_non_conforming = true;
            if ($r['r_at_discount'] < $r['at_discount']) $is_non_conforming = true;
            $rt = $this->GetSavedPOProducts($po_id);
            foreach($rt as $t) {
              if ($t['received_qty'] > $t['order_qty']) $is_non_conforming = true;
              if ($t['paid'] > ($t['price'] ?: $t['cost'])) $is_non_conforming = true;
            }
            if ($is_non_conforming) $permission_module_code = 'po-receive-non-conforming';
          }

          if ($_po_status_id == 5) {
			if ($r['po_type_id'] == 2 && !$r['date_received']) $response .= 'Date reconciled is required. ';
            if ($r['po_type_id'] == 1) {
              if (!$r['invoice_number']) $response .= 'Invoice number is required. ';
              if (!$r['invoice_filename']) $response .= 'Receiving document is required. ';
            }
          }

          if ($_po_type_id == 2) {
            $permission_module_code = str_replace('po', 'cr', $permission_module_code);
            if ($permission_module_code == 'cr-receive') $permission_module_code = 'cr-send';
          }

          if (!str_len($response)) {
            if ($this->HasModulePermission($permission_module_code, $_admin_id)) {
              if (!$back) {
                if ($po_status_id == 3) {
                  setRs("UPDATE po SET r_discount_name = discount_name, r_discount_amount = discount_amount, r_discount_rate = discount_rate, r_discount = discount,r_tax_rate = tax_rate, r_tax_amount = tax_amount, r_tax = tax WHERE po_id = ?", array($po_id));
                  $rrd = getRs("SELECT po_discount_id FROM po_discount WHERE " . is_enabled() . " AND po_id = ? AND is_receiving = 1 ORDER BY po_id", array($po_id));
                  if (!sizeof($rrd)) {
                    $rd = getRs("SELECT * FROM po_discount WHERE " . is_enabled() . " AND po_id = ? ORDER BY po_id", array($po_id));
                    foreach($rd as $d) {
                      dbPut('po_discount', array('po_id' => $d['po_id'], 'po_discount_name' => $d['po_discount_name'], 'discount_amount' => $d['discount_amount'], 'discount_rate' => $d['discount_rate'], 'discount' => $d['discount'], 'is_after_tax' => $d['is_after_tax'], 'brand_id' => $d['brand_id'], 'vendor_id' => $d['vendor_id'], 'is_receiving' => 1));
                    }
                  }
                }
                if ($po_status_id == 4 AND $_po_type_id == 1) {
                  $rt = $this->GetSavedPOProducts($po_id);
				  $storeList = getRs("SELECT * FROM store WHERE db <> ? AND " . is_active() . " AND " . is_enabled(),array($this->db));	
                  foreach($rt as $t) {
                    if ($t['product_id'] and (($t['paid'] ?: $t['price'])>.01)) {
						setRs("UPDATE {$this->db}.product SET po_cogs = ? WHERE product_id = ?", array(($t['paid'] ?: $t['price']), $t['product_id']));
						//update pricing at all stores
						if ($t['sku']) {
						foreach ($storeList as $s) {
								setRs("UPDATE {$s['db']}.product SET po_cogs = ? WHERE sku = ?", array(($t['paid'] ?: $t['price']), $t['sku']));
							}
						}
					}
                  }
                }
                if (!str_len($response)) {
                  $params = array('po_status_id' => $po_status_id);
                  if ($p['admin_field']) $params[$p['admin_field']] = $_admin_id;
                  dbUpdate('po', $params, $po_id);
                  if ($po_status_id == 2) {
                    setRs("UPDATE po SET date_ordered = CURRENT_TIMESTAMP WHERE po_id = ?", array($po_id));
                    $po_filename = getUniqueID() . '.pdf';
                    generatePO($po_id, MEDIA_PATH . 'po/' . $po_filename);
                    setRs("UPDATE po SET po_filename = ? WHERE po_id = ?", array($po_filename, $po_id));
                    $this->SavePONote($po_id, 'PO document ' . iif($r['po_filename'], 'updated', 'generated') . ': <a href="/po-download/' . $po_code . '" target="_blank"><i class="fa fa-file-pdf mr-1"></i> Download</a>', $_admin_id);
                  }
                  else if ($po_status_id == 3) {
                    
                    $_date_schedule_delivery = date('n/j/Y', strtotime("+ " . $this->GetSetting('scheduling-window'). " days"));
                    $_rv = getRs("SELECT scheduling_window FROM {$this->db}.vendor WHERE vendor_id = ?", array($r['vendor_id']));
                    if ($_v = getRow($_rv)) {
                      if ($_v['scheduling_window']) {
                        $_date_schedule_delivery = date('n/j/Y', strtotime("+ " . $_v['scheduling_window'] . " days"));
                      }
                    }
                    setRs("UPDATE po SET date_schedule_delivery = ? WHERE date_schedule_delivery IS NULL AND po_id = ?", array(toMySqlDT($_date_schedule_delivery), $po_id));
                  }
                  else if ($po_status_id == 4) {
                    setRs("UPDATE po SET po_event_status_id = 3 WHERE po_event_status_id < 3 AND po_id = ?", array($po_id));
                    setRs("UPDATE po_event SET po_event_status_id = 3 WHERE po_event_status_id < 3 AND po_id = ?", array($po_id));
                  }
                  else if ($po_status_id == 5) {
                    require_once dirname(__FILE__) . '/../inc/ai-invoice-gemini.php';
                    $validation = runInvoiceValidationForPO($po_id);
                    if (!empty($validation['debug_log'])) {
                      $note = 'AI Invoice Validation: ' . ($validation['matched'] ? 'Match' : 'No match') . "\n\n" . implode("\n", $validation['debug_log']);
                      $this->SavePONote($po_id, $note, $_admin_id);
                    }
                    if (!empty($validation['add_auto_discount']) && isset($validation['discount_amount']) && $validation['discount_amount'] != 0) {
                      $this->SavePOATDiscount(array('po_code' => $po_code, 'po_discount_name' => 'Auto-Discount to Match Invoice', 'discount_type' => 2, 'discount_amount' => $validation['discount_amount'], 'is_receiving' => 1));
                    }
                  }
                  $this->SavePONote($po_id, 'PO status changed from ' . getDisplayName('po_status', $r['po_status_id']) . ' to ' . getDisplayName('po_status', $po_status_id), $_admin_id);
                  $success = true;
                  $response = 'Status successfully updated to ' . $p['po_status_name'] . '. Just a sec .. redirecting';
                  $redirect = '{refresh}';
                }
              }
              else {
                $params = array('po_status_id' => $po_status_id);
                if ($p['admin_field']) $params[$p['admin_field']] = $_admin_id;
                dbUpdate('po', $params, $po_id);
                $this->SavePONote($po_id, 'PO status changed from ' . getDisplayName('po_status', $r['po_status_id']) . ' to ' . getDisplayName('po_status', $po_status_id), $_admin_id);
                $success = true;
                $response = 'Status successfully updated to ' . $p['po_status_name'] . '. Just a sec .. redirecting';
                $redirect = '{refresh}';
                
              }
            }
            else {
              if (!$_permission) {
                $click = '.po-permission' . iif($back, '-back');
              }
              $response = 'Sorry ' . $_first_name . '. You do not have permission to update the status of this Purchase order. ' . $permission_module_code;
            }
          }
        }
        else {
          $response = 'Status cannot be updated';
        }
      }
      else {
        $response = 'PO not found';
        $response = 'Sorry ' . $_first_name . '. You do not have permission to open POs. Please contact an admin';
      }

    }
    return array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'click' => $click);
  }

  function SavePOData($_p) {
    $success = false;
    $response = $swal = null;

    $po_id = null;
    $po_code = getVarA('c', $_p);
    $f = getVarA('f', $_p);
    $v = getVarA('v', $_p);

    if ($this->HasModulePermission('po')) {
      $rs = getRs("SELECT po_id, invoice_number, invoice_filename, date_received, payment_terms FROM po WHERE " . is_enabled() . " AND FIND_IN_SET(po_status_id, '1,3,4,5') AND po_code = ?", array($po_code));
      if ($r = getRow($rs)) {
        $po_id = $r['po_id'];
        if ($f == 'invoice_number') {
          dbUpdate('po', array('invoice_number' => $v), $po_id);
          $success = true;
          $response = 'Invoice number ' . iif($v, 'updated: ' . $v, 'removed');
        }
        if ($f == 'invoice_filename') {
          dbUpdate('po', array('invoice_filename' => $v), $po_id);
          $success = true;
          $response = 'Receiving document ' . iif($v, 'updated: <a href="/po-download-r/' . $po_code . '" target="_blank">Download</a>', 'removed');
        }
        if ($f == 'coa_filename') {
          dbUpdate('po', array('coa_filename' => $v), $po_id);
          $success = true;
          $response = 'Certificate of Analysis ' . iif($v, 'updated: <a href="/po-download-coa/' . $po_code . '" target="_blank">Download</a>', 'removed');
        }
        if ($f == 'coa_filenames') {
          $coa_filenames = null;
          $__files = array();
          if (isset($_p['coa_filenames_media_item_data'])) {
            foreach($_p['coa_filenames_media_item_data'] as $_f) {
              $__f = json_decode($_f, true);
              $__f['original_name'] = $__f['original_name'];
              array_push($__files, $__f);
            }
          }
          if (sizeof($__files)) $coa_filenames = json_encode($__files);
          dbUpdate('po', array('coa_filenames' => $coa_filenames), $po_id);
          $success = true;
          $response = 'Certificate of Analysis update';
        }
        if ($f == 'date_received') {
          dbUpdate('po', array('date_received' => toMySqlDT($v)), $po_id);
          $success = true;
          $response = 'Date received ' . iif($v, 'updated: ' . $v, 'removed');
        }
        if ($f == 'payment_terms') {
          $val = ($v !== '' && is_numeric($v)) ? (int)$v : null;
          dbUpdate('po', array('payment_terms' => $val), $po_id);
          $success = true;
          $response = 'Payment terms (days) ' . ($val !== null ? 'updated: ' . $val : 'removed');
        }
        if ($f == 'description') {
          dbUpdate('po', array('description' => $v), $po_id);
          $success = true;
          $response = 'Comments / special instructions ' . iif($v, 'updated: ' . $v, 'removed');
        }
      }
      else {
        $swal = 'Error';
        $response = 'Changes cannot be saved for this PO';
      }
    }
    else {
      $response = 'Sorry ' . $this->first_name . '. You do not have permission to update this Purchase order';
      $swal = 'Access denied';
    }
    if ($success) {
      $this->SavePONote($po_id, $response);
    }
    
    return array('success' => $success, 'response' => $response, 'swal' => $swal);
  }

  function SavePONote($po_id, $description, $admin_id = null) {
    dbPut('file', array('re_tbl' => 'po', 're_id' => $po_id, 'admin_id' => $admin_id?:$this->admin_id, 'description' => $description, 'is_auto' => 1));
  }

  function DeletePO($_p) {
    $success = false;
    $response = $swal = $redirect = null;

    $po_id = null;
    $po_code = getVarA('po_code', $_p);
    if ($this->HasModulePermission('po-cancel')) {
      $rs = getRs("SELECT po_id, po_number FROM po WHERE " . is_enabled() . " AND po_code = ?", array($po_code));
      if ($r = getRow($rs)) {
        $success = true;
        $response = 'PO: ' . $r['po_number'] . ' has been cancelled.<div class="mt-2"><a href="/pos" class="btn btn-secondary mr-2">View All POs</a> <a href="/po-new" class="btn btn-default ml-2">Add New PO</a></div>';
        $swal = 'PO Cancelled';
        //$redirect = '/pos';
        setRs("UPDATE po SET is_active = 0 WHERE po_id = ?", array($r['po_id']));
        $this->SavePONote($r['po_id'], 'Cancelled');
      }
      else {
        $response = 'PO not found';
        $swal = 'Not Found';
      }
    }
    else {
      $response = 'Sorry ' . $this->first_name . ', you do not have permission to cancel this PO.';
      $swal = 'Permission denied';
    }
    return array('success' => $success, 'response' => $response, 'swal' => $swal, 'redirect' => $redirect);

  }

  function SavePOEmail($_p) {
    $success = false;
    $response = $swal = $redirect = null;

    $po_id = getVarANum('po_id', $_p);
    $email = getVarA('email', $_p);

    if (!isEmail($email)) {
      $response = 'E-mail address is required.';
      $swal = 'Error';
    }

    if (!$response) {
      dbUpdate('po', array('email' => $email), $po_id);
      $success = true;
      $response = 'Saved successfully';
    }
    return array('success' => $success, 'response' => $response, 'swal' => $swal, 'redirect' => $redirect);
  }

  function GetPO($po_id) {
    return getRs("SELECT t.po_id, t.po_code, t.po_name, t.email, t.po_number, t.po_type_id, t.description, t.vendor_id, t.store_id, t.num_products, t.num_orders, t.date_created, t.admin_id, t.po_status_id, t.discount_name, t.discount_rate, t.discount_amount, t.discount, t.tax_rate, t.tax_amount, t.tax, t.subtotal, t.total, t.date_ordered, t.date_requested_ship, t.date_schedule_delivery, t.po_filename, t.invoice_filename, t.invoice_number, t.payment_terms, t.coa_filename, t.coa_filenames, t.date_received, t.is_confirmed, t.date_confirmed, t.confirmed_by_vendor_id, t.invoice_validated, r.po_reorder_type_id, r.po_reorder_type_name, r.field_level, t.vendor_name, s.po_status_name, s.caption AS status_caption, s.description AS status_description, s.back_caption, s.back_description FROM po_status s INNER JOIN (po_reorder_type r INNER JOIN po t ON t.po_reorder_type_id = r.po_reorder_type_id) ON s.po_status_id = t.po_status_id WHERE " . is_enabled('t,r') . " AND t.po_id = ?", array($po_id));
  }

  function NabisSummary($nabis_id = null) {
    $unlinked = $linked = 0;
    $mapping = $foot = null;
	

	$rdp = getRs("SELECT *, SUM(quantity) as totalQty FROM nabis_product WHERE quantity < 5 AND pricePerUnit = .01 AND nabis_id = {$nabis_id} and is_enabled = 1 and is_active = 1 AND lineItemSkuCode <> 'DisplayUnit-9999'");
	if ($rd = getRow($rdp)) {
		if ($rd['totalQty'] > 0) {
		dbPut('nabis_product', array('nabis_id' => $nabis_id, 'brandDoingBusinessAs' => 'Display Unit', 'orderNumber' => $rd['orderNumber'], 'orderName' => $rd['orderName'], 'daysTillPaymentDue' => $rd['daysTillPaymentDue'], 'orderCreationDate' => $rd['orderCreationDate'], 'deliveryDate' => $rd['deliveryDate'], 'gmv' => $rd['gmv'], 'orderDiscount' => $rd['orderDiscount'], 'creatorEmail' => $rd['creatorEmail'], 'status' => $rd['status'], 'paymentStatus' => $rd['paymentStatus'], 'batchCode' => $rd['batchCode'], 'manufacturerLicenseNumber' => $rd['manufacturerLicenseNumber'], 'manufacturerLegalEntityName' => $rd['manufacturerLegalEntityName'], 'lineItemSkuCode' => 'DisplayUnit-9999', 'lineItemSkuName' => '**Display Units**', 'lineItemDiscount' => $rd['lineItemDiscount'], 'unit' => $rd['unit'], 'quantity' => $rd['totalQty'], 'pricePerUnit' => $rd['pricePerUnit'], 'standardPricePerUnit' => $rd['standardPricePerUnit'], 'sampleType' => $rd['sampleType'], 'skuBatchId' => $rd['skuBatchId'], 'organization' => $rd['organization'], 'overrideQuantityPerUnitOfMeasure' => $rd['overrideQuantityPerUnitOfMeasure'], 'lineItemManifestNotes' => $rd['lineItemManifestNotes'], 'destinationSkuBatchId' => $rd['destinationSkuBatchId'], 'additionalDiscount' => $rd['additionalDiscount'], 'promotionsDiscount' => $rd['promotionsDiscount']));
		$clear = getRs("UPDATE nabis_product SET is_active = 0 WHERE quantity < 5 AND pricePerUnit = .01 AND nabis_id = {$nabis_id} and is_enabled = 1 and is_active = 1 AND lineItemSkuCode <> 'DisplayUnit-9999'");
		}
	}

    $qty = $subtotal = $discount = $total = 0;
    $po_subtotal = $po_discount = $po_total = 0;

    $rs = getRs("SELECT product_id, product_name, price, quantity, pricePerUnit, orderDiscount FROM nabis_product WHERE nabis_id = ? AND " . is_active(), array($nabis_id));
    foreach($rs as $r) {
      $qty += $r['quantity'];
      if ($r['product_id'] || $r['product_name']) $linked++;
      else $unlinked++;
      if ($r['pricePerUnit'] and $r['quantity']) $subtotal += ($r['pricePerUnit'] * $r['quantity']);
      if ($r['price'] and $r['quantity']) $po_subtotal += ($r['price'] * $r['quantity']);
      //if ($r['orderDiscount']) $discount += $r['orderDiscount'];
	  if ($r['orderDiscount']) $discount = $r['orderDiscount'];
    }
    
    $po_discount = $this->NabisDiscount($nabis_id);
	
    $total = $subtotal - $discount;
    $po_total = $po_subtotal - $po_discount;

    $mapping = iif($unlinked, '<i class="fa fa-exclamation-triangle text-danger"></i> ' . $unlinked . ' product' . iif($unlinked != 1, 's') . ' not yet mapped. ') . 
    iif($linked and sizeof($rs) > $linked, '<i class="fa fa-check-circle"></i> ' . $linked . ' product' . iif($linked != 1, 's') . ' already mapped. ') . 
    iif($linked == sizeof($rs), '<i class="fa fa-check-circle text-success"></i> All products mapped. ');

    setRs("UPDATE nabis SET qty = ?, subtotal = ?, discount = ?, total = ?, po_subtotal = ?, po_discount = ?, po_total = ?  WHERE nabis_id = ?", array($qty, $subtotal, $discount, $total, $po_subtotal, $po_discount, $po_total, $nabis_id));
 

    $foot = '
    <tr><th colspan="5">Subtotal</th><th>' . currency_format($subtotal) . '</th><th colspan="3"></th><th>' . currency_format($po_subtotal) . '</th></tr>
    <tr><th colspan="5">Discount</th><th>' . currency_format($discount) . '</th><th colspan="3"></th><th>' . currency_format($po_discount) . '</th></tr>
    <tr><th colspan="5">Total</th><th>' . currency_format($total) . '</th><th colspan="3"></th><th>' . currency_format($po_total) . '</th></tr>';
    if (abs($discount-$po_discount)>1) {
      $foot .= '<tr><th colspan="10"><div class="alert alert-danger"><i class="fa-fa-exclamation-triangle"></i> Discounts do not match !</div></th></tr>';
    }
    return array('mapping' => $mapping, 'foot' => $foot);

  }

  function NabisPO($nabis_id) {
    $vendor_id = $this->StoreSetting('nabis_vendor_id');
    $po_type_id = 1;
    $po_event_status_id = 6;
    $po_status_id = 1;
    $po_reorder_type_id = 1;
    $date_schedule_delivery = null;
	$createDate = null;
    $po_name = null;
    $email = '';
    $po_code = null;
    $rn = getRs("SELECT *, CAST(orderCreationDate AS datetime) AS OrderDate FROM nabis WHERE nabis_id = ?", array($nabis_id));
    if ($n = getRow($rn)) {
      $po_name = $n['brandDoingBusinessAs'] . ' - ' . $n['orderNumber'];
	  $createDate = $n['OrderDate'];
      $rs = getRs("SELECT * FROM nabis_product WHERE nabis_id = ? AND " . is_active() . " ORDER BY sort, nabis_product_id", array($nabis_id));
      $po_id = dbPut('po', array('store_id' => $this->store_id, 'po_event_status_id' => $po_event_status_id, 'po_status_id' => $po_status_id, 'admin_id' => $this->admin_id, 'po_name' => $po_name, 'vendor_id' => $vendor_id, 'email' => $email, 'po_type_id' => $po_type_id, 'vendor_name' => dbFieldName('vendor', $vendor_id, 'name', $this->db), 'po_reorder_type_id' => $po_reorder_type_id, 'num_products' => sizeof($rs),  'at_discount' => 0, 'tax_rate' => $this->GetSetting('tax'), 'date_schedule_delivery' => toMySqlDT($date_schedule_delivery), 'date_created' => $createDate));
      $num_products = 0;
      foreach($rs as $r) {
        $num_products ++;
        $po_product_id = dbPut('po_product', array('po_id' => $po_id, 'product_id' => $r['product_id'], 'po_product_name' => $r['product_name'], 'product_name' => $r['product_name'], 'price' => $r['price'], 'order_qty' => $r['quantity'], 'category_id' => $r['category_id'], 'brand_id' => $r['brand_id'], 'flower_type' => nicefy($r['flower_type'])));
        if (!$r['product_id']) {
          dbUpdate('po_product', array('is_created' => 0, 'is_transferred' => 0), $po_product_id);
        }
        //'par_level' => $r['par_level'], 'reorder_level' => $r['reorder_level'], 'inv_1' => $r['inv_1'], 'inv_2' => $r['inv_2'], 'on_order_qty' => $r['on_order_qty'], 'daily_sales' => $daily_sales, 'cost' => $tcost, 'weight_per_unit' => nicefy($r['weightPerUnit']), 'cannabis_type' => nicefy($r['cannabisType']), 'flower_type' => nicefy($r['flowerType']), 'suggested_qty' => $suggested_qty, 'is_editable' => $is_editable, 'is_non_conforming' => $is_non_conforming, 'is_tax' => ((strtolower($r['cannabisType']) != 'non_cannabis')?1:0), 'date_last_purchased' => $r['date_last_purchased']));
      }

      dbUpdate('nabis', array('po_id' => $po_id), $nabis_id);      
      dbUpdate('po', array('po_number' => (10000 + $po_id), 'num_products' => $num_products, 'num_orders' => $n['qty']), $po_id);
      $this->RecalculateDiscounts($po_id);

      $rp = getRs("SELECT po_code FROM po WHERE po_id = ?", array($po_id));
      if ($p = getRow($rp)) {
        $po_code = $p['po_code'];
      }
    }
    
    return $po_code;
  }

  function NabisDiscount($nabis_id, $footer = true) {
    $discount_amount = 0;
    $rs = getRs("SELECT * FROM nabis WHERE nabis_id = ?", array($nabis_id));
    if ($r = getRow($rs)) {
      $rd = getRs("SELECT brand_discount_id, brand_discount_name, brand_id, category_id, discount_rate, is_after_tax FROM {$this->db}.brand_discount WHERE " . is_enabled() . " AND brand_id IS NOT NULL AND COALESCE(discount_rate, 0) > 0  AND is_rebate = 0 ORDER BY brand_discount_id");
      foreach($rd as $d) {
        $rp = getRs("SELECT SUM(quantity * price) AS subtotal FROM nabis_product WHERE " . is_enabled() . " AND nabis_id = ? AND brand_id = ?" . iif(!empty($d['category_id'])," AND JSON_CONTAINS('{$d['category_id']}', CAST(category_id AS CHAR), '$')"), array($nabis_id, $d['brand_id']));
        $po_discount_code = null;
        if ($p = getRow($rp)) {
          if ($p['subtotal']) {
            $discount_amount += $p['subtotal'] * $d['discount_rate'] / 100;
          }
        }
      }
    }
    return $discount_amount;
  }
}

?>