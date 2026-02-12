<?php
require_once ('../_config.php');

$success = false;
$module_name = $html = null;
$response = '';

$module_id = getVarNum('id');

$rs = getRs("SELECT r.* FROM module r WHERE " . is_active('r') . " AND r.module_id = ?", array($module_id));

if ($r = getRow($rs)) {
  $success = true;
  $response = '';
  $module_name = $r['module_name'];
  $html = $r['content'];
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'id' => $module_id, 'name' => $module_name, 'html' => $html));
exit();
					
?>