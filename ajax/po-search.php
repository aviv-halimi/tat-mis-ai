<?php
require_once('../_config.php');
$kw = toLink(getVar('kw'), 50, false, ' ');
$tbl = getVar('tbl');
$ret = array();

if (strlen($kw)) {
    $a_kw = explode(' ', $kw);
    $params = array();
    $sql = "SELECT p.*, s.po_status_name, v.vendor_name FROM po_status s RIGHT JOIN (po p INNER JOIN vendor v ON v.vendor_id = p.vendor_id) ON s.po_status_id = p.po_status_id WHERE " . is_enabled('p,v') . " AND p.po_status_id = 3 AND p.po_event_status_id <> 2";
    foreach($a_kw as $k) {
        $sql .= " AND (p.po_number LIKE ? OR v.vendor_name LIKE ?)";
        array_push($params, '%' . $k . '%');
        array_push($params, '%' . $k . '%');
    }
	$sql .= " ORDER BY p.po_number, v.vendor_name LIMIT 50";
    $rs = getRs($sql, $params);
    foreach($rs as $r) {
		array_push($ret, array('id' => $r['po_code'], 'name' => $r['po_number'] . ', ' . $r['vendor_name'] . ' (' . currency_format($r['total']) . '), ' . $r['po_status_name'] . ' - ID: ' . $r['po_id']));
	}
}


echo json_encode(array('results' => $ret));
?>