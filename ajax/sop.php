<?php
require_once ('../_config.php');

$success = false;
$sop_name = $url = $link = '';
$response = '';

$sop_id = getVarNum('id');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND r.sop_id = ?", array($sop_id));

if ($r = getRow($rs)) {
  $success = true;
  $response = '';
  $sop_name = $r['sop_name'];
  $url = $r['url'] ?? '';
  if ($url) $link = '<a href="' . $r['url'] . 'edit" target="_blank" class="btn btn-primary ml-2 btn-sm"><i class="fa fa-external-link-alt"></i></a>';
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'id' => $sop_id, 'name' => $sop_name, 'url' => $url, 'link' => $link));
exit();
					
?>