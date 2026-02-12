<?php
require_once('../_config.php');
$success = true;
$response = 'test';

$start = time();
$store_id = getVarNum('store_id', null);
$product_id = null;

$test = getRs("UPDATE theartisttree_dev.store SET store_name = 'Hawthorne1' WHERE store_id = 10");

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response));
?>
