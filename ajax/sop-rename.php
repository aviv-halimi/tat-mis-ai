<?php
require_once ('../_config.php');

$success = false;
$response = $sop_name = null;

$sop_id = getVarNum('id');
$sop_name = getVar('name');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND r.sop_id = ?", array($sop_id));

if ($r = getRow($rs)) {
  dbUpdate('sop', array('sop_name' => $sop_name), $sop_id);
  $success = true;
  $response = '';
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'name' => $sop_name));
exit();
					
?>