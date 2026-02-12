<?php

define('SkipAuth', true);
require_once('../_config.php');

$run_api = true; // This allows for testing without running blaze API calls

$success = false;
$response = $redirect = null;
$a = $brands = $products = array();
$num_products = 0;
$tqty = 0;

$data = array(
array('7','4104','65ca7c44a9ebc20d1cb8e727','4','0.5','26','Clearance Credit - Feb26 710 Labs'),
array('7','26050','65ca7c44a9ebc20d1cb8e727','6','0.5','46.75','Clearance Credit - Feb26 710 Labs'),
array('5','896','5efbe3e95b723008ec5fec16','3','0.5','56','Clearance Credit - Feb26 Absolute Extracts'),
array('13','31514','68cdcf401c44c0b22a777c7d','72','0.5','5','Clearance Credit - Feb26 Big Tree'),
array('13','31515','68cdcf401c44c0b22a777c7d','60','0.5','5','Clearance Credit - Feb26 Big Tree'),
array('13','31516','68cdcf401c44c0b22a777c7d','98','0.5','5','Clearance Credit - Feb26 Big Tree'),
array('13','31517','68cdcf401c44c0b22a777c7d','82','0.5','5','Clearance Credit - Feb26 Big Tree'),
array('13','23603','68b87f377e0f5b93e37531da','29','0.5','4','Clearance Credit - Feb26 Birdies'),
array('12','21835','68f7f2a6b06b3f852c78e2b8','37','0.5','25','Clearance Credit - Feb26 Blueprint'),
array('12','36887','68f7f2a6b06b3f852c78e2b8','11','0.5','25','Clearance Credit - Feb26 Blueprint'),
array('12','36888','68f7f2a6b06b3f852c78e2b8','15','0.5','25','Clearance Credit - Feb26 Blueprint'),
array('13','21836','68f7f2a6b06b3f852c78e2b8','3','0.5','25','Clearance Credit - Feb26 Blueprint'),
array('13','36886','68f7f2a6b06b3f852c78e2b8','15','0.5','25','Clearance Credit - Feb26 Blueprint'),
array('3','23926','68fbaf6e36ff53a9b9d10457','25','0.5','12','Clearance Credit - Feb26 California Love'),
array('4','25495','68fbaf6e36ff53a9b9d10457','7','0.5','14','Clearance Credit - Feb26 California Love'),
array('5','24604','68fbaf6e36ff53a9b9d10457','4','0.5','12','Clearance Credit - Feb26 California Love'),
array('7','13023','68fbaf6e36ff53a9b9d10457','21','0.5','1.5','Clearance Credit - Feb26 California Love'),
array('7','13573','68fbaf6e36ff53a9b9d10457','14','0.5','2.5','Clearance Credit - Feb26 California Love'),
array('7','14265','68fbaf6e36ff53a9b9d10457','4','0.5','13','Clearance Credit - Feb26 California Love'),
array('7','25739','68fbaf6e36ff53a9b9d10457','16','0.5','1.5','Clearance Credit - Feb26 California Love'),
array('7','25855','68fbaf6e36ff53a9b9d10457','13','0.5','2.5','Clearance Credit - Feb26 California Love'),
array('7','25856','68fbaf6e36ff53a9b9d10457','4','0.5','2.5','Clearance Credit - Feb26 California Love'),
array('7','26229','68fbaf6e36ff53a9b9d10457','4','0.5','13','Clearance Credit - Feb26 California Love'),
array('7','26293','68fbaf6e36ff53a9b9d10457','8','0.5','12','Clearance Credit - Feb26 California Love'),
array('8','24356','68fbaf6e36ff53a9b9d10457','6','0.5','12','Clearance Credit - Feb26 California Love'),
array('11','39521','68fbaf6e36ff53a9b9d10457','88','0.5','14','Clearance Credit - Feb26 California Love'),
array('6','37386','6633c853e8797042446cc9b8','11','0.5','60','Clearance Credit - Feb26 CAM'),
array('9','53848','6633c853e8797042446cc9b8','5','0.5','70','Clearance Credit - Feb26 CAM'),
array('10','27001','6633c853e8797042446cc9b8','9','0.5','60','Clearance Credit - Feb26 CAM'),
array('12','24788','6633c853e8797042446cc9b8','8','0.5','60','Clearance Credit - Feb26 CAM'),
array('3','22949','6584b070f924207cfd2bcfa8','8','0.5','27.5','Clearance Credit - Feb26 Cannabiotix'),
array('7','28633','6584b070f924207cfd2bcfa8','4','0.5','5','Clearance Credit - Feb26 Cannabiotix'),
array('8','14533','6584b070f924207cfd2bcfa8','23','0.5','5','Clearance Credit - Feb26 Cannabiotix'),
array('11','43628','6584b070f924207cfd2bcfa8','10','0.5','5','Clearance Credit - Feb26 Cannabiotix'),
array('1','19323','5efbe3e95b723008ec5fec16','3','0.5','0','Clearance Credit - Feb26 Care By Design'),
array('8','2622','5efbe3e95b723008ec5fec16','7','0.5','20','Clearance Credit - Feb26 Care By Design'),
array('8','14683','5efbe3e95b723008ec5fec16','19','0.5','11.5','Clearance Credit - Feb26 Farmer and the Felon'),
array('8','24553','5efbe3e95b723008ec5fec16','16','0.5','11.5','Clearance Credit - Feb26 Farmer and the Felon'),
array('7','11979','6584b070f924207cfd2bcfa8','9','0.5','5','Clearance Credit - Feb26 Heirbloom'),
array('7','12627','6584b070f924207cfd2bcfa8','17','0.5','5','Clearance Credit - Feb26 Heirbloom'),
array('7','13089','6584b070f924207cfd2bcfa8','13','0.5','5','Clearance Credit - Feb26 Heirbloom'),
array('7','14532','6584b070f924207cfd2bcfa8','12','0.5','5','Clearance Credit - Feb26 Heirbloom'),
array('11','29407','6584b070f924207cfd2bcfa8','7','0.5','5','Clearance Credit - Feb26 Heirbloom'),
array('12','36564','68cdcf401c44c0b22a777c84','6','0.5','17','Clearance Credit - Feb26 Hi'),
array('12','36565','68cdcf401c44c0b22a777c84','7','0.5','17','Clearance Credit - Feb26 Hi'),
array('13','36563','68cdcf401c44c0b22a777c84','21','0.5','17','Clearance Credit - Feb26 Hi'),
array('13','36564','68cdcf401c44c0b22a777c84','21','0.5','17','Clearance Credit - Feb26 Hi'),
array('13','36565','68cdcf401c44c0b22a777c84','18','0.5','17','Clearance Credit - Feb26 Hi'),
array('7','28907','68a33d098b8b35cbf709ad61','4','0.5','6','Clearance Credit - Feb26 LAX Packs'),
array('12','36970','68a33d098b8b35cbf709ad61','3','0.5','35','Clearance Credit - Feb26 LAX Packs'),
array('1','5700','68fbaf6e36ff53a9b9d10457','18','0.5','5','Clearance Credit - Feb26 Marys Medicinals'),
array('1','18068','68fbaf6e36ff53a9b9d10457','4','0.5','6','Clearance Credit - Feb26 Marys Medicinals'),
array('5','8775','68fbaf6e36ff53a9b9d10457','3','0.5','17','Clearance Credit - Feb26 Marys Medicinals'),
array('7','5259','68fbaf6e36ff53a9b9d10457','3','0.5','5','Clearance Credit - Feb26 Marys Medicinals'),
array('7','10092','68fbaf6e36ff53a9b9d10457','14','0.5','5','Clearance Credit - Feb26 Marys Medicinals'),
array('7','10473','68fbaf6e36ff53a9b9d10457','4','0.5','20','Clearance Credit - Feb26 Marys Medicinals'),
array('7','12961','68fbaf6e36ff53a9b9d10457','3','0.5','5','Clearance Credit - Feb26 Marys Medicinals'),
array('12','34389','68ed6f0040f97ae8856c8295','17','0.5','28','Clearance Credit - Feb26 Munchies'),
array('13','36413','68ed6f0040f97ae8856c8295','13','0.5','28','Clearance Credit - Feb26 Munchies'),
array('13','36414','68ed6f0040f97ae8856c8295','16','0.5','28','Clearance Credit - Feb26 Munchies'),
array('11','42937','6682e445c5bca944a95480d3','7','0.5','13','Clearance Credit - Feb26 Oakfruitland'),
array('1','18098','658b221c2579c43e0c249559','5','0.5','0','Clearance Credit - Feb26 Papa & Barkley'),
array('5','1671','658b221c2579c43e0c249559','24','0.5','36','Clearance Credit - Feb26 Papa & Barkley'),
array('5','1737','658b221c2579c43e0c249559','6','0.5','20','Clearance Credit - Feb26 Papa & Barkley'),
array('8','1903','658b221c2579c43e0c249559','9','0.5','31.5','Clearance Credit - Feb26 Papa & Barkley'),
array('10','124','658b221c2579c43e0c249559','14','0.5','35','Clearance Credit - Feb26 Papa & Barkley'),
array('11','32237','68fbaf6e36ff53a9b9d10457','5','0.5','8','Clearance Credit - Feb26 Potters'),
array('11','32238','68fbaf6e36ff53a9b9d10457','19','0.5','7.5','Clearance Credit - Feb26 Potters'),
array('11','32242','68fbaf6e36ff53a9b9d10457','22','0.5','7.5','Clearance Credit - Feb26 Potters'),
array('4','27155','682b62fc91e28c51768aa8c1','6','0.5','18','Clearance Credit - Feb26 Pure Beauty'),
array('5','26078','682b62fc91e28c51768aa8c1','6','0.5','18','Clearance Credit - Feb26 Pure Beauty'),
array('7','13111','682b62fc91e28c51768aa8c1','24','0.5','15','Clearance Credit - Feb26 Pure Beauty'),
array('7','28174','682b62fc91e28c51768aa8c1','27','0.5','6.5','Clearance Credit - Feb26 Pure Beauty'),
array('8','26237','682b62fc91e28c51768aa8c1','20','0.5','6.5','Clearance Credit - Feb26 Pure Beauty'),
array('5','23433','68fbaf6e36ff53a9b9d10457','13','0.5','18','Clearance Credit - Feb26 Revelry'),
array('5','5987','68fbaf6e36ff53a9b9d10457','64','0.5','11.5','Clearance Credit - Feb26 RNBW'),
array('5','5989','68fbaf6e36ff53a9b9d10457','4','0.5','11.5','Clearance Credit - Feb26 RNBW'),
array('5','6734','68fbaf6e36ff53a9b9d10457','17','0.5','11.5','Clearance Credit - Feb26 RNBW'),
array('5','13154','68fbaf6e36ff53a9b9d10457','4','0.5','10','Clearance Credit - Feb26 RNBW'),
array('5','23460','68fbaf6e36ff53a9b9d10457','33','0.5','11.5','Clearance Credit - Feb26 RNBW'),
array('3','12227','68fbaf6e36ff53a9b9d10457','6','0.5','7','Clearance Credit - Feb26 Selfies'),
array('4','22821','68fbaf6e36ff53a9b9d10457','15','0.5','8','Clearance Credit - Feb26 Selfies'),
array('6','23651','68fbaf6e36ff53a9b9d10457','18','0.5','4.5','Clearance Credit - Feb26 Selfies'),
array('7','14450','68fbaf6e36ff53a9b9d10457','6','0.5','16','Clearance Credit - Feb26 Selfies'),
array('7','14462','68fbaf6e36ff53a9b9d10457','19','0.5','7','Clearance Credit - Feb26 Selfies'),
array('7','14464','68fbaf6e36ff53a9b9d10457','24','0.5','7','Clearance Credit - Feb26 Selfies'),
array('7','14465','68fbaf6e36ff53a9b9d10457','13','0.5','7','Clearance Credit - Feb26 Selfies'),
array('8','14163','68fbaf6e36ff53a9b9d10457','24','0.5','3.25','Clearance Credit - Feb26 Selfies'),
array('10','13140','68fbaf6e36ff53a9b9d10457','7','0.5','32','Clearance Credit - Feb26 Selfies'),
array('11','30901','68fbaf6e36ff53a9b9d10457','13','0.5','3.25','Clearance Credit - Feb26 Selfies'),
array('12','23007','68fbaf6e36ff53a9b9d10457','8','0.5','7','Clearance Credit - Feb26 Selfies'),
array('12','23358','5dd6f16e30f17c083f6e280f','27','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('12','24384','5dd6f16e30f17c083f6e280f','6','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('12','31073','5dd6f16e30f17c083f6e280f','25','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('12','37684','5dd6f16e30f17c083f6e280f','31','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('13','23518','5dd6f16e30f17c083f6e280f','5','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('13','31069','5dd6f16e30f17c083f6e280f','25','0.5','15','Clearance Credit - Feb26 Smoken Promises'),
array('12','36990','68cdcf401c44c0b22a777c7d','81','0.5','18','Clearance Credit - Feb26 Status'),
array('12','36991','68cdcf401c44c0b22a777c7d','47','0.5','18','Clearance Credit - Feb26 Status'),
array('12','36992','68cdcf401c44c0b22a777c7d','33','0.5','18','Clearance Credit - Feb26 Status'),
array('12','36993','68cdcf401c44c0b22a777c7d','61','0.5','18','Clearance Credit - Feb26 Status'),
array('12','36994','68cdcf401c44c0b22a777c7d','60','0.5','18','Clearance Credit - Feb26 Status'),
array('13','36988','68cdcf401c44c0b22a777c7d','53','0.5','18','Clearance Credit - Feb26 Status'),
array('13','36989','68cdcf401c44c0b22a777c7d','66','0.5','18','Clearance Credit - Feb26 Status'),
array('13','36990','68cdcf401c44c0b22a777c7d','30','0.5','18','Clearance Credit - Feb26 Status'),
array('13','36991','68cdcf401c44c0b22a777c7d','18','0.5','18','Clearance Credit - Feb26 Status'),
array('13','36992','68cdcf401c44c0b22a777c7d','75','0.5','18','Clearance Credit - Feb26 Status'),
array('12','31512','68cdcf401c44c0b22a777c7d','6','0.5','9.5','Clearance Credit - Feb26 Sticky'),
array('12','31513','68cdcf401c44c0b22a777c7d','5','0.5','9.5','Clearance Credit - Feb26 Sticky'),





	
);
$pStoreId = null;
$pVendorId = null;
$po_name = 'Clearance Credit Request';
	
foreach($data as $td) {
	$storeid = $td[0];
	$productid = $td[1];
	$vendorid = $td[2];
	$qty = $td[3];
	$price = $td[4] * $td[5];
	$po_name = $td[6];
	$db = 'blaze' . $storeid;
	
	echo "<br>" . $pVendorId . "||" . $vendorid . "||" . $pStoreId . "||" . $storeid;
	if ($pVendorId != $vendorid || $pStoreId != $storeid) {
		echo ".... ADD";
		$rv = getRs("SELECT vendor_id, email FROM {$db}.vendor WHERE id = '{$vendorid}'");
		if ($v = getRow($rv)) {
			$vendor_id = $v['vendor_id'];
			$email = $v['email'];
		}
		$po_id = dbPut('po', array('store_id' => $storeid, 'po_event_status_id' => 6, 'po_status_id' => 1, 'admin_id' => 2, 'po_name' => $po_name, 'vendor_id' => $vendor_id, 'email' => $email, 'po_type_id' => 2, 'vendor_name' => dbFieldName('vendor', $vendor_id, 'name', $db), 'po_reorder_type_id' => 1, 'num_products' => 0, 'tax_rate' => 0));
		
		
	}
	$rp = getRs("SELECT * FROM {$db}.product WHERE product_id = '{$productid}'");
	if ($rpp = getRow($rp)) {
		dbPut('po_product', array('po_id' => $po_id, 'product_id' => $rpp['product_id'], 'blaze_id' => $rpp['id'], 'product_name' => $r['product_name'], 'category_id' => $rpp['category_id'], 'brand_id' => $rpp['brand_id'], 'par_level' => 0, 'reorder_level' => 0, 'inv_1' => 0, 'inv_2' => 0, 'on_order_qty' => 0, 'daily_sales' => 0, 'cost' => $price, 'cannabis_type' => nicefy($rpp['cannabisType']), 'flower_type' => nicefy($rpp['flowerType']), 'is_editable' => 1, 'is_non_conforming' => 0, 'is_tax' => ((strtolower($rpp['cannabisType']) != 'non_cannabis')?1:0), 'order_qty' => -1*$qty, 'received_qty' => -1*$qty));
	}
	$num_products++;
	$tqty = $tqty + $qty;
	dbUpdate('po', array('po_number' => (10000 + $po_id), 'num_products' => $num_products, 'num_orders' => $num_products, 'order_qty' => $tqty), $po_id);
	//$this->RecalculateDiscounts($po_id,true,$db);
	//dbUpdate('po', array('po_status_id' => 4), $po_id);
	
	$pStoreId = $storeid;
	$pVendorId = $vendorid;
}
echo '<hr />';
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();

?>