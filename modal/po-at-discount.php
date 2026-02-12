<?php
require_once('../_config.php');
$po_code = getVar('c');
$po_discount_code = getVar('d');
$type = $po_discount_name = $discount_rate = $discount_amount = null;
$rp = getRs("SELECT po_id FROM po WHERE po_code = ?", array($po_code));
if ($p = getRow($rp)) {
$rs = getRs("SELECT * FROM po_discount WHERE po_discount_code = ?", array($po_discount_code));
if ($r = getRow($rs)) {
    $po_discount_name = $r['po_discount_name'];
    $discount_rate = $r['discount_rate'];
    $discount_amount = $r['discount_amount'];
    if ($r['discount_rate']) $type = 1;
    else if ($r['discount_amount']) $type = 2;
}
echo '
<input type="hidden" name="po_code" value="' . $po_code . '" />
<input type="hidden" name="po_discount_code" value="' . $po_discount_code . '" />
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Discount Description:</div>
<div class="col-sm-10"><input type="text" name="po_discount_name" value="' . $po_discount_name . '" class="form-control" placeholder="Optional ..." /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Discount Type:</div>
<div class="col-sm-5"><select class="form-control select2 calc-type" name="discount_type" id="discount_type">
<option value="">- None -</option>
<option value="2"' . iif($type == 2, ' selected') . '>Amount</option>
</select></div>
<div class="col-sm-5">

<div class="input-group calc-rate"' . iif($type != 1, ' style="display:none;"') . '><input type="number" step=".01" class="form-control" name="discount_rate" value="' . iif($discount_rate, number_format($discount_rate, 2, '.', ''), '') . '" placeholder="Enter Percentage" /><div class="input-group-append"><div class="input-group-text">%</div></div></div>

<div class="input-group calc-amount"' . iif($type != 2, ' style="display:none;"') . '><div class="input-group-prepend"><div class="input-group-text">$</div></div><input type="number" step=".01" class="form-control" name="discount_amount" value="' . iif($discount_amount, number_format($discount_amount, 2, '.', ''), '') . '" placeholder="Enter Amount" /></div>

</div>
</div>
';
}
else {
    echo '<div class="alert alert-danger">Purchase order not found</div>';
}
?>