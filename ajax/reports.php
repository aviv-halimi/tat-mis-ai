<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

$t = array();
$root_module_id = $_Session->GetCodeId('module', 'reports');
$module_id = getVarNum('id', $root_module_id);
$rs = getRs("SELECT r.*, c.num_children FROM (SELECT parent_module_id, COUNT(module_id) AS num_children FROM module WHERE " . is_active() . " GROUP BY parent_module_id) c RIGHT JOIN module r ON r.module_id = c.parent_module_id WHERE " . is_active('r') . " AND r.parent_module_id = ? ORDER BY r.sort, r.module_id", array($module_id));

foreach($rs as $r) {
  $type = 'root';
  if ($module_id != $root_module_id) {
    if ($r['num_children']) {
      $type = 'default';
    }
    else {
      $type = 'file';
    }
  }
  array_push($t, array('id' => $r['module_id'], 'text' => $r['module_name'], 'children' => ($r['num_children'])?true:false, 'type' => $type));
}

//["Child 1", { "id" : "demo_child_1", "text" : "Child 2", "children" : [ { "id" : "demo_child_2", "text" : "One more", "type" : "file" }] }]
echo json_encode($t);
exit();
					
?>