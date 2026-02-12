<?php
define('SkipAuth', 'true');
require_once ('../_config.php');		

$success = false;
$response = '';
$redirect = '';
$swal = false;

$email = getVar('email', '');
$password = getVar('password', '');
$remember = getVarInt('remember');
$redirect = getVar('redirect');

if (false) {
	$response = 'Invalid image code.';
}
else {
	if (strlen($redirect)) {
		//$redirect = decrypt($redirect);
		if (strpos($redirect, 'login') !== false) {
			$redirect = '/dashboard';
		}
	}
	else {
		$redirect = '/dashboard';
	}


	if (isset($_POST['email'])) {
		$a = $_Session->Login($email, $password, $remember);
		$success = $a['success'];
		$response = $a['response'];
		//$redirect = $a[2];
		$swal = $a['swal'];
	}
	else {		
		$redirect = '';
		$response = 'Nothing to do here.';
	}
}

if (!$success) {
	$redirect = '';
}

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