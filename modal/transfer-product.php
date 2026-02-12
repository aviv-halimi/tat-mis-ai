<?php
require_once('../_config.php');
$transfer_product_id = getVarNum('id');
$product_id = $product_name = $from_product_batch_location_id = $to_product_batch_location_id = $qty = $description = null;

$rs = getRs("SELECT product_batch_location_id, product_batch_location_type_id FROM {$_Session->db}.product_batch_location WHERE product_batch_location_type_id IN (1,2)");
foreach($rs as $r) {
  if ($r['product_batch_location_type_id'] == 1) {
    $from_product_batch_location_id = $r['product_batch_location_id'];
  }
  else if ($r['product_batch_location_type_id'] == 2) {
    $to_product_batch_location_id = $r['product_batch_location_id'];
  }
}

if ($transfer_product_id) {
  $rs = getRs("SELECT * FROM transfer_product WHERE transfer_product_id = ?", array($transfer_product_id));
  if ($r = getRow($rs)) {
    echo '
    <div class="row m-b-10">
      <div class="col-sm-2">Product</div>
      <div class="col-sm-10">' . $r['product_name'] . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">Transfer Amount</div>
      <div class="col-sm-10"><b>' . number_format($r['qty']) . '</b></div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">From Location</div>
      <div class="col-sm-10">' . $r['from_product_batch_location_name'] . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">To Location</div>
      <div class="col-sm-10">' . $r['to_product_batch_location_name'] . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">Notes</div>
      <div class="col-sm-10">' . nl2br($r['description']) . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">By Admin</div>
      <div class="col-sm-10">' . getAdminName($r['admin_id']) . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">Response</div>
      <div class="col-sm-10"><span class="alert alert-' . iif($r['api_success'], 'success', 'danger') . '"><i class="fa fa-' . iif($r['api_success'], 'check', 'times') . '"></i> ' . $r['response'] . '</span></div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">Transfer Date</div>
      <div class="col-sm-10">' . getLongDate($r['date_created']) . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">API Params</div>
      <div class="col-sm-10">' . print_r($r['params'], true) . '</div>
    </div>
    <div class="row m-b-10">
      <div class="col-sm-2">API Response</div>
      <div class="col-sm-10">' . print_r($r['api_response'], true) . '</div>
    </div>';
    exit();
  }
}
echo '
<div class="row m-b-10">
  <div class="col-sm-2">Product</div>
  <div class="col-sm-10"><select name="product_id" class="form-control select2 transfer-product"><option value="">- Select Product -</option>
  ';
  $rp = getRs("SELECT product_id, name, sku FROM {$_Session->db}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' ORDER BY TRIM(name), TRIM(sku)");
  foreach($rp as $p) {
    echo '<option value="' . $p['product_id'] . '">' . $p['name'] . ' (' . $p['sku'] . ')</option>';
  }
  echo '</select>
  <div id="status_transfer_product_inventory"></div>
  <div class="transfer-product-inventory"></div>
  </div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2">Transfer Amount</div>
  <div class="col-sm-10"><input type="number" class="form-control" name="qty" value="' . $qty . '" placeholder="Enter Quantity" /></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2">From Location</div>
  <div class="col-sm-10">' . dboDropdown($_Session->db . '.product_batch_location', $from_product_batch_location_id, '', 'from_product_batch_location_id', 'product_batch_location_id', 'product_batch_location_name') . '</div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2">To Location</div>
  <div class="col-sm-10">' . dboDropdown($_Session->db . '.product_batch_location', $to_product_batch_location_id, '', 'to_product_batch_location_id', 'product_batch_location_id', 'product_batch_location_name') . '</div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2">Notes</div>
  <div class="col-sm-10"><textarea name="description" class="form-control" rows="3" placeholder="Notes ...">' . $description . '</textarea></div>
</div>';
?>