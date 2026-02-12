<?php
require_once ('../_config.php');

$success = false;
$module_name = $module_id = null;
$response = '';

$root_module_id = $_Session->GetCodeId('module', 'reports');
$parent_module_id = getVarNum('parent', $root_module_id);
$sort = getVarNum('sort');
$module_name = getVar('name');

if (!strlen($response)) {
  $module_id = dbPut('module', array('parent_module_id' => $parent_module_id, 'module_name' => $module_name, 'sort' => $sort));
  dbUpdate('module', array('module_code' => toLink($module_name . ' ' . $module_id)), $module_id);
  $success = true;
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'name' => $module_name, 'id' => $module_id));
exit();
					
?>