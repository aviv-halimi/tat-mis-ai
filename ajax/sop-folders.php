<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

$t = array();
$root_sop_id = 1;
$sop_id = getVarNum('id', $root_sop_id);
$rs = getRs("SELECT r.*, c.num_children FROM (SELECT parent_sop_id, COUNT(sop_id) AS num_children FROM sop WHERE " . is_active() . " GROUP BY parent_sop_id) c RIGHT JOIN sop r ON r.sop_id = c.parent_sop_id WHERE " . is_active('r') . " AND r.parent_sop_id = ? ORDER BY r.sort, r.sop_id", array($sop_id));

foreach($rs as $r) {
  $type = 'root';
  if (true) {//$sop_id != $root_sop_id) {
    if ($r['num_children']) {
      $type = 'default';
      $num_links = 0;
      $rc = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND LENGTH(url) AND r.parent_sop_id = ? ORDER BY sort", array($r['sop_id']));
      array_push($t, array('id' => $r['sop_id'], 'text' => $r['sop_name'] . iif(sizeof($rc), ' (' . sizeof($rc). ')'), 'children' => ($r['num_children'])?true:false, 'type' => $type));
    }
    else {
      $type = 'file';
    }
  }
}
echo json_encode($t);
exit();
					
?>