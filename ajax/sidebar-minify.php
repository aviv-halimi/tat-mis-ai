<?php
require_once ('../_config.php');
		

$success = false;
$response = '';
$redirect = '';
$swal = false;

$m = getVarInt('_m');

$_Session->SaveAdminSettings('sidebar-minify', $m);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array(
	'success' => $success,
	'response' => $response,
	'redirect' => $redirect,
	'swal' => $swal
));
exit();
					
?>