<?php
require_once ('../_config.php');

$success = true;
$response = $content = '';

$sop_id = getVarNum('id');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND LENGTH(url) AND r.parent_sop_id = ? ORDER BY sort", array($sop_id));

$content = '<div class="row">';
foreach($rs as $r) {
    $content .= '<div class="col-md-4"><a href="' . $r['url'] . 'export?format=pdf" target="_blank" class="btn btn-primary btn-block">' . $r['sop_name'] . ' <i class="fa fa-external-link-alt"></i></a></div>';
}
$content .= '</div>';
if (!sizeof($rs)) {
    $content = 'No SOPs listed in this category. Please check the folders in the left column ...';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'content' => $content));
exit();
					
?>