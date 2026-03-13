?php
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
	SELECT s.store_name, p.po_product_id, p.po_product_name, p.is_created, p.is_transferred, po.po_code, po.vendor_name, po.date_ordered, t.po_status_name, IFNULL(coalesce(p.paid, p.price, p.cost),0) AS unitPrice, p.category_id, s.db, p.brand_id, s.params, po.date_po_event_scheduled as sch_date
		FROM store s 
		RIGHT JOIN (po_status t 
		RIGHT JOIN (po 
			INNER JOIN (SELECT * from po_product q WHERE ifnull(q.order_qty,0) + ifnull(q.received_qty,0) <> 0 AND " . is_enabled('q') . " AND q.product_id IS NULL AND {$sql_archive2} ) as p ON p.po_id = po.po_id)
			ON t.po_status_id = po.po_status_id) ON po.store_id = s.store_id 
		WHERE " . is_enabled('po') . " AND po.po_status_id >= 3 AND po.po_type_id = 1 " . iif($__store_id, " AND s.store_id = ?") . iif($_vendor_name, " AND po.vendor_name = ?") . iif($_po_status_id, " AND po.po_status_id = ?") . " ORDER BY po.date_ordered", $params);

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
			  $rt = getRs("SELECT po.vendor_name, SUM(CASE WHEN p.product_id IS NULL AND po.po_status_id >= 3 AND {$sql_archive} THEN 1 ELSE 0 END) AS num_products FROM po INNER JOIN po_product p ON p.po_id = po.po_id WHERE (" . is_enabled('po,p') . " AND p.product_id IS NULL AND po.po_status_id >= 3 AND po.po_type_id = 1 AND {$sql_archive}) OR po.vendor_name = ? GROUP BY po.vendor_name ORDER BY po.vendor_name", array($_vendor_name));
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

<?php // product-coordination-2 module (enrichment preview enabled)

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
			<th>Delivery Date</th>
            <th>PO Status</th>
			<th>Markup Type</th>
			<th>Wholesale</th>
			<th>Markup</th>.
			<th>Default Price</th>
           <th>Davis Price</th>
			<th>Dixon Price</th>
            <th>Created</th>
            <th>Transferred</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    foreach($rs as $r) {
		$tmarkup = 0;
		$markup_type = '(N/A)';
		$tCategory_id = $r['category_id']?$r['category_id']:0;
		$tBrand_id = $r['brand_id']?$r['brand_id']:0;
		$tCogs = $r['unitPrice']?$r['unitPrice']:0;
		$tProductName = $r['po_product_name']?str_replace("'","",$r['po_product_name']):'noname';
		//Store Override
		$rm = getRs("SELECT m.*, b.name as brand, c.name as category
				FROM {$r['db']}.markup_override m
					LEFT JOIN {$r['db']}.brand b on b.brand_id = m.brand_id
					LEFT JOIN {$r['db']}.category c on c.category_id = m.category_id
				WHERE 
					(m.category_id = {$tCategory_id} OR isnull(m.category_id)) and 
					(m.brand_id = {$tBrand_id} OR isnull(m.brand_id)) and 
					($tCogs >= m.min_cogs or isnull(m.min_cogs)) and 
					($tCogs <= m.max_cogs or isnull(m.max_cogs)) and
					(isnull(m.wildcard) or '{$tProductName}' LIKE CONCAT('%',m.wildcard,'%')) and
					m.is_active = 1 and m.is_enabled = 1
				ORDER BY 
					(case when m.category_id > 0 then 1 ELSE 0 END + 
					case when m.brand_id > 0 then 1 ELSE 0 END +
					case when isnull(m.wildcard) then 0 ELSE 2 END+
					m.min_cogs/1000)
					DESC
				LIMIT 1
			");
		if ($m = getRow($rm)) {
			if ($m['category'] && $m['brand']) {
				$markup_type = 'STORE: ' . $m['category'] . ' / ' . $m['brand'] . ' Override';
			} elseif ($m['category']) {
				$markup_type = 'STORE: ' . $m['category'] . ' Override';
			} elseif ($m['brand']) {
				$markup_type = 'STORE: ' . $m['brand'] . ' Override';
			} else {
				'STORE: Override';
			}
			if ($m['wildcard']) { $markup_type .= " (Keyword: '{$m['wildcard']}')";}
			$tmarkup = $m['markup'];
			} 
			else { 
				//Company Override
				$rc = getRs("SELECT m.*, b.name as brand, c.name as category
				FROM c_markup_override m
					LEFT JOIN {$r['db']}.brand b on b.master_brand_id = m.brand_id
					LEFT JOIN {$r['db']}.category c on c.master_category_id = m.category_id
				WHERE 
					(c.category_id = {$tCategory_id} OR isnull(m.category_id)) and 
					(b.brand_id = {$tBrand_id} OR isnull(m.brand_id)) and 
					($tCogs >= m.min_cogs or isnull(m.min_cogs)) and 
					($tCogs <= m.max_cogs or isnull(m.max_cogs)) and
					(isnull(m.wildcard) or '{$tProductName}' LIKE CONCAT('%',m.wildcard,'%')) and
					m.is_active = 1 and m.is_enabled = 1
				ORDER BY 
					(case when m.category_id > 0 then 1 ELSE 0 END + 
					case when m.brand_id > 0 then 1 ELSE 0 END +
					case when isnull(m.wildcard) then 0 ELSE 2 END+
					m.min_cogs/1000)
					DESC
				LIMIT 1
			");
			if ($c = getRow($rc)) {
			if ($c['category'] && $c['brand']) {
				$markup_type = 'COMPANY: ' . $c['category'] . ' / ' . $c['brand'] . ' Override';
			} elseif ($c['category']) {
				$markup_type = 'COMPANY: ' . $c['category'] . ' Override';
			} elseif ($c['brand']) {
				$markup_type = 'COMPANY: ' . $c['brand'] . ' Override';
			} else {
				'COMPANY: Override';
			}
			if ($c['wildcard']) { $markup_type .= " (Keyword: '{$c['wildcard']}')";}
			$tmarkup = $c['markup'];
			}
				else {
					//Store Default
					$_p = json_decode($r['params'], true);
					$tmarkup = $_p['default_markup']?$_p['default_markup']:0;
					$markup_type = 'Store Default';
				}
			}
		$rdbe = getRs("SELECT m.*, b.name as brand, c.name as category
				FROM dbe_markup_override m
					LEFT JOIN {$r['db']}.brand b on b.master_brand_id = m.brand_id
					LEFT JOIN {$r['db']}.category c on c.master_category_id = m.category_id
				WHERE 
					(c.category_id = {$tCategory_id} OR isnull(m.category_id)) and 
					(b.brand_id = {$tBrand_id} OR isnull(m.brand_id)) and 
					($tCogs >= m.min_cogs or isnull(m.min_cogs)) and 
					($tCogs <= m.max_cogs or isnull(m.max_cogs)) and
					(isnull(m.wildcard) or '{$tProductName}' LIKE CONCAT('%',m.wildcard,'%')) and
					m.is_active = 1 and m.is_enabled = 1
				ORDER BY 
					(case when m.category_id > 0 then 1 ELSE 0 END + 
					case when m.brand_id > 0 then 1 ELSE 0 END +
					case when isnull(m.wildcard) then 0 ELSE 2 END+
					m.min_cogs/1000)
					DESC
				LIMIT 1
			");
		if ($dbe = getRow($rdbe)) {
			$davisPrice = ($r['unitPrice'] < .1)?0: ($dbe['otd_price_davis']?$dbe['otd_price_davis'] / 1.3820125 :0);
			$dixonPrice = ($r['unitPrice'] < .1)?0: ($dbe['otd_price_dixon']?$dbe['otd_price_dixon'] / 1.3086281: 0);
		}
		else {
			$davisPrice = 0;
			$dixonPrice = 0;
		}
		$salePrice = ($r['unitPrice'] < .1)?0:ceil($r['unitPrice'] * $tmarkup);

        // Derive human-friendly brand/category labels for enrichment prompt/display
        $enrichBrand = '';
        $enrichCategory = '';
        if (isset($m) && is_array($m)) {
            if (!empty($m['brand'])) {
                $enrichBrand = $m['brand'];
            }
            if (!empty($m['category'])) {
                $enrichCategory = $m['category'];
            }
        }
        if (!$enrichBrand && isset($c) && is_array($c) && !empty($c['brand'])) {
            $enrichBrand = $c['brand'];
        }
        if (!$enrichCategory && isset($c) && is_array($c) && !empty($c['category'])) {
            $enrichCategory = $c['category'];
        }
        if (!$enrichBrand) {
            $enrichBrand = $r['vendor_name'];
        }

        $enrichProductName = $r['po_product_name'];

        echo '<tr>
        <td style="text-align:center;" >' . $r['store_name'] . '</td>
        <td style="text-align:center;" >' . $r['vendor_name'] . '</td>
        <td data-sort="' . $r['po_status_name'] . '"><a href="/po/' . $r['po_code'] . '" target="_blank">' . $r['po_product_name'] . '</a></td>
        <td  style="text-align:center;" data-sort="' . $r['date_ordered'] . '">' . getShortDate($r['date_ordered']). '</td>
		<td  style="text-align:center;" data-sort="' . $r['sch_date'] . '">' . getShortDate($r['sch_date']). '</td>
        <td style="text-align:center;" >' . $r['po_status_name'] . '</td>
		<td style="text-align:center;" >' . $markup_type . '</td>
		<td style="text-align:center;">' . number_format($r['unitPrice'], 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($tmarkup, 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($salePrice, 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($davisPrice, 2, '.', '') . '</td>
		<td style="text-align:center;">' . number_format($dixonPrice, 2, '.', '') . '</td>
        <td style="text-align:center;" data-sort="' . $r['is_transferred'] .'"><span class="nowrap po-product-created"><input data-type="created" data-id="' . $r['po_product_id'] . '" type="checkbox" value="' . $r['po_product_id'] . '" id="created_' . $r['po_product_id'] . '" name="disaggregate_ids[]" data-render="switchery" data-theme="info"' . iif($r['is_created'], ' checked') .' />
        <label for="disaggregate_1"><span></span></label></span></td>
        <td style="text-align:center;"  data-sort="' . $r['is_transferred'] .'"><span class="nowrap po-product-transferred"><input data-type="transferred" data-id="' . $r['po_product_id'] . '" type="checkbox" value="' . $r['po_product_id'] . '" id="transferred_' . $r['po_product_id'] . '" name="disaggregate_ids[]" data-render="switchery" data-theme="info"' . iif($r['is_transferred'], ' checked') .' />
        <label for="disaggregate_1"><span></span></label></span></td>
        <td style="text-align:center;">
            <button 
                type="button" 
                class="btn btn-xs btn-primary btn-enrich"
                data-id="' . (int)$r['po_product_id'] . '"
                data-name="' . htmlspecialchars($enrichProductName, ENT_QUOTES, 'UTF-8') . '"
                data-brand="' . htmlspecialchars($enrichBrand, ENT_QUOTES, 'UTF-8') . '"
                data-category="' . htmlspecialchars($enrichCategory, ENT_QUOTES, 'UTF-8') . '"
            >&#10024; Enrich</button>
        </td>
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
?>

<!-- Enrichment Preview Modal -->
<div class="modal fade" id="enrichModal" tabindex="-1" role="dialog" aria-labelledby="enrichModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="enrichModalLabel">Enrichment Preview</h4>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-sm-4 text-center">
            <div class="enrich-image-wrapper" style="border:1px solid #ddd;padding:10px;min-height:220px;display:flex;align-items:center;justify-content:center;background:#fafafa;">
              <img id="enrichImage" src="" alt="Product Image Preview" style="max-width:100%;max-height:200px;display:none;" />
              <span id="enrichImagePlaceholder" style="color:#888;">No image loaded yet.</span>
            </div>
            <div class="m-t-10">
              <span class="label label-default">Brand: <span id="enrichBrand"></span></span>
            </div>
            <div class="m-t-5">
              <span class="label label-default">Category: <span id="enrichCategory"></span></span>
            </div>
            <div class="m-t-10">
              <span id="enrichStatusBadge" class="label label-info">Status: Not started</span>
            </div>
          </div>
          <div class="col-sm-8">
            <div class="form-group">
              <label for="enrichDescription">AI Description</label>
              <textarea id="enrichDescription" class="form-control" rows="8" style="resize:vertical;"></textarea>
            </div>
            <div id="enrichWarning" class="alert alert-warning" style="display:none;"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function($) {
  $(document).on('click', '.btn-enrich', function(e) {
    e.preventDefault();

    var $btn = $(this);
    var id = $btn.data('id');
    var name = $btn.data('name') || '';
    var brand = $btn.data('brand') || '';
    var category = $btn.data('category') || '';

    if (!id || !name) {
      alert('Missing product information for enrichment.');
      return;
    }

    $('#enrichModal').data('po_product_id', id);
    $('#enrichImage').hide().attr('src', '');
    $('#enrichImagePlaceholder').show().text('Loading image...');
    $('#enrichBrand').text(brand);
    $('#enrichCategory').text(category);
    $('#enrichDescription').val('');
    $('#enrichWarning').hide().text('');
    $('#enrichStatusBadge')
      .removeClass('label-success label-warning label-danger')
      .addClass('label-info')
      .text('Status: Fetching enrichment…');

    $('#enrichModal').modal('show');

    $.ajax({
      url: 'ajax/product-enrich.php',
      method: 'POST',
      dataType: 'json',
      data: {
        id: id,
        name: name,
        brand: brand,
        category: category
      }
    }).done(function(resp) {
      if (!resp || !resp.success) {
        var msg = (resp && resp.error) ? resp.error : 'Enrichment failed.';
        $('#enrichStatusBadge')
          .removeClass('label-info label-success')
          .addClass('label-danger')
          .text('Status: Error');
        $('#enrichWarning').text(msg).show();
        $('#enrichImagePlaceholder').text('No image available.');
        return;
      }

      $('#enrichDescription').val(resp.description || '');
      $('#enrichBrand').text(resp.brand || brand);
      $('#enrichCategory').text(resp.category || category);

      if (resp.temp_image_url) {
        $('#enrichImage').attr('src', resp.temp_image_url).show();
        $('#enrichImagePlaceholder').hide();
      } else {
        $('#enrichImage').hide();
        $('#enrichImagePlaceholder').show().text('No image available.');
      }

      if (resp.warning) {
        $('#enrichWarning').text(resp.warning).show();
      } else {
        $('#enrichWarning').hide().text('');
      }

      var source = resp.source_found || 'Unknown';
      $('#enrichStatusBadge')
        .removeClass('label-info label-danger label-warning')
        .addClass('label-success')
        .text('Source: ' + source);
    }).fail(function() {
      $('#enrichStatusBadge')
        .removeClass('label-info label-success')
        .addClass('label-danger')
        .text('Status: Error');
      $('#enrichWarning').text('An unexpected error occurred while calling enrichment.').show();
      $('#enrichImagePlaceholder').text('No image available.');
    });
  });
})(jQuery);
</script>

<?php
include_once('inc/footer.php'); 
?>