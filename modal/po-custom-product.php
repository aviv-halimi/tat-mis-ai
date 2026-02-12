<?php
require_once('../_config.php');
$po_code = getVar('c');
$po_product_code = getVar('a');
$is_existing_product = getVarInt('d');
$rs = getRs("SELECT p.po_id, p.vendor_id, v.id FROM po p left join {$_Session->db}.vendor v on v.vendor_id = p.vendor_id WHERE po_code = ?", array($po_code));
if ($r = getRow($rs)) {
    $po_product_id = $po_product_name = $brand_id = $category_id = $flower_type = null;
    $is_tax = 1;
    if (strlen($po_product_code)) {
        $rp = getRs("SELECT po_product_id, po_product_name, product_id, is_tax, flower_type, brand_id, category_id FROM po_product WHERE po_id = ? AND po_product_code = ?", array($r['po_id'], $po_product_code));
        if ($p = getRow($rp)) {
            $po_product_id = $p['po_product_id'];
            $po_product_name = $p['po_product_name'];
            $is_existing_product = $p['product_id']?1:0;
            $is_tax = $p['is_tax'];
            $flower_type = $p['flower_type'];
            $brand_id = $p['brand_id'];
            $category_id = $p['category_id'];
        }
    }
    echo '
    <input type="hidden" name="po_code" value="' . $po_code . '" />
    <input type="hidden" name="po_product_code" value="' . $po_product_code . '" />
    <input type="hidden" name="is_existing_product" value="' . $is_existing_product . '" />
    ';
    if ($is_existing_product) {
        echo  '<div class="row form-input-flat mb-2">
        <div class="col-sm-3 col-form-label">Select Product:' . $r['id'] . '</div>
        <div class="col-sm-9"><select name="product_id" class="form-control select2 po-existing-product"><option value="">- Select Product -</option>
        ';
        $rp = getRs("SELECT product_id, name, sku FROM {$_Session->db}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' AND (vendor_id = ? OR secondaryVendors LIKE '%{$r['id']}%') ORDER BY TRIM(name), TRIM(sku)", array($r['vendor_id']));
        foreach($rp as $p) {
        echo '<option value="' . $p['product_id'] . '">' . $p['name'] . ' (' . $p['sku'] . ')</option>';
        }
        echo '</select></div></div>';
    }
    else {
        echo  '
        <div class="row form-input-flat mb-2">
            <div class="col-sm-3 col-form-label">Product Name / Description:</div>
            <div class="col-sm-9"><input type="text" class="form-control" name="po_product_name" value="' . $po_product_name . '" /></div>
        </div>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-3 col-form-label">Category:</div>
            <div class="col-sm-9">' . displayKey('category_id', $category_id, $_Session->db . '.category', null, 'Select', 'name') . '</div>
        </div>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-3 col-form-label">Brand:</div>
            <div class="col-sm-9">' . displayKey('brand_id', $brand_id, $_Session->db . '.brand', null, 'Select', 'name') . '</div>
        </div>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-3 col-form-label">Flower Type:</div>
            <div class="col-sm-9"><select name="flower_type" class="form-control select2">';
            $flower_types = $_Session->GetSetting('flower-type');
            $rf = explode(PHP_EOL, $flower_types);
            foreach($rf as $f) {
            echo '<option' . iif($flower_type == $f, ' selected') . '>' . $f . '</option>';
            }
            echo '</select></div>
        </div>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-3 col-form-label"></div>
            <div class="col-sm-9">
            <input type="checkbox" value="1" id="is_tax" name="is_tax" data-render="switchery" data-theme="primary"' . iif($is_tax == '1', ' checked') . ' />
            <label for="is_tax"><span class="m-l-5 m-r-10">Taxable</span></label></div>
        </div>';
    }
}
else {
    echo '<div class="alert alert-danger">PO not found</div>';
}
?>