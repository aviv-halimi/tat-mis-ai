<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

$r = $_PO->SavePOData($_POST);
echo json_encode($r);
exit();
					
?>