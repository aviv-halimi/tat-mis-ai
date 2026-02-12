<?php
require_once ('../_config.php');

$success = false;
$response = $redirect = null;

$sort = getVar('sort');

if (in_array($sort, array('brand', 'category', 'store', 'product', 'discount', 'dates'))) {
    $_SESSION['daily_discount_sort'] = $sort;
    $response = 'Sorting updated successfully';
    $redirect = '{refresh}';
}
else {
    $response = 'Missing info';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
					
?>