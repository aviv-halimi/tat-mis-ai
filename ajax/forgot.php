<?php
define('SkipAuth', 'true');
require_once ('../_config.php');

$a = $_Session->ForgotPassword($_POST);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array(
	'success' => $a[0],
	'response' => $a[1],
	'redirect' => $a[2]
));
exit();
					
?>