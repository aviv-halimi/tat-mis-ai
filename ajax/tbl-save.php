<?php

$success = false;
$response = '';
$redirect = '';
$swal = '';

$ItemID = getVarNum($PrimaryKey);
$LockID = 0;

/*** TO DO 
permission checking
*************************/


// dup checking
if ($TableName == 'admin') {
	$__store_ids = null;
	$_a_store_ids = array();
	if (isset($_POST['_store_id']) and is_array($_POST['_store_id'])) {
		$_store_ids = $_POST['_store_id'];
		if (sizeof($_store_ids)) {
			foreach($_store_ids as $_store_id) {
				$_employee_id = getVarNum('_employee_id_' . $_store_id);
				if (!$_employee_id) {
					$response = 'You must select an employee for each assigned store';
				}
				else {
					array_push($_a_store_ids, array('store_id' => $_store_id, 'employee_id' => $_employee_id));
				}
			}
			$__store_ids = json_encode($_a_store_ids, JSON_NUMERIC_CHECK);
		}
		else {
			$response = 'You must assign this admin to at least one store';
		}
	}
	else {
		$response = 'You must assign this admin to at least one store';
	}
}

// dup checking
if (isset($tbl['unique'])) {
	$_unique = explode(',', $tbl['unique']);
	$_w = '';
	$_p = array();
	foreach($_unique as $_u) {
		$_w .= iif($_w, ' OR ') . '(' . $_u  . ' = ? AND LENGTH(COALESCE(' . $_u . ', \'\')) > 0)';
		array_push($_p, getVar($_u));
	}
	if ($_w) $_w = '(' . $_w . ')';
	if ($ItemID) {
		$_w .= iif($_w, ' AND ') . $TableName  . '_id <> ?';
		array_push($_p, $ItemID);
	}
	$_w = iif($_w, ' AND ') . $_w;
    $_ra = getRs("SELECT {$TableName}_id, {$TableName}_name, {$tbl['unique']} FROM {$TableName} WHERE " . is_active() . $_w, $_p);
    if ($_a = getRow($_ra)) {
		$_d = '';
		foreach($_unique as $_u) {
			if (getVar($_u) == $_a[$_u]) {
				$_d .= iif($_d, ', ') . nicefy($_u);
			}
		}
		$_pf =  iif($_a[$TableName . '_name'], ' to ' . $_a[$TableName . '_name']);
      	$response = 'This '. $_d . ' is already registered' . $_pf;
    }
}

// check missing / invalid fields
$missing_fields = $invalid_fields = array();
if (str_len($RequiredFields)) {
  $rf = explode(',', $RequiredFields);
  foreach($rf as $f) {

    $i = 0;
    foreach($arr_ModFieldNames as $m) {
      if ($m == $f) {
        $n = $arr_ModDisplayNames[$i];
        $t = $arr_ModFieldTypes[$i];
        break;
      }
      $i++;
    }

    $currVal = getVar($f);

    if (!str_len($currVal)) {
      array_push($missing_fields, $n);
    }
    else {
      if (in_array($t, array('bool', 'number', 'money', 'percent', 'key', 'recur'))) {
        $currVal = numFormat($currVal);
        if ($currVal == null) array_push($invalid_fields, $n);
      }
      elseif (in_array($t, array('date', 'datetime'))) {
        if (!isDate($currVal)) array_push($invalid_fields, $n);
      }
    }
  }
}

if (sizeof($missing_fields) || sizeof($invalid_fields)) {
  if (sizeof($missing_fields)) $response = 'The following field' . iif(sizeof($missing_fields) != 1, 's are', ' is') . ' required: ' . implode(', ', $missing_fields) . '. ';
  if (sizeof($invalid_fields)) $response .= 'The value' . iif(sizeof($invalid_fields) != 1, 's') . ' entered for the following field' . iif(sizeof($invalid_fields) != 1, 's are', ' is') . ' invalid: ' . implode(', ', $invalid_fields) . '.';
  echo json_encode(array(
    'success' => false,
    'response' => $response,
    'redirect' => '',
    'swal' => ''
  ));
  exit();
}






if ($TableName == 'sku_override') {
  $rs = getRs("SELECT * FROM {$TablePrefix}{$TableName} WHERE " . is_active() . " AND product_id = ? AND sku_override_id <> ?", array(getVarNum('product_id'), getVarNum('sku_override_id')));
  if (sizeof($rs)) {
    $response = 'Override information for this SKU has already been entered. Please search for the SKU and edit the information as needed.';
  }
}

if (str_len($response)) {
  echo json_encode(array(
    'success' => false,
    'response' => $response,
    'redirect' => null,
    'swal' => 'Error',
  ));
  exit();
}

// check parent fields, make sure a child not it's own parent
if ( $ItemID ) {
	foreach ($arr_ModFieldNames as $f) {
		if (substr($f, 0, 7) == 'parent_') {
			$parent_id = getVar($f);
			if ($parent_id == $ItemID) {
				$response = 'An item cannot be it\'s own parent.';
			}
			else {
				$i = 0;
				while ($parent_id and $i < 100) {
					$i++;
					$rs = getRs("SELECT parent_{$PrimaryKey} AS parent_id FROM {$TableName} WHERE {$PrimaryKey} = ? AND " . is_active(), array($parent_id));
					if ($row = getRow($rs)) {
						$parent_id = $row['parent_id'];
						if ($parent_id == $ItemID) {
							$response = 'An item cannot be in it\'s own parent tree.';
						}
					}
					else {
						$parent_id = 0;
					}
				}
			}
		}
	}
}

if (str_len($response) == 0) {

	if ( $ItemID ) {
		
		$params = array();
		
		$sql = 'UPDATE ' . $TablePrefix . $TableName . ' SET ';
		$i = 0;
		$j = 0;
		foreach ($arr_ModFieldNames as $f) {
			$t = $arr_ModFieldTypes[$i];
			if ($t != 'display') {
				$currVal = getVar($f);			
				if ($f == 'password' && str_len($currVal) == 0) {
				}
				else {
					if ($j > 0) {
						$sql .= ', ';
					}
					if ($t == 'int') {
						if ($currVal != 1) {
							$currVal = 0;
						}
					}
					elseif ($f == 'password') {
						$currVal = formatPassword($currVal);
					}
					elseif ($f == 'blaze_ids') {
						$currVal = json_encode($_POST[$f], JSON_NUMERIC_CHECK);
					}
					elseif ($f == 'employees') {
						$currVal = array();
						$total = 0;
						if (true) {
							foreach(array('_full_time', '_temporary') as $temp) {
								foreach(array('local', 'international') as $intl) {
									foreach(array('_male', '_female') as $gender) {
										$fe = $intl . $gender . $temp;
										if (isset($_POST[$fe]) and is_numeric($_POST[$fe]) and $_POST[$fe] > 0) {
											$_t = $_POST[$fe];
											$total += $_t;
											$currVal[$fe] = $_t;
										}
									}
								}
							}
						}
						$currVal = json_encode($currVal);
					}
					elseif ($f == 'permissions') {
						$currVal = null;
						$_m = $_w = $_r = array();
						if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
							$_m = $_POST['permissions'];
							if (in_array('workflow', $_m) and isset($_POST['permissions_workflows']) && is_array($_POST['permissions_workflows'])) {
								$_w = $_POST['permissions_workflows'];
							}
							if (in_array('report', $_m) and isset($_POST['permissions_reports']) && is_array($_POST['permissions_reports'])) {
								$_r = $_POST['permissions_reports'];
							}
						}
						$currVal = array('modules' => $_m);
						if (sizeof($_w)) $currVal['workflows'] = $_w;
						if (sizeof($_r)) $currVal['reports'] = $_r;
						$currVal = json_encode($currVal);
					}
					
					elseif (substr($f, 0, 7) == 'parent_') {
						if ($currVal == 0 || !is_numeric($currVal)) $currVal = null;
					}
					elseif ($f == 'attributes') {
						$attributes = array();
						if (isset($_POST['attribute_id']) and is_array($_POST['attribute_id'])) {
							$_i = 0;
							foreach($_POST['attribute_id'] as $a) {
								$_a = array('id' => (str_len($a))?$a:getUniqueId(), 'name' => $_POST['attribute_name'][$_i], 'type' => $_POST['attribute_type'][$_i]);
								if (in_array($_POST['attribute_type'][$_i], array('select', 'checkbox'))) {
									$_a['options'] = explode(PHP_EOL, $_POST['attribute_options'][$_i]);
								}
								array_push($attributes, $_a);
								$_i++;
							}
						}
						$currVal = json_encode($attributes);
					}
					elseif (in_array($t, array('bool'))) {
						$currVal = numFormat($currVal, false, 0);
					}
					elseif (in_array($t, array('number', 'money', 'percent', 'key', 'recur'))) {
						$currVal = numFormat($currVal);
					}
					if ($t == 'date' || $t == 'datetime') {
						if (str_len($currVal)) {
							$sql .= $f . ' = ?';
							array_push($params, toMySqlDT($currVal));
						}
						else {					
							$sql .= $f . ' = NULL';
						}
					}
					else if ($t == 'json') {
						if (isset($_POST[$f]) and is_array($_POST[$f]) and sizeof($_POST[$f])) {
							$sql .= $f . ' = ?';
							array_push($params, json_encode($_POST[$f], JSON_NUMERIC_CHECK));
						}
						else {					
							$sql .= $f . ' = NULL';
						}
					}
					else if ($t == 'files') {
						$_files = null;
						if (isset($_POST[$f . '_media_item_data'])) {
						$__files = array();
						foreach($_POST[$f . '_media_item_data'] as $_f) {
							array_push($__files, json_decode($_f, true));
						}
						$_files = json_encode($__files);
						}
						array_push($params, $_files);
						$sql .= $f . ' = ?';
					}
					else {
						$sql .= $f . ' = ?';
						array_push($params, $currVal);
					}
					$j++;
				}
			}
			$i++;
		}
	
		$sql .= ', date_modified = CURRENT_TIMESTAMP';
		$sql .= ' WHERE ' . $PrimaryKey . ' = ?' . iif(str_len($_Where), ' AND ' . str_replace('t1.', '', $_Where));
		array_push($params, $ItemID);
	
		if ( $LockID > 0 ) {
			$sql .= " AND {$PrimaryKey} = ?";
			array_push($params, $LockID);
		}
		
		$rs_1 = getRs("SELECT * FROM {$TablePrefix}{$TableName} WHERE {$PrimaryKey} = ?", array($ItemID)); // so we can compare values that changed
		setRs($sql, $params);

		$success = true;
		$response = 'Record updated successfully';
		$redirect = '{refresh}';
		
		if ( count($arr_CodeFields) > 1 ) {
			saveCode($TablePrefix . $TableName, $arr_CodeFields[0], $arr_CodeFields[1], $ItemID);
		}
		/*
		if ($KeywordFields) {
			saveKeywords($TableName, $KeywordFields, $ItemID, $KeywordPages);
		}
		*/
		
		saveActivity('update', $ItemID, $TablePrefix . $TableName, $PageTitle . ' updated', getRow($rs_1));
	}
	
	else {
		
		$params = array();
		
		$sql = 'INSERT INTO ' . $TablePrefix . $TableName . ' (';
		$i = 0;
		$j = 0;
		foreach ($arr_ModFieldNames as $f) {
			$t = $arr_ModFieldTypes[$i];
			if ($t != 'display') {
				$currVal = getVar($f);
				if ($f == 'password' && str_len($currVal) == 0) {
				}
				else {
					if ($j > 0) {
						$sql .= ', ';
					}
					$sql .= $f;
					$j++;
				}
			}
			$i++;
		}
		$sql .= ') VALUES (';
		$i = 0;
		$j = 0;
		foreach ($arr_ModFieldNames as $f) {
			$t = $arr_ModFieldTypes[$i];
			if ($t != 'display') {
				$currVal = getVar($f);
				if ($f == 'password' && str_len($currVal) == 0) {
				}
				else {
					if ($j > 0) {
						$sql .= ', ';
					}
					if ($t == 'bool') {
						if ($currVal != 1) {
							$currVal = 0;
						}
					}
					elseif ($f == 'blaze_ids') {
						$currVal = json_encode($_POST[$f], JSON_NUMERIC_CHECK);
					}
					elseif ($f == 'password') {
						$currVal = formatPassword($currVal);
					}
					elseif (substr($f, 0, 7) == 'parent_') {
						if ($currVal == 0 || !is_numeric($currVal)) $currVal = null;
					}
					elseif ($f == 'attributes') {
						$attributes = array();
						if (isset($_POST['attribute_id']) and is_array($_POST['attribute_id'])) {
							$_i = 0;
							foreach($_POST['attribute_id'] as $a) {
								$_a = array('id' => (str_len($a))?$a:getUniqueId(), 'name' => $_POST['attribute_name'][$_i], 'type' => $_POST['attribute_type'][$_i]);
								if (in_array($_POST['attribute_type'][$_i], array('select', 'checkbox'))) {
									$_a['options'] = explode(PHP_EOL, $_POST['attribute_options'][$_i]);
								}
								array_push($attributes, $_a);
								$_i++;
							}
						}
						$currVal = json_encode($attributes);
					}
					elseif (in_array($t, array('bool'))) {
						$currVal = numFormat($currVal, false, 0);
					}
					elseif (in_array($t, array('number', 'money', 'percent', 'key', 'recur'))) {
						$currVal = numFormat($currVal);
					}
					elseif ($f == 'employees') {
						$currVal = array();
						$total = 0;
						if (true) {
							foreach(array('_full_time', '_temporary') as $temp) {
								foreach(array('local', 'international') as $intl) {
									foreach(array('_male', '_female') as $gender) {
										$fe = $intl . $gender . $temp;
										if (isset($_POST[$fe]) and is_numeric($_POST[$fe]) and $_POST[$fe] > 0) {
											$_t = $_POST[$fe];
											$total += $_t;
											$currVal[$fe] = $_t;
										}
									}
								}
							}
						}
						$currVal = json_encode($currVal);
					}
					elseif ($f == 'permissions') {
						$currVal = null;
						$_m = $_w = $_r = array();
						if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
							$_m = $_POST['permissions'];
							if (in_array('workflow', $_m) and isset($_POST['permissions_workflows']) && is_array($_POST['permissions_workflows'])) {
								$_w = $_POST['permissions_workflows'];
							}
							if (in_array('report', $_m) and isset($_POST['permissions_reports']) && is_array($_POST['permissions_reports'])) {
								$_r = $_POST['permissions_reports'];
							}
						}
						$currVal = array('modules' => $_m);
						if (sizeof($_w)) $currVal['workflows'] = $_w;
						if (sizeof($_r)) $currVal['reports'] = $_r;
						$currVal = json_encode($currVal);
					}
					
					if ($t == 'date' || $t == 'datetime') {
						if (str_len($currVal)) {
							$sql .= '?';
							array_push($params, toMySqlDT($currVal));
						}
						else {					
							$sql .= 'NULL';
						}
					}
					else if ($t == 'json') {
						if (isset($_POST[$f]) and is_array($_POST[$f]) and sizeof($_POST[$f])) {
							$sql .= '?';
							array_push($params, json_encode($_POST[$f], JSON_NUMERIC_CHECK));
						}
						else {					
							$sql .= 'NULL';
						}
					}
					else if ($t == 'files') {
            $_files = null;
            if (isset($_POST[$f . '_media_item_data'])) {
              $__files = array();
              foreach($_POST[$f . '_media_item_data'] as $_f) {
                array_push($__files, json_decode($_f, true));
              }
              $_files = json_encode($__files);
            }
            $sql .= '?'; 
            array_push($params, $_files);
					}
					else {
						$sql .= '?';
						array_push($params, $currVal);
					}
					$j++;
				}
			}
			$i++;
		}
		$sql .= ')';
		$NewRecord = $ItemID = setRs($sql, $params);
		if (str_len($_Where)) {
			setRs("UPDATE {$TableName} SET " . str_replace(' AND ', ', ', str_replace('t1.', '', $_Where)) . " WHERE {$PrimaryKey} = ?", array($ItemID));
		}
		$success = true;
		$response = 'Record added successfully';
		$redirect = '{refresh}';
		if(str_len($DetailsUrl)) {
			$rs2 = getRs("SELECT {$TableName}_code FROM {$TablePrefix}{$TableName} WHERE {$TableName}_id = ?", array($ItemID));
			if ($r2 = getRow($rs2)) {
				$redirect = '/' . $DetailsUrl . '/'. $r2[$TableName . '_code'];
			}
		} 
		
		if ( count($arr_CodeFields) > 1 ) {
			saveCode($TablePrefix . $TableName, $arr_CodeFields[0], $arr_CodeFields[1], $ItemID);
		}
		
		saveActivity('add', $ItemID, $TablePrefix . $TableName, $PageTitle . ' added');

	}

	if (file_exists(BASE_PATH . 'trigger/' . $TableName . '.php')) {
		require_once(BASE_PATH . 'trigger/' . $TableName . '.php');
	}
	
}

echo json_encode(array(
	'success' => $success,
	'response' => $response,
	'redirect' => $redirect,
	'swal' => $swal
));
?>