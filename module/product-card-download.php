<?php
$product_card_code = getVar('c');
$rs = getRs("SELECT product_card_id, product_card_code FROM product_card WHERE " . is_enabled() . " AND product_card_code = ?", array($product_card_code));
if ($r = getRow($rs)) {
    $pc_filename = $r['product_card_id'] . '-' . $r['product_card_code'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="ProductCard_' . $r['product_card_code'] . '.' . getExt($pc_filename));
    header('Cache-Control: max-age=0');
    readfile(MEDIA_PATH . 'product_card/' . $pc_filename);
}
else {
    echo 'Not found';
}
?>