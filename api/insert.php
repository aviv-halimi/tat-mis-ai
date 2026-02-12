<?php

require_once('../_config.php');
require_once('../inc/setup.php');
$run_queries = true;
$update_keys = true;
$_url = null;

$ignored_fields = array('totalUsage', 'limitUsage', 'balance', 'paymentReceived', 'paidAmount', 'blazeTip', 'blazeFee', 'blazeTotalCharged', 'blazeCashBack363', 'memo');

$_update = getVarInt('_update');
$_sync = getVarInt('_sync');
$_start = getVarNum('_start');
$_id = getVar('_id');
$table_index = getVarNum('_i', 7);

$tables = array(
  array('name' => 'brand', 'api' => 'store/inventory/brands', 'params' => 'start={start}'),
  array('name' => 'category', 'api' => 'store/inventory/categories', 'params' => 'skip={start}'),
  array('name' => 'vendor', 'api' => 'vendors', 'params' => 'skip={start}'),
  array('name' => 'promotion', 'api' => 'loyalty/promotions', 'params' => 'start={start}'),
  array('name' => 'product', 'api' => 'products', 'params' => 'skip={start}'),
  array('name' => 'terminal', 'api' => 'store/terminals', 'params' => 'skip={start}'),
  array('name' => 'employee', 'api' => 'employees', 'params' => 'skip={start}'),
  array('name' => 'transaction', 'api' => 'transactions', 'params' => 'skip={start}'),
  array('name' => 'member', 'api' => 'members', 'params' => 'skip={start}'),
  array('name' => 'product', 'api' => 'store/inventory/products/dates', 'params' => 'startDate={ts}&start={start}'),
  array('name' => 'transaction', 'api' => 'transactions', 'params' => 'skip={start}'),  
  //array('name' => 'product', 'api' => 'store/inventory/products', 'params' => 'start='),
  //array('name' => 'batch', 'api' => 'store/batches', 'params' => 'start=')
);

$rs = getRs("SELECT * FROM _sys_import_status WHERE _sys_import_status_id = 1 AND is_running = 1");
if ($r = getRow($rs) and !$_sync and !$_update) {
  exit('Already running. ' . $r['import_start']);
}

$table = $tables[$table_index];

if ($table) {
  $fn = $table['name'];
  $url = $_API_ROOT . $table['api'];
  $start = 0;
  $limit = 100;
  $ts = '';
  $update_params = array();
  if ($_update) {
    $_rs = getRs("SELECT params FROM _sys_sync WHERE tbl = ?", array($fn));
    if ($_r = getRow($_rs)) {
      $ts = (strtotime('2020-08-18') * 1000);
      if ($_r['params']) {
        $_params = json_decode($_r['params'], true);
        if (isset($_params['ts'])) $ts = $_params['ts'];
        if (isset($_params['start'])) $start = $_params['start'];
      }
      $update_params = json_encode(array('ts' => $ts, 'start' => $start, 'msg' => 'Started running at ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
      setRs("UPDATE _sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
    }
  }
  else if (!$_sync) {
    if (tableExists($fn)) {
      $rs = getRs("SELECT MAX({$fn}_id) AS num FROM {$fn}");
      if ($r = getRow($rs)) {
        $start = $r['num'];
        //if ($table_index == 7) $start = 56635; //56335 - 56635;
      }
    }
    if ($_start) $start = $_start;
  }
  else {
    if (!$_id) exit('You must provide the Id of item to sync. Close this window and try again.');
    $url .= '/' . $_id;
  }
  echo '<li>Started at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
  if (!$_sync and !$_update) {
    dbUpdate('_sys_import_status', array('is_running' => 1, 'import_start' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn . ' @ ' . $start), 1);
    setRs("UPDATE _sys_import_status SET date_start = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
  }

  $__params = $table['params'];
  $__params = str_replace('{start}', $start, $__params);
  $__params = str_replace('{ts}', $ts, $__params);
  $json = fetchApi($url, $__params);

  $_url = $url; //. '?' . $table['params'] . $start;

  $a = json_decode($json, true);
  if ($_sync) {
    $_a = array();
    $_a[$fn] = array($a);
    $a = $_a;
  }
  
  echo dumpArray($a, $fn);

  if (!$_sync and !$_update) {
    dbUpdate('_sys_import_status', array('is_running' => 0, 'import_end' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn), 1);    
    setRs("UPDATE _sys_import_status SET date_end = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
  }
  else if ($_update) {
    if (true) { // always returns all records !!! isset($a['total']) and is_numeric($a['total']) and (($start + $limit) >= $a['total'])) {
      $ts = time() * 1000;
      $total = (isset($a['total']))?$a['total']:null;
      $update_params = json_encode(array('ts' => $ts, 'start' => 0, 'total' => $total, 'msg' => 'Completed ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
    }
    setRs("UPDATE _sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
  }
  echo '<li>Completed at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
}


function dumpArray($a, $fn, $tbl = null, $parents = array(), $parent_id = null) {
  if ($tbl == 'values') $tbl = $fn;
  global $ignored_fields;
  global $run_queries;
  global $update_keys;
  global $_url;
  $num_add = $num_update = 0;
  $fields = array();
  $params = array();
  $id = null;
  $insert_db = true;
  $parent_name = $parent_tbl = null;
  $tbl_fields = getFields($tbl);
  if (sizeof($parents) > 1) {
    $parent_tbl = $parents[sizeof($parents) - 2];
    $parent_name = $parent_tbl . 'Id';
  }
  else $parent_name = null;
  $ret = '<ul>';
  foreach($a as $k => $v) {
    $_k = strval($k);
    if ($k === 'id') {
      $id = $v;
      if (!strlen($id)) {
        $id = $v = $tbl . '-' . getUniqueCode();
      }
    }
    $ret .= '<li>' . $k . ' => ';

    //////////////////////// member from transaction /////////////////

    if ($tbl == 'transaction' and $_k == 'memberId') {
      array_push($fields, 'memberId');
      $params['`memberId`'] = $v;

      $get_member = (strlen($v))?true:false;
      if (tableExists('member')) {
        $_rm = getRs("SELECT member_id FROM `member` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_member = false;
          if ($update_keys) setRs("UPDATE `transaction` SET member_id = ? WHERE id = ? AND memberId = ?", array($_m['member_id'], $id, $v));
        }
      }
      if ($get_member) {
        $_json = fetchApi($_API_ROOT . 'members/' . $v);

        $_a = json_decode($_json, true);
        $_a = array('member' => array($_a));
        $ret .= dumpArray($_a, 'member');
      }
    }

    //////////////////////// employees from transaction /////////////////

    if ($tbl == 'transaction' and $_k == 'sellerId') {
      array_push($fields, 'sellerId');
      $params['`sellerId`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists('member')) {
        $_rm = getRs("SELECT employee_id FROM `employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE `transaction` SET seller_id = ? WHERE id = ? AND sellerId = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi($_API_ROOT . 'employees/' . $v);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($_a, 'employee');
      }
    }

    if ($tbl == 'transaction' and $_k == 'packedBy') {
      array_push($fields, 'packedBy');
      $params['`packedBy`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists('member')) {
        $_rm = getRs("SELECT employee_id FROM `employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE `transaction` SET packer_id = ? WHERE id = ? AND packedBy = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi($_API_ROOT . 'employees/' . $v);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($_a, 'employee');
      }
    }

    if ($tbl == 'transaction' and $_k == 'preparedBy') {
      array_push($fields, 'preparedBy');
      $params['`preparedBy`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists('member')) {
        $_rm = getRs("SELECT employee_id FROM `employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE `transaction` SET preparer_id = ? WHERE id = ? AND preparedBy = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi($_API_ROOT . 'employees/' . $v);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($_a, 'employee');
      }
    }
    
    //////////////////////// product from item /////////////////

    if ($tbl == 'items' and $_k == 'productId') {
      array_push($fields, 'productId');
      $params['productId'] = $v;

      $get_product = (strlen($v))?true:false;
      if (tableExists('product')) {
        $_rm = getRs("SELECT product_id FROM `product` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_product = false;
          if ($update_keys) setRs("UPDATE `items` SET product_id = ? WHERE id = ? AND productId = ?", array($_m['product_id'], $id, $v));
        }
      }
      if ($get_product) {
        $_json = fetchApi($_API_ROOT . 'products/' . $v);

        $_a = json_decode($_json, true);
        $_a = array('product' => array($_a));
        $ret .= dumpArray($_a, 'product');
      }
    }

    else if (is_array($v)) {
      if (!is_numeric($k)) {
        array_push($fields, $k);
      }
      if (sizeof($v)) {
        $_parents = $parents;
        if (!is_numeric($k)) {
          if ($_k == 'values') $k = $fn;
          array_push($_parents, (!is_numeric($k))?$k:$tbl);
        }
        else {
          $id = $parent_id;
        }


        ///////////////////// general ////////////////


        if ($_k == 'tags') {
          if (is_array($v) and sizeof($v)) {
            $v = json_encode($v);
            array_push($fields, 'tags');
            $params['tags'] = (strlen($v) > 50)?substr($v, 0, 49):$v;
          }
        }

        //////////////////////// member /////////////////

        else if ($tbl == 'member' and $_k == 'memberGroup') {
          $add_member_group = true;
          if (tableExists('memberGroup')) {
            $_rm = getRs("SELECT memberGroup_id FROM `memberGroup` WHERE id = ?", array($v['id']));
            if ($_m = getRow($_rm)) {
              $add_member_group = false;
            }
          }
          if ($add_member_group) {
            $ret .= dumpArray($v, $fn, 'memberGroup');
          }
        }

        //////////////////////// tax /////////////////


        else if ($_k == 'taxTable') { 
          if (isset($v['consumerType'])) {
            array_push($fields, 'taxConsumerType');
            $params["`taxConsumerType`"] = $v['consumerType'];
          }
        }

        else if ($_k == 'taxResult') { 
          if (isset($v['totalPreCalcTax'])) {
            array_push($fields, 'totalPreCalcTax');
            $params["`totalPreCalcTax`"] = $v['totalPreCalcTax'];
          }
          if (isset($v['totalPostCalcTax'])) {
            array_push($fields, 'totalPostCalcTax');
            $params["`totalPostCalcTax`"] = $v['totalPostCalcTax'];
          }
          if (isset($v['totalCityTax'])) {
            array_push($fields, 'totalCityTax');
            $params["`totalCityTax`"] = $v['totalCityTax'];
          }
          if (isset($v['totalCountyTax'])) {
            array_push($fields, 'totalCountyTax');
            $params["`totalCountyTax`"] = $v['totalCountyTax'];
          }
          if (isset($v['totalStateTax'])) {
            array_push($fields, 'totalStateTax');
            $params["`totalStateTax`"] = $v['totalStateTax'];
          }
          if (isset($v['totalFedTax'])) {
            array_push($fields, 'totalFedTax');
            $params["`totalFedTax`"] = $v['totalFedTax'];
          }
          if (isset($v['totalCityPreTax'])) {
            array_push($fields, 'totalCityPreTax');
            $params["`totalCityPreTax`"] = $v['totalCityPreTax'];
          }
          if (isset($v['totalCountyPreTax'])) {
            array_push($fields, 'totalCountyPreTax');
            $params["`totalCountyPreTax`"] = $v['totalCountyPreTax'];
          }
          if (isset($v['totalStatePreTax'])) {
            array_push($fields, 'totalStatePreTax');
            $params["`totalStatePreTax`"] = $v['totalStatePreTax'];
          }
          if (isset($v['totalFedPreTax'])) {
            array_push($fields, 'totalFedPreTax');
            $params["`totalFedPreTax`"] = $v['totalFedPreTax'];
          }
          if (isset($v['totalExciseTax'])) {
            array_push($fields, 'totalExciseTax');
            $params["`totalExciseTax`"] = $v['totalExciseTax'];
          }
          if (isset($v['totalNALPreExciseTax'])) {
            array_push($fields, 'totalNALPreExciseTax');
            $params["`totalNALPreExciseTax`"] = $v['totalNALPreExciseTax'];
          }
          if (isset($v['totalALExciseTax'])) {
            array_push($fields, 'totalALExciseTax');
            $params["`totalALExciseTax`"] = $v['totalALExciseTax'];
          }
          if (isset($v['totalALPostExciseTax'])) {
            array_push($fields, 'totalALPostExciseTax');
            $params["`totalALPostExciseTax`"] = $v['totalALPostExciseTax'];
          }
        }

        //////////////////////// transaction /////////////////


        else if ($tbl == 'transaction' and $_k == 'orderTags') {
          if (is_array($v) and sizeof($v)) {
            $v = json_encode($v);
            array_push($fields, 'orderTags');
            $params['orderTags'] = (strlen($v) > 50)?substr($v, 0, 49):$v;
          }
        }

        //////////////////////// vendor /////////////////


        else if ($tbl == 'vendor' and $_k == 'brands') {
          if (is_array($v) and sizeof($v)) {
            $v = json_encode($v);
            array_push($fields, 'brandIds');
            $params['brandIds'] = $v;
          }
        }
        else if ($_k == 'address') { //$tbl == 'vendor' and || for both member and vendor !
          if (isset($v['address'])) {
            array_push($fields, 'address');
            $params["`address`"] = $v['address'];
          }
          if (isset($v['city'])) {
            array_push($fields, 'city');
            $params["`city`"] = $v['city'];
          }
          if (isset($v['state'])) {
            array_push($fields, 'state');
            $params["`state`"] = $v['state'];
          }
          if (isset($v['zipCode'])) {
            array_push($fields, 'zipCode');
            $params["`zipCode`"] = $v['zipCode'];
          }
          if (isset($v['addressLine2'])) {
            array_push($fields, 'addressLine2');
            $params["`addressLine2`"] = $v['addressLine2'];
          }
          if (isset($v['country'])) {
            array_push($fields, 'country');
            $params["`country`"] = $v['country'];
          }
        }

        //////////////////// product ////////////////////


        elseif ($tbl == 'product' and $_k == 'category') {
          if (isset($v['id'])) {
            array_push($fields, 'categoryId');
            $params["`categoryId`"] = $v['id'];
          }
        }
        elseif ($tbl == 'product' and $_k == 'vendor') {
          if (isset($v['id'])) {
            array_push($fields, 'vendorId');
            $params["`vendorId`"] = $v['id'];
          }
        }
        elseif ($tbl == 'product' and $_k == 'brand') {
          /*
          if (isset($v['id'])) {
            array_push($fields, 'brandId');
            $params["`brandId`"] = $v['id'];
          }
          */
        }
        elseif ($tbl == 'product' and $_k == 'potencyAmount') {
          if (isset($v['thc'])) {
            array_push($fields, 'thc');
            $params["`thc`"] = $v['thc'];
          }
          if (isset($v['cbd'])) {
            array_push($fields, 'cbd');
            $params["`cbd`"] = $v['cbd'];
          }
          if (isset($v['cbn'])) {
            array_push($fields, 'cbn');
            $params["`cbn`"] = $v['cbn'];
          }
          if (isset($v['thca'])) {
            array_push($fields, 'thca');
            $params["`thca`"] = $v['thca'];
          }
          if (isset($v['cbda'])) {
            array_push($fields, 'cbda');
            $params["`cbda`"] = $v['cbda'];
          }
        }

        /////////////////////

        // identifications

        else if (!in_array($_k, array('batchQRAsset', 'deviceDetails', 'recommendations', 'frontPhoto', 'recentProducts', 'splitPayment', 'memberSignature', 'orderTags', 'note', 'federalTax', 'countryTax', 'cityTax', 'stateTax', 'rules', 'promoCodes', 'quantityLogs', 'refundPaymentOptions', 'deliveryAddress', 'loc', 'taxInfo', 'taxResult', 'cultivationTaxResult', 'taxMappingInfo', 'cityTax', 'countyTax', 'stateTax', 'federalTax', 'taxTable', 'notes', 'assets', 'photo', 'taxTables', 'producerAddress', 'brandLogo', 'secondaryVendors', 'bundleItems', 'recentLocation', 'shops', 'timeCard', 'role', 'employeeOnFleetInfoList', 'appAccessList', 'potencyAmount1'))) {
          if ($_k == 'values') $k = $fn;
          $ret .= dumpArray($v, $fn, (!is_numeric($k))?$k:$tbl, $_parents, $id);
        }
        else {
          $ret .= '{IGNORE}';
        }
        if (is_numeric($k)) {
          $insert_db = false;
        }
      }
    }
    else {
      $ret .= $v;
      if (!is_numeric($k) and !in_array($ignored_fields, $k) and (!sizeof($tbl_fields) || in_array($k, $tbl_fields))) {
        array_push($fields, $k);
        $params["`" . $k . "`"] = (strlen($v) > 50)?substr($v, 0, 49):$v;
      }
    }
    $ret .= '</li>';
  }
  $ret .= '</ul>';
  if ($parent_name) {
    $params["`" . $parent_name . "`"] = $parent_id;
  }
  //if ($tbl == $fn) $params["params"] = json_encode($a); !making table too big !!
  if ($run_queries and $tbl and $insert_db) {
    if (!tableExists($tbl)) {
      if ($parent_name) array_unshift($fields, $parent_name);
      if ($tbl == 'vendor') {
        array_push($fields, 'brandIds');
        array_push($fields, 'address');
        array_push($fields, 'city');
        array_push($fields, 'state');
        array_push($fields, 'zipCode');
        array_push($fields, 'country');
      }
      if ($tbl == 'product') {
        array_push($fields, 'wmProductMapping');
        array_push($fields, 'productTagGroups');
        array_push($fields, 'discoveryTagId');
        array_push($fields, 'tagGroupId');
        array_push($fields, 'thirdPartyProductId');
        array_push($fields, 'thirdPartyBrandId');
        array_push($fields, 'discoveryTagName');
        array_push($fields, 'tagGroupName');
        array_push($fields, 'thirdPartyBrandName');
        array_push($fields, 'thirdPartyProductName');
        array_push($fields, 'brandName');
        array_push($fields, 'quantityAvailable');
        array_push($fields, 'brandId');
        array_push($fields, 'vendorId');
        array_push($fields, 'categoryId');
        array_push($fields, 'thc');
        array_push($fields, 'cbd');
        array_push($fields, 'cbn');
        array_push($fields, 'thca');
        array_push($fields, 'cbda');
      }
      if ($tbl == 'transaction') {        
        array_push($fields, 'orderTags');
      }
      if ($tbl == 'cart') { 
        array_push($fields, 'taxConsumerType');
        array_push($fields, 'totalPreCalcTax');
        array_push($fields, 'totalPostCalcTax');
        array_push($fields, 'totalCityTax');
        array_push($fields, 'totalCountyTax');
        array_push($fields, 'totalStateTax');
        array_push($fields, 'totalFedTax');
        array_push($fields, 'totalCityPreTax');
        array_push($fields, 'totalCountyPreTax');
        array_push($fields, 'totalStatePreTax');
        array_push($fields, 'totalFedPreTax');
        array_push($fields, 'totalExciseTax');
        array_push($fields, 'totalNALPreExciseTax');
        array_push($fields, 'totalALExciseTax');
        array_push($fields, 'totalALPostExciseTax');         
      }
      if ($tbl == 'items') {
        array_push($fields, 'taxConsumerType');
        array_push($fields, 'totalPreCalcTax');
        array_push($fields, 'totalPostCalcTax');
        array_push($fields, 'totalCityTax');
        array_push($fields, 'totalCountyTax');
        array_push($fields, 'totalStateTax');
        array_push($fields, 'totalFedTax');
        array_push($fields, 'totalCityPreTax');
        array_push($fields, 'totalCountyPreTax');
        array_push($fields, 'totalStatePreTax');
        array_push($fields, 'totalFedPreTax');
        array_push($fields, 'totalExciseTax');
        array_push($fields, 'totalNALPreExciseTax');
        array_push($fields, 'totalALExciseTax');
        array_push($fields, 'totalALPostExciseTax');        
      }
      if ($tbl == $fn) array_push($fields, 'params,longtext');
      $fields = array_unique($fields);
      createTable($tbl, array(), $fields);
    }
    else {
      if ($parent_name) {
        // make sure the key id field is present
        $_rc = getRs("SHOW COLUMNS FROM `{$tbl}` LIKE '{$parent_name}'");
        if (!sizeof($_rc)) {
          setRs("ALTER TABLE `{$tbl}` ADD `{$parent_name}` varchar(50) AFTER {$tbl}_id");
        }
      }
    }
    //echo '<li>Insert data into ' . $tbl . ' > ' . print_r($params, true) . '</li>';
    /*
    $fp = $tbl . '_id';
    foreach($fields AS $fd) {      
      $_rc = getRs("SHOW COLUMNS FROM `{$tbl}` LIKE '{$fd}'");
      if (!sizeof($_rc)) {
        setRs("ALTER TABLE `{$tbl}` ADD `{$fd}` varchar(50) AFTER {$fp}");
      }
      $fp = $fd;
    }
    */
    if ($tbl) {
      $__id = $___id = null;
      $__new = false;
      if (isset($params['`id`'])) {
        $__id = $params['`id`'];
        $_rs = getRs("SELECT {$tbl}_id FROM `{$tbl}` WHERE id = ?", array($__id));
        if ($_r = getRow($_rs)) {
          $__new = false;
          $___id = $_r[$tbl . '_id'];
        }
        else {
          $__new = true;
        }
      }
      if (!$__new) {
        if ($___id) dbUpdate("`{$tbl}`", $params, $___id);
        $num_update++;
      }
      else {
        $primary_id = dbPut("`{$tbl}`", $params);
        //echo '<li>Add ' . $__id . ' to ' . $tbl . ' > ' . print_r($params) . '</li>';
        $num_add++;
        if ($update_keys) {
          if ($tbl == 'transaction') {
            setRs("UPDATE `transaction` a INNER JOIN `member` b ON b.id = a.memberId SET a.member_id = b.member_id WHERE a.transaction_id = {$primary_id}");
            setRs("UPDATE `transaction` a INNER JOIN `employee` b ON b.id = a.sellerId SET a.seller_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
            setRs("UPDATE `transaction` a INNER JOIN `employee` b ON b.id = a.packedBy SET a.packer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
            setRs("UPDATE `transaction` a INNER JOIN `employee` b ON b.id = a.preparedBy SET a.preparer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
            setRs("UPDATE `transaction` a INNER JOIN `terminal` b ON b.id = a.terminalId SET a.terminal_id = b.terminal_id WHERE a.transaction_id = {$primary_id}");
          }
          if ($tbl == 'product') {
            setRs("UPDATE `product` a INNER JOIN `category` b ON b.id = a.categoryId SET a.category_id = b.category_id WHERE a.product_id = {$primary_id}");
            setRs("UPDATE `product` a INNER JOIN `brand` b ON b.id = a.brandId SET a.brand_id = b.brand_id WHERE a.product_id = {$primary_id}");
            setRs("UPDATE `product` a INNER JOIN `vendor` b ON b.id = a.vendorId SET a.vendor_id = b.vendor_id WHERE a.product_id = {$primary_id}");
          }
          if ($tbl == 'items') {
            setRs("UPDATE `items` a INNER JOIN `product` b ON b.id = a.productId SET a.product_id = b.product_id WHERE a.items_id = {$primary_id}");
          }
          if ($tbl == 'promotionReqLogs') {
            setRs("UPDATE `promotionReqLogs` a INNER JOIN `promotion` b ON b.id = a.promotionId SET a.promotion_id = b.promotion_id WHERE a.promotionReqLogs_id = {$primary_id}");
          }
          if ($tbl == 'member') {
            setRs("UPDATE `member` a INNER JOIN `memberGroup` b ON b.id = a.memberGroupId SET a.memberGroup_id = b.memberGroup_id WHERE a.member_id = {$primary_id}");
          }
        }
      }
    }
  }
  if ($tbl) {
    $notes = iif($num_add, $num_add . ' new. ') . iif($num_update, $num_update . ' updated.');
    $_rs = getRs("SELECT _sys_sync_id FROM _sys_sync WHERE tbl = ?", array($tbl));
    if ($_r = getRow($_rs)) {
      setRs("UPDATE _sys_sync SET date_sync = CURRENT_TIMESTAMP(), url = ?, notes = ? WHERE tbl = ?", array($_url, $notes, $tbl));
    }
    else {
      setRs("INSERT INTO _sys_sync (date_sync, url, notes, tbl) VALUES (CURRENT_TIMESTAMP(), ?, ?, ?)", array($_url, $notes, $tbl));
    }
  }
  //echo $ret;
  return $ret;
}

function getFields($tbl) {
  $f = array();
  if (tableExists($tbl)) {
    $rs = getRs("SHOW COLUMNS FROM `{$tbl}`");
    foreach($rs as $r) {
      array_push($f, $r['Field']);
    }
  }
  return $f;
}
?>