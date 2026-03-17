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
	SELECT s.store_name, p.po_product_id, p.po_product_name, p.is_created, p.is_transferred, po.po_code, po.vendor_name, po.vendor_id, po.date_ordered, t.po_status_name, IFNULL(coalesce(p.paid, p.price, p.cost),0) AS unitPrice, p.category_id, s.db, p.brand_id, s.params, po.date_po_event_scheduled as sch_date,
		(SELECT ppq.status FROM product_push_queue ppq WHERE ppq.po_product_id = p.po_product_id ORDER BY ppq.pushed_at DESC LIMIT 1) AS push_status
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
            <th data-priority="1" style="white-space:nowrap;">Actions</th>
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
        <td style="text-align:center;white-space:nowrap;vertical-align:middle;">';

        // Sync status badge
        $pushStatus = $r['push_status'] ?? null;
        if ($pushStatus === 'pending' || $pushStatus === 'processing') {
            echo '<span class="label label-warning" id="push-badge-' . (int)$r['po_product_id'] . '" title="Waiting for Blaze to propagate to all stores">&#8987; Syncing&hellip;</span><br>';
        } elseif ($pushStatus === 'done') {
            echo '<span class="label label-success" id="push-badge-' . (int)$r['po_product_id'] . '">&#10003; Live</span><br>';
        } elseif ($pushStatus === 'failed') {
            echo '<span class="label label-danger" id="push-badge-' . (int)$r['po_product_id'] . '" title="Sync failed — check product_push_queue">&#10007; Sync Failed</span><br>';
        }

        echo '<button 
                type="button" 
                class="btn btn-xs btn-primary btn-enrich"
                data-id="' . (int)$r['po_product_id'] . '"
                data-name="' . htmlspecialchars($enrichProductName, ENT_QUOTES, 'UTF-8') . '"
                data-brand="' . htmlspecialchars($enrichBrand, ENT_QUOTES, 'UTF-8') . '"
                data-brand-id="' . (int)$r['brand_id'] . '"
                data-category="' . htmlspecialchars($enrichCategory, ENT_QUOTES, 'UTF-8') . '"
                data-category-id="' . (int)$r['category_id'] . '"
                data-store-db="' . htmlspecialchars($r['db'], ENT_QUOTES, 'UTF-8') . '"
                data-vendor-id="' . (int)$r['vendor_id'] . '"
                data-default-price="' . number_format($salePrice, 2, '.', '') . '"
                data-davis-price="' . number_format($davisPrice, 2, '.', '') . '"
                data-dixon-price="' . number_format($dixonPrice, 2, '.', '') . '"
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

<!-- ============================================================
     Enrichment Preview Modal
     ============================================================ -->
<div class="modal fade" id="enrichModal" tabindex="-1" role="dialog" aria-labelledby="enrichModalLabel">
  <div class="modal-dialog" style="width:1150px;max-width:97vw;margin:3vh auto;" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="enrichModalLabel">&#10024; Enrichment Preview</h4>
      </div>

      <div class="modal-body" style="max-height:calc(94vh - 120px);overflow-y:auto;">

        <!-- Loading overlay with live progress checklist -->
        <div id="enrichLoadingOverlay" style="display:none;padding:28px 32px;">
          <p style="font-size:13px;font-weight:600;color:#555;margin-bottom:14px;">
            <i class="fa fa-magic" style="color:#6f42c1;margin-right:6px;"></i>
            Enriching product…
          </p>
          <ul id="enrichProgressList" style="list-style:none;padding:0;margin:0;font-size:13px;">
            <li class="enrich-step" data-step="brand_lookup">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Looking up brand &amp; category info</span>
            </li>
            <li class="enrich-step" data-step="description">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Generating AI description</span>
            </li>
            <li class="enrich-step" data-step="brand_images">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Searching brand folder</span>
            </li>
            <li class="enrich-step" data-step="master_images">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Searching master Drive folder</span>
            </li>
            <li class="enrich-step" data-step="trusted_search">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Searching Weedmaps, Leafly &amp; Dutchie</span>
            </li>
            <li class="enrich-step" data-step="web_search">
              <span class="enrich-step-icon">&#9711;</span>
              <span class="enrich-step-label">Searching the web</span>
            </li>
          </ul>
        </div>
        <style>
          .enrich-step { display:flex; align-items:center; padding:5px 0; color:#999; transition:color .2s; }
          .enrich-step-icon { width:22px; font-size:15px; flex-shrink:0; }
          .enrich-step.active { color:#337ab7; }
          .enrich-step.done   { color:#3c763d; }
          .enrich-step.skipped{ color:#bbb; text-decoration:line-through; }
          .enrich-step.active .enrich-step-icon::before { content:''; display:inline-block; width:13px; height:13px; border:2px solid #337ab7; border-top-color:transparent; border-radius:50%; animation:espin .7s linear infinite; vertical-align:middle; }
          .enrich-step.active .enrich-step-icon { font-size:0; }
          .enrich-step.done   .enrich-step-icon { color:#3c763d; font-size:15px; }
          .enrich-step.done   .enrich-step-icon::before { content:'✓'; font-size:15px; }
          .enrich-step.done   > .enrich-step-icon { font-size:0; }
          .enrich-step.skipped .enrich-step-icon::before { content:'—'; font-size:13px; }
          .enrich-step.skipped > .enrich-step-icon { font-size:0; }
          @keyframes espin { to { transform:rotate(360deg); } }
        </style>

        <div id="enrichContent" style="display:none;">
          <div class="row">

            <!-- LEFT: Image carousel -->
            <div class="col-sm-5">
              <!-- Image box — click to open full size in new tab -->
              <div id="enrichImageBox" style="border:1px solid #ddd;border-radius:4px;padding:8px;height:320px;display:flex;align-items:center;justify-content:center;background:#f9f9f9;position:relative;overflow:hidden;">
                <img id="enrichImage" src="" alt="Product image"
                     style="max-width:100%;max-height:304px;display:none;border-radius:3px;cursor:zoom-in;"
                     title="Click to open full size"
                />
                <span id="enrichImagePlaceholder" style="color:#aaa;font-size:13px;">No images found.</span>
                <!-- Expand hint overlay (bottom-right corner) -->
                <span id="enrichImageExpandHint" style="display:none;position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.45);color:#fff;font-size:10px;padding:2px 6px;border-radius:3px;pointer-events:none;">&#x26F6; open</span>
              </div>
              <!-- Carousel navigation -->
              <div id="enrichCarouselNav" style="display:none;text-align:center;margin-top:6px;">
                <button id="enrichImgPrev" class="btn btn-xs btn-default" style="margin-right:4px;">&#9664;</button>
                <span id="enrichImgCounter" style="font-size:12px;color:#666;">1 / 10</span>
                <button id="enrichImgNext" class="btn btn-xs btn-default" style="margin-left:4px;">&#9654;</button>
              </div>
              <!-- Source label -->
              <div class="text-center m-t-6">
                <span id="enrichImageSource" style="font-size:11px;color:#888;"></span>
              </div>
              <!-- Upload own image -->
              <div class="text-center m-t-6">
                <label class="btn btn-xs btn-default" style="margin:0;cursor:pointer;" title="Upload your own image">
                  <i class="fa fa-upload"></i> Upload Image
                  <input type="file" id="enrichImageUpload" accept="image/*" style="display:none;" />
                </label>
              </div>
              <!-- Status -->
              <div class="text-center m-t-10">
                <span id="enrichStatusBadge" class="label label-info">Not started</span>
              </div>
              <div id="enrichWarning" class="alert alert-warning m-t-10" style="display:none;font-size:12px;"></div>
            </div>

            <!-- RIGHT: Form fields -->
            <div class="col-sm-7">

              <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;margin-bottom:2px;">Product Name</label>
                <input type="text" id="enrichProductName" class="form-control input-sm" />
              </div>

              <div class="row" style="margin-bottom:8px;">
                <div class="col-sm-4">
                  <label style="font-size:12px;margin-bottom:2px;">Brand</label>
                  <select id="enrichBrandSelect" class="form-control input-sm">
                    <option value="">-- Loading… --</option>
                  </select>
                </div>
                <div class="col-sm-4">
                  <label style="font-size:12px;margin-bottom:2px;">Category</label>
                  <select id="enrichCategorySelect" class="form-control input-sm">
                    <option value="">-- Loading… --</option>
                  </select>
                </div>
                <div class="col-sm-4">
                  <label style="font-size:12px;margin-bottom:2px;">Flower Type</label>
                  <select id="enrichFlowerType" class="form-control input-sm">
                    <option value="">-- Unknown --</option>
                    <option>Indica</option>
                    <option>Sativa</option>
                    <option>Hybrid</option>
                    <option>Sativa Leaning</option>
                    <option>Indica Leaning</option>
                    <option>Indica-Dominant</option>
                    <option>Sativa-Dominant</option>
                  </select>
                </div>
              </div>

              <div class="row" style="margin-bottom:8px;">
                <div class="col-sm-4">
                  <label style="font-size:12px;margin-bottom:2px;">Weight Per Unit</label>
                  <select id="enrichWeightPerUnit" class="form-control input-sm">
                    <option>Each</option>
                    <option>Half Gram Unit</option>
                    <option>Full Gram Unit</option>
                    <option>Eighth Per Unit</option>
                    <option>Custom Weight</option>
                  </select>
                </div>
                <div class="col-sm-4" id="enrichCustomGramTypeWrap" style="display:none;">
                  <label style="font-size:12px;margin-bottom:2px;">Gram Type</label>
                  <select id="enrichCustomGramType" class="form-control input-sm">
                    <option>Gram</option>
                    <option>Milligrams</option>
                    <option>Fluid Ounce</option>
                    <option>Ounce</option>
                  </select>
                </div>
                <div class="col-sm-4" id="enrichCustomWeightWrap" style="display:none;">
                  <label style="font-size:12px;margin-bottom:2px;">Custom Weight (g)</label>
                  <input type="number" id="enrichCustomWeight" class="form-control input-sm" step="0.001" min="0" placeholder="e.g. 7" />
                </div>
              </div>

              <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:12px;margin-bottom:2px;">Description</label>
                <textarea id="enrichDescription" class="form-control" rows="10" style="resize:vertical;font-size:13px;"></textarea>
              </div>

              <div class="row">
                <div class="col-sm-4">
                  <div class="form-group" style="margin-bottom:8px;">
                    <label style="font-size:12px;margin-bottom:2px;">Default Price ($)</label>
                    <input type="number" id="enrichDefaultPrice" class="form-control input-sm" step="0.01" min="0" placeholder="0.00" />
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group" style="margin-bottom:8px;">
                    <label style="font-size:12px;margin-bottom:2px;">Davis Price ($)</label>
                    <input type="number" id="enrichDavisPrice" class="form-control input-sm" step="0.01" min="0" placeholder="None" />
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group" style="margin-bottom:8px;">
                    <label style="font-size:12px;margin-bottom:2px;">Dixon Price ($)</label>
                    <input type="number" id="enrichDixonPrice" class="form-control input-sm" step="0.01" min="0" placeholder="None" />
                  </div>
                </div>
              </div>

            </div><!-- /col-sm-8 -->
          </div><!-- /row -->

          <!-- Search again -->
          <div class="m-t-15" style="border-top:1px solid #eee;padding-top:12px;">
            <label style="font-size:12px;margin-bottom:4px;display:block;"><i class="fa fa-search"></i> Image Search Query <span style="color:#aaa;font-weight:normal;">(edit &amp; click Search Again to find better images)</span></label>
            <div class="input-group input-group-sm">
              <input type="text" id="enrichSearchQuery" class="form-control" placeholder="Search query…" />
              <span class="input-group-btn">
                <button id="enrichBtnSearchAgain" class="btn btn-default" type="button">
                  <i class="fa fa-refresh"></i> Search Again
                </button>
              </span>
            </div>
            <div id="enrichSearchLoading" style="display:none;font-size:12px;color:#888;margin-top:4px;"><i class="fa fa-spinner fa-spin"></i> Searching…</div>
          </div>

          <!-- Blaze API response -->
          <div id="enrichBlazeResponseArea" style="display:none;margin-top:15px;">
            <hr />
            <h5 style="margin-bottom:6px;">Blaze API Response</h5>
            <pre id="enrichBlazeResponseText" style="background:#f4f4f4;border:1px solid #ddd;border-radius:3px;padding:10px;font-size:11px;max-height:280px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
          </div>

        </div><!-- /enrichContent -->

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <div class="pull-left">
          <span id="enrichBlazeStatus" style="font-size:13px;"></span>
        </div>
        <button id="enrichBtnPushBlaze" type="button" class="btn btn-success" style="display:none;">
          <i class="fa fa-cloud-upload"></i> Push to Blaze
        </button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>

    </div><!-- /modal-content -->
  </div>
</div>

<script>
window.addEventListener('load', function() {
  var $ = window.jQuery;
  if (!$) { return; }

  // Resize modal body to fill available window height dynamically
  function resizeEnrichModalBody() {
    var $modal   = $('#enrichModal');
    var $body    = $modal.find('.modal-body');
    var $header  = $modal.find('.modal-header');
    var $footer  = $modal.find('.modal-footer');
    var used     = $header.outerHeight(true) + $footer.outerHeight(true) + 60; // 60 = margins + padding
    var avail    = Math.round(window.innerHeight * 0.94) - used;
    $body.css('max-height', Math.max(300, avail) + 'px');
  }
  $('#enrichModal').on('shown.bs.modal', resizeEnrichModalBody);
  $(window).on('resize', function() {
    if ($('#enrichModal').hasClass('in')) resizeEnrichModalBody();
  });

  /* ---- state ---- */
  var enrichImages       = [];
  var enrichImageSources = [];   // parallel to enrichImages — one source label per URL
  var enrichImgIdx       = 0;
  var enrichStoreDb      = '';

  var sourceIcons = {
    'Brand Drive Folder':    '&#128190; Source: Brand Drive Folder',
    'Brand Dropbox Folder':  '&#128230; Source: Brand Dropbox Folder',
    'Google Drive':          '&#128190; Source: Google Drive',
    'Brand Site':            '&#127968; Source: Brand Site',
    'Trusted Menu':          '&#127758; Source: Trusted Menu',
    'Web Search':            '&#127760; Source: Web Search',
    'Uploaded':           '&#128190; Source: Uploaded'
  };

  /* ---- helpers ---- */
  function showImage(idx) {
    if (!enrichImages.length) return;
    idx = Math.max(0, Math.min(idx, enrichImages.length - 1));
    enrichImgIdx = idx;
    var url = enrichImages[idx];
    $('#enrichImage').attr('src', url).show();
    $('#enrichImagePlaceholder').hide();
    $('#enrichImgCounter').text((idx + 1) + ' / ' + enrichImages.length);

    // Update the per-image source label
    var src = enrichImageSources[idx] || 'Web Search';
    $('#enrichImageSource').html(sourceIcons[src] || ('&#127760; Source: ' + src));

    // Show the expand hint when an image is visible
    $('#enrichImageExpandHint').show();
  }

  // Click image → open full size in new tab
  $(document).on('click', '#enrichImage', function() {
    var url = enrichImages[enrichImgIdx];
    if (url) window.open(url, '_blank');
  });

  function populateDropdown(selectId, items, selectedId) {
    var $sel = $('#' + selectId);
    $sel.empty();
    $sel.append('<option value="">-- Select --</option>');
    $.each(items || [], function(_, item) {
      $sel.append($('<option>').val(item.id).text(item.name));
    });
    if (selectedId) $sel.val(selectedId);
  }

  /* ---- Open modal / trigger enrichment ---- */
  $(document).on('click', '.btn-enrich', function(e) {
    e.preventDefault();

    var $btn      = $(this);
    var id        = $btn.data('id');
    var name      = $btn.data('name')      || '';
    var brand     = $btn.data('brand')     || '';
    var brandId   = $btn.data('brand-id')  || 0;
    var category  = $btn.data('category')  || '';
    var catId     = $btn.data('category-id') || 0;
    var storeDb    = $btn.data('store-db')    || '';
    var vendorId   = $btn.data('vendor-id')   || 0;
    var defPrice   = $btn.data('default-price') || '';
    var davPrice   = $btn.data('davis-price')   || '';
    var dixPrice   = $btn.data('dixon-price')   || '';

    if (!id || !name) {
      alert('Missing product information for enrichment.');
      return;
    }

    enrichImages       = [];
    enrichImageSources = [];
    enrichImgIdx       = 0;
    enrichStoreDb      = storeDb;
    $('#enrichImageExpandHint').hide();

    /* store IDs so Push to Blaze can use them */
    $('#enrichModal')
      .data('brand_id',      brandId)
      .data('category_id',   catId)
      .data('store_db',      storeDb)
      .data('vendor_id',     vendorId)
      .data('po_product_id', id)
      .data('davis_price',   davPrice)
      .data('dixon_price',   dixPrice);

    /* reset modal */
    $('#enrichLoadingOverlay').show();
    $('#enrichContent').hide();
    $('#enrichBlazeResponseArea').hide();
    $('#enrichBlazeResponseText').text('');
    $('#enrichBlazeStatus').text('').css('color', '');
    $('#enrichBtnPushBlaze').hide();
    $('#enrichProductName').val(name);
    $('#enrichDefaultPrice').val(defPrice);
    $('#enrichDavisPrice').val(davPrice  > 0 ? davPrice  : '');
    $('#enrichDixonPrice').val(dixPrice  > 0 ? dixPrice  : '');
    $('#enrichDescription').val('');
    $('#enrichFlowerType').val('');
    $('#enrichWeightPerUnit').val('Each');
    $('#enrichCustomGramType').val('Gram');
    $('#enrichCustomWeight').val('');
    $('#enrichCustomGramTypeWrap, #enrichCustomWeightWrap').hide();
    $('#enrichWarning').hide().text('');
    $('#enrichStatusBadge')
      .show()
      .removeClass('label-success label-warning label-danger')
      .addClass('label-info')
      .text('Fetching enrichment…');

    $('#enrichModal').modal('show');

    /* ── helper: update a progress step row ─────────────────────────────── */
    function enrichSetStep(stepId, state, labelOverride) {
      var $li = $('#enrichProgressList [data-step="' + stepId + '"]');
      $li.removeClass('active done skipped').addClass(state);
      if (labelOverride) $li.find('.enrich-step-label').text(labelOverride);
    }

    /* ── helper: apply the final enrichment result to the modal ─────────── */
    function enrichApplyResult(resp) {
      $('#enrichLoadingOverlay').hide();
      $('#enrichContent').show();

      if (!resp || !resp.success) {
        var msg = (resp && resp.error) ? resp.error : 'Enrichment failed.';
        $('#enrichStatusBadge')
          .show()
          .removeClass('label-info label-success')
          .addClass('label-danger')
          .text('Error');
        $('#enrichWarning').text(msg).show();
        return;
      }

      /* Description */
      $('#enrichDescription').val(resp.description || '');

      /* Dropdowns */
      populateDropdown('enrichBrandSelect',    resp.brands || [],     resp.brand_id    || brandId);
      populateDropdown('enrichCategorySelect', resp.categories || [], resp.category_id || catId);

      /* Flower type */
      $('#enrichFlowerType').val(resp.flower_type || '');

      /* Weight */
      var wpu = resp.weight_per_unit || 'Each';
      $('#enrichWeightPerUnit').val(wpu);
      if (wpu === 'Custom Weight') {
        $('#enrichCustomGramType').val(resp.custom_gram_type || 'Gram');
        $('#enrichCustomWeight').val(resp.custom_weight  || '');
        $('#enrichCustomGramTypeWrap, #enrichCustomWeightWrap').show();
      } else {
        $('#enrichCustomGramTypeWrap, #enrichCustomWeightWrap').hide();
      }

      /* Image carousel */
      enrichImages       = resp.images        || [];
      enrichImageSources = resp.image_sources || [];
      if (enrichImages.length) {
        showImage(0);
        if (enrichImages.length > 1) {
          $('#enrichCarouselNav').show();
          $('#enrichImgCounter').text('1 / ' + enrichImages.length);
        } else {
          $('#enrichCarouselNav').hide();
        }
      } else {
        $('#enrichImage').hide();
        $('#enrichImagePlaceholder').show().text('No images found.');
        $('#enrichCarouselNav').hide();
      }

      $('#enrichStatusBadge').hide();
      $('#enrichSearchQuery').val(resp.search_query || '');

      if (resp.warning) {
        $('#enrichWarning').text(resp.warning).show();
      }

      $('#enrichBtnPushBlaze').show();
    }

    /* ── progress checklist polling + main AJAX call ─────────────────────── */
    // Reset all steps to pending
    $('#enrichProgressList .enrich-step').removeClass('active done skipped');

    // Generate a unique job ID for this enrichment run
    var jobId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    // Poll enrich-progress.php every 500ms for live step updates
    var progressPoller = setInterval(function() {
      $.getJSON('ajax/enrich-progress.php', { job_id: jobId }, function(prog) {
        var steps = prog.steps || {};
        $.each(steps, function(stepId, step) {
          var state = step.state || '';
          if (state === 'active') {
            enrichSetStep(stepId, 'active', step.label || null);
          } else if (state === 'done') {
            var suffix = (step.count !== undefined) ? ' (' + step.count + ' found)' : '';
            var $li = $('#enrichProgressList [data-step="' + stepId + '"]');
            var base = $li.find('.enrich-step-label').text().replace(/ \(\d+ found\)$/, '');
            enrichSetStep(stepId, 'done', base + suffix);
          } else if (state === 'skipped') {
            enrichSetStep(stepId, 'skipped');
          }
        });
      });
    }, 500);

    $.ajax({
      url:      'ajax/product-enrich.php',
      method:   'POST',
      dataType: 'json',
      data: {
        id:          id,
        name:        name,
        brand:       brand,
        brand_id:    brandId,
        category:    category,
        category_id: catId,
        store_db:    storeDb,
        job_id:      jobId
      }
    }).done(function(resp) {
      clearInterval(progressPoller);
      // Do one final poll to show the last completed steps before hiding overlay
      $.getJSON('ajax/enrich-progress.php', { job_id: jobId }, function(prog) {
        var steps = prog.steps || {};
        $.each(steps, function(stepId, step) {
          if ((step.state || '') === 'done') enrichSetStep(stepId, 'done');
        });
      });
      enrichApplyResult(resp);
    }).fail(function(xhr, status, error) {
      clearInterval(progressPoller);
      $('#enrichLoadingOverlay').hide();
      $('#enrichContent').show();
      $('#enrichStatusBadge')
        .removeClass('label-info label-success')
        .addClass('label-danger')
        .text('Error');
      var msg = (xhr.responseJSON && xhr.responseJSON.error)
        ? xhr.responseJSON.error
        : ('An unexpected error occurred (' + status + ')');
      $('#enrichWarning').text(msg).show();
    });   // end $.ajax fail / end of $.ajax chain

  });   // end $(document).on('click', '.btn-enrich', ...)

  /* ---- Carousel prev / next ---- */
  $(document).on('click', '#enrichImgPrev', function() {
    if (enrichImages.length) showImage(enrichImgIdx - 1);
  });
  $(document).on('click', '#enrichImgNext', function() {
    if (enrichImages.length) showImage(enrichImgIdx + 1);
  });

  /* ---- Search Again ---- */
  $(document).on('click', '#enrichBtnSearchAgain', function() {
    var query = $('#enrichSearchQuery').val().trim();
    if (!query) { alert('Please enter a search query.'); return; }

    $('#enrichBtnSearchAgain').prop('disabled', true);
    $('#enrichSearchLoading').show();

    $.ajax({
      url: 'ajax/product-image-search.php',
      method: 'POST',
      dataType: 'json',
      data: {
        query:    query,
        name:     $('#enrichProductName').val().trim(),
        brand:    $('#enrichBrandSelect option:selected').text().trim(),
        brand_id: parseInt($('#enrichModal').data('brand_id') || 0, 10),
        store_db: enrichStoreDb
      }
    }).done(function(resp) {
      if (resp && resp.success && resp.images && resp.images.length) {
        enrichImages       = resp.images;
        enrichImageSources = resp.image_sources || [];
        enrichImgIdx = 0;
        showImage(0);   // sets per-image source label automatically
        $('#enrichCarouselNav').toggle(enrichImages.length > 1);
        $('#enrichImgCounter').text('1 / ' + enrichImages.length);
      } else {
        $('#enrichImagePlaceholder').show().text('No images found for that query.');
        $('#enrichImage').hide();
        $('#enrichCarouselNav').hide();
      }
    }).fail(function(xhr, status, error) {
      var detail = xhr.responseText ? xhr.responseText.substring(0, 300) : error;
      alert('Image search failed (' + xhr.status + '): ' + detail);
    }).always(function() {
      $('#enrichBtnSearchAgain').prop('disabled', false);
      $('#enrichSearchLoading').hide();
    });
  });

  /* ---- Upload own image ---- */
  $(document).on('change', '#enrichImageUpload', function() {
    var file = this.files[0];
    if (!file) return;

    var reader = new FileReader();
    reader.onload = function(e) {
      var dataUrl = e.target.result;
      // Prepend the uploaded image to the front of the carousel
      enrichImages.unshift(dataUrl);
      enrichImageSources.unshift('Uploaded');
      enrichImgIdx = 0;
      showImage(0);   // sets source label to "Uploaded" automatically
      $('#enrichCarouselNav').toggle(enrichImages.length > 1);
      $('#enrichImgCounter').text('1 / ' + enrichImages.length);
    };
    reader.readAsDataURL(file);
    // Reset so the same file can be re-selected if needed
    this.value = '';
  });

  /* ---- Weight per unit toggle ---- */
  $(document).on('change', '#enrichWeightPerUnit', function() {
    var isCustom = $(this).val() === 'Custom Weight';
    $('#enrichCustomGramTypeWrap, #enrichCustomWeightWrap').toggle(isCustom);
  });

  /* ---- Push to Blaze ---- */
  $(document).on('click', '#enrichBtnPushBlaze', function() {
    var $modal       = $('#enrichModal');
    var brandId      = $('#enrichBrandSelect').val()    || $modal.data('brand_id')    || 0;
    var categoryId   = $('#enrichCategorySelect').val() || $modal.data('category_id') || 0;
    var storeDb      = $modal.data('store_db')      || '';
    var vendorId     = $modal.data('vendor_id')     || 0;
    var poProductId  = $modal.data('po_product_id') || 0;
    var davisPrice   = parseFloat($('#enrichDavisPrice').val())  || 0;
    var dixonPrice   = parseFloat($('#enrichDixonPrice').val())  || 0;
    var name         = $('#enrichProductName').val().trim();
    var description  = $('#enrichDescription').val().trim();
    var price        = parseFloat($('#enrichDefaultPrice').val()) || 0;
    var flowerType   = $('#enrichFlowerType').val();
    var weightPerUnit   = $('#enrichWeightPerUnit').val()    || 'Each';
    var customGramType  = $('#enrichCustomGramType').val()   || 'Gram';
    var customWeight    = parseFloat($('#enrichCustomWeight').val()) || 0;

    if (!name) { alert('Product name is required before pushing to Blaze.'); return; }

    $('#enrichBtnPushBlaze').prop('disabled', true).text('Pushing…');
    $('#enrichBlazeStatus').text('').css('color', '');
    $('#enrichBlazeResponseArea').hide();

    $.ajax({
      url: 'ajax/product-blaze-push.php',
      method: 'POST',
      dataType: 'json',
      data: {
        name:             name,
        description:      description,
        price:            price,
        davis_price:      davisPrice,
        dixon_price:      dixonPrice,
        brand_id:         brandId,
        category_id:      categoryId,
        store_db:         storeDb,
        vendor_id:        vendorId,
        po_product_id:    poProductId,
        image_url:        enrichImages.length ? enrichImages[enrichImgIdx] : '',
        flower_type:      flowerType,
        weight_per_unit:  weightPerUnit,
        custom_gram_type: customGramType,
        custom_weight:    customWeight
      }
    }).done(function(resp) {
      $('#enrichBtnPushBlaze').prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> Push to Blaze');

      var prettyResp = JSON.stringify(resp, null, 2);
      $('#enrichBlazeResponseText').text(prettyResp);
      $('#enrichBlazeResponseArea').show();

      if (resp && resp.success) {
        $('#enrichBlazeStatus').text('✓ Pushed successfully to Blaze. Propagation queued.').css('color', '#3c763d');
        // Update the sync badge in the table row
        if (poProductId) {
          var $badge = $('#push-badge-' + poProductId);
          if ($badge.length) {
            $badge.removeClass('label-success label-danger label-default')
                  .addClass('label-warning')
                  .html('&#8987; Syncing&hellip;');
          } else {
            // Badge didn't exist yet (first push) — inject it before the Enrich button
            $('[data-id="' + poProductId + '"].btn-enrich').before(
              '<span class="label label-warning" id="push-badge-' + poProductId + '">&#8987; Syncing&hellip;</span><br>'
            );
          }
        }
      } else {
        var errMsg = (resp && resp.curl_error) ? resp.curl_error
                   : (resp && resp.blaze_response && resp.blaze_response.message) ? resp.blaze_response.message
                   : 'Push failed (HTTP ' + (resp && resp.http_code ? resp.http_code : '?') + ').';
        $('#enrichBlazeStatus').text('✗ ' + errMsg).css('color', '#a94442');
      }
    }).fail(function() {
      $('#enrichBtnPushBlaze').prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> Push to Blaze');
      $('#enrichBlazeStatus').text('✗ Network error calling push endpoint.').css('color', '#a94442');
      $('#enrichBlazeResponseArea').show();
      $('#enrichBlazeResponseText').text('AJAX request failed.');
    });
  });

});
</script>

<?php
include_once('inc/footer.php'); 
?>