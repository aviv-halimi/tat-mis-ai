<?php
require_once ('../_config.php');

$success = $hide = false;
$response = null;

$po_product_id = getVarNum('id');
$type = getVar('type');
$checked = getVarInt('checked');

$rs = getRs("SELECT is_created, is_transferred FROM po_product WHERE po_product_id = ?", array($po_product_id));
if ($r = getRow($rs)) {
    $success = true;
    $response = 'Done';
    $params = array();
    if ($type == 'created') {
        $params = array('created' => $checked);
        setRs("UPDATE po_product SET is_created = ? WHERE po_product_id = ?", array($checked, $po_product_id));
        if ($r['is_transferred'] and $checked) $hide = true;
    }
    if ($type == 'transferred') {
        setRs("UPDATE po_product SET is_transferred = ? WHERE po_product_id = ?", array($checked, $po_product_id));
        if ($r['is_created'] and $checked) $hide = true;
    }
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'hide' => $hide));
exit();
					
?>