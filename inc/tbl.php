<?php
require_once ('./_config.php');

$_a = getVar('_a');

$TableName = $tbl['name'];
$ModalEditor = true;
if (isset($tbl['key'])) {
	$PrimaryKey = $tbl['key'];
}
else {
	$PrimaryKey = $tbl['name'] . '_id';
}
if (isset($tbl['title'])) {
	$PageTitle = $tbl['title'];
}
else {
	$PageTitle = nicefy($TableName);
}
if (isset($tbl['titles'])) {
	$PageTitles = $tbl['titles'];
}
else {
	if (substr($PageTitle, -1) == 's') {
		$PageTitles = $PageTitle . 'es';
	}
	elseif (substr($PageTitle, -1) != 'y') {
		$PageTitles = $PageTitle . 's';
	}
	else {
		$PageTitles = substr($PageTitle, 0, str_len($PageTitle) - 1) . 'ies';
	}
}
$page_title = $PageTitles;

if (isset($tbl['active'])) {
	$ActiveRecords = $tbl['active'];
}
else {
	$ActiveRecords = true;
}

if (isset($tbl['filters'])) {
	$_AllowFilters = $tbl['filters'];
}
else {
  $_AllowFilters = true;
}

if (isset($tbl['add'])) {
	$AllowAdd = $tbl['add'];
}
else {
	$AllowAdd = true;
}
if (isset($tbl['delete'])) {
	$AllowDelete = $tbl['delete'];
}
else {
	$AllowDelete = false;
}
if (isset($tbl['code'])) {
	$CodeFields = $tbl['code'];
}
else {
	$CodeFields = '';
}
if (isset($tbl['has_code']) || isset($tbl['include_code'])) {
	$CodeFields = $TableName . '_code,' . $TableName . '_name';
}
if (isset($tbl['url'])) {
	$DetailsUrl = $tbl['url'];
}
else {
	$DetailsUrl = '';
}
if (isset($tbl['add_url'])) {
	$AddUrl = $tbl['add_url'];
}
else {
	$AddUrl = null;
}
if (isset($tbl['edit'])) {
	$AllowEdit = $tbl['edit'];
}
else {
	$AllowEdit = true;
}
if (isset($tbl['view'])) {
	$AllowView = $tbl['view'];
}
else {
	$AllowView = false;
}
if (isset($tbl['modal'])) {
	$ModalUrl = $tbl['modal'];
}
else {
	$ModalUrl = null;
}
if (!isset($ListGroup)) {
	$ListGroup = '';
}
if (isset($tbl['cols'])) {
	$DisplayFields = $tbl['cols'];
}
else {
	$DisplayFields = array("{$TableName}_name,{$PageTitle}");
}
if (isset($tbl['rows'])) {
	$ModFields = $tbl['rows'];
}
else {
	$ModFields = $DisplayFields;
}
if (isset($tbl['required'])) {
	$RequiredFields = $tbl['required'];
}
else {
	$RequiredFields = '';
}
if (isset($tbl['sql'])) {
	$ListSql = $tbl['sql'];
}
else {
	$ListSql = '';
}
if (isset($tbl['where'])) {
	$Where = $tbl['where'];
}
else {
	$Where = '';
}
if (str_len($ListSql)) {
	$Where = '';
}
if (isset($LockId)) {
	$Where .= iif(str_len($Where), " AND") . " {$PrimaryKey} = {$LockId}";
}
else {
	$LockId = 0;
}


if (isset($tbl['limit_selection_a']) and getVarNum('a')) {
	$Where .= iif($Where, ' AND ') . str_replace('?', getVarNum('a'), $tbl['limit_selection_a']);
}
if (isset($tbl['limit_selection_b']) and getVarNum('b')) {
	$Where .= iif($Where, ' AND ') . str_replace('?', getVarNum('b'), $tbl['limit_selection_b']);
}
$_Where = $Where;


if (isset($tbl['local']) and $tbl['local']) {
  $Where .= iif(str_len($Where), ' AND ') . 't1.store_id = ' . $_Session->store_id;
}
$TablePrefix = '';
if (isset($tbl['store']) and $tbl['store']) {
  $TablePrefix = $_Session->db . '.';
}

$_Where = $Where;

$arr_FieldNames = $arr_AllFieldNames = $arr_SqlFieldNames = $arr_SearchFieldNames = $arr_AllSearchFieldNames = $arr_AllSqlFieldNames = $arr_OrigFieldNames = $arr_AllOrigFieldNames = array();
$arr_DisplayNames = $arr_AllDisplayNames = array();
$arr_DisplayTypes = $arr_AllDisplayTypes = array();
$arr_DisplayRefs = $arr_AllDisplayRefs = array();
$arr_DisplayWhere = $arr_AllDisplayWhere = array();
$arr_DisplayVisible = $arr_AllDisplayVisible = array();
$arr_TablePrefix = $arr_AllTablePrefix = array();
$arr_ModFieldNames = array();
$arr_ModDisplayNames = array();
$arr_ModFieldTypes = array();
$arr_ModFieldRefs = array();
$arr_ModFieldWhere = array();
$ModFieldNames = '';

array_unshift($DisplayFields, $PrimaryKey . ",ID,number");
if (isset($Table['enabled'])) {
	array_push($DisplayFields, "is_enabled,Enabled,bool");
}

$TableSql = $TablePrefix . $TableName . ' t1';
$_ti = 1;
foreach($DisplayFields as $d) {
	$a = explode(',', $d);
	array_push($arr_DisplayNames, (sizeof($a) > 1)?$a[1]:ucwords(str_replace('_id', '', $a[0])));
	if (sizeof($a) > 2) {
		array_push($arr_DisplayTypes, $a[2]);
	}
	else {
		array_push($arr_DisplayTypes, (strpos($a[0], '_id') === false)?'string':'key');
	}
	if (sizeof($a) > 3) {
		array_push($arr_DisplayRefs, ($a[3] == 'null')?null:$a[3]);
	}
	else {
		array_push($arr_DisplayRefs, null);
	}
	if (sizeof($a) > 4) {
		array_push($arr_DisplayWhere, ($a[4] == 'null')?null:$a[4]);
	}
	else {
		array_push($arr_DisplayWhere, null);
	}
	if (sizeof($a) > 5) {
		array_push($arr_DisplayVisible, ($a[5] == 'false')?false:true);
	}
	else {
		array_push($arr_DisplayVisible, true);
  }
  if (sizeof($a) > 6) {
	  array_push($arr_TablePrefix, $a[6] . '.');
  }
  else {
	  array_push($arr_TablePrefix, null);
}
  array_push($arr_OrigFieldNames, $a[0]);

  if (str_len($ListSql) == 0 and $arr_DisplayTypes[sizeof($arr_DisplayTypes) - 1] == 'key') {
    $_ti++;
    $_fn = (sizeof($a) > 3)?$a[3]:str_replace('_id', '', $a[0]);
    array_push($arr_FieldNames, $a[0] . '_name_display');
    if ($_fn == 'product') {
      array_push($arr_SqlFieldNames, 'CONCAT(t' . $_ti . '.name, \' (\', t' . $_ti . '.sku, \')\') AS product_id_name_display');
    }
    else {
      array_push($arr_SqlFieldNames, 't' . $_ti . '.' . (($TablePrefix)?'name':$_fn . '_name') . ' AS ' . $a[0] . '_name_display');
    }
    array_push($arr_SearchFieldNames, 't' . $_ti . '.' . (($TablePrefix)?'name':$_fn . '_name'));
    $TableSql = $TablePrefix . $_fn . ' t' . $_ti . ' RIGHT JOIN (' . $TableSql . ') ON t' . $_ti . '.' . $_fn . '_id = t1.' . $a[0];
  }
  else {
    array_push($arr_FieldNames, $a[0]);
    array_push($arr_SqlFieldNames, 't1.' . $a[0]);
    array_push($arr_SearchFieldNames, 't1.' . $a[0]);
  }
}

$arr_AllFieldNames = $arr_FieldNames;
$arr_AllOrigFieldNames = $arr_OrigFieldNames;
$arr_AllSqlFieldNames = $arr_SqlFieldNames;
$arr_AllSearchFieldNames = $arr_SearchFieldNames;
$arr_AllDisplayNames = $arr_DisplayNames;
$arr_AllDisplayTypes = $arr_DisplayTypes;
$arr_AllDisplayRefs = $arr_DisplayRefs;
$arr_AllDisplayWhere = $arr_DisplayWhere;
$arr_AllDisplayVisible = $arr_DisplayVisible;

$_rdsa = $_Session->GetModuleOptions($module_code);
$_rds = $_Session->GetModuleOptions($module_code, $_Session->admin_id, 10);
$_ds = $_Session->GetTableDisplaySettings($module_code);

if (isset($_ds['fields']) and sizeof($_ds['fields'])) {
  $_fields = $_ds['fields'];
	$arr_FieldNames = array($arr_FieldNames[0]);
	$arr_OrigFieldNames = array($arr_OrigFieldNames[0]);
	$arr_SqlFieldNames = array($arr_SqlFieldNames[0]);
	$arr_SearchFieldNames = array($arr_SearchFieldNames[0]);
	$arr_DisplayNames = array($arr_DisplayNames[0]);
	$arr_DisplayTypes = array($arr_DisplayTypes[0]);
	$arr_DisplayRefs  = array($arr_DisplayRefs[0]);
	$i = -1;
	foreach($arr_AllFieldNames as $d) {
		$i++;
    if ($i == 0) continue;
		if (in_array($d, $_fields)) {
			array_push($arr_FieldNames, $arr_AllFieldNames[$i]);
			array_push($arr_OrigFieldNames, $arr_AllOrigFieldNames[$i]);
			array_push($arr_SqlFieldNames, $arr_AllSqlFieldNames[$i]);
			array_push($arr_SearchFieldNames, $arr_AllSearchFieldNames[$i]);
			array_push($arr_DisplayNames, $arr_AllDisplayNames[$i]);
			array_push($arr_DisplayTypes, $arr_AllDisplayTypes[$i]);
			array_push($arr_DisplayRefs, $arr_AllDisplayRefs[$i]);
		}
	}
}
else {
	$arr_FieldNames = array($arr_FieldNames[0]);
	$arr_OrigFieldNames = array($arr_OrigFieldNames[0]);
	$arr_SqlFieldNames = array($arr_SqlFieldNames[0]);
	$arr_SearchFieldNames = array($arr_SearchFieldNames[0]);
	$arr_DisplayNames = array($arr_DisplayNames[0]);
	$arr_DisplayTypes = array($arr_DisplayTypes[0]);
	$arr_DisplayRefs  = array($arr_DisplayRefs[0]);
	$i = -1;
	foreach($arr_AllFieldNames as $d) {
		$i++;
    if ($i == 0) continue;
		if ($arr_AllDisplayVisible[$i]) {
			array_push($arr_FieldNames, $arr_AllFieldNames[$i]);
			array_push($arr_OrigFieldNames, $arr_AllOrigFieldNames[$i]);
			array_push($arr_SqlFieldNames, $arr_AllSqlFieldNames[$i]);
			array_push($arr_SearchFieldNames, $arr_AllSearchFieldNames[$i]);
			array_push($arr_DisplayNames, $arr_AllDisplayNames[$i]);
			array_push($arr_DisplayTypes, $arr_AllDisplayTypes[$i]);
			array_push($arr_DisplayRefs, $arr_AllDisplayRefs[$i]);
		}
	}
}
// filters
$_ds_filters = 0;
$i = 0;
foreach($arr_AllOrigFieldNames as $d) {
  if (isset($_ds[$d]) and is_array($_ds[$d])) {
    if ($arr_AllDisplayTypes[$i] == 'key') { 
      $Where .= iif(str_len($Where), " AND") . " FIND_IN_SET(t1.{$d}, '" . implode(',', $_ds[$d]) . "')";
      $_ds_filters++;
    }
    else if ($arr_AllDisplayTypes[$i] == 'json') {
      $Where .= iif(str_len($Where), " AND ") . " (";
      $___d = 0;
      foreach($_ds[$d] as $__d) {
        $Where .= iif($___d++, " OR ");
        //$Where .= "JSON_SEARCH(t1.{$d}, 'one', {$__d})";
        $Where .= "JSON_CONTAINS(t1.{$d}, '{$__d}', '$')";
      }
      $Where .= ")";
      $_ds_filters++;
    }
  }
  if (in_array($arr_AllDisplayTypes[$i], array('date', 'datetime', 'birthdate'))) {
    if (isset($_ds[$d . '_from']) and isDate($_ds[$d . '_from'])) {
      if ($TableName == 'daily_discount') {
		 $Where .= iif(str_len($Where), " AND") . " (t1.{$d} >= '" . toMySqlDT($_ds[$d . '_from']) .  "' OR ISNULL(t1.{$d}))";
	  }
		else {
		 $Where .= iif(str_len($Where), " AND") . " t1.{$d} >= '" . toMySqlDT($_ds[$d . '_from']) .  "'";
		}
      $_ds_filters++;
    }
    if (isset($_ds[$d . '_to']) and isDate($_ds[$d . '_to'])) {
      $Where .= iif(str_len($Where), " AND") . " t1.{$d} <= '" . toMySqlDT($_ds[$d . '_to']) .  "'";
      $_ds_filters++;
    }
  }
  $i++;
}

// is_enabled auto add
if ($ActiveRecords) {
  array_push($ModFields, "is_enabled,Enabled,bool");
  $ExtraFields = "is_enabled";
}

foreach($ModFields as $d) {
	$a = explode(',', $d);
	array_push($arr_ModFieldNames, $a[0]);
	array_push($arr_ModDisplayNames, (sizeof($a) > 1)?$a[1]:ucwords(str_replace('_id', '', $a[0])));
	if (sizeof($a) > 2) {
		array_push($arr_ModFieldTypes, $a[2]);
	}
	else {
		array_push($arr_ModFieldTypes, (strpos($a[0], '_id') === false)?'string':'key');
	}
	if (sizeof($a) > 3) {
		array_push($arr_ModFieldRefs, $a[3]);
	}
	else {
		array_push($arr_ModFieldRefs, null);
	}
	if (sizeof($a) > 4) {
		array_push($arr_ModFieldWhere, $a[4]);
	}
	else {
		array_push($arr_ModFieldWhere, null);
	}
	$ModFieldNames .= iif(str_len($ModFieldNames), ', ') . $a[0];
	//if (sizeof($a) > 2 and $a[2] == 'html') $ModalEditor = false;
}


if (!isset($ExtraFields)) {
	$ExtraFields = '';
}
if (!isset($Limit)) {
	$Limit = 0;
}
if (!isset($ListParams)) {
	$ListParams = array();
}
$PageName = $module_code; //$page_name;

$arr_CodeFields = explode(',', $CodeFields);
$arr_ModFieldValues = array_fill(0, sizeof($arr_ModFieldTypes)-1, null);


$ItemID = getVar('id', $LockId);
$meta_title = $PageTitles;
if ($ItemID) {
	$meta_title = 'Edit ' . $PageTitle;
}
$_id = getVar('id');
$__a = getVarNum('__a');
$__b = getVarNum('__b');

if ($ItemID > 0) {

	$rs = getRs("SELECT {$PrimaryKey}, {$ModFieldNames} FROM {$TablePrefix}{$TableName} t1 WHERE 1 = 1" . iif($ActiveRecords, " AND " . is_active()) . iif(str_len($_Where), " AND " . $_Where) . " AND {$PrimaryKey} = ?", array($ItemID));

	foreach ($rs as $row) {
		for ($i = 0; $i < count($arr_ModFieldNames); $i++) {
			if (in_array($arr_ModFieldNames[$i], array('birthdate', 'date_auction'))) {
				$arr_ModFieldValues[$i] = getShortDate($row[$arr_ModFieldNames[$i]]);
			}
			elseif (substr($arr_ModFieldNames[$i], 0, 5) == 'date_') {
				$arr_ModFieldValues[$i] = toHumanDT($row[$arr_ModFieldNames[$i]]); 
			}
			elseif ($arr_ModFieldNames[$i] == 'password') {
				$arr_ModFieldValues[$i] = ''; //$passMask;
			}
			else {
				$arr_ModFieldValues[$i] = $row[$arr_ModFieldNames[$i]];
			}
		}
	}
	$BtnText = 'Save Changes';
}
else {
	for ($i = 0; $i < count($arr_ModFieldNames); $i += 1) {
		if ($arr_ModFieldTypes[$i] == 1 || $arr_ModFieldTypes[$i] == 7 || $arr_ModFieldTypes[$i] == 95) {
			//$arr_ModFieldValues[$i] = null;		
		}
		elseif ($arr_ModFieldNames[$i] == 'is_enabled' || $arr_ModFieldNames[$i] == 'allow_comment') {
			//$arr_ModFieldValues[$i] = '';
			$arr_ModFieldValues[$i] = 1;		
		}
		else {
			//$arr_ModFieldValues[$i] = '';
			//$arr_ModFieldValues[$i] = '';
		}
		if (in_array($arr_ModFieldNames[$i], array('workflow_type_id', 'vessel_id', 'account_id'))) {
			$arr_ModFieldValues[$i] = getVarNum('a');
		}
		if ($TableName == 'po_event_setting' and $arr_ModFieldNames[$i] == 'date_start') {
			$arr_ModFieldValues[$i] = getVar('__a');
		}
		if ($TableName == 'po_event_setting' and $arr_ModFieldNames[$i] == 'date_end') {
			$_d = getVar('__a');
			$__d = strtotime($_d);
			$__d += (60 * 60 * 24) - 60;
			$arr_ModFieldValues[$i] = date('n/j/Y', $__d) . ' ' . date('g:i a', $__d);
		}
		if ($arr_ModFieldNames[$i] == 'po_event_setting_type_id') {
			$arr_ModFieldValues[$i] = 2;
		}
		
	}
	$BtnText = 'Add New ' . $PageTitle;
}


if (isset($_REQUEST['del']) and $AllowDelete) {
	$ItemID = getVarNum('del');
	$success = false;
	$response = 'Archived successfully.';
	$redirect = '{refresh}'; //$PageName; // . '.php';
	$swal = '';
	$html = '';
	setRs("UPDATE {$TablePrefix}{$TableName} SET is_active = 0 WHERE {$PrimaryKey} = ?", array($ItemID));
	$success = true;
	$error = 'Record deleted successfully';
	saveActivity('archive', $ItemID, $TableName, $PageTitle . ' archived');

	echo json_encode(array(
		'success' => $success,
		'response' => $response,
		'redirect' => $redirect
	));
}
else if (isset($_POST['PageName'])) {
	include_once('ajax/tbl-save.php');
}
else if ($_a == 'modal') {
	include_once('modal/tbl.php');
}
else if ($_a == 'list') {
	include_once('ajax/tbl.php');
}
else {

if ($TableName == 'daily_discount_log') {
	$footer = '<script language="javascript" type="text/javascript">
	$(document).ready(function(e) {
	bindForm("daily-discounts");
	});
	</script>';
}
if ($TableName == 'nabis') {
	$footer = '<script language="javascript" type="text/javascript">
	$(document).ready(function(e) {
		$(\'.btn-nabis-import\').on(\'click\', function(e) {
			$(this).hide();
			$(\'#f_nabis-import\').slideDown(500);
		});
		bindForm("nabis-import");
	});
	</script>';
}
include_once ('./inc/header.php');

if (str_len($_id) == 0) {

if ($TableName == 'daily_discount_log') {
	/*echo '<div class="panel panel-default">
	<div class="panel-body">
	<form id="f_daily-discounts" method="post">
	<label><input type="radio" name="store_id" value="' . $_Session->store_id . '" checked /> This store (' . getDisplayName('store', $_Session->store_id) . ')</label>
	<label><input type="radio" name="store_id" value="" /> All stores</label>
	<div class="status"></div>
	<div class="form-btns">
	<button type="submit" class="btn btn-lg btn-primary">Set Daily Discounts</button>
	</div>
	</div>
	</div>';*/
}
if ($TableName == 'nabis') {
	$_rn = getRs("SELECT nabis_id FROM nabis WHERE po_id IS NULL AND " . is_enabled());
	echo '<div' . iif(!sizeof($_rn), ' style="display:none;"') . '><button class="btn btn-warning btn-nabis-import mb-2">Import New File</button></div>';
	echo '<form action="" id="f_nabis-import" method="post"' . iif(sizeof($_rn), ' style="display:none;"') . '>
	<div class="panel bg-warning-1">
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-8">' . uploadWidget('nabis', 'filename', null, null, null, 'Select Nabis CSV file for import ...') . '</div>
				<div class="col-sm-4 text-right">
					<div class="status mb-2" id="status_import" style="display:none;"></div>
					<div class="form-btns"><button type="submit" class="btn btn-large btn-primary"><i class="fa fa-arrow-right"></i> Upload File</button></div>
				</div>
			</div>
		</div>
	</div>
	</form>';
}
if (sizeof($arr_AllFieldNames) > 2) {
echo '
  <form role="form" class="ajax-form display-options" id="f_table-display" method="post">
  <input type="hidden" name="module_code" value="' . $module_code . '" />
	<div class="panel panel-info-1">
    <div class="panel-heading">    
    <div class="panel-heading-btn">';
      if (sizeof($_rdsa)) {
      echo '
      <span id="status_table_display_load-1" style="display:inline-block;"></span>
      <div class="btn-group btn-group-xs-1 pull-right-1">
        <a href="#" class="btn btn-default btn-rounded btn-sm dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
          <i class="fa fa-redo"></i> Load display <b class="caret text-muted"></b>
        </a>
        <ul class="dropdown-menu dropdown-menu-right " role="menu">';
          foreach($_rds as $_d) {
            echo '<li><a href="" class="btn-table-display-load" data-c="' . $_d['module_option_code'] . '">' . shorten($_d['module_option_name'], 100) . '</a></li>';
          }
          echo '
          <li class="divider"></li>
          <li><a href="" class="btn-dialog" data-url="table-display" data-c="' . $module_code . '" data-hide-btns="true">View All</a></li>
        </ul>
      </div>';
      }
      echo '

      <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
      <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
    </div>
    <h4 class="panel-title">Display Options' . iif($_ds_filters, ' <span class="badge badge-warning"><i class="fa fa-exclamation-circle"></i> '. $_ds_filters . ' filter' . iif($_ds_filters != 1, 's') . ' applied</span>') . '</h4>
       
    </div>
		<div class="panel-body pb-0" style="display:none;">
			<div class="panel-option pt-1 pb-1 pl-4">Select visible columns</div>
			<div class="row form-input-flat">
				<div class="col-sm-12">';
				for ($i = 1; $i < sizeof($arr_AllFieldNames); $i++) {
					echo '
          <span class="nowrap"><input type="checkbox" value="' . $arr_AllFieldNames[$i] . '" id="field_' . $arr_AllFieldNames[$i] . '" name="fields[]" data-render="switchery" data-theme="info"' . iif(in_array($arr_AllFieldNames[$i], $arr_FieldNames), ' checked') . ' />
          <label for="field_' . $arr_AllFieldNames[$i] . '"><span class="m-l-5 m-r-10">' . $arr_AllDisplayNames[$i] . '</span></label></span>
          ';
				}
				echo '
				</div>
			</div><div class="clearfix"></div>';
      $_filters  ='';
      if ($_AllowFilters) {
        for ($i = 0; $i < sizeof($arr_AllDisplayTypes); $i++) {
          if (!in_array($TableName, array('category', 'daily_discount', 'daily_discount_report')) && in_array($arr_AllDisplayTypes[$i], array('key', 'json'))) {
            $_ds_f = array();
            //if (isset($_ds[$arr_AllOrigFieldNames[$i]]) and str_len($_ds[$arr_AllOrigFieldNames[$i]]) and is_numeric($_ds[$arr_AllOrigFieldNames[$i]])) {
            if (isset($_ds[$arr_AllOrigFieldNames[$i]]) and is_array($_ds[$arr_AllOrigFieldNames[$i]])) {
              $_ds_f = $_ds[$arr_AllOrigFieldNames[$i]];
            }
            $_filters .= '<div class="col-md-4 mb-2">
            <div>' . displayKeys($arr_AllOrigFieldNames[$i], $_ds_f, $TablePrefix . $arr_AllDisplayRefs[$i], $arr_AllDisplayWhere[$i]) . '</div>
            <small>' . $arr_AllDisplayNames[$i] . '</small></div>';
          }
        }
      }
      echo iif(str_len($_filters), '<div class="panel-option mt-3 pt-1 pb-1 pl-4">Column Filters</div><div class="row mt-2 form-input-flat">' . $_filters . '</div>');

			$_filters  ='';
			for ($i = 0; $i < sizeof($arr_AllDisplayTypes); $i++) {
				if (in_array($arr_AllDisplayTypes[$i], array('date', 'datetime', 'birthdate'))) {
          $_ds_from = $_ds_to = null;
          if (isset($_ds[$arr_AllOrigFieldNames[$i] . '_from']) and isDate($_ds[$arr_AllOrigFieldNames[$i] . '_from'])) {
            $_ds_from = $_ds[$arr_AllOrigFieldNames[$i] . '_from'];
          }
          if (isset($_ds[$arr_AllOrigFieldNames[$i] . '_to']) and isDate($_ds[$arr_AllOrigFieldNames[$i] . '_to'])) {
            $_ds_to = $_ds[$arr_AllOrigFieldNames[$i] . '_to'];
          }
          $_filters .= '<div class="row mt-2 form-input-flat">
          <div class="col-sm-4 mb-2 date">
            <div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
              <input type="text" class="form-control" placeholder="' . $arr_AllDisplayNames[$i] . ' from ..." name="' . $arr_AllOrigFieldNames[$i] . '_from" value="' . $_ds_from . '" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
            <small>' . $arr_AllDisplayNames[$i] . ' Range</small>
          </div>
          <div class="col-sm-4 mb-2 date">
            <div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
              <input type="text" class="form-control" placeholder="' . $arr_AllDisplayNames[$i] . ' to ..." name="' . $arr_AllOrigFieldNames[$i] . '_to" value="' . $_ds_to . '" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>
          </div>';
				}
			}
      echo iif(str_len($_filters), '<div class="panel-option mt-3 pt-1 pb-1 pl-4">Date Filters</div>' . $_filters);
    echo '
    <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
      <hr class="m-0" />
      <div class="p-10">
        <div class="row">
          <div class="col-sm-6">
          

            <span id="status_table_display" class="status"></span>
          </div>
          <div class="col-sm-6 text-right">
            <button type="submit" class="btn btn-warning mt-0">Update</button>
            <button type="button" class="btn btn-secondary btn-table-display-restore mt-0" data-c="' . $module_code . '">Restore Default</button>
          </div>
        </div>
      </div>
		</div>
		</div>
	</div>
  </form>';
  
}

echo '
<div class="panel pagination-inverse m-b-0 clearfix">
';
					$num_records = 0;
					$rs_size = getRs("SELECT COUNT({$PrimaryKey}) AS num_records FROM {$TablePrefix}{$TableName}" . iif($ActiveRecords, " WHERE is_active = 1"));
					if ($row_size = getRow($rs_size)) {
						$num_records = $row_size['num_records'];
					}
					echo '			
					<input type="hidden" name="ItemID" id="ItemID" value="' . $ItemID . '" />
					<input type="hidden" name="AllowAdd" id="AllowAdd" value="' . $AllowAdd . '" />
					<input type="hidden" name="PageName" id="PageName" value="' . $PageName . '" />
					<input type="hidden" name="TableName" id="TableName" value="' . $TablePrefix . $TableName . '" />
					<input type="hidden" name="PageTitle" id="PageTitle" value="' . $PageTitle . '" />
					<input type="hidden" name="PageTitles" id="PageTitles" value="' . $PageTitles . '" />
					' . iif($ModalUrl, '<input type="hidden" name="ModalUrl" id="ModalUrl" value="' . $ModalUrl . '" />') . '
					' . iif($AddUrl, '<input type="hidden" name="AddUrl" id="AddUrl" value="' . $AddUrl . '" />') . '
						<table id="' . $PageName . '" class="table datatable table-bordered table-hover t-' . $PageName . iif($num_records > 1000, ' large') . '">
							<thead>
								<tr class="inverse">';
									for ($i = 0; $i < sizeof($arr_FieldNames); $i++) {
										echo '<th>' . $arr_DisplayNames[$i] . '</th>';
									}
									if (str_len($DetailsUrl) || str_len($ModalUrl) || $AllowDelete || $AllowEdit || $AllowAdd) echo '<th>Actions</th>';
									echo '
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>

</div>
	</form>';
}
else {
echo '<div class="panel"><div class="panel-body">
<form method="post" action="" class="form-horizontal f-tbl t-tbl-inline" role="form" id="f_tbl">
<input type="hidden" name="PageName" id="PageName" value="' . $PageName . '" />
<input type="hidden" name="TableName" id="TableName" value="' . $TablePrefix . $TableName . '" />
<input type="hidden" name="' . $PrimaryKey . '" id="' . $PrimaryKey . '" value="' . $ItemID . '" />
<input type="hidden" name="RequiredFields" id="RequiredFields" value="' . $RequiredFields . '" />
<input type="hidden" name="redirect" id="redirect" value="' . getVar('redirect') . '" />
' . buildForm() . '</form>
</div></div>';
}

include_once ('./inc/footer.php');

}


function buildForm($submit = true) {
	
	global $arr_ModFieldNames;
	global $arr_ModDisplayNames;
	global $arr_ModFieldTypes;
	global $arr_ModFieldValues;
	global $arr_ModFieldRefs;
	global $arr_ModFieldWhere;
  global $TableName;
  global $TablePrefix;
	global $ItemID;
	global $__a;
	global $__b;
	global $PageName;
	global $BtnText;
	global $AddNote;
	global $AllowDelete;
	global $_Session;
	
	$ret = '';

  for ($i = 0; $i < count($arr_ModFieldNames); $i++) {
		if ($arr_ModFieldTypes[$i] != -2) {
			$ret .= '<div class="row input-row form-input-flat mb-2 row-' . $arr_ModFieldNames[$i] . '">';
			$ret .= '<div class="col-sm-2 text-right input-label">' . iif($arr_ModFieldTypes[$i] != 'bool', $arr_ModDisplayNames[$i] . ':') . '</div>';
			$ret .= '<div class="col-sm-10" id="' . $arr_ModFieldNames[$i] . '_block">';

			if ( true ) { //($TableName != 'user' || $i > 0) && ($arr_ModFieldTypes[$i] != -2) ) {		
				if ($arr_ModFieldTypes[$i] == 'display') {
					$ret .= '<b>' . nl2br($arr_ModFieldValues[$i]) . '</b>';		
				}
				elseif ($arr_ModFieldTypes[$i] == 'bool') {
					if ($arr_ModFieldNames[$i] == 'is_clearance' && $TableName == 'daily_discount' && $arr_ModFieldValues[$i] === null) {
						$arr_ModFieldValues[$i] = 1;
					}
					$ret .= '
					
					<input type="checkbox" value="1" id="' . $arr_ModFieldNames[$i] . '" name="' . $arr_ModFieldNames[$i] . '" data-render="switchery" data-theme="primary"' . iif($arr_ModFieldValues[$i] == '1', ' checked') . ' />
						<label for="' . $arr_ModFieldNames[$i] . '"><span class="m-l-5 m-r-10">' . $arr_ModDisplayNames[$i] . '</span></label>
					';
				}
				elseif ($arr_ModFieldTypes[$i] == 'text') {
					$ret .= '<textarea name="' . $arr_ModFieldNames[$i] . '" class="form-control" rows="3">'  . $arr_ModFieldValues[$i] . '</textarea>';
				}
				elseif ($arr_ModFieldTypes[$i] == 'html') {
					$ret .= '<textarea name="' . $arr_ModFieldNames[$i] . '" id="' . $arr_ModFieldNames[$i] . '" class="ckeditor form-control">'  . $arr_ModFieldValues[$i] . '</textarea>';
					if ($arr_ModFieldNames[$i] == 'message') {
						//$ret .= '<div class="alert alert-info mg-t-10"><b>Placeholders:</b><br />[first_name], [last_name], [email], [company], [title], [email_token], [password_token]</div>';
					}
					$ret .= '<a href="" class="btn btn-info ckeditor-gallery" data-toggle="modal" data-target="#modal" id="ckeditor_gallery_' . $arr_ModFieldNames[$i] . '">Insert Library Image</a>';
				}
				elseif ($arr_ModFieldNames[$i] == 'date_end' && $TableName == 'vessel_license') {
					$ret .= '
					<div class="input-group">
						<div class="input-group-prepend">
							<div class="input-group-text">
								<i class="fa fa-calendar tx-16 lh-0 op-6"></i>
							</div>
						</div>
						<input id="' . $arr_ModFieldNames[$i] . '_display" class="form-control" value="' . $arr_ModFieldValues[$i] . '" placeholder="DD/MM/YYYY" type="text" disabled />
						<input name="' . $arr_ModFieldNames[$i] . '" id="' . $arr_ModFieldNames[$i] . '" class="form-control" value="' . $arr_ModFieldValues[$i] . '" type="hidden" />
					</div>';
				}
				elseif ($arr_ModFieldNames[$i] == 'birthdate') {
					$ret .= '
					<div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
						<input type="text" class="form-control" placeholder="dd/mm/yyyy" id="' . $arr_ModFieldNames[$i] . '"  name="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" />
						<div class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</div>
					</div>';
				}	
				elseif ($arr_ModFieldTypes[$i] == 'date') {
					$ret .= '
					<div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
						<input type="text" class="form-control" placeholder="dd/mm/yyyy" id="' . $arr_ModFieldNames[$i] . '"  name="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" />
						<div class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</div>
					</div>';
				}	
				elseif ($arr_ModFieldTypes[$i] == 'datetime') {
					$ret .= '
					<div class="input-group date datetimepicker dt-' . $arr_ModFieldNames[$i] . '" data-date-format="mm/dd/yyyy">
						<input type="text" class="form-control" placeholder="mm/dd/yyyy hh:mm a" id="' . $arr_ModFieldNames[$i] . '"  name="' . $arr_ModFieldNames[$i] . '" value="' . toHumanDT($arr_ModFieldValues[$i]) . '" />
						<div class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</div>
					</div>';
				}
				elseif ($arr_ModFieldTypes[$i] == 'percent') {
					$ret .= '<div class="input-group"><input type="text" id="' . $arr_ModFieldNames[$i] . '"  name="' . $arr_ModFieldNames[$i] . '" value="' . (float)$arr_ModFieldValues[$i] . '" class="form-control" /><div class="input-group-append"><div class="input-group-text">%</div></div></div>';
				}
				
				elseif ($arr_ModFieldTypes[$i] == 'money') {
					$ret .= '<div class="input-group">
						<div class="input-group-prepend">
							<div class="input-group-text">
								$
							</div>
						</div>
						<input type="text" name="' . $arr_ModFieldNames[$i] . '" value="' . number_format($arr_ModFieldValues[$i], 2) . '" class="form-control" />
					</div>';
				}
				elseif ($arr_ModFieldTypes[$i] == 8) {
					$ret .= '<div class="input-group"><span class="input-group-addon"><i class="fa fa-' . $arr_ModFieldValues[$i] . '"></i></span><input type="text" name="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" class="form-control" /></div>';
				}
				elseif ($arr_ModFieldTypes[$i] == 'file') {
					$ret .= uploadWidget($TableName, $arr_ModFieldNames[$i], $arr_ModFieldValues[$i], ((str_len($arr_ModFieldValues[$i]))?'/media/' . $TableName . '/' . $arr_ModFieldValues[$i]:''));	
		
					//$ret .= '</div>';
				}
				elseif ($arr_ModFieldTypes[$i] == 'files') {
          $ret .= uploadWidget($TableName, $arr_ModFieldNames[$i], $arr_ModFieldValues[$i], ((str_len($arr_ModFieldValues[$i]))?'/media/' . $TableName . '/' . $arr_ModFieldValues[$i]:''), 'multiple');
		
					//$ret .= '</div>';
				}
				elseif ($__a && $arr_ModFieldNames[$i] == 'weekday_id' && in_array($TableName, array('daily_discount'))) {
					$rs1 = getRs("SELECT * FROM {$_Session->db}.weekday WHERE weekday_id = ?", array($__a));
					if ($r1 = getRow($rs1)) {
						$ret .= '<b>' . $r1['name'] . '</b>
						<input type="hidden" id="weekday_id" name="weekday_id" value="' . $r1['weekday_id'] . '" />';
					}
				}

				elseif (in_array($arr_ModFieldNames[$i], array('ward_id'))) {
          $_district_id = null;
          $rs1 = getRs("SELECT district_id FROM ward WHERE ward_id = ?", array($arr_ModFieldValues[$i]));
          if ($r1 = getRow($rs1)) {
            $_district_id = $r1['district_id'];
          }
          else {
            $rs1 = getRs("SELECT district_id FROM {$TableName} WHERE {$TableName}_id = ?", array($ItemID));
            if ($r1 = getRow($rs1)) $_district_id = $r1['district_id'];
          }
					$ret .= '<div class="input-group"><div class="input-group-prepend" style="width:45%">' . displayKey('_district_id', $_district_id, 'district', null, 'Select District') . '</div><div class="input-group-prepend" style="width:5%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:50%">';

          $rs1 = getRs("SELECT * FROM ward WHERE " . is_active() . " ORDER BY sort, ward_name");
          $ret .= '<select class="form-control select2" id="_' . $arr_ModFieldNames[$i] . '" name="' . $arr_ModFieldNames[$i] . '">';
					$_v = $_w = '<option data-district="0" value="">- Select Ward -</option>';
					foreach($rs1 as $r1) {
						if ($_district_id == $r1['district_id']) {
              $_v .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $r1['ward_name'] . '</option>';
            }
						$_w .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $r1['ward_name'] . '</option>';
					}
					$ret .= $_v . '</select></div></div><div id="__' . $arr_ModFieldNames[$i] . '" class="hide">' . $_w . '</div>';
				}
				elseif (in_array($arr_ModFieldNames[$i], array('settlement_id'))) {
          $_district_id = $_ward_id = null;
          $rs1 = getRs("SELECT w.district_id, w.ward_id FROM ward w INNER JOIN settlement s ON s.ward_id = w.ward_id WHERE s.settlement_id = ?", array($arr_ModFieldValues[$i]));
          if ($r1 = getRow($rs1)) {
            $_district_id = $r1['district_id'];
            $_ward_id = $r1['ward_id'];
          }
          else {
            $rs1 = getRs("SELECT district_id, ward_id FROM {$TableName} WHERE {$TableName}_id = ?", array($ItemID));
            if ($r1 = getRow($rs1)) {
              $_district_id = $r1['district_id'];
              $_ward_id = $r1['ward_id'];
            }
          }
					$ret .= '<div class="input-group"><div class="input-group-prepend" style="width:30%">' . displayKey('_district_id', $_district_id, 'district', null, 'Select District') . '</div><div class="input-group-prepend" style="width:5%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:30%">';

          $rs1 = getRs("SELECT * FROM ward WHERE " . is_active() . " ORDER BY sort, ward_name");
          $ret .= '<select class="form-control select2" id="_ward_id" name="_ward_id">';
					$_v = $_w = '<option data-district="0" value="">- Select Ward -</option>';
					foreach($rs1 as $r1) {
						if ($_district_id == $r1['district_id']) {
              $_v .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $_ward_id, ' selected') . '>' . $r1['ward_name'] . '</option>';
            }
						$_w .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $r1['ward_name'] . '</option>';
          }
					$ret .= $_v . '</select></div>';
          
          $rs2 = getRs("SELECT * FROM settlement WHERE " . is_active() . " ORDER BY sort, settlement_name");
          $ret .= '<div class="input-group-prepend" style="width:5%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:30%"><select class="form-control select2" id="_settlement_id" name="settlement_id">';
					$_x = $_y = '<option data-ward="0" value="">- Select Settlement -</option>';
					foreach($rs2 as $r2) {
						if ($_ward_id == $r2['ward_id']) {
              $_x .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $r2['settlement_name'] . '</option>';
            }
						$_y .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $r2['settlement_name'] . '</option>';
          }

					$ret .= $_x . '</select></div></div><div id="__ward_id" class="hide">' . $_w . '</div><div id="__settlement_id" class="hide">' . $_y . '</div>';
				}
				elseif ($TableName != 'daily_discount' && in_array($arr_ModFieldNames[$i], array('store_ids'))) {
					$__store_ids = array();
					if (isJson($arr_ModFieldValues[$i])) {
						$__store_ids = json_decode($arr_ModFieldValues[$i], true);
					}
					$__rs = getRs("SELECT store_id, store_name, db FROM store WHERE " . is_active() . " ORDER BY sort, store_id");
					foreach($__rs as $__s) {
						$__selected = false;
						$__store_id = $__employee_id = null;
						$__re = getRs("SELECT employee_id, firstName, lastName, email FROM {$__s['db']}.employee ORDER BY lastName, firstName");
						foreach($__store_ids as $__store) {
							$__store_id = isset($__store['store_id'])?$__store['store_id']:null;
							if ($__s['store_id'] == $__store_id) {
								$__selected = true;
								$__employee_id = isset($__store['employee_id'])?$__store['employee_id']:null;
							}
						}
						$ret .= '<div class="row mb-2"><div class="col-sm-6"><span class="__store_id"><input type="checkbox" name="_store_id[]" id="__store_id_' . $__s['store_id'] . '" data-id="' . $__s['store_id'] . '" value="' . $__s['store_id'] . '" data-render="switchery" data-theme="info"' . iif($__selected, ' checked') . ' /><label for="__store_id_' . $__s['store_id'] . '" class="ml-2">' . $__s['store_name'] . '</label></span></div><div class="col-sm-6 __employees_' . $__s['store_id'] . '"' . iif(!$__selected, ' style="display:none;"') . '"><select name="_employee_id_' . $__s['store_id'] . '" class="form-control select2" style="width="100%"><option value="">- Select Employee -</option>';
						foreach($__re as $__e) {
							$ret .= '<option value="' . $__e['employee_id'] . '"' . iif($__e['employee_id'] == $__employee_id, ' selected') . '>' . $__e['firstName'] . ' ' . $__e['lastName'] . '</option>';
						}
						$ret .= '</select></div></div>';
					}
				}
				elseif ($arr_ModFieldNames[$i] == 'blaze_id' && $TableName == 'vendor') {

					$ret .= '<select name="blaze_id" id="blaze_id" class="form-control select2"><option value="">- Select -</option>';
					$_rv = getRs("SELECT id, name FROM {$_Session->db}.vendor WHERE " . is_enabled() . " ORDER BY name");
					foreach($_rv as $_v) {
						$ret .= '<option value="' . $_v['id'] . '"' . iif($_v['id'] == $arr_ModFieldValues[$i], ' selected') . '>' . $_v['name'] . '</option>';
					}
					$ret .= '</select>';

				}
							
				elseif ($arr_ModFieldNames[$i] == 'blaze_ids' && $TableName == 'vendor') {


					$_blaze_ids = isJson($arr_ModFieldValues[$i])?json_decode($arr_ModFieldValues[$i]):array();

					$ret .= '<select name="blaze_ids[]" id="blaze_ids" class="multiple-select form-control" multiple="multiple" data-placeholder="Select Vendor(s)">';
					$_rv = getRs("SELECT id, name FROM {$_Session->db}.vendor WHERE " . is_enabled() . " ORDER BY name");
					foreach($_rv as $_v) {
						$ret .= '<option value="' . $_v['id'] . '"' . (in_array($_v['id'], $_blaze_ids ?? array())? ' selected':'') . '>' . $_v['name'] . '</option>';
					}
					$ret .= '</select>';

				}
				elseif ($arr_ModFieldNames[$i] == 'daily_discount_report_specialty_id') {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i] ?? 1, $TablePrefix . $arr_ModFieldRefs[$i], $arr_ModFieldWhere[$i]);
				}
				elseif ($arr_ModFieldNames[$i] == 'daily_discount_report_type_id') {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i] ?? 1, $TablePrefix . $arr_ModFieldRefs[$i], $arr_ModFieldWhere[$i]);
					$_date_start = $_date_end = null;
					$__rs = getRs("SELECT date_start, date_end FROM daily_discount_report WHERE daily_discount_report_id = ?", $ItemID);
					if ($__r = getRow($__rs)) {
						$_date_start = toHumanDT($__r['date_start']);
						$_date_end = toHumanDT($__r['_date_end']);
					}
					$ret .= '<div class="pt-2 daily_discount_report_type_id_3"' . iif($arr_ModFieldValues[$i] != 3, ' style="display:none;"') . '>
					<div class="row">
						<div class="col-md-5">
							<div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
								<input type="text" class="form-control" placeholder="dd/mm/yyyy" id="date_start"  name="date_start" value="' . $_date_start . '" />
								<div class="input-group-addon">
									<i class="fa fa-calendar"></i>
								</div>
							</div>
						</div>
						<div class="col-md-2 text-center">to</div>
						<div class="col-md-5">
							<div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
								<input type="text" class="form-control" placeholder="dd/mm/yyyy" id="date_end"  name="date_end" value="' . $_date_end . '" />
								<div class="input-group-addon">
									<i class="fa fa-calendar"></i>
								</div>
							</div>
						</div>
					</div>					
					</div>';
				}
				elseif (in_array($TableName, array('daily_discount', 'daily_discount_report', 'c_markup_override', 'dbe_markup_override', 'casepack')) && $arr_ModFieldNames[$i] == 'brand_id') {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], 'blaze1.brand');
				}
				elseif ($TableName == 'daily_discount' && $arr_ModFieldNames[$i] == 'linked_brand_id') {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], 'blaze1.brand');
				}
				elseif (in_array($TableName, array('daily_discount', 'daily_discount_report', 'c_markup_override', 'dbe_markup_override', 'casepack')) && in_array($arr_ModFieldNames[$i],array('category_id'))) {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], 'blaze1.category');
				}
				elseif (in_array($TableName, array('daily_discount', 'daily_discount_report', 'c_markup_override', 'dbe_markup_override', 'casepack')) && in_array($arr_ModFieldNames[$i],array('category_ids'))) {
					$ret .= displayKeys($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], 'blaze1.category', $arr_ModFieldWhere[$i]);
				}
				elseif ($arr_ModFieldNames[$i] == 'master_category_id') {
					$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], 'blaze1.category');
				}
				elseif ($arr_ModFieldTypes[$i] == 'key') {
					if (true or !($TableName == 'daily_discount' and $ItemID)) {
						$ret .= displayKey($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], $TablePrefix . $arr_ModFieldRefs[$i], $arr_ModFieldWhere[$i]);
					}
					else {
						$ret .= '<input type="hidden" name="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" /><b>' . getDisplayName($TablePrefix . $arr_ModFieldRefs[$i], $arr_ModFieldValues[$i], 'name', $arr_ModFieldRefs[$i] . '_id') . '</b>';
					}
				}
				elseif ($arr_ModFieldTypes[$i] == 'recur') {
					$ret .= dboDropDownRecur(str_replace('_id', '', $arr_ModFieldNames[$i]), $arr_ModFieldValues[$i], 0, '', 'Select');
				}
				elseif ($arr_ModFieldTypes[$i] == 12) {
					$nav = str_replace('parent_', '', $arr_ModFieldNames[$i]);
					$nav = str_replace('_id', '', $nav);
					$ret .= dboDropDownRecur($nav, $arr_ModFieldValues[$i], 0, '', 'None', 'parent_' . $nav . '_id');
				}
		
				elseif ($arr_ModFieldNames[$i] == 'module_ids' and $TableName == 'admin_group') {
					$a_modules = array();
					if (isJson($arr_ModFieldValues[$i])) $a_modules = json_decode($arr_ModFieldValues[$i]);
					$ret .= $_Session->ModulePermissionOptions($a_modules);
				}
				elseif ($arr_ModFieldTypes[$i] == 'json') {
					$ret .= displayKeys($arr_ModFieldNames[$i], $arr_ModFieldValues[$i], $TablePrefix . $arr_ModFieldRefs[$i], $arr_ModFieldWhere[$i]);
				}
				elseif ($arr_ModFieldNames[$i] == 'employees') {
					$ret .= '<div class="row">';
					$total = 0;
					$a_employees = array();
					if (str_len($arr_ModFieldValues[$i])) {
						$a_employees = json_decode($arr_ModFieldValues[$i], true);
					}
					foreach(array('_full_time', '_temporary') as $temp) {
						$ret .= '<div class="col-md-6"><div class="row">';
						$ret .= '<div class="col-12 pb-2"><b>' . nicefy($temp) . ' Employees</b></div>';
						foreach(array('local', 'international') as $local) {
							foreach(array('_male', '_female') as $gender) {
								$f = $local . $gender . $temp;
								$fe = $local . $gender;
								if (isset($a_employees[$f])) {
									$$f = $a_employees[$f];
									$total += $$f;
								}
								else {
									$$f = null;
								}

								$ret .= '<div class="col-12"><input type="text" class="form-control" name="' . $f . '" placeholder="' . nicefy($fe) . '" value="' . $$f . '" /><div class="mb-3"><small>' . nicefy($fe) . '</small></div></div>';
							}
						}
						$ret .= '</div></div>';
					}
					$ret .= '<div class="col-12">Total Employees: ' . $total . '</div>';
					$ret .= '</div>';
				}
				
				
				else {
					
					if (false and $arr_ModFieldNames[$i] == 'account_id') {
						$rs_1 = getRs("SELECT account_id, account_name FROM account WHERE account_id = ?", array($arr_ModFieldValues[$i]));
						if ($row_1 = getRow($rs_1)) {
							$ret .= '<input type="text" class="form-control" value="' . $row_1['account_name'] . '" disabled /><input type="hidden" name="account_id" value="' . $row_1['account_id'] . '" />';
						}
					}
					elseif ($arr_ModFieldNames[$i] == 'country') {
						$ret .= selectCountry($arr_ModFieldValues[$i]);
					}
					elseif ($arr_ModFieldNames[$i] == 'password') {
						$ret .= '<input type="text" name="' . $arr_ModFieldNames[$i] . '" id="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" class="form-control" placeholder="Change password ..." />';
					}
					else {
						$ret .= '<input type="text" name="' . $arr_ModFieldNames[$i] . '" id="' . $arr_ModFieldNames[$i] . '" value="' . $arr_ModFieldValues[$i] . '" class="form-control" placeholder="' . iif($arr_ModFieldNames[$i] == 'leadtime', $_Session->GetSetting('leadtime') . ' (default)') . iif($arr_ModFieldNames[$i] == 'target_days_on_hand', $_Session->GetSetting('target-days-on-hand') . ' (default)') . iif($arr_ModFieldNames[$i] == 'scheduling_window', $_Session->GetSetting('scheduling-window') . ' (default)') . '" />';
					}
				}
	
			$ret .= '</div></div>';
			}
		}
	}

	if ($submit) {
	$ret .= '
	<div class="row form-footer">
		<div class="col-sm-2"></div>
		<div class="col-sm-10">
			<div class="status" id="tbl_status"></div>
			<button type="submit" class="btn btn-primary">' . $BtnText . '</button>
			<button type="reset" class="btn btn-default">Reset</button>
			<div class="pull-right">' . iif($AllowDelete && $ItemID, '<a href="" rel="' . $ItemID . '" class="btn btn-danger btn-delete">Delete</a>') . '</div>
			';
			if (str_len($AddNote) and $ItemID == 0) {
				$ret .= '<div class="mt-10"><div class="alert alert-info">' . $AddNote . '</div></div>';
			}
		$ret .= '
		</div>
	</div>';
	}

	
	if ($ItemID and $TableName == 'message') {
		setRs("UPDATE message SET is_new = 0 WHERE message_id = ?", array($ItemID));
	}
	
	return $ret;
	
}
?>