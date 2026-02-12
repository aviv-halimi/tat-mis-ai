<?php
require_once ('../_config.php');
require_once ('../inc/pdf.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

$r = $_PO->SavePOStatus($_POST);
echo json_encode($r);
exit();
					
?>