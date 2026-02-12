<?php
require_once('../_config.php');
$kw = getVar('kw');
$ret = array();

$nabis_vendor_id = $_Session->StoreSetting('nabis_vendor_id');

if ($nabis_vendor_id and strlen($kw)) {
    $a_kw = explode(' ', $kw);
    $params = array($nabis_vendor_id);
	$rv = getRs("SELECT id FROM {$_Session->db}.vendor WHERE vendor_id = ?",$params);
	if ($rvv = getRow($rv)) {
		$vendorid = $rvv['id'];
	}
	
    $sql = "SELECT p.product_id, p.sku, p.name FROM {$_Session->db}.product p WHERE (p.vendor_id = ? OR secondaryVendors LIKE '%{$vendorid}%') AND deleted = '' AND Active = '1' AND " . is_enabled('p');
    foreach($a_kw as $k) {
        $sql .= " AND (p.sku LIKE ? OR p.name LIKE ?)";
        array_push($params, '%' . $k . '%');
        array_push($params, '%' . $k . '%');
    }
	$sql .= " ORDER BY p.name LIMIT 50";
    $rs = getRs($sql, $params);
    foreach($rs as $r) {
		array_push($ret, array('id' => $r['product_id'], 'text' => $r['sku'] . ' - ' . $r['name'], 'sku' => $r['sku'], 'name' => $r['name']));
	}
}


echo json_encode(array('results' => $ret));
?>