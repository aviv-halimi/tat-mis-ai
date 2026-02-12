<?php
require_once('../_config.php');
$po_code = getVar('c');
$rs = getRs("SELECT (CASE WHEN po_status_id = 1 THEN tax_amount ELSE r_tax_amount END) AS tax_amount, (CASE WHEN po_status_id = 1 THEN tax_rate ELSE r_tax_rate END) AS tax_rate FROM po WHERE po_code = ?", array($po_code));
if ($r = getRow($rs)) {

if ($r['tax_amount']) $type = 2;
else  $type = 1;
echo '
<input type="hidden" name="po_code" value="' . $po_code . '" />
<div class="row input-row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Tax Type:</div>
<div class="col-sm-5"><select class="form-control select2 calc-type" name="tax_type" id="tax_type">
<option value="1"' . iif($type == 1, ' selected') . '>Percent</option>
<option value="2"' . iif($type == 2, ' selected') . '>Amount</option>
</select></div>
<div class="col-sm-5">

<div class="input-group calc-rate"' . iif($type != 1, ' style="display:none;"') . '><input type="number" class="form-control" name="tax_rate" placeholder="' . iif($r['tax_rate'], number_format($r['tax_rate'], 2, '.', ''), '') . '" disabled /><div class="input-group-append"><div class="input-group-text">%</div></div></div>

<div class="input-group calc-amount"' . iif($type != 2, ' style="display:none;"') . '><div class="input-group-prepend"><div class="input-group-text">$</div></div><input type="number" step=".01" class="form-control" name="tax_amount" value="' . iif($r['tax_amount'], number_format($r['tax_amount'], 2, '.', ''), '') . '" placeholder="Enter Amount" /></div>

</div>
</div>
';
}
else {
    echo '<div class="alert alert-danger">Purchase order not found</div>';
}
?>