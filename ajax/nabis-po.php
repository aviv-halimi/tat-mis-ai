<?php
require_once ('../_config.php');

$success = false;
$response = $swal = $redirect = null;

$nabis_id = getVarNum('id');
$a_unlinked = $a_unpriced = array();

$rn = getRs("SELECT * FROM nabis WHERE nabis_id = ? AND " . is_enabled(), $nabis_id);
if ($n = getRow($rn)) {
    if (!$n['po_id']) {

        $rs = getRs("SELECT * FROM nabis_product WHERE nabis_id = ? AND " . is_enabled(), $nabis_id);
        foreach($rs as $r) {
            if (!$r['product_id'] and !$r['product_name']) {
                array_push($a_unlinked, $r['lineItemSkuName']);
            }
            if (!$r['price']) {
                array_push($a_unpriced, $r['lineItemSkuName']);
            }
        }

        if (!sizeof($a_unlinked) and !sizeof($a_unpriced)) {
        
            $po_code = $_PO->NabisPO($nabis_id);

            $success = true;
            $response = 'PO generated successfully.';
            $redirect = '/po/' . $po_code;
        }
        else {
            if (sizeof($a_unpriced)) {
                $swal = 'Some items not priced';
                $response = 'You need to enter a price for all items in this order before generating PO. The following items are not priced: ' . implode(', ', $a_unpriced);
            }
            if (sizeof($a_unlinked)) {
                $swal = 'Some items not mapped';
                $response = 'You need to map all items in this order before generating PO. The following items are not mapped: ' . implode(', ', $a_unlinked);
            }
        }
    }
    else {
        $swal = 'Error';
        $response = 'PO already generated for this Nabis order';
    }
}
else {
    $response = 'Product not found';
}




header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'swal' => $swal, 'redirect' => $redirect));
exit();
					
?>