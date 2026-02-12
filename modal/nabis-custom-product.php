<?php
require_once('../_config.php');
$po_code = getVar('c');
$nabis_product_id = getVar('a');
$rs = getRs("SELECT *, COALESCE(price, pricePerUnit) AS price FROM nabis_product WHERE nabis_product_id = ?", array($nabis_product_id));
if ($r = getRow($rs)) {
    echo '
    <input type="hidden" name="nabis_product_id" value="' . $nabis_product_id . '" />
	<div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">NABIS Product:</div>
        <div class="col-sm-9"><div class="form-control"><b>' . $r['lineItemSkuCode'] . ' - ' . $r['lineItemSkuName'] . '</b></div></div>
    </div>

    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Product Name / Description:</div>
        <div class="col-sm-9"><input type="text" class="form-control" name="product_name" value="' . $r['product_name'] . '" /></div>
    </div>
    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Category:</div>
        <div class="col-sm-9">' . displayKey('category_id', $r['category_id'], $_Session->db . '.category', null, 'Select', 'name') . '</div>
    </div>
    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Brand:</div>
        <div class="col-sm-9">' . displayKey('brand_id', $r['brand_id'], $_Session->db . '.brand', null, 'Select', 'name') . '</div>
    </div>
    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Flower Type:</div>
        <div class="col-sm-9"><select name="flower_type" class="form-control select2">';
        $flower_types = $_Session->GetSetting('flower-type');
        $rf = explode(PHP_EOL, $flower_types);
        foreach($rf as $f) {
        echo '<option' . iif($r['flower_type'] == $f, ' selected') . '>' . $f . '</option>';
        }
        echo '</select></div>
    </div>
    
    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Price:</div>
        <div class="col-sm-9"><div class="input-group"><div class="input-group-prepend"><div class="input-group-text">$</div></div><input id="price" name="price" type="text" class="form-control" placeholder="" value="' . (($r['price'])?number_format($r['price'], 2):null) . '" /></div></div>
    </div>
    <div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label"></div>
        <div class="col-sm-9">
        <input type="checkbox" value="1" id="is_tax" name="is_tax" data-render="switchery" data-theme="primary"' . iif($r['is_tax'], ' checked') . ' />
        <label for="is_tax"><span class="m-l-5 m-r-10">Taxable</span></label></div>
    </div>';
}
else {
    echo '<div class="alert alert-danger">Product not found</div>';
}
?>