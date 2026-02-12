<?php
require_once ('../_config.php');

$success = false;
$response = $redirect = $swal = null;

$store_code = getVar('c');


if ($store_code and $_Session->store_ids) {
  $_a_store_ids = json_decode($_Session->store_ids, true);
  foreach($_a_store_ids as $_store) {
    if (isset($_store['store_id']) and isset($_store['employee_id'])) {
      $_store_id = $_store['store_id'];
      $_employee_id = $_store['employee_id'];
      $_rs = getRs("SELECT * FROM store WHERE " . is_enabled() . " AND JSON_CONTAINS(?, CAST(store_id AS CHAR), '$') AND store_code = ?", array($_store_id, $store_code));
      if ($_s = getRow($_rs)) {
        if ($_s['store_code'] == $store_code) {
          $_ra = getRs("SELECT * FROM admin WHERE admin_id = ?", $_Session->admin_id);
          dbUpdate('admin', array('store_id' => $_s['store_id'], 'employee_id' => $_employee_id), $_Session->admin_id);
          saveActivity('update', $_Session->admin_id, 'admin', 'Store changed to: ' . $_s['store_name'], getRow($_ra));
          $success = true;
          $response = 'Store selection updated';
          //$swal = 'Success';
          $redirect = '{refresh}';
        }
      }
    }
  }
}
else {
  $response = 'You cannot change stores at this time';
  $swal = 'Not so fast!';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'swal' => $swal));
exit();
					
?>