<?php
require_once ('../_config.php');

$success = false;
$response = '';

$daily_discount_id = getVarNum('id');
$is_enabled = getVarInt('is_enabled');

if ($daily_discount_id) {
    setRs("UPDATE daily_discount SET is_enabled = ? WHERE daily_discount_id = ?", array($is_enabled, $daily_discount_id));
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