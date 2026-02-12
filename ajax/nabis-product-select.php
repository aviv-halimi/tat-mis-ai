<?php
require_once ('../_config.php');

$success = false;
$response = $swal = $product = $price = $subtotal = $nabis_id = $nabis_code = $mapping = $foot = null;

$product_id = getVarNum('id');
$nabis_product_id = getVarNum('n');
$qty = 0;

$rn = getRs("SELECT nabis_id, quantity, lineItemSkuCode FROM nabis_product WHERE nabis_product_id = ?", $nabis_product_id);
if ($n = getRow($rn)) {
    $nabis_id = $n['nabis_id'];
    $qty = $n['quantity'];
    $nabis_code = $n['lineItemSkuCode'];
	$price = 0;
	
    $rs = getRs("SELECT p.product_id, p.sku, p.name, p.brand_id, p.category_id, p.cogs, p.unitPrice, p.flowerType, p.nabis_code, p.po_cogs, p.platformProductId FROM {$_Session->db}.product p WHERE p.product_id = ?", $product_id);
    if ($r = getRow($rs)) {
        if ((!$r['nabis_code']) OR ($r['nabis_code'] = $nabis_code)) {
            //$price = $r['cogs']; //unitPrice'];
			
			/*$rpp = getRs("SELECT COALESCE(paid, cost, price) as price FROM po_product pp INNER JOIN po ON po.po_id = pp.po_id WHERE pp.order_qty > 0 AND po.store_id = {$_Session->store_id} AND " . is_active('pp,po') . " AND " . is_enabled('pp,po') . " AND pp.product_id = {$product_id} ORDER BY po_product_id DESC LIMIT 1");
                if ($trpp = getRow($rpp)) { 
					$price = $trpp['price'];
				}
			*/
			$price = $r['po_cogs'];
            $product_id = $r['product_id'];
			$product_sku = $r['sku'];
			$platformProductId = $r['platformProductId'];
            $product = $r['sku'] . ' - ' . $r['name'];
            setRs("UPDATE {$_Session->db}.product SET nabis_code = NULL WHERE nabis_code = ?", array($nabis_code));
            setRs("UPDATE {$_Session->db}.product SET nabis_code = ? WHERE product_id = ?", array($nabis_code, $product_id));
			
			//Update all stores
			$storeList = getRs("SELECT * FROM store WHERE store_id <> {$_Session->store_id} AND " . is_active() . " AND " . is_enabled());
			foreach ($storeList as $s) {
				setRs("UPDATE {$s['db']}.product SET nabis_code = NULL WHERE nabis_code = ?", array($nabis_code));
            	//setRs("UPDATE {$s['db']}.product SET nabis_code = ? WHERE sku = ?", array($nabis_code, $product_sku));
				setRs("UPDATE {$s['db']}.product SET nabis_code = ? WHERE platformProductId = ?", array($nabis_code, $platformProductId));
			}
			
            dbUpdate('nabis_product', array('product_id' => $product_id, 'product_name' => $r['name'], 'brand_id' => $r['brand_id'], 'category_id' => $r['category_id'], 'price' => $price, 'flower_type' => $r['flowerType']), $nabis_product_id);
            $subtotal = currency_format($price * $qty);
            $price = number_format($price, 2);
            $success = true;
            $response = 'Price fetched';
        }
        else {
            $response = 'This product is already mapped to another Nabis SKU: ' . $r['nabis_code'];
            $swal = 'Product already mapped';
        }
    }
    else {
        $response = 'Product not found';
    }
}
else {
    $reponse = 'Nabis product not found';
}

if ($success) {
    $_summary = $_PO->NabisSummary($nabis_id);
    $foot = $_summary['foot'];
    $mapping = $_summary['mapping'];
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'swal' => $swal, 'product' => $product, 'price' => $price, 'subtotal' => $subtotal, 'mapping' => $mapping, 'foot' => $foot));
exit();
					
?>