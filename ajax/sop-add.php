<?php
require_once ('../_config.php');

$success = false;
$module_name = $url = $link = '';
$response = '';

$parent_sop_id = getVarNum('parent');
$sort = getVarNum('sort');
$sop_name = getVar('name', '');

if (!strlen($response)) {
  $sop_id = dbPut('sop', array('parent_sop_id' => $parent_sop_id, 'sop_name' => $sop_name, 'sort' => $sort));
  $success = true;
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'name' => $sop_name, 'id' => $sop_id, 'url' => $url, 'link' => $link));
exit();
					
?>