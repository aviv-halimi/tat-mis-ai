<?php
$footer = '
<script language="javascript" type="text/javascript">
$(document).ready(function(e) {
  $("#po_type_id").on("change.select2", function(e) {
    if ($(this).val() == 2) $("#po_reorder_type_id").val(4).trigger("change");
  });
  $(".btn-po-discounts").on("click", function(e) {
    postAjax("po-discounts", {po_id: $(this).data("id")});
  });
});
</script>';

include_once('_config.php');
$_ajax = getVarInt('_ajax');
$po_code = getVar('c');
if ($po_code) {
  $rt = getRs("SELECT po_id, store_id, params, po_type_id FROM po WHERE " . is_active() . " AND po_code = ?", array($po_code));
  if ($t = getRow($rt)) {
    if ($t['store_id'] == $_Session->store_id) {
      $_p = ($t['params'])?json_decode($t['params'], true):array();
      $_p['po_id'] = $t['po_id'];
      $_Session->admin_settings['_ds-po'] = $_p;
      setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($_Session->admin_settings), $_Session->admin_id));
    }
    else {
      $success = false;
      $response = 'You do not have access to this store';
      if ($_Session->store_ids) {
        $_a_store_ids = json_decode($_Session->store_ids, true);
        foreach($_a_store_ids as $_store) {
          if (isset($_store['store_id']) and isset($_store['employee_id'])) {
            $_store_id = $_store['store_id'];
            $_employee_id = $_store['employee_id'];
            $_rs = getRs("SELECT * FROM store WHERE " . is_enabled() . " AND JSON_CONTAINS(?, CAST(store_id AS CHAR), '$') AND store_id = ?", array($_store_id, $t['store_id']));
            if ($_s = getRow($_rs)) {
              if ($_s['store_id'] == $t['store_id']) {
                dbUpdate('admin', array('store_id' => $_s['store_id'], 'employee_id' => $_employee_id), $_Session->admin_id);
                $success = true;
                $response = 'Store selection updated';
				echo $response;
              }
            }
          }
        }
      }
      if (!$success) {
        require_once('inc/header.php');
        echo '<div class="alert alert-danger alert-bordered text-lg">
        <strong>Permission denied</strong>
        <p>Sorry ' . $_Session->first_name . ', you do not have permission to view this PO. Please contact system administrator for more information.</p>
        </div>';
        require_once('inc/footer.php');
        exit();
      }
      else {
        header( "refresh:5; url= ". getCurrentHost() . "/po/" . $po_code);
		require_once('inc/header.php');
        echo '<div class="alert alert-danger alert-bordered text-lg">
        <strong style="font-size:40px">ALERT:  Switching Stores</strong>
		<br/><br/>
        <p style="font-size:25px">Hi ' . $_Session->first_name . ', you have selected a PO from a different store than you were logged into previously.  <br/><br/>You are now being logged into a new store...</p>
        </div>';
        
		require_once('inc/footer.php');
		//redirectTo('/po/' . $po_code);
        exit();
      }
    }
  }
}

$restock_type_id = $_restock_type_id = null;
$_hide_options = false;
$timespan = '';
$_po_name = $_description = $po_number = $_po_number = $_po_status_id = $_date_ordered = $_date_last_purchased = $_date_requested_ship = $_date_schedule_delivery = $_date_received = $_invoice_number = $_invoice_filename = $_po_filename = $_coa_filename = $_coa_filenames = $_email = $_payment_terms = null;

$_date_schedule_delivery_placeholder = date('n/j/Y', strtotime("+ " . $_Session->GetSetting('scheduling-window') . " days")) . ' (default)';

$_ds = $_Session->GetTableDisplaySettings($module_code);
$_po_id = (isset($_ds['po_id']))?$_ds['po_id']:null;
$_po_reorder_type_id = (isset($_ds['po_reorder_type_id']))?$_ds['po_reorder_type_id']:null;
$_vendor_id = (isset($_ds['vendor_id']))?$_ds['vendor_id']:null;
$_po_type_id = (isset($_ds['po_type_id']))?$_ds['po_type_id']:1;
$_category_id = (isset($_ds['category_id']))?$_ds['category_id']:null;
$_brand_id = (isset($_ds['brand_id']))?$_ds['brand_id']:null;
$_date_last_purchased = (isset($_ds['date_last_purchased']))?$_ds['date_last_purchased']:null;
$_sort_by = (isset($_ds['sort_by']))?$_ds['sort_by']:null;
$_disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:($_po_id?array():array(1,2));
if ($_po_id) {
  $rs = $_PO->GetPO($_po_id);
  if ($r = getRow($rs)) {
    $meta_title = iif(str_len($r['po_name']), $r['po_name'], iif($r['po_type_id'] == 1, 'PO', 'CR') . ' Details') . ': ' . $r['po_number'] . ' (' . $r['po_status_name'] . ') - ' . $r['vendor_name'] . ' (' . getShortDate($r['date_created']) . ')';
    $po_code = $r['po_code'];
    $po_number = $r['po_number'];
    $_po_name = $r['po_name'];
    $_email = $r['email'];
    $_description = $r['description'];
    $_po_reorder_type_id = $r['po_reorder_type_id'];
    $_po_status_id = $r['po_status_id'];
    $_invoice_number = $r['invoice_number'];
    $_invoice_filename = $r['invoice_filename'];
    $_payment_terms = isset($r['payment_terms']) && $r['payment_terms'] !== '' && $r['payment_terms'] !== null ? (int)$r['payment_terms'] : '';
    $_po_filename = $r['po_filename'];
    $_vendor_id = $r['vendor_id'];
    $_po_type_id = $r['po_type_id'];
    $_coa_filename = $r['coa_filename'];
    $_coa_filenames = $r['coa_filenames'];
    $_date_ordered = getShortDate($r['date_ordered']);
    $_date_received = getShortDate($r['date_received']);
    //$_date_start = getShortDate($r['date_start']);
    $_date_requested_ship = getShortDate($r['date_requested_ship']);
    $_date_schedule_delivery = getShortDate($r['date_schedule_delivery']);
    $_hide_options = true;

    $_rv = getRs("SELECT scheduling_window FROM {$_Session->db}.vendor WHERE vendor_id = ?", array($r['vendor_id']));
    if ($_v = getRow($_rv)) {
      if ($_v['scheduling_window']) {
        $_date_schedule_delivery_placeholder = date('n/j/Y', strtotime("+ " . $_v['scheduling_window'] . " days"));
      }
    }
  }
  else {
    redirectTo('/pos');
  }
}
$header = '<link href="/assets/css/pdf-inline.css" rel="stylesheet" />';
include_once('inc/header.php');

$rf = $_Session->GetFiles('po', $_po_id);
$num_files = 0;
foreach($rf as $f) {
  if (!$f['is_auto']) $num_files++;
}
?>

<div class="panel bg-semi-transparent">
            <ul class="nav nav-tabs nav-pills- bg-white" style="text-transform:uppercase;">
                <li class="nav-item">
                    <a href="javascript:void();" data-target="#dashboard" data-toggle="tab" class="nav-link active">Details</a>
                </li>
                <?php if ($_po_id) {?>
                <li class="nav-item">
                    <a href="javascript:void();" data-target="#notes" data-toggle="tab" class="nav-link">Files / Notes <?php echo iif($num_files, '<span class="badge badge-info ml-1">' . $num_files . '</span> '); ?></a>
                </li>
                <?php } ?>
            </ul>
            <div class="tab-content bg-transparent">


              <div class="tab-pane active" id="dashboard">


<form role="form" class="ajax-form display-options po-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="po" />
<input type="hidden" name="po_id" id="po_id" value="<?php echo $_po_id; ?>" />
<input type="hidden" name="po_code" id="po_code" value="<?php echo $_Session->GetIdCode('po', $_po_id); ?>" />
<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title"><?php echo iif($_po_id, 'Display Options', 'Generate PO'); ?></h4>
    </div>
    <div class="panel-body pb-0"<?php echo iif($_hide_options, ' style="display:none;"'); ?>>
			  <div class="panel-option pt-1 pb-1 pl-4">Purchase Order Details</div>
        <div class="row form-input-flat mb-3">
          <div class="col-sm-7">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-cog mr-1"></i> Type
                </div>
              </div>
              <?php
              if ($_po_id and $_po_type_id) {
                echo '<input type="hidden" name="po_type_id" value="' . $_po_type_id . '" />                
                <div class="input-group-text"><b>' . dbFieldName('po_type', $_po_type_id) . '</b></div>';
              }
              else {
                echo displayKey('po_type_id', $_po_type_id, null, (!$_Session->HasModulePermission('cr'))?'po_type_id <> 2':null, 'Select Type');
              }
              ?>
            </div>
          </div>
        </div>
        
        <div class="row form-input-flat mb-3">
          <div class="col-sm-7">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-pen mr-1"></i> Name / Description
                </div>
              </div>
              <?php
              if ($_po_id and $_po_status_id > 3) {
                echo '<div class="input-group-text"><b>' . $_po_name . '</b></div>';
              }
              else {
              echo '<input type="text" name="po_name" class="form-control" value="' . $_po_name . '" placeholder="Optional ..." />';
              }
              ?>
            </div>
          </div>
          <?php
          if ($_po_id and $_po_status_id > 3) {
          echo '
          <div class="col-sm-5">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-calendar mr-1"></i> Latest schedule date
                </div>
              </div>
              <div class="input-group-text"><b>' . $_date_schedule_delivery . '</b></div>
            </div>
          </div>';
          }
          else {
          echo '
          <div class="col-sm-5 date">
            <div class="input-group date datepicker" data-date-format="mm/dd/yyyy">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Latest schedule date
                </div>
              </div>
              <input type="text" id="date_schedule_delivery" class="form-control" placeholder="' . $_date_schedule_delivery_placeholder . '" name="date_schedule_delivery" value="' . $_date_schedule_delivery . '" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>';
          }
          if (false) {
          if ($_po_id and $_po_status_id > 1) {
          echo '
          <div class="col-sm-5">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-calendar mr-1"></i> Requested Ship Date
                </div>
              </div>
              <div class="input-group-text"><b>' . $_date_requested_ship . '</b></div>
            </div>
          </div>';
          }
          else {
          echo '
          <div class="col-sm-5 date">
            <div class="input-group date datepicker" data-date-format="mm/dd/yyyy">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Requested Ship Date
                </div>
              </div>
              <input type="text" class="form-control" placeholder="Requested Ship Date ..." name="date_requested_ship" value="' . $_date_requested_ship . '" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>';
          }
        }
          ?>
        </div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-7">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-folder mr-1"></i> Vendor
                </div>
              </div>
              <?php
              if ($_po_id and $_vendor_id) {
                echo '<input type="hidden" name="vendor_id" value="' . $_vendor_id . '" />                
                <div class="input-group-text"><b>' . dbFieldName('vendor', $_vendor_id, 'name', $_Session->db) . '</b></div>';
              }
              else {
                $rv = getRs("SELECT vendor_id, email, name, scheduling_window, is_suspended FROM {$_Session->db}.vendor WHERE " . is_enabled() . " ORDER BY name");
                echo '<select id="vendor_id" name="vendor_id" class="form-control select2" data-id="vendor_id">
                <option value="">- Select Vendor -</option>';
                foreach($rv as $v) {
                  echo '<option value="' . $v['vendor_id'] . '" data-delivery-placeholder="' . date('n/j/Y', strtotime("+ " . (($v['scheduling_window'])?$v['scheduling_window']:$_Session->GetSetting('scheduling-window')) . " days")) . (($v['scheduling_window'])?'':' (default)') . '" data-email="' . $v['email'] . '" ' . iif($v['is_suspended'], ' data-suspended="1"') . iif($v['vendor_id'] == $_vendor_id, ' selected') . '>' . $v['name'] . '</option>';
                }
                echo '</select>';
              }
              ?>
            </div>
            <div class="input-group mt-1">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-envelope mr-1"></i> E-mail
                </div>
              </div>
              <input type="text" id="email" name="email" value="<?php echo $_email; ?>" class="form-control" placeholder="Vendor e-mail address ..." />
              <?php 
              if ($_po_id and $_vendor_id) {
                echo '<div class="input-group-append"><button type="button" class="btn btn-secondary btn-po-email" class="button" data-po="' . $_po_id . '">Save</button></div>';
              }
              ?>
            </div>
          </div>
          <div class="col-sm-5">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-clock mr-1"></i> Reorder Type
                </div>
              </div>
              <?php
              if ($_po_id and $_po_reorder_type_id) {
                echo '<input type="hidden" name="_po_reorder_type_id" value="' . $_po_reorder_type_id . '" />                
                <div class="input-group-text"><b>' . dbFieldName('po_reorder_type', $_po_reorder_type_id) . '</b></div>';
              }
              else {
                echo displayKey('po_reorder_type_id', $_po_reorder_type_id); 
              }
              ?>
            </div>
          </div>
        </div>

        
			  <div class="panel-option mt-3 pt-1 pb-1 pl-4">Filters</div>
        <div class="row form-input-flat mb-2">
          <?php
          if ($_po_id and $_po_status_id > 1) {
          echo '
          <div class="col-sm-7">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-calendar mr-1"></i> Ordered after
                </div>
              </div>
              <div class="input-group-text"><b>' . $_date_last_purchased . '</b></div>
            </div>
          </div>';
          }
          else {
          echo '
          <div class="col-sm-7 date">
            <div class="input-group date datepicker" data-date-format="mm/dd/yyyy">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  Ordered after
                </div>
              </div>
              <input type="text" class="form-control" placeholder="Products ordered on or after ..." name="date_last_purchased" value="' . $_date_last_purchased . '" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>';
          }
          ?>
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

          <span class="nowrap"><input type="checkbox" value="1" id="disaggregate_1" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(1, $_disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_1"><span class="m-l-5 m-r-10">Category</span></label></span>

          <span class="nowrap"><input type="checkbox" value="2" id="disaggregate_2" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(2, $_disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_2"><span class="m-l-5 m-r-10">Brand</span></label></span>

          <span class="nowrap"><input type="checkbox" value="3" id="disaggregate_3" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(3, $_disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_3"><span class="m-l-5 m-r-10">Cannabis Type</span></label></span>

          <span class="nowrap"><input type="checkbox" value="4" id="disaggregate_4" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(4, $_disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_4"><span class="m-l-5 m-r-10">Weight per Unit</span></label></span>

          <span class="nowrap"><input type="checkbox" value="5" id="disaggregate_5" name="disaggregate_ids[]" data-render="switchery" data-theme="info"<?php echo iif(in_array(5, $_disaggregate_ids), ' checked'); ?> />
          <label for="disaggregate_5"><span class="m-l-5 m-r-10">Flower Type</span></label></span>

          </div>
        </div>

        <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
          <hr class="m-0" />
          <div class="p-10">
            <div class="row">
              <div class="col-sm-6">
                <span id="status_table_display" class="status"></span>
                <?php if ($_po_id and $_Session->HasModulePermission('po-cancel')) {
                  echo '<button type="button" class="btn btn-danger btn-po-del mt-0" data-title="Are you sure you want to cancel and remove PO: ' . $po_number . '?">Cancel PO</button>';
                }?>
              </div>
              <div class="col-sm-6 text-right form-btns">
                <?php if ($_po_id) {
                  echo '<button type="submit" class="btn btn-warning mt-0">Update Display</button>';
                }
                else {                  
                  echo '<button type="submit" class="btn btn-primary mt-0">Get Products</button>';
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
if ($_po_id) {
$rt = $_PO->GetPO($_po_id);
if ($t = getRow($rt)) {
if ($t['po_type_id'] == 1 || $_Session->HasModulePermission('cr')) {
$rs = $_PO->GetSavedPOProducts($_po_id, $_brand_id, $_category_id, ($_po_status_id == 1)?$_date_last_purchased:null, $_disaggregate_ids, $_sort_by);
$progress = $_PO->POProgress($_po_id);

if (true) { //sizeof($rs)) {

  $_ns = $_na = array();

  if ($t['is_confirmed']) {
    $_rv = getRs("SELECT * FROM vendor WHERE vendor_id = ?", $r['confirmed_by_vendor_id']);
    if ($_v = getRow($_rv)) {
        $vendor_name = $_v['first_name'] . ' ' . $_v['last_name'] . ' (' . $_v['vendor_name'] . ')';
    }
    else {
        $vendor_name = null;
    }
    array_push($_ns, '<i class="fa fa-check-circle text-success"></i>  Confirmed on <b>' . getLongDate($t['date_confirmed']) . '</b> by <b>' . $vendor_name . '</b>');
  }

  $rn = getRs("SELECT * FROM po_edit WHERE po_edit_status_id = 1 AND po_id = ? AND " . is_enabled(), array($_po_id));
  if ($n = getRow($rn)) {
    array_push($_na, '<i class="fa fa-exclamation-triangle"></i>  PO modifications submitted <b>' . getLongDate($n['date_created']) . '</b>. <a href="" class="btn-dialog text-white" data-url="po-edit" data-id="' . $_po_id . '" data-title="Review PO Modifications" data-hide-btns="true"><b>Click here</b></a> to review and approved or decline.');
  }
  $rn = getRs("SELECT t.notification_type_name, n.date_created FROM notification_type t INNER JOIN notification n ON n.notification_type_id = t.notification_type_id WHERE n.notification_type_id = 1 AND n.po_id = ? ORDER BY n.date_created DESC LIMIT 1", array($_po_id));
  if ($n = getRow($rn)) {
    array_push($_ns, yesNoFormat(1) . ' ' . $n['notification_type_name'] . ' sent at <b>' . getLongDate($n['date_created']) . '</b>');
  }
  $rn = getRs("SELECT s.po_event_status_id, s.po_event_status_name, p.date_start FROM po_event p INNER JOIN po_event_status s ON s.po_event_status_id = p.po_event_status_id WHERE " . is_enabled('p') . " AND p.po_id = ? ORDER BY p.po_event_id DESC", array($_po_id));
  if ($n = getRow($rn)) {
    array_push($_ns, yesNoFormat(1) . ' ' . $n['po_event_status_name'] . ': ' . getLongDate($n['date_start']) . '</b> ' . iif($n['po_event_status_id'] == 2, ' <div class="btn-group m-b-5 m-r-5">
    <a href="javascript:;" class="btn btn-success"><i class="fa fa-envelope"></i> Notify </a>
    <a href="javascript:;" data-toggle="dropdown" class="btn btn-success dropdown-toggle">
        <span class="caret"></span>
    </a>
    <ul class="dropdown-menu pull-right">
        <li><a href="javascript:;" class="btn-dialog" data-url="notification" data-a="5" data-c="' . $po_code . '">Cancel and Reschedule Delivery</a></li>
        <li><a href="javascript:;" class="btn-dialog" data-url="notification" data-a="4" data-c="' . $po_code . '">Cancel Delivery</a></li>
    </ul>
    </div>'));
  }
  if (sizeof($_na)) {
    echo '
    <div class="panel text-white bg-danger mt-3">
      <div class="panel-body">
        <div class="row">
          <div class="col-md-12">
            <div class="row form-input-flat">';
            foreach($_na as $_n) {
              echo '<div class="col-sm-6 col-form-label">' . $_n . '</div>';
            }
            echo '
            </div>
          </div>
        </div>
      </div>
    </div>';
  }
  if (sizeof($_ns)) {
    echo '
    <div class="panel mt-3">
      <div class="panel-body">
        <div class="row">
          <div class="col-md-12">
            <div class="row form-input-flat">';
            foreach($_ns as $_n) {
              echo '<div class="col-sm-6 col-form-label">' . $_n . '</div>';
            }
            echo '
            </div>
          </div>
        </div>
      </div>
    </div>';
  }

$qbo_term_display = '';
$invoice_terms_log_line = '';
if (isset($t['po_status_id']) && (int)$t['po_status_id'] >= 5 && !empty($t['store_id'])) {
  require_once(BASE_PATH . 'inc/qbo.php');
  $store_rs = getRs("SELECT db FROM store WHERE store_id = ?", array($t['store_id']));
  $store_row = getRow($store_rs);
  if ($store_row && !empty($store_row['db'])) {
    $store_db = preg_replace('/[^a-z0-9_]/i', '', $store_row['db']);
    $vr = getRow(getRs("SELECT QBO_ID FROM {$store_db}.vendor WHERE vendor_id = ?", array($t['vendor_id'])));
    if (!$vr && !empty($t['vendor_id'])) {
      $vr = getRow(getRs("SELECT QBO_ID FROM {$store_db}.vendor WHERE id = ?", array($t['vendor_id'])));
    }
    $qbo_id = ($vr && !empty($vr['QBO_ID'])) ? trim($vr['QBO_ID']) : '';
    $pt_lookup = qbo_lookup_payment_term($store_row['db'], isset($t['payment_terms']) ? $t['payment_terms'] : null);
    $qbo_term_display = $pt_lookup['qbo_term_name'] !== '' ? $pt_lookup['qbo_term_name'] : '—';
    $invoice_terms_po = ($_payment_terms !== '' && $_payment_terms !== null) ? (int)$_payment_terms : '—';
    $vendor_term = qbo_get_vendor_term_ref($t['store_id'], $qbo_id);
    $qbo_terms_display = '—';
    if (!empty($vendor_term['term_ref_id'])) {
      $qbo_terms_display = qbo_get_term_name($t['store_id'], $vendor_term['term_ref_id']);
      if ($qbo_terms_display === '') {
        $qbo_terms_display = $vendor_term['term_ref_id'];
      }
    }
    $invoice_terms_log_line = 'Invoice Terms: ' . $invoice_terms_po . ' || QBO Payment Terms: ' . $qbo_terms_display;
  } else {
    $qbo_term_display = '—';
  }
}
if ($t['po_status_id'] > 3) {
echo '
<div class="panel mt-3">
  <div class="panel-body">
    <div class="row">
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Date Ordered: <b>' . $_date_ordered . '</b></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Date Received: <b>' . $_date_received . '</b></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Invoice #: <b>' . $_invoice_number . '</b></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Payment terms (days): <b>' . ($_payment_terms !== '' && $_payment_terms !== null ? (int)$_payment_terms : '—') . '</b></div>
        </div>
      </div>' . ($t['po_status_id'] == 5 ? '
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">QBO Payment Term: <b>' . htmlspecialchars($qbo_term_display) . '</b></div>
        </div>
      </div>' : '') . ($t['po_status_id'] == 5 ? '
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">AI Invoice Validation: ' . (isset($t['invoice_validated']) && (int)$t['invoice_validated'] === 1 ? '<span class="badge badge-success">Match</span>' : '<span class="badge badge-warning">No match</span>') . '</div>
        </div>
      </div>' : '') . ($t['po_status_id'] >= 5 && $invoice_terms_log_line !== '' ? '
      <div class="col-md-12 mt-2">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label text-muted small">' . htmlspecialchars($invoice_terms_log_line) . '</div>
        </div>
      </div>' : '') . '
    </div>
  </div>
</div>';
}


	
// Show action buttons when PO status > 1 (no longer require po_filename so buttons show locally without media/PDF)
if ($t['po_status_id'] > 1) {
echo '
<form id="f_po-data" class="po-data" action="" method="post">
<input type="hidden" name="c" value="' . $po_code . '" />
<input type="hidden" name="f" value="coa_filenames" />
<div class="mb-2 clearfix">';

if ($r['po_status_id'] == 3 and $r['po_event_status_id'] != 2) {
  echo '<div class="btn-group m-b-5 m-r-5"><a href="javascript:;" class="btn btn-dialog btn-warning" data-url="po-event" data-c="' . $po_code . '" data-title="Schedule Delivery" titlte="Schedule Delivery"><i class="fa fa-clock"></i> Schedule Delivery </a></div> ';
}

if (in_array($_Session->admin_group_id,array(1,3,11,12,15))) {
	echo '<div class="btn-group m-b-5 m-r-5">
  <a href="../module/notify_boh.php?poid=' . $_po_id . '" class="btn btn-info"><i class="fa fa-file"></i> Notify BOH </a>
  </div>
  <div class="btn-group m-b-5 m-r-5">
  <a href="../module/notify_andrew.php?poid=' . $_po_id . '" class="btn btn-info"><i class="fa fa-file"></i> Notify Andrew </a>
  </div>';
}
if ($t['po_status_id'] == 5) {
  echo '<div class="btn-group m-b-5 m-r-5"><button type="button" class="btn btn-primary btn-po-qbo-push" data-c="' . htmlspecialchars($po_code) . '"><i class="fa fa-external-link-alt"></i> Push to QuickBooks</button></div> ';
}
echo '
<div class="btn-group m-b-5 m-r-5">
<a href="javascript:;" class="btn btn-success"><i class="fa fa-file-pdf"></i> Purchase Order Document </a>
<a href="javascript:;" data-toggle="dropdown" class="btn btn-success dropdown-toggle">
    <span class="caret"></span>
</a>
<ul class="dropdown-menu pull-right">';
if (str_len($_po_filename)) {
echo '
    <li><a href="/po-download/' . $po_code . '" target="_blank"><i class="fa fa-angle-double-down mr-1"></i> Download</a></li>
    <li><a href="/ajax/po-pdf/' . $po_code . '" target="_blank"><i class="fa fa-sync mr-1"></i> Regenerate PDF</a></li>
    ';
} else {
echo '
    <li><a href="/ajax/po-pdf/' . $po_code . '" target="_blank"><i class="fa fa-sync mr-1"></i> Generate PDF</a></li>
    <li class="text-muted"><span class="mr-1">Download</span> (generate PDF first)</li>
    ';
}
    $is_po_email = 0;
    $_rv = getRs("SELECT is_po_email FROM {$_Session->db}.vendor WHERE vendor_id = ?", array($_vendor_id));
    if ($_v = getRow($_rv)) {
      $is_po_email = $_v['is_po_email'];
    }
    if ($is_po_email) {
    echo '<li><a href="javascript:;" class="btn-dialog" data-url="notification" data-a="1" data-c="' . $po_code . '"><i class="fa fa-envelope"></i> Email to Vendor</a></li>';
    }
    echo '
    <!--<li class="divider"></li>
    <li><a href="" class="btn-po-regenerate"><i class="fa fa-sync mr-1"></i> Regenerate</a></li>
    -->
</ul>
</div>' . iif($_invoice_filename, '
<div class="btn-group m-b-5 m-r-5">
<a href="javascript:;" class="btn btn-danger"><i class="fa fa-file"></i> Receiving Document </a>
<a href="javascript:;" data-toggle="dropdown" class="btn btn-danger dropdown-toggle">
    <span class="caret"></span>
</a>
<ul class="dropdown-menu pull-right">
    <li><a href="/po-download-r/' . $po_code . '" target="_blank"><i class="fa fa-angle-double-down mr-1"></i> Download</a></li>
    <li><a href="javascript:;"><i class="text-muted">Email to Vendor</i></a></li>
    <li><a href="javascript:;"><i class="text-muted">E-mail to Admin</i></a></li>
</ul>
</div>');

if ($t['po_status_id'] > 2) {
  if ($_coa_filenames) {
    echo '<div class="btn-group m-b-5 m-r-5">
      <a href="javascript:;" class="btn btn-info"><i class="fa fa-file"></i> Certificate of Analysis </a>
      <a href="javascript:;" data-toggle="dropdown" class="btn btn-info dropdown-toggle">
          <span class="caret"></span>
      </a>
      <ul class="dropdown-menu pull-right">';

          $__coa_filenames = json_decode($_coa_filenames, true);
          foreach($__coa_filenames as $_f) {
            echo '<li><a href="/download/po/' . $_f['name'] . '" target="_blank"><i class="fa fa-angle-double-down mr-1"></i> ' . $_f['original_name'] . '</a></li>';
          }
          if ($t['po_status_id'] == 3) {
          echo '
          <li class="divider"></li>
          <li><a href="" class="btn-edit-coa"><i class="fa fa-edit mr-1"></i> Edit</a></li>';
          }
          echo '
      </ul>
    </div>';
    }
    echo '<div class="float-right coa-filenames"' . iif($_coa_filenames, ' style="display:none;"') . '>' . uploadWidget('po', 'coa_filenames', $_coa_filenames, '', 'multiple', 'Upload COA file(s)...') . '</div>';
    
  }

echo '
</div>
</form>
';
}


?>

<div class="panel panel-default mb-3 hide">
  <div class="panel-heading">
    <h4 class="panel-title">PO Progress. Generated on <?php echo getLongDate($t['date_created']) . ' by ' . getAdminName($t['admin_id']); ?></h4>
  </div>
  <div class="panel-body">
    <div class="progress progress-striped m-b-10">
      <div class="progress-bar fulfillment-progress-percent" style="width: <?php echo $progress['percent'] . '%'; ?>"><?php echo $progress['percent'] . '%'; ?></div>
    </div>
    <div class="fulfillment-progress mt-1"><?php echo $progress['response']; ?></div>
  </div>
</div>

<div class="panel pagination-inverse m-b-0 clearfix">
<form action="" method="post">
<table class="table table-bordered table-striped po">
  <thead>
    <tr class="inverse">
      <th>Product</th>
      <?php echo iif($t['po_status_id'] == 1, '<th colspan="5" class="text-center hidden-sm">Level</th><th class="text-center d-lg-none">Level</th>'); ?>
      <th colspan="3" class="text-center">Purchase Order</th>
      <?php echo iif($t['po_status_id'] > 2, '<th>' . iif($t['po_status_id'] == 3, '<button class="btn btn-inverse btn-sm r-suggested-qty-all"><i class="ion-arrow-right-a"></i></button>') . '</th>
      <th colspan="3" class="text-center">Receiving</th>'); ?>
    </tr>
  </thead>
  <tbody class="products">
  <?php
  echo $_PO->ProductRows($rs, $_disaggregate_ids);
  ?>
  </tbody>
  <?php
  if (in_array($t['po_status_id'], array(1, 3))) {
  echo '
  <tbody class="btns">
  <tr><td colspan="10"><button class="btn btn-info btn-icon btn-dialog mr-1" data-url="po-custom-product" data-title="Add Existing Product to PO: ' . $po_number . '" data-c="' . $po_code . '" data-d="1"><i class="fa fa-database mr-1"></i> Add Existing Product</button><button class="btn btn-info btn-icon btn-dialog mr-1" data-url="po-custom-product" data-title="Add Custom Product to PO: ' . $po_number . '" data-c="' . $po_code . '"><i class="fa fa-plus mr-1"></i> Add Custom Product</button><button class="btn btn-info btn-icon btn-dialog mr-1" data-url="po-multiple-products" data-title="Add Custom Products to PO: ' . $po_number . '" data-c="' . $po_code . '"><i class="fa fa-plus mr-1"></i> Add Multiple Custom Products</button></td></tr>
  </tbody>';
  }
  ?>
  <tfoot class="po-foot">
  <?php echo $progress['foot']; ?>
  </tfoot>
</table>
</form>
</div>

<?php
echo iif($t['po_status_id'] == 3, '
<form action="" method="post" class="po-data">
<div class="panel panel-danger mt-3">
  <div class="panel-heading">
    <h4 class="panel-title">Required Information</h4>
  </div>
  <div class="panel-body">
    <div class="row">
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Date Received:</div>
          <div class="col-sm-12">
            <input type="hidden" id="date_ordered" value="' . $_date_ordered . '" />
            <div class="date">
              <div class="input-group date datepicker date-received" data-date-format="mm/dd/yyyy">
                <input type="text" class="form-control" placeholder="mm/dd/yy" id="date_received" name="date_received" value="' . $_date_received . '" />
                <div class="input-group-addon">
                  <i class="fa fa-calendar"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
		
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Invoice #:</div>
          <div class="col-sm-12"><input type="text" class="form-control" placeholder="" id="invoice_number" name="invoice_number" value="' . $_invoice_number . '" /></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Payment terms (days):</div>
          <div class="col-sm-12"><input type="number" class="form-control" placeholder="e.g. 30" id="payment_terms" name="payment_terms" value="' . ($_payment_terms !== '' && $_payment_terms !== null ? (int)$_payment_terms : '') . '" min="0" /></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Receiving Document:</div>
          <div class="col-sm-12">' . uploadWidget('po', 'invoice_filename', $_invoice_filename) . '</div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>');

echo iif($t['po_status_id'] == 4, '
<form action="" method="post" class="po-data">
<div class="panel panel-danger mt-3">
  <div class="panel-heading">
    <h4 class="panel-title">Required Information</h4>
  </div>
  <div class="panel-body">
    <div class="row">');

	echo iif(($t['po_type_id'] == 2 & $t['po_status_id'] == 4), '
	<div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Date Reconciled:</div>
          <div class="col-sm-12">
            <input type="hidden" id="date_ordered" value="' . $_date_ordered . '" />
            <div class="date">
              <div class="input-group date datepicker date-received" data-date-format="mm/dd/yyyy">
                <input type="text" class="form-control" placeholder="mm/dd/yy" id="date_received" name="date_received" value="' . $_date_received . '" />
                <div class="input-group-addon">
                  <i class="fa fa-calendar"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
		
      </div>');

	echo iif($t['po_status_id'] == 4, '
	  
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Invoice #:</div>
          <div class="col-sm-12"><input type="text" class="form-control" placeholder="" id="invoice_number" name="invoice_number" value="' . $_invoice_number . '" /></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Payment terms (days):</div>
          <div class="col-sm-12"><input type="number" class="form-control" placeholder="e.g. 30" id="payment_terms" name="payment_terms" value="' . ($_payment_terms !== '' && $_payment_terms !== null ? (int)$_payment_terms : '') . '" min="0" /></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12 col-form-label">Receiving Document:</div>
          <div class="col-sm-12">' . uploadWidget('po', 'invoice_filename', $_invoice_filename) . '</div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>');

if ($_po_status_id == 1) {
  echo iif(!str_len($_description), '<button class="btn btn-default btn-po-description mt-3"><i class="ion-chatbubble-working mr-1"></i> Add Comments / Special Instructions</button>') . '
  ' . iif(false, '<button class="btn btn-default btn-po-discounts mt-3" data-id="' . $_po_id . '"><i class="ion-redo mr-1"></i> Recalculate Discounts</button>') . '
  <div class="panel panel-default mt-3 po-data po-description"' . iif(!str_len($_description), ' style="display:none;"') . '>
  <div class="panel-heading">
    <h4 class="panel-title"><i class="ion-chatbubble-working mr-1"></i> Comments / Special Instructions</h4>
  </div>
  <div class="panel-body">    
    <div class="row">
      <div class="col-12">
        <textarea name="description" id="description" class="form-control description" rows="5">' . $_description . '</textarea>
        <small class="mt-1">Note: These comments will be displayed on the purchase order document. Use the "Files / Notes" tab for internal notes and comments.</small>
      </div>
    </div>
  </div>
</div>';
}

echo iif($t['back_caption'] || $t['status_caption'], '
<div class="panel panel-default text-white mt-3">
  <div class="panel-body">
    <div class="row">
      <div class="col-sm-6">' . iif($t['back_caption'], '    
      <span class="btn-status">
      <button type="button" class="btn btn-lg btn-secondary btn-po-status" data-c="' . $po_code . '" data-d="1" data-title="' . $t['back_description'] . '"><i class="fa fa-arrow-left mr-2"></i>' . $t['back_caption'] . '</button>') . '
      </span>
      </div>
      <div class="col-sm-6 text-right">' . iif($t['status_caption'], '   
        <div class="status" id="status_po"></div>
        <span class="btn-status">
        <b class="mr-3">Ready to Proceed?</b><button type="button" class="btn btn-lg btn-primary btn-po-status" data-c="' . $po_code . '" data-title="' . $t['status_description'] . '">' . $t['status_caption'] . '<i class="fa fa-arrow-right ml-2"></i></button>') . '
        </span>
      </div>
    </div>
  </div>
</div>');

echo iif($t['po_status_id'] == 1, '
<div class="panel panel-default mt-3">
  <div class="panel-heading">
    <h4 class="panel-title">PO Progress</h4>
  </div>
  <div class="panel-body">
    <div class="progress progress-striped m-b-10">
      <div class="progress-bar po-progress-percent" style="width: ' . $progress['percent'] . '%">' . $progress['percent'] . '%</div>
    </div>
    <div class="po-progress mt-1">' . $progress['response'] . '</div>
  </div>
</div>');

echo '<button class="hide po-permission btn-dialog" data-url="po-permission" data-c="' . $po_code . '" data-title="<i class=\'ion-locked mr-2\'></i> Authorization Required" data-save-text="Authorize">Get Permission</button>';
echo '<button class="hide po-permission-back btn-dialog" data-url="po-permission" data-c="' . $po_code . '" data-d="1" data-title="<i class=\'ion-locked mr-2\'></i> Authorization Required" data-save-text="Authorize">Get Permission</button>';
}
else {
  echo '<div class="alert alert-danger">No products found for selected vendor</div>';
}
}
else {
  echo '<div class="alert alert-danger">You do not have permission to view or edit ' . getDisplayName('po_type', $t['po_type_id']) . 's.</div>';
}
}
}
?>

</div>
<div class="tab-pane" id="notes">
    <?php echo $_Session->ShowFiles($rf, 'po', $po_code); ?>
  </div>
</div>
</div>

<?php
include_once('inc/footer.php'); 
?>