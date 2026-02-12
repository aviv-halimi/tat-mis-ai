<?php
require_once ('../_config.php');

$success = false;
$response = '';

$d = getVarToDT('d');
$_Session->SaveAdminSettings('date_dd', $d);
$success = true;
$response = 'Updated successfully';
$redirect = '{refresh}';

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
					
?>