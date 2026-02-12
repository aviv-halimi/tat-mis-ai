<?php

define('SkipAuth', true);
require_once('../_config.php');
require_once('../inc/setup.php');

$run_start = time();

$run_queries = true;
$update_keys = true;
$_url = null;
$num_add = $num_update = $num_add_tbl = 0;

$ignored_fields = array('totalUsage', 'limitUsage', 'balance', 'paymentReceived', 'paidAmount', 'blazeTip', 'blazeFee', 'blazeTotalCharged', 'blazeCashBack363', 'memo', 'deviceName', 'address', 'city', 'licenseNumber', 'cartDiscountNotes', 'salesPrice');

$args = null;
$table_index = 10;
$_start = -1;
$_sync = 1;
$_update = 0;
$_id = null;

$_tStartMax = 0;

$tables = array(
  array('name' => 'brand', 'api' => 'store/inventory/brands', 'params' => 'start={start}'),
  array('name' => 'category', 'api' => 'store/inventory/categories', 'params' => 'skip={start}'),
  //array('name' => 'vendor', 'api' => 'vendors', 'params' => 'skip={start}'),
  array('name' => 'vendor', 'api' => 'vendors', 'params' => 'skip={start}'),
  array('name' => 'promotion', 'api' => 'loyalty/promotions', 'params' => 'start=0'),
  //array('name' => 'product', 'api' => 'products', 'params' => 'skip={start}'),
  array('name' => 'product', 'api' => 'store/inventory/products/dates', 'params' => 'startDate={ts}&endDate={ts_end}&skip={start}'),
  array('name' => 'terminal', 'api' => 'store/terminals', 'params' => 'skip={start}'),
  array('name' => 'employee', 'api' => 'employees', 'params' => 'start={start}'),
  array('name' => 'transaction', 'api' => 'transactions/days', 'params' => 'startDate={date_start}&skip={start}&limit=500&days=7'),
  array('name' => 'product', 'api' => 'store/inventory/products/dates', 'params' => 'startDate={ts}&endDate={ts_end}&skip={start}'),
  array('name' => 'member', 'api' => 'members/days', 'params' => 'startDate={date_start}&endDate={date_end}&skip={start}'),
  array('name' => 'transaction', 'api' => 'transactions', 'params' => 'skip={start}'),  
  //array('name' => 'product', 'api' => 'store/inventory/products', 'params' => 'start='),
  //array('name' => 'batch', 'api' => 'store/batches', 'params' => 'start=')
);

$_pagesize = 100;

$table = $tables[$table_index];
$fn = $fn_tbl = $table['name'];
$date_start = $date_end = null;
//1,3,4,5,6,8,9,10
$_rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . " AND FIND_IN_SET(store_id, '4') ORDER BY store_id");
foreach($_rs as $s) {

$current_store_id = $s['store_id'];

$_ids = array();

if ($current_store_id == 8) {
    $_ids = array( '67cdcf2a49a8d82c9ea1d05b');
}
else if ($current_store_id == 6) {
    $_ids = array( '67d37d4efd3e2970b94bb299', '67d37e2f84ce5e7309f8e635', '67d2481adcf6da3753b0b0d4', '67dacd667102551ff4df2c97', '67d25602caea29381328df43', '67d2589123540e453e17d0d7', '67cc610827de884c87a7fbaf', '67d57a3eeebcd11585e6a72a', '67d47c52c2035570af6bdf8a', '67d581d2bac2285ab18b8dc2', '67d37e2677c9ca550ed3506a', '67d46b8ceae4d14c6715c18b', '67d25735e69d603b2a862b4f', '67d4789a1c6f596f2bd78f82', '67d57ad1eebcd11585e6d647', '67db5acec81a413c4842722a', '67d629290f214a67033a55b7', '67d6257458f9b66836fced05', '67da2941791d80455f689c69', '67db5113d0a48714e01c368d', '67d9a5e14b840147979ae92b', '67d74e3574982460611a6f55', '67d6304deebcd1158502d242', '67d9c5762232fd6dea107d7a', '67d7544cbf812e314d8a04c0', '67dad32288e9cb789eb29d95', '67db5a4c36eec27ce8047083', '67da3203610724019e5a69e9', '67d9a6181293c30cfec30559', '67db5b1f0ea9032c595279fe', '67dc79ac54da7219e27d0cf5', '67db565f225fc42d49dfa042', '67d9a5228645256818c1db8a', '67db103604c9dc501afcf8de', '67d9c0bb337ea91fb565388e', '67db5a087679947680441bfd', '67d630a058f9b66836fe2458', '67d7561bf1b8435c405be4cc', '67db5b008450d03bbc7a5d14', '67d756bca2ed8e3b1f4763d7' );
}
else if ($current_store_id == 3) {
    $_ids = array( '67cb4141e3273e47b4146fa2', '67d5964ccec11631a57d7588', '67d9b4369feb83223daa716d' );
}
else if ($current_store_id == 10) {    
    $_ids = array( '67db5ad2cfbf212cba88482e', '67dad89d88e9cb789eb36aca', '67db5a997e70b97810e9b570' );
}
else if ($current_store_id == 4) {
    $_ids = array( '67d257fd9cd0811b959d6b7e', '67d258718575213250dc8a7a', '67d47c277907ff393489135e');
}
else if ($current_store_id == 9) {
    $_ids = array( '67cd0ec5efe02e0278413a56', '67d1ca86f203a955abe63b73', '67d256d1e69d603b2a861f9b', '67d64a7ffd90902841016c10', '67d747f70e11a12bea7b0914', '67da31b74334333c353dbf3c', '67da28ab7899565ea6aff343', '67da2f04760e24632f2a7a12', '67dc7d20d7e55f664e69bef8');
}
else if ($current_store_id == 5) {
    $_ids = array( '67ccfce286075829660cf03c', '67d0757f45e02f664ff36ad9', '67d257e77f2fca3f4aecd624', '67d23f49caea29381326c016', '67d25b2196750c7a2ae3a27c', '67d47bbb17ab5372a89715c6', '67d47d3ad0bd1810966e3340', '67d47c352337e606a5e84ae1', '67d47dd96c96b93418f017d7', '67d47de4c2035570af6c25d9', '67d588a2750dda29ceb2ba7d', '67d630de58f9b66836fe2979', '67d631afeebcd1158502f612', '67d75715f1b8435c405c0253', '67d757aff1b8435c405c12b6', '67d99611921ae873f37f0b32', '67da32894334333c353dd5a1', '67da33524334333c353deb3f', '67da353a10d3c80b64cceaf9', '67da3421d6fa750bd9bc34a8', '67dc822c642e885c9602cfc3' );
}
else if ($current_store_id == 1) {
    $_ids = array( '67d2565e23540e453e17955d', '67d37f8c9f9f3d0547075369', '67d3801694eb941938d1b44a', '67d380715ff6dd045a2edee4', '67d47bd31c6f596f2bd80512', '67d48e75eae4d14c671bb27c', '67d3587bf11a2e14f8f35303', '67d5847c750dda29ceb1f47b', '67d63138aa29d9252c6ab529', '67d6314a65b5ac0512e9bee2', '67d6306358f9b66836fe1cf7', '67d75474bc338b382ea4d126', '67da3467beed675ff3332159', '67dcd6faa399842f312c5ef5' );
}



if ($table_index == 0 || $table_index == 2 || $table_index == 6) {
    $_tStartMax = 1000;
}
else if ($table_index == 4 || $table_index == 8) {
    $_update = 1;
    $_tStartMax = 3000;
}
else {
    $_tStartMax = 0;
}

      
for ($_tStart = 0; $_tStart <= $_tStartMax; $_tStart+=$_pagesize) {
if ($table_index == 0 || $table_index == 2 || $table_index == 6) $_start = $_tStart;
        


$run_start = time();

$run_queries = true;
$update_keys = true;
$num_add = $num_update = $num_add_tbl = 0;


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


  foreach($_ids as $_id) {


    $_rt = getRs("SELECT transaction_id, date_created FROM {$s['db']}.transaction WHERE id = ?", array($_id));
    if ($_t = getRow($_rt)) {
        echo '<li>' . $s['db'] . ': ' . $_id . ' already added on ' . $_t['date_created'] . ' as ID ' . $_t['transaction_id'] . '</li>';
        continue;
    }
    else {
        echo '<li>' . $s['db'] . ': ' . $_id . ' syncing ...</li>';
    }

  if ($table) {
    $url = $table['api'];
    $start = 0;
    $date_start = '12/26/2021';
    $limit = 100;
    $ts = $ts_end = '';
    $update_params = array();
    if ($_update) {
      $_rs = getRs("SELECT params FROM {$s['db']}._sys_sync WHERE tbl = ?", array($fn));
      if ($_r = getRow($_rs)) {
        $ts = (strtotime('2023-06-17') * 1000);
        if ($_r['params']) {
          $_params = json_decode($_r['params'], true);
          if (isset($_params['ts'])) $ts = $_params['ts'];
          if (isset($_params['ts_end'])) $ts_end = $_params['ts_end'];
          if (isset($_params['start'])) $start = $_params['start'];
        }
        $update_params = json_encode(array('ts' => $ts, 'start' => $start, 'msg' => 'Started running at ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
        setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
      }
    }
    else if (!$_sync) {
      if (tableExists($s['db'], $fn)) {
        if ($fn == 'transaction' ) {
          $_date_start = '2021-12-26';
          $_rs = getRs("SELECT params FROM {$s['db']}._sys_sync WHERE tbl = ?", array($fn));
          if ($_r = getRow($_rs)) {
            if ($_r['params']) {
              $_params = json_decode($_r['params'], true);
              if (isset($_params['date_start'])) $date_start = $_params['date_start'];
              if (isset($_params['_date_start'])) $_date_start = $_params['_date_start'];
            }
          }
          $CompletedDate = strtotime($_date_start) * 1000;
          //$rs = getRs("SELECT (COUNT({$fn}_id) - 1) AS num FROM {$s['db']}.{$fn} WHERE CompletedTime >= ?", array($CompletedDate));
		      $rs = getRs("SELECT (COUNT(DISTINCT id) - 1) AS num FROM {$s['db']}.{$fn} WHERE CompletedTime >= ?", array($CompletedDate));
        }
        else {
          $rs = getRs("SELECT MAX({$fn}_id) AS num FROM {$s['db']}.{$fn}");
        }
        if ($r = getRow($rs)) {
          $start = $r['num'];
          //if ($table_index == 7) $start = 56635; //56335 - 56635;
        }
        if ($fn == 'member') {
          $start = null;
          $date_start = strtotime('2021-12-26') * 1000;
          $_rs = getRs("SELECT params FROM {$s['db']}._sys_sync WHERE tbl = ?", array($fn));
          if ($_r = getRow($_rs)) {
            if ($_r['params']) {
              $_params = json_decode($_r['params'], true);
              if (isset($_params['date_modified'])) $date_start = $_params['date_modified'];
            }
          }
        }
      }
      if ($_start != -1) $start = $_start;
    }
    else {
      if (!$_id) exit('You must provide the Id of item to sync. Close this window and try again.');
      $url .= '/' . $_id;
    }
    //echo '<li>Started at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
    if (!$_sync) {
      dbUpdate($s['db'] . '._sys_import_status', array('is_running' => 1, 'import_start' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn . ' @ ' . $start, 'current_id' => 'N/A'), 1);
      setRs("UPDATE {$s['db']}._sys_import_status SET date_start = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
    }

    $end_date = time() * 1000;

    

    $__params = $table['params'];
    $__params = str_replace('{date_start}', $date_start, $__params);
    $__params = str_replace('{date_end}', $date_end, $__params);
    $__params = str_replace('{start}', $start, $__params);
    $__params = str_replace('{ts}', $ts, $__params);
    $__params = str_replace('{ts_end}', $ts_end, $__params);

    $_sys_log_id = dbPut($s['db'] . '._sys_log', array('tbl' => $fn, 'notes' => '(Auto) Start: ' . $start . iif($ts, ' | Ts: ' . $ts . ' | '  . $__params)));

    setRs("UPDATE _sys_sync_log SET table_index = ?, table_name = ?, url = ?, notes = ? WHERE _sys_sync_log_id = ?", array($table_index, $fn, $url . '?' . $__params,  'Start: ' . $start . iif($ts, ' | Ts: ' . $ts), $_sys_sync_log_id));

    $json = fetchApi($url, $s['api_url'], $s['auth_code'], $s['partner_key'], $__params);

    $_url = $url; //. '?' . $table['params'] . $start;

    $a = json_decode($json, true);
    if ($_sync) {
      $_a = array();
      $_a[$fn] = array($a);
      $a = $_a;
    }
    
    //echo 
    dumpArray($s, $a, $fn);

    if (!$_sync) {
      dbUpdate($s['db'] . '._sys_import_status', array('is_running' => 0, 'import_end' => date('n/j/Y', time()) . ' ' . date('g:i a', time()) . ' > ' . $fn), 1);    
      setRs("UPDATE {$s['db']}._sys_import_status SET date_end = CURRENT_TIMESTAMP WHERE _sys_import_status_id = 1");
    }
    if ($_update) {

        $ts_end = (isset($a['beforeDate']))?$a['beforeDate']:'';
        $total = (isset($a['total']))?$a['total']:null;
        if ($total) {
            if (($start + $_pagesize) < $total) {
                $start += $_pagesize;
            }
            else {
                $start = 0;
                $ts = ($ts_end)?$ts_end:(time() - 300) * 1000; // 5 min earlier
                $ts_end = '';
                $_tStartMax = 0;
            }
        }
        else {
            $start = 0;
            $ts = ($ts_end)?$ts_end:(time() - 300) * 1000; // 5 min earlier
            $ts_end = '';
            $_tStartMax = 0;
        }
        $update_params = json_encode(array('ts' => $ts, 'ts_end' => $ts_end, 'start' => $start, 'total' => $total, 'msg' => 'Completed ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
    
        setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
    }
    if ($fn == 'transaction') {
      $date_start = $_date_start = null;
      $__rs = getRs("SELECT MAX(CompletedTime) AS CompletedTime FROM {$s['db']}.{$fn}");
      if ($__r = getRow($__rs)) {
        $date_start = date('m/d/Y', ($__r['CompletedTime'])/ 1000);
        $_date_start = date('Y-m-d', ($__r['CompletedTime'])/ 1000);
      }
      $start = (isset($a['start']))?$a['start']:null;
      $skip = (isset($a['skip']))?$a['skip']:null;
      $total = (isset($a['total']))?$a['total']:null;
      $update_params = json_encode(array('date_start' => $date_start, '_date_start' => $_date_start, 'start' => $start, 'skip' => $skip, 'total' => $total, 'msg' => 'Completed ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
      setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
    }
    if ($fn == 'member') {
      $date_modified = null;
      $__rs = getRs("SELECT MAX(modified) AS modified FROM {$s['db']}.{$fn}");
      if ($__r = getRow($__rs)) {
        $date_modified = $__r['modified'];
      }
      $start = (isset($a['start']))?$a['start']:null;
      $skip = (isset($a['skip']))?$a['skip']:null;
      $total = (isset($a['total']))?$a['total']:null;
      $update_params = json_encode(array('date_modified' => $date_modified, 'start' => $start, 'skip' => $skip, 'total' => $total, 'msg' => 'Completed ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())));
      setRs("UPDATE {$s['db']}._sys_sync SET params = ? WHERE tbl = ?", array($update_params, $fn));
    }
    //echo '<li>Completed at: ' . date('n/j/Y', time()) . ' ' . date('g:i a', time())  . '</li>';
    
    setRs("UPDATE {$s['db']}._sys_log SET duration = ?, notes = CONCAT(notes, ?) WHERE _sys_log_id = ?", array((time() - $run_start), iif($num_add_tbl, ' | Add: ' . $num_add_tbl) . iif($num_update, ' | Update: ' . $num_update) . ' | ' . $url . ' | ' . json_encode($__params), $_sys_log_id));

    setRs("UPDATE _sys_sync_log SET duration = ?, notes = CONCAT(notes, ?) WHERE _sys_sync_log_id = ?", array((time() - $store_run_start), iif($num_add, ' | Add: ' . $num_add) . iif($num_update, ' | Update: ' . $num_update) . ' | ' . $url . ' | ' . json_encode($__params), $_sys_sync_log_id));
  }

}
}
}


function dumpArray($s, $a, $fn, $tbl = null, $parents = array(), $parent_id = null) {
  if ($tbl == 'values') $tbl = $fn;
  global $_API_ROOT;
  global $ignored_fields;
  global $run_queries;
  global $update_keys;
  global $_url;
  global $num_add;
  global $num_add_tbl;
  global $num_update;
  global $fn_tbl;
  global $current_store_id;
  $get_member = $get_product = true;
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
  
  if (!is_array($a)) return '';

  $ret = '<ul>';
  foreach($a as $k => $v) {
    $_k = strval($k);
    if ($k === 'id') {
      $id = $v;
      if (!strlen($id)) {
        $id = $v = $tbl . '-' . getUniqueCode();
      }
    }
	 if (($tbl == 'member' or $tbl == 'transaction' or $tbl == 'product') and $_k == 'id') {
		 setRs("UPDATE {$s['db']}._sys_import_status SET current_id = ? WHERE _sys_import_status_id = 1",array($id));
	 }
    $ret .= '<li>' . $k . ' => ';

    //////////////////////// member from transaction /////////////////
    
    if ($tbl == 'transaction' and $_k == 'memberId') {
      array_push($fields, 'memberId');
      $params['`memberId`'] = $v;

      $get_member = (strlen($v))?true:false;
      if (true) { //tableExists($s['db'], 'transaction') and tableExists($s['db'], 'member')) {
        $_rm = getRs("SELECT member_id FROM {$s['db']}.`member` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_member = false;
          $params['`member_id`'] = $_m['member_id'];
          //if ($update_keys) setRs("UPDATE {$s['db']}.`transaction` SET member_id = ? WHERE id = ? AND memberId = ?", array($_m['member_id'], $id, $v));
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
      /*
      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'employee')) {
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
      }*/
    }

    if ($tbl == 'transaction' and $_k == 'packedBy') {
      array_push($fields, 'packedBy');
      $params['`packedBy`'] = $v;
      /*
      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'employee')) {
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
      */
    }

    if ($tbl == 'transaction' and $_k == 'preparedBy') {
      array_push($fields, 'preparedBy');
      $params['`preparedBy`'] = $v;
      /*
      $get_employee = (strlen($v))?true:false;
      if (tableExists($s['db'], 'transaction') and tableExists($s['db'], 'employee')) {
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
      */
    }
    
    //////////////////////// product from item /////////////////

    if ($tbl == 'items' and $_k == 'productId') {
      array_push($fields, 'productId');
      $params['productId'] = $v;

      $get_product = (strlen($v))?true:false;
      if (true) { //tableExists($s['db'], 'items') and tableExists($s['db'], 'product')) {
        $_rm = getRs("SELECT product_id, ifnull(unitPrice,0) unitPrice, ifnull(brand_id,0) brand_id, ifnull(category_id,0) category_id, case when tags LIKE '%clearance%' THEN 1 ELSE 0 END is_clearance FROM {$s['db']}.`product` WHERE id = ?", array($v));
        if ($_m = getRow($_rm)) {
          $get_product = false;
          $params['product_id'] = $_m['product_id'];
		  
          array_push($fields, 'fullprice');
          $params['fullprice'] = $_m['unitPrice'];
		
		  array_push($fields, 'is_clearance');
          $params['is_clearance'] = $_m['is_clearance'];
		  
		  
		  /*
		  $_rdd = getRs("SELECT ifnull(d.discount_rate/100,0) discount FROM {$s['db']}.daily_discount d WHERE (d.brand_id = ? OR ISNULL(d.brand_id)) AND (d.category_id = ? OR ISNULL(d.category_id)) AND d.weekday_id = weekday(FROM_UNIXTIME(?/1000)) and is_enabled = 1 and is_active = 1", array($_m['brand_id'],$_m['category_id'],$params['modified']));
		  if ($_dd = getRow($_rdd)) {
			   array_push($fields, 'dailyDealDiscount');
          	   $params['fullprice'] = $_dd['discount'];
		  }
		 */
          //if ($update_keys) setRs("UPDATE {$s['db']}.`items` SET product_id = ? WHERE id = ? AND productId = ?", array($_m['product_id'], $id, $v));
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
            $_l = (strtolower($tbl) == 'product')?255:50;
            $v = json_encode($v);
            array_push($fields, 'tags');
            $params['tags'] = (strlen($v) > $_l)?substr($v, 0, $_l - 1):$v;
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
        else if (($tbl == 'vendor' or $tbl == 'member') and $_k == 'address') { //$tbl == 'vendor' and || for both member and vendor !
          if (isset($v['address']) and !is_array($v['address'])) {
            //array_push($fields, 'address');
            //$params["`address`"] = (strlen($v['address']) > 255)?substr($v['address'], 0, 254):$v;
          }
          if (isset($v['city']) and !is_array($v['city'])) {
            array_push($fields, 'city');
            $params["`city`"] = (strlen($v['city']) > 50)?substr($v['city'], 0, 49):$v['city'];
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
		elseif ($tbl == 'product' and $_k == 'assets') {
          if (isset($v[0]['publicURL'])) {
            array_push($fields, 'photo');
            $params["`photo`"] = $v[0]['publicURL'];
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
        elseif ($tbl == 'product' and $_k == 'priceBreaks') {
          if (isset($v[0]['salePrice'])) {
            array_push($fields, 'salesPrice');
            $params["`salesPrice`"] = $v[0]['salePrice'];
          }
		  else {
			   if (isset($v[0]['price'])) {
				array_push($fields, 'salesPrice');
				$params["`salesPrice`"] = $v[0]['price'];
			   }
          }	
        }

        /////////////////////

        // identifications

        else if (!in_array($_k, array('batchQRAsset', 'deviceDetails', 'recommendations', 'frontPhoto', 'recentProducts', 'splitPayment', 'memberSignature', 'orderTags', 'note', 'federalTax', 'countryTax', 'cityTax', 'stateTax', 'rules', 'promoCodes', 'refundPaymentOptions', 'deliveryAddress', 'loc', 'taxInfo', 'taxResult', 'cultivationTaxResul$vt', 'taxMappingInfo', 'cityTax', 'countyTax', 'stateTax', 'federalTax', 'taxTable', 'notes', 'assets', 'photo', 'taxTables', 'producerAddress', 'brandLogo', 'secondaryVendors', 'bundleItems', 'recentLocation', 'shops', 'timeCard', 'role', 'employeeOnFleetInfoList', 'appAccessList', 'potencyAmount1', 'cartMinimums', 'receiptInfo'))) {
          //, 'shop', 'cardFeesLog', 'blazePayRequests', 'refundLog', 'signaturePhoto', 'contracts', 'latestRecommendation'
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
        /*
        $__len = ($tbl == 'product' and $k == 'name')?255:50;
        $__v = (strlen($v) > $__len)?substr($v, 0, ($__len - 1)):$v;
        $__v = cleaner($__v);
        */
        // 2023/06/29 - Truncate all to 50 chars
        /*
        */
        if (strtolower($tbl) == 'product' and in_array(strtolower($k), array('name', 'tags','description'))) {
          $__v = $v;
        }
        elseif (strtolower($tbl) == 'transaction' and in_array(strtolower($k), array('memo'))) {
          $__v = $v;
        }
        else {          
          $__v = (strlen($v) > 50)?substr($v, 0, 49):$v;
        }
        //$__v = (strlen($v) > 50)?substr($v, 0, 49):$v;
        $params["`" . $k . "`"] = $__v;
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
      $__id = $___id = null;
      $__new = false;
      if (isset($params['`id`'])) {
        $__id = $params['`id`'] . iif($tbl == 'quantityLogs' and $parent_id, '__' . $parent_id);
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
        $primary_id = $___id;
        // +++ 2023-03-21 -- Only update the following tables, nothing else -- //
        if (in_array($tbl, array('product', 'member', 'transaction','vendor','brand','category','promotion'))) {
          if ($___id) {
            dbUpdate($s['db'] . '.' . $tbl, $params, $___id);
          }
          if ($tbl == 'product') {
            setRs("UPDATE {$s['db']}.`product` SET is_batch_updated = 0 WHERE product_id = {$___id}");
          }
          $num_update++;
        }
      }
      else {        
        $primary_id = dbPut("{$s['db']}.`{$tbl}`", $params);
        //echo '<li>Add ' . $__id . ' to ' . $tbl . ' > ' . print_r($params) . '</li>';
        $num_add++;
        if ($tbl == $fn_tbl) $num_add_tbl++;
      }
      if ($update_keys and $primary_id) {
        if ($tbl == 'brand') {
          setRs("UPDATE {$s['db']}.`product` a INNER JOIN {$s['db']}.`brand` b ON b.id = a.brandId SET a.brand_id = b.brand_id WHERE b.brand_id = {$primary_id}");
        }
        if ($tbl == 'transaction') {
          if ($get_member) setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`member` b ON b.id = a.memberId SET a.member_id = b.member_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.sellerId SET a.seller_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.packedBy SET a.packer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`employee` b ON b.id = a.preparedBy SET a.preparer_id = b.employee_id WHERE a.transaction_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`transaction` a INNER JOIN {$s['db']}.`terminal` b ON b.id = a.terminalId SET a.terminal_id = b.terminal_id WHERE a.transaction_id = {$primary_id}");
        }
        if ($tbl == 'transaction') {
          setRs("UPDATE {$s['db']}.`transaction` b INNER JOIN {$s['db']}.`cart` a ON b.id = a.transactionId SET a.transaction_id = b.transaction_id WHERE b.transaction_id = {$primary_id}");
		  setRs("UPDATE {$s['db']}.`transaction` b INNER JOIN {$s['db']}.`payments` a ON b.id = a.transactionId SET a.transaction_id = b.transaction_id WHERE b.transaction_id = {$primary_id}");
		 
			$rDisc = getRs("SELECT i.items_id, ifnull(d.discount_rate/100,0) dailyDealDiscount, ifnull( bd.discount_rate/(case when bd.is_fixed_discount then 1 else 100 end),0) poBrandDiscount, ifnull(bd.is_fixed_discount,0) as poBrandDiscount_fixed, ifnull(d.is_clearance,0) dd_include_clearance, ifnull(dt.daily_discount_type_name,'none') dd_type, ifnull(d.rebate_percent/100,0) dd_brand_rebate, ifnull(d.rebate_wholesale_discount/100,0) dd_wholesale_discount
					FROM {$s['db']}.items i
						INNER JOIN {$s['db']}.cart c ON c.cart_id = i.cart_id
						INNER JOIN {$s['db']}.transaction t ON t.transaction_id = c.transaction_id
						INNER JOIN {$s['db']}.product p ON p.product_id = i.product_id
						LEFT JOIN {$s['db']}.brand br ON br.brand_id = p.brand_id
						LEFT JOIN {$s['db']}.category ca ON ca.category_id = p.category_id
						LEFT JOIN {$s['db']}.brand_discount bd on bd.brand_id = p.brand_id AND bd.is_enabled = 1 and bd.is_active = 1
						LEFT JOIN theartisttree.daily_discount d ON 
							(d.brand_id = br.master_brand_id OR ISNULL(d.brand_id)) AND 
							(d.category_id = ca.master_category_id OR ISNULL(d.category_id)) AND 
							d.weekday_id = weekday(FROM_UNIXTIME(t.completedTime/1000))+1 AND d.is_enabled = 1 AND d.is_active = 1
							AND (JSON_CONTAINS(d.store_ids, CAST({$s['store_id']} AS CHAR), '$') OR d.store_ids IS NULL)
							AND (d.date_end IS NULL OR d.date_end >= FROM_UNIXTIME(t.completedTime/1000)) AND (d.date_start IS NULL OR d.date_start <= FROM_UNIXTIME(t.completedTime/1000))
						LEFT JOIN theartisttree.daily_discount_type dt ON dt.daily_discount_type_id = d.daily_discount_type_id
					WHERE t.transaction_id = {$primary_id} AND ifnull(d.discount_rate/100,0) + ifnull(bd.discount_rate/100,0) > 0");
			foreach($rDisc as $rds) {
				setRs("UPDATE {$s['db']}.items i SET 
						i.dailyDealDiscount = {$rds['dailyDealDiscount']}, 
						i.dd_brand_rebate = {$rds['dd_brand_rebate']}, 
						i.dd_include_clearance = {$rds['dd_include_clearance']}, 
						i.dd_type = '{$rds['dd_type']}', 
						i.dd_wholesale_discount = {$rds['dd_wholesale_discount']}, 
						i.poBrandDiscount = {$rds['poBrandDiscount']},
						i.poBrandDiscount_fixed = {$rds['poBrandDiscount_fixed']}
					WHERE i.items_id = {$rds['items_id']}");
			}
        }
        if ($tbl == 'cart') {
          setRs("UPDATE {$s['db']}.`cart` b INNER JOIN {$s['db']}.`items` a ON b.id = a.cartId SET a.cart_id = b.cart_id WHERE b.cart_id = {$primary_id}");
          setRs("UPDATE {$s['db']}.`cart` b INNER JOIN {$s['db']}.`promotionReqLogs` a ON b.id = a.cartId SET a.cart_id = b.cart_id WHERE b.cart_id = {$primary_id}");
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
          //Aviv change 3.4.24 to include fullPrice update
		      if ($get_product) setRs("UPDATE {$s['db']}.`items` a INNER JOIN {$s['db']}.`product` b ON b.id = a.productId SET a.product_id = b.product_id AND a.fullprice = ifnull(b.unitprice,0) AND a.is_clearance = case when tags LIKE '%clearance%' THEN 1 ELSE 0 END WHERE a.items_id = {$primary_id}");
        }
        if ($tbl == 'promotionReqLogs') {
          setRs("UPDATE {$s['db']}.`promotionReqLogs` a INNER JOIN {$s['db']}.`promotion` b ON b.id = a.promotionId SET a.promotion_id = b.promotion_id WHERE a.promotionReqLogs_id = {$primary_id}");
        }
        if ($tbl == 'member') {
          setRs("UPDATE {$s['db']}.`member` a INNER JOIN {$s['db']}.`memberGroup` b ON b.id = a.memberGroupId SET a.memberGroup_id = b.memberGroup_id WHERE a.member_id = {$primary_id}");
        }
		/*
		if ($tbl == 'payments') {
          setRs("UPDATE {$s['db']}.`payments` a INNER JOIN {$s['db']}.`transaction` b ON b.id = a.transactionId SET a.transaction_id = b.transaction_id WHERE a.payments_id = {$primary_id}");
        }
		*/
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


?>