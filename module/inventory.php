<?php
$footer = '<script language="javascript" type="text/javascript">
$(document).ready(function(e) {
  $("#po_type_id").on("change.select2", function(e) {
    if ($(this).val() == 2) $("#po_reorder_type_id").val(4).trigger("change");
  });
});
</script>';

include_once('_config.php');
$_ajax = getVarInt('_ajax');
$po_code = getVar('c');
$_qty = 2;
$_days = 60;
$date = null;

$_ds = $_Session->GetTableDisplaySettings($module_code);
$_qty = (isset($_ds['qty']))?$_ds['qty']:2;
$_days = (isset($_ds['days']))?$_ds['days']:60;
$_date = (isset($_ds['date']))?$_ds['date']:null;

$params = array($_qty, $_days, $_qty, $_days, $_Session->store_id, $_days);
if ($_date) {
    $params = array($_qty, $_days, $_qty, $_days, $_Session->store_id, toMySqlDT($_date), $_days, toMySqlDT($_date));
}

$rs = getRs("SELECT p.product_id, p.name, SUM(CASE WHEN i.qty > ? THEN 1 END) AS inv_days, CASE WHEN ? > 0 THEN (SUM(CASE WHEN i.qty > ? THEN 1 END) / ? * 100) END AS inv_days_percent FROM inventory i INNER JOIN {$_Session->db}.product p ON p.product_id = i.product_id WHERE store_id = ? AND date_inventory >= DATE_SUB(" . iif($_date, "?", "CURDATE()") . ", INTERVAL ? DAY)" . iif($_date, " AND date_inventory <= ?") . " GROUP BY p.name ORDER BY p.product_id, p.name", $params);

include_once('inc/header.php');

?>

<form role="form" class="ajax-form display-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="inventory" />
<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body pb-0">        
        <div class="row form-input-flat mb-3">
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-pen mr-1"></i> Stock Days
                </div>
              </div>
              <?php
              echo '<input type="text" name="days" class="form-control" value="' . $_days . '" placeholder="Optional ..." />';
              ?>
            </div>
          </div>
          <div class="col-sm-4 date">
            <div class="input-group date datepicker" data-date-format="mm/dd/yyyy">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-calendar mr-1"></i> Starting On
                </div>
              </div>
              <input type="text" class="form-control" placeholder="Default is today ..." name="date" value="<?php echo $_date; ?>" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-pen mr-1"></i> Minumum Qty
                </div>
              </div>
              <?php
              echo '<input type="text" name="qty" class="form-control" value="' . $_qty . '" placeholder="Optional ..." />';
              ?>
            </div>
          </div>
        </div>
        </div>

        <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
          <hr class="m-0" />
          <div class="p-10">
            <div class="row">
              <div class="col-sm-6">
                <span id="status_table_display" class="status"></span>
              </div>
              <div class="col-sm-6 text-right form-btns">
                  <button type="submit" class="btn btn-warning mt-0">Update Display</button>
              </div>
            </div>
          </div>
        </div>


        </div>

      </div>
</form>

<?php

echo '
<div class="panel pagination-inverse m-b-0 clearfix">
<table id="t_inventory" class="table table-analytics table-bordered table-hover">
<thead>
    <tr class="inverse">
        <th>Product</th>
        <th>Days of Inventory</th>
        <th>% Days of Inventory</th>
        <th></th>
    </tr>
</thead>
<tbody>';
foreach($rs as $r) {
    echo '<tr><td>' . $r['name'] . '</td><td>' . $r['inv_days'] . '</td><td>' . number_format($r['inv_days_percent'])  . '%</td><td><button type="button" class="btn btn-primary btn-dialog" data-url="inventory" data-id="' . $r['product_id'] . '" data-title="' . $r['name'] . '" data-hide-btns="true">Details</button></td></tr>';
}
echo '</tbody>
</table>
</div>';

include_once('inc/footer.php'); 
?>