<?php
require_once ('../_config.php');

$success = false;
$response = '';

$brand_id = getVarNum('id');
$dailyDeal = getVar('dd');

if ($brand_id) {
    setRs("UPDATE {$_Session->db}.brand SET dailyDeal = ? WHERE brand_id = ?", array($dailyDeal, $brand_id));
    $success = true;
    $response = 'Updated successfully';
}
else {
    $response = 'Missing info';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response));
exit();
					
?>