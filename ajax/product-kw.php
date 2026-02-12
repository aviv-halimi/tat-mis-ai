<?php
require_once ('../_config.php');

function ProductSuggestions($kw) {
    $ret = '';
    $kw = trim($kw);
    if (strlen($kw) > 1) {
        $arr_kw = explode(' ', $kw);
        $params = array();
        //$sql = "SELECT DISTINCT d.po_product_name FROM po_product d WHERE d.product_id IS NULL AND " . is_enabled('d');
		$sql = "SELECT d.po_product_name FROM (SELECT distinct po_product_name FROM theartisttree.po_product WHERE isnull(product_id) AND is_enabled=1 ORDER BY po_product_id DESC LIMIT 1000) AS d WHERE 1=1 ";
        foreach($arr_kw as $k) {
            /*$sql .= " AND (d.po_product_name LIKE ? OR d.po_product_name LIKE ? OR d.po_product_name LIKE ?)";
            array_push($params, $k . '%');
            array_push($params, '%' . $k . '%');
            array_push($params, '%' . $k);
			*/
			$sql .= " AND d.po_product_name LIKE ?";
            array_push($params, '%' . $k . '%');
        }
        $sql .= " ORDER BY d.po_product_name LIMIT 10";
        $rs = getRs($sql, $params);
        foreach($rs as $r) {
            $ret .= '<li><a href="" class="link">' . hiliteKw($kw, $r['po_product_name']) . '</a></li>';
        }
        if (strlen($ret) == 0) {
            $ret = '<li class="no-results"><b>No products found matching your keywords.</b> Please refine your search. <span style="text-decoration:underline;cursor:pointer;" class="closer">Close</span></li>';
        }
    }
    return $ret;		
}

function hiliteKw2($kw, $str) {
    return $str;
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('ret' => ProductSuggestions(getVar('kw'))));
exit();
					
?>