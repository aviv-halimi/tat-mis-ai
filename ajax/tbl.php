  <?php

$start = 0;
$length = 0;
$kw = '';
$sort_col = 0;
$sort_dir = '';

if (isset($_POST['start'])) {
	$start = getVarNum('start', 0);
	$length = getVarNum('length', 10);
	$kw = trim($_POST['search']['value']);
	$sort_col = $_POST['order'][0]['column'];
	$sort_dir = $_POST['order'][0]['dir']; //	asc
}

$sql_kw = '';
$params_kw = array();

if (str_len($ListSql) == 0) {
	//$sql = "SELECT {$PrimaryKey},{$FieldNames}" . iif(str_len($ExtraFields), ",{$ExtraFields}") . " FROM {$TableName} WHERE is_active = 1" . iif(str_len($Where), " AND {$Where}");
	$sql = "SELECT " . implode(',', $arr_SqlFieldNames) . iif(str_len($DetailsUrl), ", t1." . $TableName . "_code") . ", " . iif($ActiveRecords, "t1.is_enabled", "1") . " AS is_enabled FROM {$TableSql} WHERE 1 = 1" . iif($ActiveRecords, " AND t1.is_active = 1") . iif(str_len($Where), " AND {$Where}");
}
else {
	$sql = $ListSql;
}
if ($length) {
	if (str_len($kw)) {
		$a_kw = explode(' ', $kw);
    foreach($a_kw as $_kw) {
      $sql_kw .= iif(str_len($sql_kw), " AND ") . "(";
      $k = 0;
		  foreach($arr_SearchFieldNames as $f) {
        $sql_kw .= iif($k++ > 0, " OR ") . " {$f} LIKE ? ";
				array_push($ListParams, '%' . $_kw . '%');
        array_push($params_kw, '%' . $_kw . '%');
      }
      $sql_kw .= ")";
		}
		$sql_kw = " AND ({$sql_kw})";
		$sql .= $sql_kw;
	}
	if (str_len($ListGroup)) {
		$sql .= ' ' . $ListGroup;
	}
	if (sizeof($arr_SqlFieldNames) > $sort_col) {
		$__order = $arr_SqlFieldNames[$sort_col];
		$a__order = explode(' AS ', $__order);
		if (sizeof($a__order) == 2) {
			$__order = $a__order[0];
		}
		$sql .= " ORDER BY " . $__order . iif($sort_dir == 'desc', " DESC");
	}
	if ($length > 0) $sql .= " LIMIT {$start}, {$length}";
}

$iTotalRecords = $length;
$iTotalDisplayRecords = 0;
$rs = getRs("SELECT COUNT(t1.{$PrimaryKey}) AS num_records FROM {$TableSql} WHERE 1 = 1" . iif($ActiveRecords, " AND t1.is_active = 1") . iif(str_len($Where), " AND {$Where}"));
if ($row = getRow($rs)) {
	$iTotalRecords = $iTotalDisplayRecords = $row['num_records'];
}
if (str_len($kw)) {
	if (str_len($ListSql)) {
		$rs = getRs($ListSql . $sql_kw . $ListGroup, $ListParams);
		$iTotalDisplayRecords = sizeof($rs);
	}
	else {
		$rs = getRs("SELECT COUNT(t1.{$PrimaryKey}) AS num_records FROM {$TableSql} WHERE 1 = 1" . iif($ActiveRecords, " AND t1.is_active = 1") . iif(str_len($Where), " AND {$Where}") . $sql_kw, $params_kw);
		if ($row = getRow($rs)) {
			$iTotalDisplayRecords = $row['num_records'];
		}
	}
}

$rs = getRs($sql, $ListParams);
//$iTotalDisplayRecords = sizeof($rs);

if ($Limit) {
	if ($length > 0) $sql .= " LIMIT {$Limit}, {$length}";
}

$rs = getRs($sql . $ListGroup, $ListParams);

$data = array();
foreach($rs as $row) {
	$record = array();
	for ($i = 0; $i < count($arr_FieldNames); $i++) {
		if ($i > 0) {
			array_push($record, formatField($row, $arr_FieldNames[$i], $arr_DisplayTypes[$i], $arr_DisplayRefs[$i]));
		}
		else {
			if (str_len($DetailsUrl)) {
				array_push($record, '<a href="/' . $DetailsUrl . '/' . $row[$TableName . '_code'] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
			}
			else if (str_len($ModalUrl)) {
				array_push($record, '<a href="" class="btn-dialog" data-url="' . $ModalUrl . '" data-title="Edit ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
			}
			else if ($AllowEdit) {
				if ($ModalEditor) {
					array_push($record, '<a href="" class="btn-table-dialog" data-url="' . $PageName . '" data-title="Edit ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
				}
				else {
					array_push($record, '<a href="' . $PageName . '?id=' . $row[$arr_FieldNames[0]] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
				}
			}
			else if ($AllowView) {
				if ($ModalEditor) {
					array_push($record, '<a href="" class="btn-table-dialog" data-hide-btns="true" data-url="' . $PageName . '" data-title="View ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
				}
				else {
					array_push($record, '<a href="' . $PageName . '?id=' . $row[$arr_FieldNames[0]] . '">' . $row[$arr_FieldNames[$i]] . '</a>');
				}
			}
			else {
				array_push($record, $row[$arr_FieldNames[$i]]); //formatField($row, $arr_FieldNames[$i], $arr_DisplayTypes[$i], $arr_DisplayRefs[$i]));
			}
		}
	}
	$dd = '<div class="nowrap">' . (str_len($DetailsUrl)?'<a href="/' . $DetailsUrl . '/' . $row[$TableName . '_code'] . '" class="btn btn-dark btn-xs p-l-10 p-r-10 btn"><i class="ion-navicon-round"></i> Details</a> ':'');
	if ($AllowEdit) {
		if ($ModalEditor) {
			$dd .= '<a href="" class="btn btn-default btn-xs p-l-10 p-r-10 btn' . iif(str_len($ModalUrl) == 0, '-table') . '-dialog" data-url="' . iif(str_len($ModalUrl) == 0, $PageName, $ModalUrl) . '" data-title="Edit ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '"><i class="ion-edit"></i> Edit</a>';
		}
		else {
			$dd .= '<a href="' . $PageName . '?id=' . $row[$arr_FieldNames[0]] . '" class="btn btn-info btn-sm btn-icon"><i class="ion-edit"></i></a>';

		}
	}
	elseif ($AllowView) {
		if ($ModalEditor) {
			$dd .= '<a href="" class="btn btn-default btn-xs p-l-10 p-r-10 btn' . iif(str_len($ModalUrl) == 0, '-table') . '-dialog" data-url="' . iif(str_len($ModalUrl) == 0, $PageName, $ModalUrl) . '" data-hide-btns="true" data-title="View ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '"><i class="ion-edit"></i> View</a>';
		}
		else {
			$dd .= '<a href="' . $PageName . '?id=' . $row[$arr_FieldNames[0]] . '" class="btn btn-info btn-sm btn-icon"><i class="ion-edit"></i></a>';

		}
	}
  else if (str_len($ModalUrl)) {
    array_push($record, '<a href="" class="btn btn-info btn-dialog" data-url="' . $ModalUrl . '" data-title="View ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '" data-hide-btn="true">View</a>');
  }
  if (isset($tbl['links'])) {
	  foreach($tbl['links'] as $_link) {
		  $_show_link = true;
		  if (isset($_link['condition'])) {
			  $_show_link = true;
			  foreach($_link['condition'] as $__c) {
				  if ($__c[1] == 'NOT NULL') {
					  if (!$row[$__c[0]]) $_show_link = false;
				  }
				  else {
					  if ($row[$__c[0]] != $__c[1]) $_show_link = false;
				  }
			  }
		  }
		  $code = getIdCode($TableName, $row[$TableName . '_id']);
		  if ($TableName == 'daily_discount_report') {
			$dd .= '<span class="span-daily-discount-report-' . $row[$TableName . '_id'] . '">';
			$__rd = getRs("SELECT r.daily_discount_report_id, r.daily_discount_report_code, r.params, r.progress, r.filename, COUNT(b.daily_discount_report_brand_id) AS num_brands FROM daily_discount_report_brand b RIGHT JOIN daily_discount_report r ON r.daily_discount_report_id = b.daily_discount_report_id AND b.filename IS NOT NULL WHERE r.daily_discount_report_id = ? GROUP BY r.daily_discount_report_id, r.daily_discount_report_code, r.params, r.progress, r.filename", $row[$TableName . '_id']);
			if ($__r = getRow($__rd)) {
				if ($__r['filename'] || $__r['progress'] >= 100) {
					if ($__r['num_brands'] == 1) {
						$dd .= '<a href="/' . $_link['href'] . '/' . $code . '" data-code="' . $code . '" class="' . (isset($_link['class'])?$_link['class']:'btn btn-info btn-xs ml-1') . '" data-url="' . (isset($_link['url'])?$_link['url']:'') . '" data-id="' . (isset($_link['id'])?$row[$_link['id']]:'') . '"' . (isset($_link['target'])?' target="' . $_link['target'] . '"':'') . (isset($_link['attr'])?$_link['attr']:'') . '>' . $_link['name'] . '</a>
						<a href="/daily-discount-report-xlsx/' . $code . '" class="btn btn-danger btn-xs ml-1" target="_blank"><i class="fa fa-file-excel"></i> Excel</a>';
					}
					else {
						$dd .= '<a href="/daily-discount-report/' . $__r['daily_discount_report_code'] . '" class="btn btn-info btn-xs ml-1">View All Downloads (' . $__r['num_brands'] . ')</a>';
					}
				}
				else {
					if ($__r['progress'] == -1) $dd .= '<button type="button" class="btn btn-danger btn-xs ml-1"><i class="fa fa-exclamation-triangle"></i> No Orders</button>';
					else if ($__r['progress'] == 0) $dd .= '<button type="button" class="btn btn-secondary btn-xs ml-1 btn-daily-discount-report" data-id="' . $row[$TableName . '_id'] . '"><i class="fa fa-clock"></i> Queued ...</button>';
					else $dd .= '<button type="button" class="btn btn-warning btn-xs ml-1 btn-daily-discount-report" data-id="' . $row[$TableName . '_id'] . '"><i class="fa fa-clock"></i> Generating ...' . round($__r['progress']) . '%</button>';
				}
			}
			$dd .= '</span>';
		  }
		  else {
			if ($_show_link) $dd .= '<a href="/' . $_link['href'] . '/' . $code . '" data-code="' . $code . '" class="' . (isset($_link['class'])?$_link['class']:'btn btn-info btn-xs ml-1') . '" data-url="' . (isset($_link['url'])?$_link['url']:'') . '" data-id="' . (isset($_link['id'])?$row[$_link['id']]:'') . '"' . (isset($_link['target'])?' target="' . $_link['target'] . '"':'') . (isset($_link['attr'])?$_link['attr']:'') . '>' . $_link['name'] . '</a>';
		  }
	  }
  }
 
 	 if ($AllowDelete) {
		$dd .= '<a href="" class="btn btn-danger btn-xs btn-del ml-1" data-url="' . $PageName . '" data-title="Edit ' . $PageTitle . '" data-id="' . $row[$arr_FieldNames[0]] . '"><i class="ion-trash-b"></i> Archive</a>';
	}
	$dd .= '</div>';
	if (str_len($ModalUrl) || str_len($DetailsUrl) || $AllowDelete || $AllowEdit || $AllowAdd || isset($tbl['links'])) array_push($record, $dd);
	array_push($data, $record);
}

echo json_encode(array("iTotalRecords" => $iTotalRecords, "iTotalDisplayRecords" => $iTotalDisplayRecords, 'data' => $data));

function formatField($row, $i, $type, $ref) {
	global $_Session, $PageName, $TableName, $arr_DisplayNames;
	
	$j = 0;

	$a_i = explode('.', $i);
	if (sizeof($a_i) == 2) $i = $a_i[1];
	
	$v = $row[$i];
	if ($i == 'module_ids') {
    	$v = $_Session->GetModuleList($v);
	}
	/*
		elseif ($i == 'store_ids') {
		if (str_len($v)) {
		$store_ids = json_decode($v, true);
		$v = '';
		foreach($store_ids as $store) {
			$_rs = getRs("SELECT * FROM store WHERE store_id = ?", array($store['store_id']));
			if ($_s = getRow($_rs)) {
			$v .= '<div>' . $_s['store_name'];
			$_re = getRs("SELECT * FROM {$_s['db']}.employee WHERE employee_id = ?", array($store['employee_id']));
			if ($_e = getRow($_re)) {
				$v .= ' (' . $_e['firstName'] . ' ' . $_e['lastName'] . ')';
			}
			$v .= '</div>';
			}
		}
		}
	}
	*/
	elseif ($i == 'category_ids') {
		$v = getDisplayNamesB1('category', $v, 'name');
	}
	elseif ($TableName == 'brand_discount' && $i == 'category_id') {
		$v = getLocalDisplayNames("{$_Session->db}.category", $v, 'name','category_id');
		$v = empty($v) ? '**ALL**' : $v;
	}
	elseif ($type == 'json') {
		$t = str_replace('_ids', '', $i);
		if ($ref) $t = $ref;
		$v = getDisplayNames($t, $v);
	}
	elseif (in_array($TableName, array('daily_discount', 'daily_discount_report', 'daily_discount_report_brand','casepack','c_markup_override','dbe_markup_override')) && in_array($i, array('category_id', 'brand_id'))) {
		$t = str_replace('_id', '', $i);
		if ($ref) $t = $ref;
		$v = ($v)?getDisplayName($t, $v, 'name', null, false, 'blaze1.'):'* ALL *';
	}
	elseif ($i == 'date_generated') {
		$v = '<span class="span-daily-discount-report-generated-' . $row[$TableName . '_id'] . '">' . getLongDate($v) . '</span>';
	}
	elseif (in_array($TableName, array('daily_discount_report', 'daily_discount_report_brand')) && $i == 'total') {
		$v = '<span class="span-daily-discount-report-total-' . $row[$TableName . '_id'] . '">' . (($v)?currency_format($v):'') . '</span>';
	}
	elseif ($i == 'master_category_id') {
		$v = getDisplayName('category', $v, 'name', null, false, 'blaze1.');
	}
	elseif ($i == 'linked_brand_id') {
		$v = getDisplayName('brand', $v, 'name', null, false, 'blaze1.');
	}
	elseif ($i == 'po_days_pending') {
		$v = (isset($row['date_po_event_notified']) and isset($row['po_event_status_id']) and $row['po_event_status_id'] == 1)?number_format((time() - strtotime($row['date_po_event_notified']))/(24 * 60 * 60)):null;
	}
	elseif ($i == 'leadtime' and !$v) {
		$v = $_Session->GetSetting('leadtime');
	}
	elseif ($i == 'scheduling_window' and !$v) {
		$v = $_Session->GetSetting('scheduling-window');
	}
	elseif ($i == 'target_days_on_hand' and !$v) {
		$v = $_Session->GetSetting('target-days-on-hand');
	}
	elseif ($type == 'number') {
		$v = (float)$v;
	}
	elseif ($type == 'display' and $i == 'qty') {
		$v = number_format($v);
	}
	elseif ($i == 'blaze_id') {
		$_rv = getRs("SELECT id, name FROM {$_Session->db}.vendor WHERE " . is_enabled() . " AND id = ?", $v);
		if ($_v = getRow($_rv)) {
			$v = $_v['name'];
		}
	}
	elseif ($i == 'blaze_ids' and $v) {
		$blaze_ids = ($v and isJson($v))?json_decode($v):array();
		if (is_array($blaze_ids) and sizeof($blaze_ids)) {
			$_rv = getRs("SELECT id, name FROM {$_Session->db}.vendor WHERE " . is_enabled() . " AND FIND_IN_SET(id, ?)", array(implode(',', $blaze_ids)));
			$v = '';
			foreach ($_rv as $_v) {
				$v .= iif(str_len($v), ', ') . $_v['name'];
			}
		}
	}
	elseif ($type == 'money') {
		$v = currency_format($v);
	}
	elseif ($type == 'file') {
		$v = '<img src="/media/' . $TableName . '/sm/' . $v . '" alt="" class="img-thumb" />';
	}
	elseif ($type == 'date') {
		$v = getShortDate($v);
	}
	elseif ($type == 'datetime') {
		$v = getLongDate($v);
	}
	elseif ($type == 'bool') {
		$v = yesNoFormat($v);
	}
	elseif ($type == 'nicefy') {
		$v = nicefy($v);
	}
	elseif ($type == 'subject') {
    $_t = $row['re_table'];
    $_rs = getRs("SELECT {$_t}_id, {$_t}_name FROM {$_t} WHERE {$_t}_id = ?", array($v));
    if ($_r = getRow($_rs)) {
      $v = $_r[$_t . '_name'] . '<br /><small><a href="" class="btn-table-dialog" data-url="admins" data-title="Edit: ' . $_r[$_t . '_name'] . '" data-id="' . $_r[$_t . '_id'] . '">Edit</a></small>';
    }
    else {
      $v = '';
    }
	}
	if (!$row['is_enabled']) {
		$v = '<s>' . $v . '</s>';
	}
	return $v; //iconv("utf-8", "utf-8//ignore", $v);
}
?>