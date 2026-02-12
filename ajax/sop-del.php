<?php
require_once ('../_config.php');

$success = true;
$response = '';

$sop_id = getVarNum('id');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND r.sop_id = ?", array($sop_id));

if ($r = getRow($rs)) {
  dbUpdate('sop', array('is_enabled' => 0, 'is_active' => 0), $sop_id);
  $sucess = true;
  $response = '';
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response));
exit();
					
?>