<?php

//exit('Deactivating due to errors');

error_reporting(E_ALL);
error_reporting(-1);
ini_set('display_errors' , 'On');
ini_set('error_reporting', E_ALL);

define('SkipAuth', true);
require_once('../_config.php');
require_once('../inc/setup.php');

$run_start = time();

$run_queries = true;
$update_keys = false;
$_url = null;
$num_add = $num_update = 0;

$ignored_fields = array('totalUsage', 'limitUsage', 'balance', 'paymentReceived', 'paidAmount', 'blazeTip', 'blazeFee', 'blazeTotalCharged', 'blazeCashBack363', 'memo', 'description', 'deviceName', 'city', 'licenseNumber');
//'firstName', 'searchText', 

$_store_id = getVarNum('_store_id');
$_update = getVarInt('_update');
$_sync = getVarInt('_sync');
$_start = getVarNum('_start', -1);
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

if ($table_index == -1) {
  $_ri = getRs("SELECT table_index FROM setting_import WHERE setting_import_id = 1");
  if ($_i = getRow($_ri)) {
    $table_index = $_i['table_index'];
  }
}

$table = $tables[$table_index];
$fn = $table['name'];

$_rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . iif($_store_id, " AND store_id = {$_store_id}") . " ORDER BY store_id");
foreach($_rs as $s) {


  /*
  if ($s['store_id'] == 5) {
    $table_index = 4;
    $table = $tables[$table_index];
    $fn = $table['name'];
  }
  */

  $num_add = $num_update = 0;

  $store_run_start = time();

  $_sys_sync_log_id = setRs("INSERT INTO _sys_sync_log (store_id, table_index, table_name) VALUES (?, ?, ?)", array($s['store_id'], $table_index, $fn));

  $rs = getRs("SELECT * FROM {$s['db']}._sys_import_status WHERE _sys_import_status_id = 1 AND is_running = 1");
  if ($r = getRow($rs) and !$_sync) {
    echo '<li>Already running. ' . $r['import_start'] . '<li>';
    setRs("UPDATE _sys_sync_log SET duration = ?, notes = ? WHERE _sys_sync_log_id = ?", array((time() - $store_run_start), 'Already running: ' . $r['import_start'], $_sys_sync_log_id));
    continue;
  }


  if ($table) {
    $url = $table['api'];
    $start = 0;
    $limit = 100;
    $ts = '';
    $update_params = array();
    if ($_update) {
      $_rs = getRs("SELECT params FROM {$s['db']}._sys_sync WHERE tbl = ?", array($fn));
      if ($_r = getRow($_rs)) {
        $ts = (strtotime('2022-2-5') * 1000);
        echo 'ts: ' . $ts;
        if ($_r['params']) {
          $_params = json_decode($_r['params'], true);
          if (isset($_params['ts'])) {
            if ($_params['ts'] > $ts) $ts = $_params['ts'];
          }
          if (isset($_params['start'])) $start = $_params['start'];
        }
        $update_params = json_encode(array('ts' => $ts, 'start' => $start, 'msg' => 'Started running at ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
        setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
      }
    }
    else if (!$_sync) {
      if (tableExists($s['db'], $fn)) {
        $rs = getRs("SELECT MAX({$fn}_id) AS num FROM {$s['db']}.{$fn}");
        if ($r = getRow($rs)) {
          $start = $r['num'];
          //if ($table_index == 7) $start = 56635; //56335 - 56635;
        }
      }
      if ($_start != -1) $start = $_start;
    }
    else {
      if (!$_id) exit('You must provide the Id of item to sync. Close this window and try again.');
      $url .= '/' . $_id;
    }
    echo '<li>Started at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
    if (!$_sync) {
      dbUpdate($s['db'] . '._sys_import_status', array('is_running' => 1, 'import_start' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn . ' @ ' . $start), 1);
      setRs("UPDATE {$s['db']}._sys_import_status SET date_start = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
    }

    $_sys_log_id = dbPut($s['db'] . '._sys_log', array('tbl' => $fn, 'notes' => '(Manual run) Start: ' . $start . iif($ts, ' | Ts: ' . $ts)));
    

    $__params = $table['params'];
    $__params = str_replace('{start}', $start, $__params);
    $__params = str_replace('{ts}', $ts, $__params);

    setRs("UPDATE _sys_sync_log SET table_index = ?, table_name = ?, url = ?, notes = ? WHERE _sys_sync_log_id = ?", array($table_index, $fn, $url . '?' . $__params,  'Start: ' . $start . iif($ts, ' | Ts: ' . $ts), $_sys_sync_log_id));

    $json = fetchApi($url, $s['api_url'], $s['auth_code'], $s['partner_key'], $__params);

    $_url = $url; //. '?' . $table['params'] . $start;

    $a = json_decode($json, true);
    if ($_sync) {
      $_a = array();
      $_a[$fn] = array($a);
      $a = $_a;
    }
    
    echo dumpArray($s, $a, $fn);
	//echo '<br/> CALL dumpArray Function...';
    if (!$_sync) {
        //echo '<br/> NOT _sync...';
		dbUpdate($s['db'] . '._sys_import_status', array('is_running' => 0, 'import_end' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn), 1);    
      setRs("UPDATE {$s['db']}._sys_import_status SET date_end = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
    }
    if ($_update) {
       //echo '<br/> _Update=1...';
		if (true) { // always returns all records !!! isset($a['total']) and is_numeric($a['total']) and (($start + $limit) >= $a['total'])) {
        $ts = time() * 1000;
        $__rs = getRs("SELECT MAX(modified) AS modified FROM {$s['db']}.product");
        if ($__r = getRow($__rs)) {
          $ts = $__r['modified'] + 1;
        }
        $total = (isset($a['total']))?$a['total']:null;
        $update_params = json_encode(array('ts' => $ts, 'start' => 0, 'total' => $total, 'msg' => 'Completed ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
      }
      setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
    }
    echo '<li>Completed at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
    
    setRs("UPDATE {$s['db']}._sys_log SET duration = ?, notes = CONCAT(notes, ?) WHERE _sys_log_id = ?", array((time() - $run_start), iif($num_add, ' | Add: ' . $num_add) . iif($num_update, ' | Update: ' . $num_update), $_sys_log_id));

    setRs("UPDATE _sys_sync_log SET duration = ?, notes = CONCAT(notes, ?) WHERE _sys_sync_log_id = ?", array((time() - $store_run_start), iif($num_add, ' | Add: ' . $num_add) . iif($num_update, ' | Update: ' . $num_update), $_sys_sync_log_id));
  }
}


function dumpArray($s, $a, $fn, $tbl = null, $parents = array(), $parent_id = null) {
   //echo '<br/> Start dumpArray...';
  if ($tbl == 'values') $tbl = $fn;
  global $_API_ROOT;
  global $ignored_fields;
  global $run_queries;
  global $update_keys;
  global $_url;
  global $num_add;
  global $num_update;
  $fields = array();
  $params = array();
  $id = null;
  $insert_db = true;
  $parent_name = $parent_tbl = null;
  $tbl_fields = getFields($s['db'], $tbl);
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
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'member')) {
        $_rm = getRs("SELECT member_id FROM {$s['db']}.`member` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_member = false;
          if ($update_keys) setRs("UPDATE {$s['db']}.`transaction` SET member_id = ? WHERE id = ? AND memberId = ?", array($_m['member_id'], $id, $v));
        }
      }
      if ($get_member) {
        $_json = fetchApi('members/' . $v, $s['api_url'], $s['auth_code'], $s['partner_key']);

        $_a = json_decode($_json, true);
        $_a = array('member' => array($_a));
        $ret .= dumpArray($s, $_a, 'member');
      }
    }

    //////////////////////// employees from transaction /////////////////

    if ($tbl == 'transaction' and $_k == 'sellerId') {
      array_push($fields, 'sellerId');
      $params['`sellerId`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'member')) {
        $_rm = getRs("SELECT employee_id FROM {$s['db']}.`employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE {$s['db']}.`transaction` SET seller_id = ? WHERE id = ? AND sellerId = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi('employees/' . $v, $s['api_url'], $s['auth_code'], $s['partner_key']);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($s, $_a, 'employee');
      }
    }

    if ($tbl == 'transaction' and $_k == 'packedBy') {
      array_push($fields, 'packedBy');
      $params['`packedBy`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'member')) {
        $_rm = getRs("SELECT employee_id FROM {$s['db']}.`employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE {$s['db']}.`transaction` SET packer_id = ? WHERE id = ? AND packedBy = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi('employees/' . $v, $s['api_url'], $s['auth_code'], $s['partner_key']);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($s, $_a, 'employee');
      }
    }

    if ($tbl == 'transaction' and $_k == 'preparedBy') {
      array_push($fields, 'preparedBy');
      $params['`preparedBy`'] = $v;

      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'member')) {
        $_rm = getRs("SELECT employee_id FROM {$s['db']}.`employee` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_employee = false;
          if ($update_keys) setRs("UPDATE {$s['db']}.`transaction` SET preparer_id = ? WHERE id = ? AND preparedBy = ?", array($_m['employee_id'], $id, $v));
        }
      }
      if ($get_employee) {
        $_json = fetchApi('employees/' . $v, $s['api_url'], $s['auth_code'], $s['partner_key']);

        $_a = json_decode($_json, true);
        $_a = array('employee' => array($_a));
        $ret .= dumpArray($s, $_a, 'employee');
      }
    }
    
    //////////////////////// product from item /////////////////

    if ($tbl == 'items' and $_k == 'productId') {
      array_push($fields, 'productId');
      $params['productId'] = $v;

      $get_product = (strlen($v))?true:false;
      if (tableExists($s['db'], 'items') and tableExists($s['db'], 'product')) {
        $_rm = getRs("SELECT product_id FROM {$s['db']}.`product` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_product = false;
          if ($update_keys) setRs("UPDATE {$s['db']}.`items` SET product_id = ? WHERE id = ? AND productId = ?", array($_m['product_id'], $id, $v));
        }
      }
      if ($get_product) {
        $_json = fetchApi('products/' . $v, $s['api_url'], $s['auth_code'], $s['partner_key']);

        $_a = json_decode($_json, true);
        $_a = array('product' => array($_a));
        $ret .= dumpArray($s, $_a, 'product');
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
          if (tableExists($s['db'], 'memberGroup')) {
            $_rm = getRs("SELECT memberGroup_id FROM {$s['db']}.`memberGroup` WHERE id = ?", array($v['id']));
            if ($_m = getRow($_rm)) {
              $add_member_group = false;
            }
          }
          if ($add_member_group) {
            $ret .= dumpArray($s, $v, $fn, 'memberGroup');
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
        else if ($tbl == 'member' and $_k == 'firstName') {
          array_push($fields, 'firstName');
          $params["`firstName`"] = null;
        }
        
        else if (($tbl == 'vendor' or $tbl == 'member') and $_k == 'address') { //$tbl == 'vendor' and || for both member and vendor !
          if (isset($v['address'])) {
            array_push($fields, 'address');
            $params["`address`"] = (strlen($v['address']) > 255)?substr($v['address'], 0, 254):$v;
          }
          if (isset($v['city']) and !is_array($v['city'])) {
            array_push($fields, 'city');
            $params["`city`"] = $v['city'];
          }
          if (isset($v['state']) and !is_array($v['state'])) {
            array_push($fields, 'state');
            $params["`state`"] = $v['state'];
          }
          if (isset($v['zipCode']) and !is_array($v['zipCode'])) {
            array_push($fields, 'zipCode');
            $params["`zipCode`"] = $v['zipCode'];
          }
          if (isset($v['addressLine2']) and !is_array($v['addressLine2'])) {
            array_push($fields, 'addressLine2');
            $params["`addressLine2`"] = $v['addressLine2'];
          }
          if (isset($v['country']) and !is_array($v['country'])) {
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

        else if (!in_array($_k, array('batchQRAsset', 'deviceDetails', 'recommendations', 'frontPhoto', 'recentProducts', 'splitPayment', 'memberSignature', 'orderTags', 'note', 'federalTax', 'countryTax', 'cityTax', 'stateTax', 'rules', 'promoCodes', 'refundPaymentOptions', 'deliveryAddress', 'loc', 'taxInfo', 'taxResult', 'cultivationTaxResult', 'taxMappingInfo', 'cityTax', 'countyTax', 'stateTax', 'federalTax', 'taxTable', 'notes', 'assets', 'photo', 'taxTables', 'producerAddress', 'brandLogo', 'secondaryVendors', 'bundleItems', 'recentLocation', 'shops', 'timeCard', 'role', 'employeeOnFleetInfoList', 'appAccessList', 'potencyAmount1'))) {
          if ($_k == 'values') $k = $fn;
          $ret .= dumpArray($s, $v, $fn, (!is_numeric($k))?$k:$tbl, $_parents, $id);
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
      if (!is_numeric($k) and !in_array($k, $ignored_fields) and (!sizeof($tbl_fields) || in_array($k, $tbl_fields)) and !is_array($v)) {
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
    if (!tableExists($s['db'], $tbl)) {
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
      createTable($tbl, array(), $fields, $s['db'] . '.');
    }
    else {
      if ($parent_name) {
        // make sure the key id field is present
        $_rc = getRs("SHOW COLUMNS FROM {$s['db']}.`{$tbl}` LIKE '{$parent_name}'");
        if (!sizeof($_rc)) {
          setRs("ALTER TABLE {$s['db']}.`{$tbl}` ADD `{$parent_name}` varchar(50) AFTER {$tbl}_id");
        }
      }
    }
    if ($tbl) {
      //echo '<br/> ...RUNNING...';
	  $__id = $___id = null;
      $__new = false;
      if (isset($params['`id`'])) {
        $__id = $params['`id`'];
        $_rs = getRs("SELECT {$tbl}_id FROM {$s['db']}.`{$tbl}` WHERE id = ?", array($__id));
        if ($_r = getRow($_rs)) {
          $__new = false;
          $___id = $_r[$tbl . '_id'];
        }
        else {
          $__new = true;
        }
      }
      if (!$__new) {
		  //echo '<br/>EXISTING RECORD...';
        if ($___id) {
          dbUpdate($s['db'] . '.' . $tbl, $params, $___id);
		  //echo '<br/>UPDATING RECORD...';
        }
        $primary_id = $___id;
        if ($tbl == 'product') {
          setRs("UPDATE {$s['db']}.`product` SET is_batch_updated = 0 WHERE product_id = {$___id}");
        }
        $num_update++;
      }
      else {
        $primary_id = dbPut("{$s['db']}.`{$tbl}`", $params);
        echo '<li>Add ' . $__id . ' to ' . $tbl . ' > ' . print_r($params) . '</li>';
        $num_add++;
      }
      if ($update_keys and $primary_id) {
        if ($tbl == 'brand') {
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`brand` b ON b.id = a.brandId SET a.brand_id = b.brand_id WHERE b.brand_id = {$primary_id}");
        }
        if ($tbl == 'transaction') {
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`member` b ON b.id = a.memberId SET a.member_id = b.member_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.sellerId SET a.seller_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.packedBy SET a.packer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.preparedBy SET a.preparer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`terminal` b ON b.id = a.terminalId SET a.terminal_id = b.terminal_id WHERE a.transaction_id = {$primary_id}");
        }
        if ($tbl == 'product') {
          setRs("UPDATE {$s['db']}.`product` SET is_batch_updated = 0 WHERE product_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`category` b ON b.id = a.categoryId SET a.category_id = b.category_id WHERE a.product_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`brand` b ON b.id = a.brandId SET a.brand_id = b.brand_id WHERE a.product_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`vendor` b ON b.id = a.vendorId SET a.vendor_id = b.vendor_id WHERE a.product_id = {$primary_id}");
        }
        if ($tbl == 'vendor') {
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`vendor` b ON b.id = a.vendorId SET a.vendor_id = b.vendor_id WHERE b.vendor_id = {$primary_id}");
        }
        if ($tbl == 'items') {
          setRs("UPDATE {$s['db']}.`items` a INNER JOIN {$s['db']}.`product` b ON b.id = a.productId SET a.product_id = b.product_id WHERE a.items_id = {$primary_id}");
        }
        if ($tbl == 'promotionReqLogs') {
          setRs("UPDATE {$s['db']}.`promotionReqLogs` a INNER JOIN {$s['db']}.`promotion` b ON b.id = a.promotionId SET a.promotion_id = b.promotion_id WHERE a.promotionReqLogs_id = {$primary_id}");
        }
        if ($tbl == 'member') {
          setRs("UPDATE {$s['db']}.`member` a INNER JOIN {$s['db']}.`memberGroup` b ON b.id = a.memberGroupId SET a.memberGroup_id = b.memberGroup_id WHERE a.member_id = {$primary_id}");
        }
      }
    }
  }
  if ($tbl) {
    $notes = iif($num_add, $num_add . ' new. ') . iif($num_update, $num_update . ' updated.');
    $_rs = getRs("SELECT _sys_sync_id FROM {$s['db']}._sys_sync WHERE tbl = ?", array($tbl));
    if ($_r = getRow($_rs)) {
      setRs("UPDATE {$s['db']}._sys_sync SET date_sync = CURRENT_TIMESTAMP(), url = ?, notes = ? WHERE tbl = ?", array($_url, $notes, $tbl));
    }
    else {
      setRs("INSERT INTO {$s['db']}._sys_sync (date_sync, url, notes, tbl) VALUES (CURRENT_TIMESTAMP(), ?, ?, ?)", array($_url, $notes, $tbl));
    }
  }
  //echo $ret;
  return $ret;
}

function getFields($db, $tbl) {
  $f = array();
  if (tableExists($db, $tbl)) {
    $rs = getRs("SHOW COLUMNS FROM `{$db}`.`{$tbl}`");
    foreach($rs as $r) {
      array_push($f, $r['Field']);
    }
  }
  return $f;
}

setRs("UPDATE setting_import SET table_index = (CASE WHEN table_index < 8 THEN (table_index + 1) ELSE 0 END) WHERE setting_import_id = 1");
?>