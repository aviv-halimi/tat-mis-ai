<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
$errors = $_p = array();
$po_code = getVar('po_code');
for($i = 0; $i<20; $i++) {
    $category_id = $brand_id = $flower_type = $is_tax = $price = $qty = null;
    $product_name = getVar('product_name_' . $i);
    $category_name = getVar('category_name_' . $i);
    $brand_name = getVar('brand_name_' . $i);
    $flower_type_name = getVar('flower_type_name_' . $i);
    $is_tax = getVarInt('is_tax_' . $i);
    $qty = getVarNum('qty_' . $i);
    $price = getVarNum('price_' . $i);
    if ($product_name) {
        if ($category_name) {
            $rs = getRs("SELECT category_id FROM {$_Session->db}.category WHERE name = ?", array($category_name));
            if ($r = getRow($rs)) {
                $category_id = $r['category_id'];
            }
            else {
                array_push($errors, $category_name . ' not found in category table');
            }
        }
        if ($brand_name) {
            $rs = getRs("SELECT brand_id FROM {$_Session->db}.brand WHERE name = ?", array($brand_name));
            if ($r = getRow($rs)) {
                $brand_id = $r['brand_id'];
            }
            else {
                array_push($errors, $brand_name . ' not found in brand table');
            }
        }
        if ($flower_type_name) {            
            $flower_types = $_Session->GetSetting('flower-type');
            $rf = explode(PHP_EOL, $flower_types);
            foreach($rf as $f) {
                if ($flower_type_name == trim($f)) {
                    $flower_type = trim($f);
                }
            }
            if (!$flower_type) {
                array_push($errors, $flower_type_name . ' not found in flower type table');

            }
        }
        array_push($_p, array('po_code' => $po_code, 'po_product_name' => $product_name, 'category_id' => $category_id, 'brand_id' => $brand_id, 'flower_type' => $flower_type, 'is_tax' => $is_tax, 'qty' => $qty, 'price' => $price));
    }
}
if (sizeof($errors)) {
    $errors = array_unique($errors);
    $r = array('success' => false, 'response' => implode(', ', $errors));
}
else {
    foreach($_p as $_r) {
        $r = $_PO->SavePOCustomProduct($_r);
    }
    $r = array('success' => true, 'response' => 'Custom product' . iif(sizeof($_p) != 1, 's') . ' added successfully', 'redirect' => '{refresh}');
}
echo json_encode($r);
exit();
					
?>