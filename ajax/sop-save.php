<?php
require_once ('../_config.php');

$success = false;
$response = $sop_name = $link = '';

$sop_id = getVarNum('id');
$url = getVar('url');

$rs = getRs("SELECT r.* FROM sop r WHERE " . is_active('r') . " AND r.sop_id = ?", array($sop_id));

if ($r = getRow($rs)) {
    $a_url = explode('?', $url);
    $url = $a_url[0];
    $a_url = explode('edit', $url);
    $url = $a_url[0];
    //https://docs.google.com/document/d/12Vj88KDHBEu9W_OjBmBkq9ITQIGzU4jNO1Kg65vVHHs/edit?usp=sharing
    $pattern = "#^https?://docs.google.com/document/d/(/.*)?$#";

    if ( strpos($url, 'https://docs.google.com/document/d/') === 0 || strlen($url) == 0) {
        dbUpdate('sop', array('url' => $url), $sop_id);
        if ($url) $link = ' <a href="' . $url . 'edit" target="_blank" class="btn btn-primary ml-2 btn-sm"><i class="fa fa-external-link-alt"></i></a>';
        $success = true;
        $response = 'Saved successfully';
    }
    else {
        $response = 'The URL you entered does not match the format allowed for SOPs. Use this format: https://docs.google.com/document/d/{DOCUMENT_ID}/edit';
    }
}
else {
  $response = 'Not found';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'name' => $sop_name, 'url' => $url, 'link' => $link));
exit();
					
?>