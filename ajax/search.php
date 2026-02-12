<?php
require_once ('../_config.php');

$a = $_Patient->Search($_POST);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array(
	'success' => $a[0],
	'response' => $a[1],
	'records' => $a[2],
	'html' => $a[3]
));
exit();
					
?>