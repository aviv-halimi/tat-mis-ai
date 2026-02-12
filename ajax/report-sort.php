<?php
require_once ('../_config.php');

$success = false;
$module_name = $module_id = null;
$response = '';

$root_module_id = $_Session->GetCodeId('module', 'reports');
$module_id = getVarNum('id');
$parent_module_id = getVarNum('parent', $root_module_id);
$sort = getVarNum('sort');

$rs = getRs("SELECT r.* FROM module r WHERE " . is_active('r') . " AND r.module_id = ?", array($module_id));

if ($r = getRow($rs)) {
  dbUpdate('module', array('parent_module_id' => $parent_module_id, 'sort' => $sort), $module_id);
  $rc = getRs("SELECT r.module_id FROM module r WHERE " . is_active('r') . " AND r.module_id <> ? AND r.parent_module_id = ? ORDER BY r.sort, r.module_id", array($module_id, $parent_module_id));
  $_sort = 0;
  foreach($rc as $c) {
    if ($_sort == $sort) $_sort++;
    dbUpdate('module', array('sort' => $_sort), $c['module_id']);
    $_sort++;
  }
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