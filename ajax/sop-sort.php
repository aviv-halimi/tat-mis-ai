<?php
require_once ('../_config.php');

$success = false;
$sop_name = $sop_id = null;
$response = '';

$root_sop_id = 1;
$sop_id = getVarNum('id');
$parent_sop_id = getVarNum('parent', $root_sop_id);
$sort = getVarNum('sort');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND r.sop_id = ?", array($sop_id));

if ($r = getRow($rs)) {
  dbUpdate('sop', array('parent_sop_id' => $parent_sop_id, 'sort' => $sort), $sop_id);
  $rc = getRs("SELECT r.sop_id FROM sop r WHERE " . is_active('r') . " AND r.sop_id <> ? AND r.parent_sop_id = ? ORDER BY r.sort, r.sop_id", array($sop_id, $parent_sop_id));
  $_sort = 0;
  foreach($rc as $c) {
    if ($_sort == $sort) $_sort++;
    dbUpdate('sop', array('sort' => $_sort), $c['sop_id']);
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