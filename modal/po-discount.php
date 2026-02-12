<?php
require_once('../_config.php');
$po_code = getVar('c');
$rs = getRs("SELECT (CASE WHEN po_status_id = 1 THEN discount_rate ELSE r_discount_rate END) AS discount_rate, (CASE WHEN po_status_id = 1 THEN discount_amount ELSE r_discount_amount END) AS discount_amount, (CASE WHEN po_status_id = 1 THEN discount_name ELSE r_discount_name END) AS discount_name FROM po WHERE po_code = ?", array($po_code));
if ($r = getRow($rs)) {
$type = null;
if ($r['discount_rate']) $type = 1;
else if ($r['discount_amount']) $type = 2;
echo '
<input type="hidden" name="po_code" value="' . $po_code . '" />
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Discount Description:</div>
<div class="col-sm-10"><input type="text" name="discount_name" value="' . $r['discount_name'] . '" class="form-control" placeholder="Optional ..." /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Discount Type:</div>
<div class="col-sm-5"><select class="form-control select2 calc-type" name="discount_type" id="discount_type">
<option value="">- None -</option>
<option value="1"' . iif($type == 1, ' selected') . '>Percent</option>
<option value="2"' . iif($type == 2, ' selected') . '>Amount</option>
</select></div>
<div class="col-sm-5">

<div class="input-group calc-rate"' . iif($type != 1, ' style="display:none;"') . '><input type="number" step=".01" class="form-control" name="discount_rate" value="' . iif($r['discount_rate'], number_format($r['discount_rate'], 2, '.', ''), '') . '" placeholder="Enter Percentage" /><div class="input-group-append"><div class="input-group-text">%</div></div></div>

<div class="input-group calc-amount"' . iif($type != 2, ' style="display:none;"') . '><div class="input-group-prepend"><div class="input-group-text">$</div></div><input type="number" step=".01" class="form-control" name="discount_amount" value="' . iif($r['discount_amount'], number_format($r['discount_amount'], 2, '.', ''), '') . '" placeholder="Enter Amount" /></div>

</div>
</div>
';
}
else {
    echo '<div class="alert alert-danger">Purchase order not found</div>';
}
?>