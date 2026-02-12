<?php
include_once('_config.php');
if (isset($_archive)) $sql_archive = "(p.is_created = 1 AND p.is_transferred = 1 and p.date_created >= now()-interval 3 month)";
else $sql_archive = "(p.is_created = 0 OR p.is_transferred = 0)";

if (isset($_archive)) $sql_archive2 = "(q.is_created = 1 AND q.is_transferred = 1 and q.date_created >= now()-interval 3 month)";
else $sql_archive2 = "(q.is_created = 0 OR q.is_transferred = 0)";

$__store_id = $_vendor_name = $_po_status_id = null;
$_ds = $_Session->GetTableDisplaySettings($module_code);
$__store_id = (isset($_ds['store_id']))?$_ds['store_id']:null;
$_vendor_name = (isset($_ds['vendor_name']))?$_ds['vendor_name']:null;
$_po_status_id = (isset($_ds['po_status_id']))?$_ds['po_status_id']:null;

$params = array();
if ($__store_id) array_push($params, $__store_id);
if ($_vendor_name) array_push($params, $_vendor_name);
if ($_po_status_id) array_push($params, $_po_status_id);

//$rs = getRs("SELECT s.store_name, p.po_product_id, p.po_product_name, p.is_created, p.is_transferred, po.po_code, po.vendor_name, po.date_ordered, t.po_status_name FROM store s RIGHT JOIN (po_status t RIGHT JOIN (po INNER JOIN po_product p ON p.po_id = po.po_id) ON t.po_status_id = po.po_status_id) ON po.store_id = s.store_id WHERE " . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive}" . iif($__store_id, " AND s.store_id = ?") . iif($_vendor_name, " AND po.vendor_name = ?") . iif($_po_status_id, " AND po.po_status_id = ?") . " ORDER BY po.date_ordered", $params);


$rs = getRs("
	SELECT s.store_name, p.po_product_id, p.po_product_name, p.is_created, p.is_transferred, po.po_code, po.vendor_name, po.date_ordered, t.po_status_name, IFNULL(coalesce(p.paid, p.price, p.cost),0) AS unitPrice, p.category_id, s.db
		FROM store s 
		RIGHT JOIN (po_status t 
		RIGHT JOIN (po 
			INNER JOIN (SELECT * from po_product q WHERE ifnull(q.order_qty,0) + ifnull(q.received_qty,0) <> 0 AND " . is_enabled('q') . " AND q.product_id IS NULL AND {$sql_archive2} ) as p ON p.po_id = po.po_id)
			ON t.po_status_id = po.po_status_id) ON po.store_id = s.store_id 
		WHERE " . is_enabled('po') . " AND po.po_status_id >= 3 " . iif($__store_id, " AND s.store_id = ?") . iif($_vendor_name, " AND po.vendor_name = ?") . iif($_po_status_id, " AND po.po_status_id = ?") . " ORDER BY po.date_ordered", $params);

include_once('inc/header.php');
?>

<form role="form" class="ajax-form display-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="<?php echo $module_code; ?>" />
<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body pb-0">
		<div class="panel-option pt-1 pb-1 pl-4">Filters</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Store
                </div>
              </div>
              <select name="store_id" class="form-control select2">
              <option value="">- All -</option>
              <?php
              /*$rt = getRs("SELECT s.store_id, s.store_name, SUM(CASE WHEN p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive} THEN 1 ELSE 0 END) AS num_products FROM po_product p RIGHT JOIN (po RIGHT JOIN store s ON s.store_id = po.store_id) ON p.po_id = po.po_id  WHERE (" . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive}) OR s.store_id = ? GROUP BY s.store_id, s.store_name ORDER BY s.store_id", array($_store_id));
              foreach($rt as $t) {
                echo '<option value="' . $t['store_id'] . '"' . iif($__store_id == $t['store_id'], ' selected') . '>' . $t['store_name'] . ' (' . $t['num_products']  . ')</option>';
              }
			  */
			  $rt = getRs("SELECT s.store_id, s.store_name FROM store s ORDER BY s.store_id");
              foreach($rt as $t) {
                echo '<option value="' . $t['store_id'] . '"' . iif($__store_id == $t['store_id'], ' selected') . '>' . $t['store_name'] . ' </option>';
              }
              ?>
              </select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Vendor
                </div>
              </div>
              <select name="vendor_name" class="form-control select2">
              <option value="">- All -</option>
              <?php
              /*$rt = getRs("SELECT po.vendor_name, SUM(CASE WHEN p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive} THEN 1 ELSE 0 END) AS num_products FROM po INNER JOIN po_product p ON p.po_id = po.po_id WHERE (" . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive}) OR po.vendor_name = ? GROUP BY po.vendor_name ORDER BY po.vendor_name", array($_vendor_name));
              foreach($rt as $t) {
                echo '<option value="' . $t['vendor_name'] . '"' . iif($_vendor_name == $t['vendor_name'], ' selected') . '>' . $t['vendor_name'] . ' (' . $t['num_products']  . ')</option>';
              }*/
			  $rt = getRs("SELECT po.vendor_name, SUM(CASE WHEN p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive} THEN 1 ELSE 0 END) AS num_products FROM po INNER JOIN po_product p ON p.po_id = po.po_id WHERE (" . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive}) OR po.vendor_name = ? GROUP BY po.vendor_name ORDER BY po.vendor_name", array($_vendor_name));
              foreach($rt as $t) {
                echo '<option value="' . $t['vendor_name'] . '"' . iif($_vendor_name == $t['vendor_name'], ' selected') . '>' . $t['vendor_name'] . ' (' . $t['num_products']  . ')</option>';
              }
              ?>
              </select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Status
                </div>
              </div>
              <select name="po_status_id" class="form-control select2">
              <option value="">- All -</option>
              <?php
              /*$rt = getRs("SELECT s.po_status_id, s.po_status_name, SUM(CASE WHEN p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive} THEN 1 ELSE 0 END) AS num_products FROM po_product p RIGHT JOIN (po RIGHT JOIN po_status s ON s.po_status_id = po.po_status_id) ON p.po_id = po.po_id  WHERE (" . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive}) OR s.po_status_id = ? GROUP BY s.po_status_id, s.po_status_name ORDER BY s.po_status_id", array($_po_status_id));
              foreach($rt as $t) {
                echo '<option value="' . $t['po_status_id'] . '"' . iif($_po_status_id == $t['po_status_id'], ' selected') . '>' . $t['po_status_name'] . ' (' . $t['num_products']  . ')</option>';
              */
				$rt = getRs("SELECT s.po_status_id, s.po_status_name from po_status s ORDER BY s.po_status_id");
              foreach($rt as $t) {
                echo '<option value="' . $t['po_status_id'] . '"' . iif($_po_status_id == $t['po_status_id'], ' selected') . '>' . $t['po_status_name'] . '</option>';  
				  }
              ?>
              </select>
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
                <button type="submit" class="btn btn-primary mt-0">Update</button>
              </div>
            </div>
          </div>
        </div>


        </div>

      </div>
</form>

<?php

if (sizeof($rs)) {
?>

<div class="panel pagination-inverse m-b-0 clearfix">
<form action="">
<table class="table table-bordered table-striped dtable">
    <thead>
        <tr class="inverse">
            <th>Store</th>
            <th>Vendor</th>
            <th>Item Name</th>
            <th>Date Order Sent</th>
            <th>PO Status</th>
			<th>Category</th>
			<th>Wholesale Price</th>
			<th>Markup</th>.
			<th>SalePrice</th>
            <th>Created</th>
            <th>Transferred</th>
        </tr>
    </thead>
    <tbody>
    <?php
    foreach($rs as $r) {
		$tmarkup = 0;
		$category_name = '(N/A)';
		if (isset($r['category_id']) and $r['category_id'] > 0 and isset($r['unitPrice'])) {
			$rm = getRs("SELECT * from {$r['db']}.category where category_id = {$r['category_id']}");
			if ($m = getRow($rm)) {
				$category_name = $m['name'];
				if (isset($m['markup']) AND $m['markup'] > 0) {
					$tmarkup = $m['markup'];
				} else { 
					$rmc = getRs("SELECT * from {$r['db']}.markup where cogs <= {$r['unitPrice']} order by cogs desc limit 1");
					$tmarkup = ($rmc = getRow($rmc))?$rmc['markup']:0;
					
				}
			}
		}
			
		
	
		
		$salePrice = ($r['unitPrice'] < .1)?0:ceil($r['unitPrice'] * $tmarkup * 4)/4;
        echo '<tr>
        <td style="text-align:center;" >' . $r['store_name'] . '</td>
        <td style="text-align:center;" >' . $r['vendor_name'] . '</td>
        <td data-sort="' . $r['po_status_name'] . '"><a href="/po/' . $r['po_code'] . '" target="_blank">' . $r['po_product_name'] . '</a></td>
        <td  style="text-align:center;" data-sort="' . $r['date_ordered'] . '">' . getShortDate($r['date_ordered']). '</td>
        <td style="text-align:center;" >' . $r['po_status_name'] . '</td>
		<td style="text-align:center;" >' . $category_name . '</td>
		<td style="text-align:center;">' . number_format($r['unitPrice'], 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($tmarkup, 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($salePrice, 2, '.', '') . '</td>
        <td style="text-align:center;" data-sort="' . $r['is_transferred'] .'"><span class="nowrap po-product-created"><input data-type="created" data-id="' . $r['po_product_id'] . '" type="checkbox" value="' . $r['po_product_id'] . '" id="created_' . $r['po_product_id'] . '" name="disaggregate_ids[]" data-render="switchery" data-theme="info"' . iif($r['is_created'], ' checked') .' />
        <label for="disaggregate_1"><span></span></label></span></td>
        <td style="text-align:center;"  data-sort="' . $r['is_transferred'] .'"><span class="nowrap po-product-transferred"><input data-type="transferred" data-id="' . $r['po_product_id'] . '" type="checkbox" value="' . $r['po_product_id'] . '" id="transferred_' . $r['po_product_id'] . '" name="disaggregate_ids[]" data-render="switchery" data-theme="info"' . iif($r['is_transferred'], ' checked') .' />
        <label for="disaggregate_1"><span></span></label></span></td>
        </tr>';
    }
    ?>
    </tbody>
</table>
</form>
</div>

<?php
}
else {
    echo '<div class="alert alert-danger alert-bordered text-lg">
    No products found
</div>';
}
include_once('inc/footer.php'); 
?>