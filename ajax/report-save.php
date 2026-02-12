<?php
require_once ('../_config.php');

$success = true;
$response = $module_name = $html = null;

$module_id = getVarNum('id');
$content = getVar('content');

$rs = getRs("SELECT r.* FROM module r WHERE " . is_active('r') . " AND r.module_id = ?", array($module_id));

if ($r = getRow($rs)) {
  dbUpdate('module', array('content' => $content), $module_id);
  $sucess = true;
  $response = '';
  $html = $content;
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'name' => $module_name, 'html' => $html));
exit();
					
?>