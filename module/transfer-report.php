<?php
include_once('_config.php');
$_ajax = getVarInt('_ajax');
$transfer_report_code = getVar('c');
if ($transfer_report_code) {
  $rt = getRs("SELECT transfer_report_id, params FROM transfer_report WHERE " . is_active() . " AND store_id = ? AND transfer_report_code = ?", array($_Session->store_id, $transfer_report_code));
  if ($t = getRow($rt)) {
    $_p = json_decode($t['params'], true);
    $_p['transfer_report_id'] = $t['transfer_report_id'];
    $_Session->admin_settings['_ds-transfer-report'] = $_p;
    setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($_Session->admin_settings), $_Session->admin_id));
    redirectTo('/transfer-report');
    exit();
  }
}

$restock_type_id = null;
$_hide_options = false;
$timespan = '';
$_ds = $_Session->GetTableDisplaySettings($module_code);
$_transfer_report_id = (isset($_ds['transfer_report_id']))?$_ds['transfer_report_id']:null;
$_restock_type_id = (isset($_ds['restock_type_id']))?$_ds['restock_type_id']:null;
$_category_id = (isset($_ds['category_id']))?$_ds['category_id']:null;
$_brand_id = (isset($_ds['brand_id']))?$_ds['brand_id']:null;
$_sort_by = (isset($_ds['sort_by']))?$_ds['sort_by']:null;
$disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:($_transfer_report_id?array():array(1,2));
include_once('inc/header.php');
?>


<form role="form" class="ajax-form display-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="transfer-report" />
<input type="hidden" name="transfer_report_id" id="transfer_report_id" value="<?php echo $_transfer_report_id; ?>" />
<input type="hidden" name="transfer_report_code" id="transfer_report_code" value="<?php echo $_Session->GetIdCode('transfer_report', $_transfer_report_id); ?>" />
<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body pb-0"<?php echo iif($_hide_options, ' style="display:none;"'); ?>>
			  <div class="panel-option pt-1 pb-1 pl-4">Report Options</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-clock mr-1"></i> Restock Type
                </div>
              </div>
              <?php
              if ($_transfer_report_id and $_restock_type_id) {
                echo '<input type="hidden" name="restock_type_id" value="' . $_restock_type_id . '" />                
                <div class="input-group-text"><b>' . dbFieldName('restock_type', $_restock_type_id) . '</b></div>';
              }
              else {
                echo displayKey('restock_type_id', $_restock_type_id); 
              }
              ?>
            </div>
          </div>
        </div>
			  <div class="panel-option mt-3 pt-1 pb-1 pl-4">Filters</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="ion-android-folder mr-1"></i> Category
                </div>
              </div>
              <?php echo displayKey('category_id', $_category_id, $_Session->db . '.category'); ?>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="ion-load-b mr-1"></i> Brand
                </div>
              </div>
              <?php echo displayKey('brand_id', $_brand_id, $_Session->db . '.brand'); ?>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-sort-alpha-down mr-1"></i> Sort By
                </div>
              </div>
              <select name="sort_by" class="form-control select2">
              <option value="1">Product Name</option>
              <option value="2"<?php echo iif($_sort_by == 2, ' selected'); ?>>SKU</option>
              </select>
            </div>
          </div>
        </div>
			  <div class="panel-option mt-3 pt-1 pb-1 pl-4">Disaggregate by</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12">

          <span class="nowrap"><input type="checkbox" value="1" id="disaggregate_1" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(1, $disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_1"><span class="m-l-5 m-r-10">Category</span></label></span>

          <span class="nowrap"><input type="checkbox" value="2" id="disaggregate_2" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(2, $disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_2"><span class="m-l-5 m-r-10">Brand</span></label></span>

          </div>
        </div>



        <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
          <hr class="m-0" />
          <div class="p-10">
            <div class="row">
              <div class="col-sm-6">
                <span id="status_table_display" class="status"></span>
              </div>
              <div class="col-sm-6 text-right">
                <?php if ($_transfer_report_id) {
                  echo '<button type="submit" class="btn btn-warning mt-0">Update</button>';
                }
                else {                  
                  echo '<button type="submit" class="btn btn-primary mt-0">Generate Transfer Report</button>';
                }
                ?>
              </div>
            </div>
          </div>
        </div>


        </div>

      </div>
</form>

<?php
if ($_transfer_report_id) {
$rt = getRs("SELECT t.transfer_report_id, t.num_products, t.num_transfers, t.date_created, t.admin_id, t.transfer_report_status_id, t.transfer_report_api_status_id, t.api_response, r.restock_type_id, r.restock_type_name, r.field_level FROM restock_type r INNER JOIN transfer_report t ON t.restock_type_id = r.restock_type_id WHERE " . is_enabled('t,r') . " AND t.transfer_report_id = ?", array($_transfer_report_id));
if ($t = getRow($rt)) {
$rs = $_Fulfillment->GetTransferReportProducts($_transfer_report_id, $_category_id, $_brand_id, $disaggregate_ids, $_sort_by);
$progress = $_Fulfillment->TransferReportProgress($_transfer_report_id);
?>

<div class="panel panel-default mb-3">
  <div class="panel-heading">
    <h4 class="panel-title">Fulfillment Progress. Generated on <?php echo getLongDate($t['date_created']) . ' by ' . getAdminName($t['admin_id']); ?></h4>
  </div>
  <div class="panel-body">
    <div class="progress progress-striped m-b-10">
      <div class="progress-bar fulfillment-progress-percent" style="width: <?php echo $progress['percent'] . '%'; ?>"><?php echo $progress['percent'] . '%'; ?></div>
    </div>
    <div class="fulfillment-progress mt-1"><?php echo $progress['response']; ?></div>
  </div>
</div>

<div class="panel pagination-inverse m-b-0 clearfix">
<form action="">
<table class="table table-bordered table-striped">
  <thead>
    <tr class="inverse">
      <th>Product</th>
      <th class="hidden-sm"<?php echo iif($t['restock_type_id'] != 1, ' colspan="2"'); ?>>Level</th>
      <th colspan="3" class="text-center hidden-sm">Inventory</th>
      <th colspan="3" class="text-center"><a href="" class="btn btn-dark text-white suggested-qty-all py-0 px-2"><b>Transfer Quantities</b></a></th>
    </tr>
  </thead>
  <tbody>
  <?php

  
  $first_run = true;
  $brand_id = $category_id = null;
  foreach($rs as $r) {
    if ($category_id != $r['category_id'] && in_array(1, $disaggregate_ids)) {
      $category_id = $r['category_id'];
      echo '
      <tr style="background:#daf3f1;">
        <th>' . $r['category_name'] . '</th>
        ' . iif($t['restock_type_id'] != 1, '<th class="hidden-sm">Par Level</th>') . '
        <th class="hidden-sm">' . $t['restock_type_name'] . '</th>
        <th class="hidden-sm">Fulfillment</th>
        <th class="hidden-sm">Main Vault</th>
        <th class="hidden-sm">Refresh</th>
        <th><a href="" data-ref="tc-' . $r['category_id'] . '" class="btn btn-default suggested-qty-category py-0 px-2"><b>Suggested</b></a></th>
        <th>Actual</th>
        <th>Submit</th>
      </tr>';
      $first_run = false;
    }
    if ($brand_id != $r['brand_id'] && in_array(2, $disaggregate_ids)) {
      $brand_id = $r['brand_id'];
      echo '
      <tr style="background:#fef2e1;">
        <th' . iif(in_array(1, $disaggregate_ids), ' colspan="9"') . '>' . $r['brand_name'] . '</th>
        ' . iif(!in_array(1, $disaggregate_ids), iif($t['restock_type_id'] != 1, '<th class="hidden-sm">Par Level</th>') . '
        <th class="hidden-sm">' . $t['restock_type_name'] . '</th>
        <th class="hidden-sm">Fulfillment</th>
        <th class="hidden-sm">Main Vault</th>
        <th class="hidden-sm">Refresh</th>
        <th><a href="" data-ref="tb-' . $r['brand_id'] . '" class="btn btn-default suggested-qty-brand py-0 px-2"><b>Suggested</b></a></th>
        <th>Actual</th>
        <th>Submit</th>') . '
      </tr>';
      $first_run = false;
    }
    if ($first_run) {
      echo '
      <tr class="inverse">
        <th></th>
        ' . iif($t['restock_type_id'] != 1, '<th class="hidden-sm">Par Level</th>') . '
        <th class="hidden-sm">' . $t['restock_type_name'] . '</th>
        <th class="hidden-sm">Fulfillment</th>
        <th class="hidden-sm">Main Vault</th>
        <th class="hidden-sm">Refresh</th>
        <th><a href="" data-ref="tb-' . $r['brand_id'] . '" class="btn btn-default suggested-qty-brand py-0 px-2"><b>Suggested</b></a></th>
        <th>Actual</th>
        <th>Submit</th>
      </tr>';
      $first_run = false;
    }
    $css = $icon = null;
    if ($r['transfer_qty'] > $r['inv_1']) {
      $css = 'has-error';
      $icon = '<span class="fa fa-times form-control-feedback"></span>';
    }
    else if ($r['transfer_qty'] and $r['transfer_qty'] < $r["suggested_qty"]) {
      $css = 'has-warning';
      $icon = '<span class="fa fa-exclamation-triangle form-control-feedback"></span>';
    }
    else if ($r['transfer_qty']) {
      $css = 'has-success';
      $icon = '<span class="fa fa-check-circle form-control-feedback"></span>';
    }
    echo '
    <tr class="product-' . $r['product_id'] . '">
    <td><span class="hidden-sm">' . $r['product_name'] . ' (</span>' . $r['sku'] . '<span class="hidden-sm">)</span></td>
    ' . iif($t['restock_type_id'] != 1, '<td class="hidden-sm">' . number_format($r['par_level']) . '</td>') . '
    <td class="hidden-sm">' . number_format($r['fulfillment_level']) . '</td>
    <td class="hidden-sm inv-2" id="inv_2_' . $r['transfer_report_product_id'] . '" data-sort="' . $r['inv_2'] . '">' . number_format($r['inv_2']) . '</td>
    <td class="hidden-sm inv-1" id="inv_1_' . $r['transfer_report_product_id'] . '" data-sort="' . $r['inv_1'] . '">' . number_format($r['inv_1']) . '</td>
    <td class="hidden-sm"><button type="button" class="btn btn-default btn-transfer-product-refresh" data-id="' . $r['product_id'] . '"><i class="fa fa-redo"></i></button></td>
    <td id="suggested_qty_' . $r['transfer_report_product_id'] . '" data-sort="' . $r['suggested_qty'] . '"><a href="" id="suggested_qty_' . $r['transfer_report_product_id'] . '" data-ref="transfer_qty_' . $r['transfer_report_product_id'] . '" class="btn btn-default suggested-qty tc-' . $r['category_id'] . ' tb-' . $r['brand_id'] . '">' . number_format($r['suggested_qty']) . '</a></td>
    <td style="width: 80px;">
      <div class="form-group has-feedback m-0 ' . $css . '">
        <input type="number" id="transfer_qty_' . $r['transfer_report_product_id'] . '" data-id="' . $r['transfer_report_product_id'] . '" data-code="' . $r['transfer_report_product_code'] . '" class="transfer-qty form-control" value="' . (($r['transfer_qty'] > 0)?number_format($r['transfer_qty']):'') . '"' . iif($r['api_success'], ' disabled') . ' />' . $icon . '
      </div>
    </td>
    <td><button type="button" class="btn btn-' . iif($r['api_success'], 'success', 'danger') . ' btn-transfer-product-api" data-id="' . $r['product_id'] . '"' . iif($r['api_success'], ' disabled') . '><i class="fa ' . iif($r['api_success'], 'fa-check', 'fa-arrow-right') . '"></i></button></td>
    </tr>';
  }
  ?>
  </tbody>
</table>
</form>
</div>

<div class="panel panel-default mt-3">
  <div class="panel-heading">
    <h4 class="panel-title">Fulfillment Progress</h4>
  </div>
  <div class="panel-body">
    <div class="progress progress-striped m-b-10">
      <div class="progress-bar fulfillment-progress-percent" style="width: <?php echo $progress['percent'] . '%'; ?>"><?php echo $progress['percent'] . '%'; ?></div>
    </div>
    <div class="fulfillment-progress mt-1"><?php echo $progress['response']; ?></div>
  </div>
  <div class="panel-footer text-right">
    <div class="status" id="status_api"><?php echo $t['api_response']; ?></div>
    <?php echo iif($t['transfer_report_api_status_id'] != 1, '<button type="button" class="btn hide btn-lg btn-danger btn-transfer-report-api">Submit Transfer</button>'); ?>
  </div>
</div>

<?php
}
else {
  echo '<div class="alert alert-danger">You must select Restock Type in order to generate a report</div>';
}
}

include_once('inc/footer.php'); 
?>