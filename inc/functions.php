<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function str_len($s) {
	return strlen($s ?? '');
}
function datePicker($f, $v = null) {
	global $_Session;
  return '
  <div class="input-group date datepicker p-0" data-date-format="dd/mm/yyyy">
	  <input type="text" class="form-control" placeholder="dd/mm/yyyy" id="' . $f . '"  name="' . $f . '" value="' . toHumanDT($v) . '" />
	  <div class="input-group-addon">
		  <i class="fa fa-calendar"></i>
	  </div>
  </div>';
}

function dateTimePicker($f, $v = null) {
  return '<i class="glyphicon glyphicon-chevron-down"></i>
  <div class="input-group date">
	  <input type="text" class="form-control form-date datetimepicker" placeholder="dd/mm/yyyy hh:mm" id="' . $f . '"  name="' . $f . '" value="' . toHumanDT($v) . '" />
	  <div class="input-group-addon">
		  <i class="fa fa-calendar"></i>
	  </div>
  </div>';
}
function getVarToDT($f, $d = null) {
	if ( isset($_REQUEST[$f]) ) {
		return toMySqlDT($_REQUEST[$f]);
	}
	return $d;
}
function fetchApi($url, $api_url = null, $auth_code = null, $partner_key = null, $params = null, $body = null) {
	global $_Session;
	
	if (!$api_url) $api_url = $_Session->api_url;
	if (!$auth_code) $auth_code = $_Session->auth_code;
	if (!$partner_key) $partner_key = $_Session->partner_key;

	$url = $api_url . $url;
  if (str_len($params)) $url .= '?' . $params;
	$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER         => false,    // don't return headers
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_USERAGENT      => "spider", // who am i
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
	);
	if ($body) {
		$options[CURLOPT_CUSTOMREQUEST] = 'PUT';
		$options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_NUMERIC_CHECK);
	}
	$ch = curl_init( $url );
	curl_setopt_array( $ch, $options );
	$headers = array(
		'Content-Type: application/json',
		'Authorization: ' . $auth_code,
		'X-API-KEY: ' . $partner_key
	);

	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

	$content = curl_exec( $ch );

	curl_close( $ch );

	return $content;
}


function alertBox($msg, $css = 'warning') {
	return '<div class="alert alert-' . $css . ' alert-bordered text-lg">
      ' . $msg . '
  </div>';
}

function putApi($url, $api_url = null, $auth_code = null, $partner_key = null, $data = null) {
	$url = $api_url . $url;
 	$ch = curl_init();
	$json_data = json_encode($data, JSON_NUMERIC_CHECK);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: ' . $auth_code, 'X-API-KEY: ' . $partner_key, 'Content-Length: ' . str_len($json_data)));
   
	$resp = curl_exec($ch);   
	curl_close($ch);
	return $resp;
}
function postApi2($url, $api_url = null, $auth_code = null, $partner_key = null, $data = null) {
    $url = $api_url . $url;
    $ch = curl_init();
    $json_data = json_encode($data, JSON_NUMERIC_CHECK);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json', 
        'Authorization: ' . $auth_code, 
        'X-API-KEY: ' . $partner_key, 
        'Content-Length: ' . strlen($json_data)
    ));
   
    $resp = curl_exec($ch);   
    curl_close($ch);
    return $resp;
}


function arrayVal($a, $v, $d = null, $n = false) {
	$r = $d;
	if (isset($a[$v])) {
		$r = $a[$v];
	}
	else {
		$r = $d;
	}
	if ($n) {
		if (!is_numeric($r)) $r = $d;
	}
	return $r;
}

function displayKey($f, $v, $r = null, $w = null, $select = 'Select', $sort = null) {
	global $_Session;
	$t = str_replace('_id', '', $f);
	if ($r) $t = $r;
  if (strpos($r ?? '', '.')) {
    $name = 'name';
  }
  else {
    $name = $t . '_name';
  }
	if ($w == 'local') $w = 'site_id = ' . $_Session->site_id;
	return dboDropDown($t, $v, $select, $f, $f, $name, '', $sort, $w);
}

function displayKeys($f, $v, $r = null, $w = null) {
	global $_Session;
	$_tbl = str_replace('_ids', '', $f);
	$_tbl = str_replace('_id', '', $_tbl);

	
  if (!$r) $r = $_tbl;
  if (strpos($r, '.')) {
    $name = 'name';
  }
  else {
    $name = $_tbl . '_name';
  }
	if ($f == 'suspended_categories') {
		$_tbl = "category";
		$r = "{$_Session->db}.category";
	}
  $_ids = array();
  if (is_array($v)) $_ids = $v;
	else if (isJson($v)) $_ids = json_decode($v);
	if ($w == 'local') $w = 'site_id = ' . $_Session->site_id;
	$ret = '<select class="form-control multiple-select" id="' . $f . '" name="' . $f . '[]" multiple="multiple" data-placeholder="Select" style="width: 100%">';
	//$rs1 = getRs("SELECT * FROM {$_tbl} WHERE " . is_enabled() . iif($w, " AND " . $w) . " ORDER BY sort, {$name}");
	$rs1 = getRs("SELECT * FROM {$r} WHERE " . is_enabled() . iif($w, " AND " . $w) . " ORDER BY sort, {$name}");
	foreach($rs1 as $r1) {
		$ret .= '<option value="' . $r1[$_tbl . '_id'] . '"' . iif(in_array($r1[$_tbl . '_id'], $_ids), ' selected') . '>' . $r1[$name] . '</option>';
	}
	$ret .= '</select>';
	return $ret;
}

function arrayDump($a, $e = array()) {
	$e = array_merge($e, array('admin_id', 'api_id', 'api_type_id', 'gps', 'account_id', 'field_shape'));
	$ret = '<div class="row">
    <div class="col-md-6">
<div class="panel panel-info">
  <div class="panel-heading">
      <h4 class="panel-title">Survey Results</h4>
  </div>
  <div class="table-responsive">
      <table class="table table-striped">
      <thead><tr><th>Key</th><th>Value</th></tr></thead></tbody>';
      foreach($a as $k => $v) {
        if (!in_array($k, $e)) {
          $ret .= '<tr><th>' . nicefy($k) . '</th><td>';
          if (substr($k, str_len($k) - 3, 3) == '_id') {
            $k_tbl = substr($k, 0, str_len($k) - 3);
            $ret .= getDisplayName($k_tbl, $v);
          }
          elseif (substr($k, str_len($k) - 4, 4) == '_ids') {
            $k_tbl = substr($k, 0, str_len($k) - 4);
            $ret .= getDisplayNames($k_tbl, $v);
          }
          else {
            $ret .= print_r($v, true);
          }
          $ret .= '</td></tr>';
        }
      }
	  $ret .= '</tbody></table></div></div></div><div class="col-md-6">
	 ';
	 if (isset($a['field_shape']) and str_len($a['field_shape'])) {
		 $ret .= '<div class="panel panel-warning">
		 <div class="panel-heading">
			 <h4 class="panel-title">Field Shape</h4>
		 </div><div class="panel-body"><img src="/media/nass_crop/' . $a['field_shape'] . '" class="img-responsive" alt="Field Shape" /></div></div>';
	 }
	 $ret .= '
	  </div></div>';
	  return $ret;
	}

function nicefy($str) {
	if (substr($str ?? '', str_len($str) - 4, 4) == '_ids') {
		$str = substr($str, 0, str_len($str) - 4);
	}
	if (substr($str ?? '', str_len($str) - 3, 3) == '_id') {
		$str = substr($str ?? '', 0, str_len($str) - 3);
	}
	return ucwords(preg_replace('/[^A-Za-z0-9 ]/', ' ', strtolower($str ?? '')));
}



function loadingChart() {
  return '            
  <div class="alert alert-info text-center p-b-5">
    <div><i class="fa fa-spinner fa-spin"></i> Loading chart ...</div>
  </div>';
}

function postApi($url, $vars = array()) {	
	$ch = curl_init($url) ;//. '?' . $vars);
	curl_setopt( $ch, CURLOPT_POST, 1);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt( $ch, CURLOPT_HEADER, 0);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: ' . $auth_code, 'X-API-KEY: ' . $partner_key, 'Content-Length: ' . str_len($json_data)));
	
	$ret = curl_exec( $ch );
	return $ret;
}

function compareText($t1, $t2) {
	if (trim(strtolower($t1)) == trim(strtolower($t2))) {
		return true;
	}
	else {
		return false;
	}
}


function getVarToJson($f, $d = null) {
	if ( isset($_REQUEST[$f]) and is_array($_REQUEST[$f])) {
		return json_encode($_REQUEST[$f], JSON_NUMERIC_CHECK);
	}
	return $d;
}

function isJson($string) {
  if (!$string) return false;
  json_decode($string);
	return (json_last_error() === JSON_ERROR_NONE);
 } 

function toMySqlDT($d, $us = true) {
	$d = trim($d ?? '');
	if (!str_len($d)) return null;
	$r = $d;
	$a1 = explode(' ', $d);
	if (count($a1) == 2) {
		$a2 = explode('/', $a1[0]);	
		if (count($a2) == 3) {
			$r = $a2[2] . '-' . $a2[1] . '-' . $a2[0];
			if ($us) $r = $a2[2] . '-' . $a2[0] . '-' . $a2[1];
			$a3 = explode(':', $a1[1]);
			if (count($a3) == 2) {
				$r .= ' ' . $a1[1] . ':00';
			}				
		}
	}
	if (count($a1) == 3) {
		$a2 = explode('/', $a1[0]);	
		if (count($a2) == 3) {
			$r = $a2[2] . '-' . $a2[1] . '-' . $a2[0];
			if ($us) $r = $a2[2] . '-' . $a2[0] . '-' . $a2[1];
			$a3 = explode(':', $a1[1]);
			$a3 = explode(':', $a1[1]);
			if (count($a3) == 2) {
				$r .= ' ' . (((strtolower($a1[2]) == 'pm' and $a3[0] != 12)?12:0) + ((strtolower($a1[2]) == 'am' and $a3[0] == 12)?0:$a3[0])) . ':' . $a3[1] . ':00';
			}				
		}
	}
	elseif (count($a1) == 1) {
		$a2 = explode('/', $a1[0]);	
		if (count($a2) == 3) {
			$r = $a2[2] . '-' . $a2[1] . '-' . $a2[0];	
			if ($us) $r = $a2[2] . '-' . $a2[0] . '-' . $a2[1];	
		}
	}
	return (str_len($r))?$r:NULL;
}


function toHumanDT($d, $us = true) {
	if ($d == '0000-00-00 00:00:00' || $d == '0000-00-00') {
		return '';
	}
	$r = $d;
	$a1 = explode(' ', $d);
	if (count($a1) == 2) {
		$a2 = explode('-', $a1[0]);	
		if (count($a2) == 3) {
			$r = $a2[2] . '/' . $a2[1] . '/' . $a2[0];
			if ($us) $r = $a2[1] . '/' . $a2[2] . '/' . $a2[0];
			$a3 = explode(':', $a1[1]);
			if (count($a3) == 3) {
				$pm = ((strtolower($a3[0]) > 11)?'PM':'AM');
				$r .= ' ' . ((($pm == 'PM')?-12:0) + $a3[0]). ':' . $a3[1] . ' ' . $pm;
			}				
		}
	}
	elseif (count($a1) == 1) {
		$a2 = explode('-', $a1[0]);
		if (count($a2) == 3) {
			$r = $a2[2] . '/' . $a2[1] . '/' . $a2[0];
			if ($us) $r = $a2[1] . '/' . $a2[2] . '/' . $a2[0];
		}
	}
	return $r;
}


function getIdsNames($tbl, $ids) {
	$names = '';
	if (str_len($ids)) {
		$_ids = json_decode($ids);
		$_ids = implode(',', $_ids);
		$rs = getRs("SELECT {$tbl}_id, {$tbl}_name FROM {$tbl} WHERE FIND_IN_SET({$tbl}_id, '{$_ids}')");
		foreach($rs as $r) {
			$names .= iif(str_len($names), ', ') . $r[$tbl . '_name'];
		}
	}
	return $names;
}


function saveActivity($activity_type_code, $re_id, $table_name, $notes = '', $rs_1 = array(), $admin_id = null, $site_id = null, $is_success = 1) {
  global $IP, $USER_AGENT, $_Session;
  $table_prefix = null;
  if (strpos($table_name, '.')) {
    return;
  }
	$rs = getRs("SELECT * FROM {$table_name} WHERE {$table_name}_id = ?", array($re_id));
	$rs_2 = getRow($rs);

	if (isset($rs_2[$table_name . '_name'])) {
		$notes = (isset($_POST[$table_name . '_name'])?$_POST[$table_name . '_name'] . ' ':'') . $notes;
	}
	if (isset($rs_2['title'])) {
		$notes = $rs_2['title'] . ' ' . $notes;
	}
	$rs = getRs("SELECT activity_type_id, has_params FROM activity_type WHERE activity_type_code = ?", array($activity_type_code));
	if ($r = getRow($rs)) {
		$activity_type_id = $r['activity_type_id'];
		$has_params = $r['has_params'];
	}
	else {
		$activity_type_id = 1;
		$has_params = 0;
	}
	$params = array();
	if ($has_params) {
		foreach($rs_2 as $k => $new) {
			if (isset($rs_1[$k])) {
				$prev = $rs_1[$k];
			}
			else {
				$prev = null;
			}
			$prev_name = $new_name = null;

			if (!in_array($k, array('date_modified')) and $prev != $new) {
				if (in_array($k, array('password'))) {
					$prev = null;
					$new = '{encrypted}';
				}
				if (substr($k, str_len($k) - 3, 3) == '_id') {
					$k_tbl = substr($k, 0, str_len($k) - 3);
					if (in_array($k_tbl, array('nationality'))) $k_tbl = 'country';
          if (in_array($k_tbl, array('second_crop', 'last_crop'))) $k_tbl = 'crop';
          if (in_array($k_tbl, array('motwi_departure', 'motwi_departure'))) $k_tbl = 'airport';
          if (in_array($k_tbl, array('parent_person'))) $k_tbl = 'person';
          if (in_array($k_tbl, array('gpd_visit_frequency_id'))) $k_tbl = 'frequency';
          

          $_rt = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'moa' AND table_name = '{$k_tbl}' LIMIT 1");
          if (sizeof($_rt)) {
            $rs_k = getRs("SELECT {$k_tbl}_id, {$k_tbl}_name FROM {$k_tbl} WHERE FIND_IN_SET({$k_tbl}_id, '{$prev},{$new}')");
            foreach($rs_k as $r_k) {
              if ($r_k[$k_tbl . '_id'] == $prev) $prev_name = $r_k[$k_tbl . '_name'];
              if ($r_k[$k_tbl . '_id'] == $new) $new_name = $r_k[$k_tbl . '_name'];
            }
          }
				}
				if (substr($k, str_len($k) - 4, 4) == '_ids') {
					$k_tbl = substr($k, 0, str_len($k) - 4);
          $_rt = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'moa' AND table_name = '{$k_tbl}' LIMIT 1");
          if (sizeof($_rt)) {
            if (str_len($prev)) {
              $prev_ids = json_decode($prev);
              $prev_ids = implode(',', $prev_ids);
              $rs_k = getRs("SELECT {$k_tbl}_id, {$k_tbl}_name FROM {$k_tbl} WHERE FIND_IN_SET({$k_tbl}_id, '{$prev_ids}')");
              foreach($rs_k as $r_k) {
                $prev_name .= iif(str_len($prev_name), ', ') . $r_k[$k_tbl . '_name'];
              }
            }
            if (str_len($new)) {
              $new_ids = json_decode($new);
              $new_ids = implode(',', $new_ids);
              $rs_k = getRs("SELECT {$k_tbl}_id, {$k_tbl}_name FROM {$k_tbl} WHERE FIND_IN_SET({$k_tbl}_id, '{$new_ids}')");
              foreach($rs_k as $r_k) {
                $new_name .= iif(str_len($new_name), ', ') . $r_k[$k_tbl . '_name'];
              }
            }
        }
				}
				if (substr($k, 0, 3) == 'is_') {
					$prev_name = ($prev)?'Yes':'No';
					$new_name = ($new)?'Yes':'No';
				}
				if (in_array($k, array('image'))) {
					$prev_name = iif(str_len($prev), '<img src="/media/' . $table_name . '/md/' . $prev . '" />');
					$new_name = iif(str_len($new), '<img src="/media/' . $table_name . '/md/' . $new . '" />');
				}
				array_push($params, array('key' => $k, 'prev' => $prev, 'new' => $new, 'prev_name' => $prev_name, 'new_name' => $new_name));
			}
		}
	}
	if (true) { //sizeof($params)) {
		setRs("INSERT INTO activity (site_id, admin_id, activity_type_id, re_id, re_table, notes, params, is_success, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", array((!$site_id)?$_Session->site_id:$site_id, (!$admin_id)?$_Session->admin_id:$admin_id, $activity_type_id, $re_id, $table_name, $notes, json_encode($params), $is_success, $IP, $USER_AGENT));
	}
}

function saveActivity2($activity_type_id, $re_id, $table_name, $description) {
	global $IP;
	global $USER_AGENT;
	
	if (isset($_POST[$table_name . '_name'])) {
		$description = $_POST[$table_name . '_name'] . ' ' . $description;
	}
	if (isset($_POST['title'])) {
		$description = $_POST['title'] . ' ' . $description;
	}
	//setRs("INSERT INTO activity (admin_id, activity_type_id, re_id, re_table, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)", array($_SESSION['admin_id'], $activity_type_id, $re_id, $table_name, $description, $IP, $USER_AGENT));
}

function saveCode($t, $c, $n, $id) {
	$rs = getRs("SELECT {$t}_id, {$n} FROM {$t} WHERE is_active = 1 AND ({$c} IS NULL OR LENGTH({$c}) = 36 OR LENGTH({$c}) = 0) AND {$t}_id = {$id}");
	foreach($rs as $row) {
		$code = toLink($row[$n]);
		$rs1 = getRs("SELECT {$t}_id FROM {$t} WHERE is_active = 1 AND {$c} = ?", array($code));
		$i = 0;
		while ($row1 = getRow($rs1)) {
			$i++;
			$code = toLink($row[$n]) . '-' . $i;
			$rs1 = getRs("SELECT {$t}_id FROM {$t} WHERE is_active = 1 AND {$c} = ?", array($code));
		}
		setRs("UPDATE {$t} SET {$c} = ? WHERE {$t}_id =?", array($code, $row[$t . '_id']));
	}
}

function saveKeywords($t, $f, $id = 0, $p = '', $where = '') {
	$kw = '';
	$kw_fr = '';
	$a_f = explode(',', $f);
	$rs = getRs("SELECT * FROM {$t} WHERE is_active = 1" . iif(str_len($where), " AND {$where}") . iif($id, " AND {$t}_id = {$id}"));
	foreach($rs as $row) {
		$kw = '';
		foreach($a_f as $i) {
			$kw .= ' ' . strip_tags($row[$i]);
		}
		if (str_len($p)) {
			$rs_p = getRs("SELECT {$p}_name, content FROM {$p} WHERE is_active = 1 AND {$t}_id = ?", array($row[$t . '_id']));
			foreach($rs_p as $row_p) {
				$kw .= ' ' . $row_p[$p . '_name'] . ' ' . strip_tags($row_p['content']);
			}
		}
		$kw = implode(' ', array_unique(explode(' ', $kw)));
		$kw = preg_replace('/[^A-Za-z0-9 ]/', ' ', $kw);
		$kw = preg_replace('!\s+!', ' ', $kw);
		$kw = ' ' . strtolower($kw) . ' ';
		$rs_kw = getRs("SELECT keyword_id FROM keyword WHERE re_tbl = ? AND re_id = ?", array($t, $row[$t . '_id']));
		if ($row_kw = getRow($rs_kw)) {
			setRs("UPDATE keyword SET keywords = ? WHERE keyword_id = ?", array($kw, $row_kw['keyword_id']));
		}
		else {
			setRs("INSERT INTO keyword (re_tbl, re_id, keywords) VALUES (?, ?, ?)", array($t, $row[$t . '_id'], $kw));
		}
	}
}

function saveKeywords2($t, $f, $id = 0, $p = '', $where = '') {
	$kw = '';
	$kw_fr = '';
	$a_f = explode(',', $f);
	$rs = getRs("SELECT * FROM {$t} WHERE is_active = 1" . iif(str_len($where), " AND {$where}") . iif($id, " AND {$t}_id = {$id}"));
	foreach($rs as $row) {
		$kw = '';
		foreach($a_f as $i) {
			$kw .= ' ' . strip_tags($row[$i]);
		}
		$kw = implode(' ', array_unique(explode(' ', $kw)));
		$kw = preg_replace('/[^A-Za-z0-9 ]/', ' ', $kw);
		$kw = preg_replace('!\s+!', ' ', $kw);
		$kw = ' ' . strtolower($kw) . ' ';
		setRs("UPDATE {$t} SET keywords = ? WHERE {$t}_id = ?", array($kw, $row[$t . '_id']));
	}
}

function alphaNum($s) {
	return preg_replace('/[^A-Za-z0-9]/', '', $s);
}

function lpnFormat($s) {
  $s = preg_replace('/\s+/', '', $s);
  return strtoupper($s);
}

function numFormat($s, $negative = false, $d = null) {
	$regex = '/[^0-9.]/';
	if ($s != '0') $s = ltrim(trim($s ?? ''), '0');
	if ($negative) $regex = '/[^0-9.-]/';
	$r = preg_replace($regex, '', $s);
	if (!is_numeric($r)) {
		$r = $d;
	}
	return $r;
}

function verifyGPS($gps, $response) {
	if (str_len($gps)) {
		$a = explode(',', $gps);
		if (sizeof($a) != 2 and sizeof($a) != 4) return $response;
		foreach($a as $g) {
			if (!is_numeric(trim($g))) return $response;
		}
	}
	return '';
}

function hiliteKw($kw, $str) {
	$arr_kw = explode(' ', $kw);
	foreach($arr_kw as $k) {
		if (str_len($k) > 1) {
			$str = preg_replace("/\w*?$k\w*/i", "<em>$0</em>", $str);
		}
	}
	return $str;
}	

function getAdminName($admin_id) {
	$rs = getRs("SELECT admin_name FROM admin WHERE admin_id = ?", array($admin_id));
	if ($row = getRow($rs)) {
		return $row['admin_name'];
	}
	else {
		return '';
	}
}

function getDisplayName($tbl, $id, $name = '', $id_name = '', $use_brackets = false, $db = null) {
	$fn = ((str_len($name) == 0)?($tbl . '_name'):$name);
	$id_name = ((str_len($id_name) == 0)?($tbl . '_id'):$id_name);
  $str = '';
  //$rs = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'moa' AND table_name = '{$tbl}' LIMIT 1");
  //if (!sizeof($rs)) return $id;
	$rs = getRs("SELECT " . $fn . ", is_enabled, is_active FROM {$db}{$tbl} WHERE {$id_name} IN (" . ListAppend($id, 0) . ")");
	if (ListLen($id) > 1) {
		$str = ValueList($rs, $fn, ' ');
	}
	else {
		if ($row = getRow($rs)) {
			if ( $row['is_enabled'] && $row['is_active']) {
				$i = 0;
				$arr = explode(',', $fn);
				$str = '';
				foreach($arr as $f) {
					$str .= iif($i > 0, ' ') . iif($i == 1 and $use_brackets, '(') . $row[$f];
					$i++;
				}
				$str .= iif($i > 1 and $use_brackets, ')');
			}
			else {			
				$str = '<s>';
				$i = 0;
				$arr = explode(',', $fn);
				foreach($arr as $f) {
					$str .= iif($i > 0, ' ') . iif($i == 1 and $use_brackets, '(') . $row[$f];
					$i++;
				}
				$str .= iif($i > 1 and $use_brackets, ')');
				$str .= '</s>';
			}
		}
	}
	return $str;
}

function getDisplayNameRecur($tbl, $id, $name = '') {
	$fn = ((str_len($name) == 0)?($tbl . '_name'):$name);
	$str = '';
	$rs = getRs("SELECT " . $fn . ", parent_" . $tbl . "_id, is_enabled FROM {$tbl} WHERE is_active = 1 AND {$tbl}_id = ?", array($id));
	foreach ($rs as $row) {
		if ( $row['is_enabled']) {
			$str = $row[$fn];
		}
		else {			
			$str = '<s>' . $row[$fn] . '</s>';
		}
		if ($row['parent_' . $tbl . '_id'] > 0) {
			$str = getDisplayNameRecur($tbl, $row['parent_' . $tbl . '_id']) . ' > ' . $str;
		}
	}
	return $str;
}
function getLocalDisplayNames($tbl, $ids = null, $name = '', $id_name = '', $use_brackets = false) {
  if (!isJson($ids)) return;
	$fn = ((str_len($name) == 0)?($tbl . '_name'):$name);
  $id_name = ((str_len($id_name) == 0)?($tbl . '_id'):$id_name);
  
  //$rs = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'blaze1' AND table_name = '{$tbl}' LIMIT 1");
  //if (!sizeof($rs)) return null;
	$ret = array();
	$rs = getRs("SELECT " . $fn . ", is_enabled, is_active FROM {$tbl} WHERE FIND_IN_SET({$id_name}, ?)", array(implode(',', json_decode($ids))));
	foreach($rs as $r) {
		array_push($ret, $r[$fn]);
	}
	return $ret;
}
function getDisplayNames($tbl, $ids = null, $name = '', $id_name = '', $use_brackets = false) {
  if (!isJson($ids)) return;
	$fn = ((str_len($name) == 0)?($tbl . '_name'):$name);
  $id_name = ((str_len($id_name) == 0)?($tbl . '_id'):$id_name);
  
  $rs = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'theartisttree' AND table_name = '{$tbl}' LIMIT 1");
  if (!sizeof($rs)) return null;
	$ret = array();
	$rs = getRs("SELECT " . $fn . ", is_enabled, is_active FROM {$tbl} WHERE FIND_IN_SET({$id_name}, ?)", array(implode(',', json_decode($ids))));
	foreach($rs as $r) {
		array_push($ret, $r[$fn]);
	}
	return implode(', ', $ret);
}
function getDisplayNamesB1($tbl, $ids = null, $name = '', $id_name = '', $use_brackets = false) {
  if (!isJson($ids)) return;
	$fn = ((str_len($name) == 0)?($tbl . '_name'):$name);
  $id_name = ((str_len($id_name) == 0)?($tbl . '_id'):$id_name);
  
  $rs = getRs("SELECT * FROM information_schema.tables WHERE table_schema = 'blaze1' AND table_name = '{$tbl}' LIMIT 1");
  //if (!sizeof($rs)) return null;
	$ret = array();
	$rs = getRs("SELECT " . $fn . ", is_enabled, is_active FROM blaze1.{$tbl} WHERE FIND_IN_SET({$id_name}, ?)", array(implode(',', json_decode($ids))));
	foreach($rs as $r) {
		array_push($ret, $r[$fn]);
	}
	return implode(', ', $ret);
}

function selectCurrency($currency_id, $full = false, $n = 'currency_id') {
	$ret = '<select id="' . $n . '" name="' . $n . '" class="currency-id custom-select">';
	$rs = getRs("SELECT * FROM currency WHERE " . is_active() . " ORDER BY sort, currency_id");
	foreach($rs as $row) {
		$ret .= '<option data-symbol="' . $row['symbol'] . '" value="' . $row['currency_id'] . '"' . iif($row['currency_id'] == $currency_id, ' selected') . '>' . iif($full, $row['currency_name'] . ' (' . $row['symbol'] . ')', $row['symbol']) . '</option>';
	}
	$ret .= '</select>';
	return $ret;
}

function selectSettlement($settlement_id, $r = array()) {
  $_district_id = $_ward_id = null;
  $rs1 = getRs("SELECT w.district_id, w.ward_id FROM ward w INNER JOIN settlement s ON s.ward_id = w.ward_id WHERE s.settlement_id = ?", array($settlement_id));
  if ($r1 = getRow($rs1)) {
    $_district_id = $r1['district_id'];
    $_ward_id = $r1['ward_id'];
  }
  else {
    if (isset($r['district_id'])) $_district_id = $r['district_id'];
    if (isset($r['ward_id'])) $_ward_id = $r['ward_id'];
  }
  $ret = '<div class="input-group"><div class="input-group-prepend" style="width:30%">' . displayKey('_district_id', $_district_id, 'district', null, 'Select District') . '</div><div class="input-group-prepend" style="width:5%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:30%">';

  $rs1 = getRs("SELECT * FROM ward WHERE " . is_active() . " ORDER BY sort, ward_name");
  $ret .= '<select class="form-control select2" id="_ward_id" name="_ward_id">';
  $_v = $_w = '<option data-district="0" value="">- Select Ward -</option>';
  foreach($rs1 as $r1) {
    if ($_district_id == $r1['district_id']) {
      $_v .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $_ward_id, ' selected') . '>' . $r1['ward_name'] . '</option>';
    }
    $_w .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $settlement_id, ' selected') . '>' . $r1['ward_name'] . '</option>';
  }
  $ret .= $_v . '</select></div>';
  
  $rs2 = getRs("SELECT * FROM settlement WHERE " . is_active() . " ORDER BY sort, settlement_name");
  $ret .= '<div class="input-group-prepend" style="width:5%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:30%"><select class="form-control select2" id="_settlement_id" name="settlement_id">';
  $_x = $_y = '<option data-ward="0" value="">- Select Settlement -</option>';
  foreach($rs2 as $r2) {
    if ($_ward_id == $r2['ward_id']) {
      $_x .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $settlement_id, ' selected') . '>' . $r2['settlement_name'] . '</option>';
    }
    $_y .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $settlement_id, ' selected') . '>' . $r2['settlement_name'] . '</option>';
  }

  $ret .= $_x . '</select></div></div><div id="__ward_id" class="hide">' . $_w . '</div><div id="__settlement_id" class="hide">' . $_y . '</div>';

  return $ret;
}

function selectEA($enumeration_area_id, $r = array()) {
  $_district_id = $_ward_id = $_settlement_id = null;
  $rs1 = getRs("SELECT w.district_id, w.ward_id, s.settlement_id FROM ward w INNER JOIN (settlement s INNER JOIN enumeration_area e ON e.settlement_id = s.settlement_id) ON s.ward_id = w.ward_id WHERE e.enumeration_area_id = ?", array($enumeration_area_id));
  if ($r1 = getRow($rs1)) {
    $_district_id = $r1['district_id'];
    $_ward_id = $r1['ward_id'];
    $_settlement_id = $r1['settlement_id'];
  }
  else {
    if (isset($r['district_id'])) $_district_id = $r['district_id'];
    if (isset($r['ward_id'])) $_ward_id = $r['ward_id'];
    if (isset($r['settlement_id'])) $_district_id = $r['settlement_id'];
  }
  $ret = '<div class="input-group"><div class="input-group-prepend" style="width:22%">' . displayKey('_district_id', $_district_id, 'district', null, 'Select District') . '</div><div class="input-group-prepend" style="width:4%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:22%">';

  $rs1 = getRs("SELECT * FROM ward WHERE " . is_active() . " ORDER BY sort, ward_name");
  $ret .= '<select class="form-control select2" id="_ward_id" name="_ward_id">';
  $_w = $_w1 = '<option data-district="0" value="">- Select Ward -</option>';
  foreach($rs1 as $r1) {
    if ($_district_id == $r1['district_id']) {
      $_w .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $_ward_id, ' selected') . '>' . $r1['ward_name'] . '</option>';
    }
    $_w1 .= '<option data-district="' . $r1['district_id'] . '" value="' . $r1['ward_id'] . '"' . iif($r1['ward_id'] == $_ward_id, ' selected') . '>' . $r1['ward_name'] . '</option>';
  }
  $ret .= $_w . '</select></div>';
  
  $rs2 = getRs("SELECT * FROM settlement WHERE " . is_active() . " ORDER BY sort, settlement_name");
  $ret .= '<div class="input-group-prepend" style="width:4%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:22%"><select class="form-control select2" id="_settlement_id" name="_settlement_id">';
  $_s = $_s1 = '<option data-ward="0" value="">- Select Settlement -</option>';
  foreach($rs2 as $r2) {
    if ($_ward_id == $r2['ward_id']) {
      $_s .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $_settlement_id, ' selected') . '>' . $r2['settlement_name'] . '</option>';
    }
    $_s1 .= '<option data-ward="' . $r2['ward_id'] . '" value="' . $r2['settlement_id'] . '"' . iif($r2['settlement_id'] == $_settlement_id, ' selected') . '>' . $r2['settlement_name'] . '</option>';
  }

  $ret .= $_s . '</select></div>';

  $rs2 = getRs("SELECT * FROM enumeration_area WHERE " . is_active() . " ORDER BY sort, enumeration_area_name");
  $ret .= '<div class="input-group-prepend" style="width:4%"><div class="input-group-text text-center" style="width:100%"><i class="fa fa-angle-right"></i></div></div><div class="input-group-append" style="width:22%"><select class="form-control select2" id="_enumeration_area_id" name="enumeration_area_id">';
  $_e = $_e1 = '<option data-settlement="0" value="">- Select EA -</option>';
  foreach($rs2 as $r2) {
    if ($_settlement_id == $r2['settlement_id']) {
      $_e .= '<option data-settlement="' . $r2['settlement_id'] . '" value="' . $r2['enumeration_area_id'] . '"' . iif($r2['enumeration_area_id'] == $enumeration_area_id, ' selected') . '>' . $r2['enumeration_area_name'] . '</option>';
    }
    $_e1 .= '<option data-settlement="' . $r2['settlement_id'] . '" value="' . $r2['enumeration_area_id'] . '"' . iif($r2['enumeration_area_id'] == $enumeration_area_id, ' selected') . '>' . $r2['enumeration_area_name'] . '</option>';
  }

  $ret .= $_e . '</select></div>';
  
  $ret .= '</div><div id="__ward_id" class="hide">' . $_w1 . '</div><div id="__settlement_id" class="hide">' . $_s1 . '</div><div id="__enumeration_area_id" class="hide">' . $_e1 . '</div>';

  return $ret;
}

function getCurrencySymbol($currency_id) {
	$rs = getRs("SELECT symbol FROM currency WHERE currency_id = ?", array($currency_id));
	if ($row = getRow($rs)) {
		return $row['symbol'];
	}
	else {
		return 'D';
	}
}

function multiSelect($tbl, $ids = array()) {
	$ret = '<select class="multiple-select form-control" multiple="multiple" name="' . $tbl . '[]" id="' . $tbl . '" data-placeholder="All">';
	$rs = dbGet($tbl);
	foreach($rs as $r) {
		$ret .= '<option value="' . $r[$tbl . '_id'] . '"' . iif(in_array($r[$tbl . '_id'], $ids), ' selected') . '>' . $r[$tbl . '_name'] . '</option>';
	}
	$ret .= '</select>';
	return $ret;
}

function selectCountry($country_name) {
  //return dboSelect('country', $country_name, '', '', 'country_name', 'custom-select', false, 'country_name', 'country');
  return dboDropDown('country', $country_name, 'Select', 'country', 'country_name', 'country_name');
}

function dboSelect($params, $_ddSelected = '', $_AllowNull = '', $_Where = '', $_ddValue = '', $_ddClass = 'custom-select', $_HasOther = false, $_selValue = '', $_ddField = '') {
	$_ddTable = $_ddDisplay = $_ddSort = $data_id = '';
	$_NullValue = '0';
	$_Other = '';
	$_OtherVal = 0;

	if (!is_array($params)) {
		$_ddTable = $params;
	}
	else {
		if (isset($params['tbl'])) $_ddTable = $params['tbl'];
		if (isset($params['null'])) $_AllowNull = $params['null'];
		if (isset($params['null_value'])) $_NullValue = $params['null_value'];
		if (isset($params['where'])) $_Where = $params['where'];
		if (isset($params['sort'])) $_ddSort = $params['sort'];
		if (isset($params['display'])) $_ddDisplay = $params['display'];
		if (isset($params['value'])) $_ddValue = $params['value'];
		if (isset($params['field'])) $_ddField = $params['field'];
		if (isset($params['class'])) $_ddClass = $params['class'];
		if (isset($params['has_other'])) {
			$_HasOther = $params['has_other'];
		}
		else if (isset($params['other'])) {
			$_HasOther = true;
			$_Other = $params['other'];
		}
		if (isset($params['data_id'])) $data_id = $params['data_id'];
	}

	$strDD = '';
	$sQuery = '';
	$i = 0;

	if (str_len($_ddValue) == 0) {
		$_ddValue = $_ddTable . '_id';
	}

	if (str_len($_ddDisplay) == 0) {
		$_ddDisplay = $_ddTable . '_name';
	}
	
	if (str_len($_ddSort) == 0) {
		$_ddSort = 'sort, ' . $_ddDisplay;
	}
	
	$arr_ddDisplay = explode(',', $_ddDisplay);

	if (str_len($_ddField) == 0) {
		$_ddField = $_ddValue;
	}
	if (str_len($_selValue) == 0) {
		$_selValue = $_ddValue;
	}

	if (strpos($_ddField, '[]')) {
		$_ddField_other = str_replace('[]', '', $_ddField) . '_other[]';
	}
	else {
		$_ddField_other = $_ddField . '_other';
	}

	$sQuery =  'SELECT ' . $_selValue . ', ' . $_ddValue . ', ' . $_ddDisplay . ' FROM ' . $_ddTable . " WHERE is_active = 1";
	
	if ( str_len($_Where) > 0 ) {
		$sQuery .= " AND {$_Where}";
	}
	
	if (str_len($_ddSort) > 0) {
		$sQuery .= ' ORDER BY ' . $_ddValue;
	}

	if ($_ddTable == 'inventory_location') {
		$sQuery = "SELECT i.inventory_location_id, i.inventory_location_code, i.inventory_location_name, a.account_location_code FROM inventory_location i INNER JOIN account_location a ON a.account_location_id = i.account_location_id WHERE " . is_active('a,i') . " AND {$_Where} ORDER BY i.sort, i.inventory_location_name";
	}
	
	$strDD = '';
	//echo $sQuery;
	$rs = getRs($sQuery);// or die ('Error executing list query');
	foreach ($rs as $row) {
		$strDD .= '<option value="' . $row[$_ddValue] . '"' . (($_ddTable == 'inventory_location')?' data-premise="' . $row['account_location_code'] . '"':'');
		if ($_ddSelected == $row[$_selValue]) {
			$strDD .= ' selected';
		}
		$strDD .= '>';
		if ($_ddTable != 'course') {		
			for ($i = 0; $i < sizeof($arr_ddDisplay); $i += 1) {
				if ($arr_ddDisplay[$i] != 'affiliate_id') {
					$strDD .= $row[$arr_ddDisplay[$i]];
				}
				else {
					$strDD .= '(' . $row[$arr_ddDisplay[$i]] . ')';					
				}
				if ($i < sizeof($arr_ddDisplay) - 1) {
					$strDD .= ' ';
				}
			}
		}
		else {
			$strDD .=date(PHP_SHORT_DATE_FORMAT, $row['date_start']) . '. ' . $row['course_name'] . ' (Level ' . $row['level_id'] . '), ' . $row['location']; 
		}
		$strDD .= '</option>';
	}
	$ret = '';
	if ($_HasOther) {
		$ret .= '<div class="input-group mb-2"' . iif(!sizeof($rs), ' style="display:none;"') . '>';
	}
	$ret .= '<select' . iif($_HasOther, ' data-other="' . $_OtherVal . '"') . ' id="' . $_ddField . '" name="' . $_ddField . '" class="' . $_ddClass . iif($_HasOther, ' has-other') . ' select-' . $_ddTable . ' select-' . $_ddField . '"';
	$ret .= iif(str_len($data_id), ' data-id="' . $data_id . '"') . '>';
	if ( str_len($_AllowNull) > 0 ) {
		$ret .= '<option value="' . $_NullValue . '">- ' . $_AllowNull . ' -</option>';
	}
	$ret .= $strDD;
	$ret .= '</select>';

	if ($_HasOther) {
		$ret .= '<span class="input-group-append">
			<button class="btn btn-light btn-other btn-' . $_ddTable . ' btn-' . $_ddField . '" type="button">Add New</button>
			<input type="hidden" name="' . $_ddField . '_use_other" id="' . $_ddField . '_use_other" value="0" />
		</span></div>';
	}

	if ($_HasOther) {
		$ret .= '<div class="other"' . iif(sizeof($rs), ' style="display:none;"') . '>
		<input type="text" name="' . $_ddField_other . '" class="form-control" value="' . $_Other . '" placeholder="Add new ..." />
		</div>';
	}

	return $ret;
}

function dboDropDown($_ddTable, $_ddSelected = '0', $_AllowNull = '', $_ddField = '', $_ddValue = '', $_ddDisplay = '', $_NullValue = '', $_ddSort = '', $_Where = '', $_ddClass = 'form-control select2', $data_id = '') {

	$strDD = '';
	$sQuery = '';
	$i = 0;
	if ($_ddValue == 'linked_brand_id') {
		$_ddValue = 'brand_id';
		$_Where = 'brand_id IN (SELECT brand_id FROM daily_discount GROUP BY brand_id HAVING ifnull(sum(linked_brand_id),0) = 0)';
	}
	

	
	if (str_len($_ddValue) == 0) {
		$_ddValue = $_ddTable . '_id';
	}

	if (str_len($_ddDisplay) == 0) {
		$_ddDisplay = $_ddTable . '_name';
	}

	if (str_len($_ddSort) == 0) {
		$_ddSort = 'sort, ' . $_ddValue;
  }
  
  if ($_ddValue == 'product_id') $_ddDisplay = $_ddDisplay. ',SKU';

	$arr_ddDisplay = explode(',', $_ddDisplay);

	if (str_len($_ddField) == 0) {
		$_ddField = $_ddValue;
	}
	$sQuery =  'SELECT ' . $_ddValue . ', ' . $_ddDisplay . ' FROM ' . $_ddTable;
		
	if ( str_len($_Where) > 0 ) {
		$sQuery .= " WHERE is_enabled = 1 AND is_active = 1 AND {$_Where}";
	}
	else {
		$sQuery .= ' WHERE is_enabled = 1 AND is_active = 1';	
	}
	
	if (str_len($_ddSort) > 0) {
		$sQuery .= ' ORDER BY ' . $_ddSort;
	}

	$strDD = '<select id="' . $_ddField . '" name="' . $_ddField . '" class="' . $_ddClass . '"';
	$strDD .= iif(str_len($data_id), ' data-id="' . $data_id . '"') . '>';
	if ( str_len($_AllowNull) > 0 ) {
		$strDD .= '<option value="' . $_NullValue . '">- ' . $_AllowNull . ' -</option>';
	}
	
	//echo $sQuery;
	$rs = getRs($sQuery);// or die ('Error executing list query');
	foreach ($rs as $row) {
		$strDD .= '<option value="' . $row[$_ddValue] . '"';
		if ($_ddSelected == $row[$_ddValue]) {
			$strDD .= ' selected';
		}
		$strDD .= '>';
		if ($_ddTable != 'course') {		
			for ($i = 0; $i < sizeof($arr_ddDisplay); $i += 1) {
				if ($arr_ddDisplay[$i] == 'product_id' || $i == 1) {
					$strDD .= ' (' . $row[$arr_ddDisplay[$i]] . ')';	
				}
				else {
					$strDD .= $row[$arr_ddDisplay[$i]];
				}
				if ($i < sizeof($arr_ddDisplay) - 1) {
					$strDD .= ' ';
				}
			}
		}
		else {
			$strDD .=date(PHP_SHORT_DATE_FORMAT, $row['date_start']) . '. ' . $row['course_name'] . ' (Level ' . $row['level_id'] . '), ' . $row['location']; 
		}
		$strDD .= '</option>';
	}
	$strDD .= '</select>';

	return $strDD;
}

function dboDropDownRecur($_ddTable, $ddValue = '0', $parent_id = 0, $buffer = '', $_AllowNull = '', $_ddField = '', $_ddValue = '', $_ddDisplay = '', $_ddSort = '', $_Where = '', $_ddClass = 'form-control') {
	if (str_len($_ddField) == 0) {
		$_ddField = $_ddTable . '_id';
	}
	$ret = '';
	$rs = getRs("SELECT {$_ddTable}_id, {$_ddTable}_name FROM {$_ddTable} WHERE is_active = 1 AND (parent_{$_ddTable}_id = {$parent_id}" . iif($parent_id == 0, " OR parent_{$_ddTable}_id IS NULL") . ")" . iif(str_len($_Where), " AND " . $_Where) . " ORDER BY sort, {$_ddTable}_id");
	foreach($rs as $row) {
		$ret .= '<option value="' . $row[$_ddTable . '_id'] . '"';
		if ( $ddValue == $row[$_ddTable . '_id'] ) {
			$ret .= ' selected';
		}
		$ret .= '>' . $buffer . $row[$_ddTable . '_name'] . '</option>' . dboDropdownRecur($_ddTable, $ddValue, $row[$_ddTable . '_id'], $buffer . $row[$_ddTable . '_name'] . ' > ');
	}
	if ($parent_id == 0) {
		return '<select id="' . $_ddField . '" name="' . $_ddField . '" class="' . $_ddClass . '" parsley-trigger="change" parsley-required="true" parsley-error-container="#' . $_ddField . '_block">' . iif( str_len($_AllowNull), '<option value="0">- ' . $_AllowNull . ' -</option>') . $ret . '</select>';
	}
	else {
	return $ret;
	}
}

function tDropDown($_ddTable, $_Selected = 0, $_Where = '', $_FieldName = '', $_Css = '') {

	$strDD = '';
	$sQuery = '';
	$i = 0;
	
	$_ddValue = $_ddTable . '_id';
	$_FieldName = (str_len($_FieldName))?$_FieldName:$_ddValue;
	$_ddDisplay = $_ddTable . '_name';
	$_ddSort = 'sort, ' . $_ddValue;
	$arr_ddDisplay = explode(',', $_ddDisplay);
	$sQuery =  'SELECT ' . $_ddValue . ', ' . $_ddDisplay . ' FROM ' . $_ddTable;
	
	$sQuery .= ' WHERE ' . is_enabled();
	
	if ( str_len($_Where) > 0 ) {
		$sQuery .= " AND {$_Where}";
	}
	
	$sQuery .= ' ORDER BY sort, ' . $_ddValue;
	
	$rs = getRs($sQuery);// or die ('Error executing list query');
	foreach ($rs as $row) {
		$strDD .= '<option value="' . $row[$_ddValue] . '"';
		if ($_Selected == $row[$_ddValue]) {
			$strDD .= ' selected';
		}
		$strDD .= '>';
		for ($i = 0; $i < sizeof($arr_ddDisplay); $i += 1) {
			$strDD .= $row[$arr_ddDisplay[$i]];
			if ($i < sizeof($arr_ddDisplay) - 1) {
				$strDD .= ' ';
			}
		}
		$strDD .= '</option>';
	}
	
	$strDD = '<select id="' . $_FieldName . '" name="' . $_FieldName . '" class="form-control ' . $_Css . ' ' . $_FieldName . '"><option value="0">- Please Select -</option>' . $strDD . '</select>';

	return $strDD;
}

function dboRadio($params, $_ddSelected = '') {
	$_ddTable = $_Where = $_ddSort =  $_ddDisplay = $_ddValue = $_ddField = '';
	$_ddType = 'radio';
	$_ddCss = 'inline';

	if (!is_array($params)) {
		$_ddTable = $params;
	}
	else {
		if (isset($params['tbl'])) $_ddTable = $params['tbl'];
		if (isset($params['where'])) $_Where = $params['where'];
		if (isset($params['sort'])) $_ddSort = $params['sort'];
		if (isset($params['display'])) $_ddDisplay = $params['display'];
		if (isset($params['value'])) $_ddValue = $params['value'];
		if (isset($params['field'])) $_ddField = $params['field'];
		if (isset($params['type'])) $_ddType = $params['type'];
		if (isset($params['css'])) $_ddCss = $params['css'];
	}

	$strDD = '';
	$sql = '';
	$i = 0;

	if (str_len($_ddValue) == 0) {
		$_ddValue = $_ddTable . '_id';
	}
	if (str_len($_ddDisplay) == 0) {
		$_ddDisplay = $_ddTable . '_name';
	}
	$arr_ddDisplay = explode(',', $_ddDisplay);
	
	if (str_len($_ddField) == 0) {
		$_ddField = $_ddTable . '_id';
	}
	$sql =  'SELECT ' . $_ddValue . ', ' . $_ddDisplay . ' FROM ' . $_ddTable . ' WHERE ' . is_enabled();
	
	if ( str_len($_Where) > 0 ) {
		$sql .= " AND {$_Where}";
	}	
	if (str_len($_ddSort) > 0) {
		$sql .= ' ORDER BY ' . $_ddSort;
	}
	else {
		$sql .= ' ORDER BY sort';
	}
	

	$rs = getRs($sql);// or die ('Error executing list query');
	foreach ($rs as $row) {
		$strDD .= '<label class="custom-control custom-' . $_ddType . '">
			<input type="' . $_ddType . '" class="custom-control-input radio-' . $_ddTable . '" id="' . $_ddField . '_' . $row[$_ddValue] . '" name="' . $_ddField . iif($_ddType == 'checkbox', '[]') . '" value="' . $row[$_ddValue] . '"';
			if (is_array($_ddSelected)) {
				$strDD .= iif(in_array($row[$_ddValue], $_ddSelected), ' checked');
			}
			else {
				$strDD .= iif(ListFind($_ddSelected, $row[$_ddValue]), ' checked');
			} 
			$strDD .= ' /><span class="custom-control-label">';
			for ($i = 0; $i < sizeof($arr_ddDisplay); $i += 1) {
				$strDD .= $row[$arr_ddDisplay[$i]];
				if ($i < sizeof($arr_ddDisplay) - 1) {
					$strDD .= ' ';
				}
			}
			$strDD .= '</span>
		</label>';
	}

	return '<div class="custom-controls-' . $_ddCss . '">' . $strDD . '</div>';
}

function dboCheckbox($tbl, $value) {
	return dboRadio(array('tbl' => $tbl, 'type' => 'checkbox', 'css' => 'stacked'), $value);
}

function boolRadio($name, $value = '', $css = '') {
	return '<div class="custom-controls-inline">
		<label class="custom-control custom-radio">
			<input type="radio" class="custom-control-input ' . $css . '" id="' . $name . '_1" name="' . $name . '" value="1"' . iif($value == 1, ' checked') . ' />
			<span class="custom-control-label">Yes</span>
		</label>
		<label class="custom-control custom-radio">
			<input type="radio" class="custom-control-input ' . $css . '" id="' . $name . '_0" name="' . $name . '" value="0"' . iif($value == 0 && $value != null, ' checked') . ' />
			<span class="custom-control-label">No</span>
		</label>
	</div>';
}

function districtSelect($district_id = 0) {
	$lga_id = 0;
	$ret = '<select name="district_id" id="district_id" class="form-control">';
	$rs = getRs("SELECT l.lga_id, l.lga_name, d.district_id, d.district_name FROM district d INNER JOIN lga l ON l.lga_id = d.lga_id WHERE " . is_enabled('d,l'));
	foreach($rs as $row) {
		if ($lga_id != $row['lga_id']) {
			$ret .= iif($lga_id, '</optgroup>') . '<optgroup label="' . $row['lga_name'] . '">';
			$lga_id = $row['lga_id'];
		}
		$ret .= '<option value="' . $row['district_id'] .'"' . iif($row['district_id'] == $district_id, ' selected') . '>' . $row['district_name'] . '</option>';
	}
	$ret .= iif($lga_id, '</optgroup>');
	$ret .= '</select>';
	return $ret;
}

function redirectTo($url) {
	header("Location: {$url}");
}

//************************************************
//**************** Helper functions ***************
//*************************************************

function getScriptName() {
  $p = $_SERVER["SCRIPT_NAME"];
	$arr = explode("/", $p);
	return getFilename($arr[sizeof($arr) - 1]);
}

function getVar($f, $d = '') {
	if ( isset($_REQUEST[$f]) ) {
		return safe($_REQUEST[$f]);
	}
	return $d;
}
function getVarJson($f, $numeric = true, $d = null) {
	if ( isset($_REQUEST[$f]) and isJson($_REQUEST[$f])) {
    $a = json_decode($_REQUEST[$f]);
    if ($numeric) return json_encode($a, JSON_NUMERIC_CHECK);
    else return json_encode($a);
	}
	return $d;
}
function getVarAJson($f, $_p, $numeric = true, $d = null) {
	if ( isset($_p[$f]) and isJson($_p[$f])) {
    $a = json_decode($_p[$f]);
    if ($numeric) return json_encode($a, JSON_NUMERIC_CHECK);
    else return json_encode($a);
	}
	return $d;
}
function getVarInt($f, $d = 0, $s = 0, $e = 1) {
	if ( isset($_REQUEST[$f]) && is_numeric($_REQUEST[$f])) {
		$n = round($_REQUEST[$f]);
		return iif($n >= $s && $n <= $e, $n, $d);
	}
	return $d;
}
function getVarNum($f, $d = null) {
	if ( isset($_REQUEST[$f]) ) {
		$r = numFormat($_REQUEST[$f], true);
		return iif(str_len($r) and is_numeric($r), $r, $d);
	}
	return $d;
}

function getVarA($f, $_p, $d = '') {
	if ( isset($_p[$f]) ) {
		return safe($_p[$f]);
	}
	return $d;
}
function getVarAInt($f, $_p, $d = 0, $s = 0, $e = 1) {
	if ( isset($_p[$f]) && is_numeric($_p[$f])) {
		$n = round($_p[$f]);
		return iif($n >= $s && $n <= $e, $n, $d);
	}
	return $d;
}
function getVarANum($f, $_p, $d = null) {
	if ( isset($_p[$f]) ) {
		$r = numFormat($_p[$f], true);
		return iif(str_len($r) and is_numeric($r), $r, $d);
	}
	return $d;
}


function encrypt($text) {
	return $text; //trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, SALT, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
}

function decrypt($encrypted) {
	// trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, SALT, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
	$password = 'encryptuser';
	$method = 'aes-256-cbc';

	// Must be exact 32 chars (256 bit)
	$password = substr(hash('sha256', $password, true), 0, 32);

	// IV must be exact 16 chars (128 bit)
	$iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);

	// av3DYGLkwBsErphcyYp+imUW4QKs19hUnFyyYcXwURU=
	//$encrypted = base64_encode(openssl_encrypt($plaintext, $method, $password, OPENSSL_RAW_DATA, $iv));

	// My secret message 1234
	$decrypted = openssl_decrypt(base64_decode($encrypted), $method, $password, OPENSSL_RAW_DATA, $iv);

	return $decrypted;
}

function createPassword($max_len = 8) {
	$str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789";
	$ret = "";
	srand(time());

	while (str_len($ret) < $max_len) {
		$random = (rand()%35);
		$ret .= substr($str, $random, 1);
	}
	return $ret;
}

function formatPassword($pass) {
	return sha1($pass);
}

function safe($posted) {
	return $posted;
	$posted = str_replace('<', '&lt;', $posted);
	$posted = str_replace('>', '&gt;', $posted);
	/*
	if (!get_magic_quotes_gpc()) {
		return addslashes($posted);
	} else {
		return $posted;
	}
	*/
}

function formatNumber($n) {
	return iif(is_numeric($n), $n, 0);
}

function compareDates($d1, $d2) {
	$t1 = (is_numeric($d1))?$d1:strtotime($d1);
	$t2 = (is_numeric($d2))?$d2:strtotime($d2);
	if ($t1 < $t2) {
		return -1;
	}
	else if ($t1 > $t2) {
		return 1;
	}
	else {
		return 0;
	}
}

function ListLen($l, $d = ',') {
	$a = explode($d, $l ?? '');
	return sizeof($a);
}
function ListAppend($l, $v, $d = ',') {
	return $l . iif((str_len($l ?? '') > 0), $d) . $v;
}
function ListGetAt($l, $i, $d = ',') {
	$v = '';
	$a = explode($d, $l ?? '');
	if ( sizeof($a) >= $i ) {
		$v = $a[$i - 1];
	}
	return $v;
}

function ListLast($l, $d = ',') {
	return ListGetAt($l, ListLen($l, $d), $d);
}

function ListRest($l, $i, $d = ',') {
	$v = '';
	$a = explode($d, $l ?? '');
	for($j=($i - 1); $j<sizeof($a); $j++) {
		$v = ListAppend($v, $a[$j], $d);
	}
	return $v;
}

function ListFind($l, $f) {
	$a = explode(',', $l ?? '');
	foreach ( $a as $v ) {
		if ( strtolower($v) == strtolower($f) ) {
			return true;
		}
	}
	return false;
}

function ValueList($rs, $field, $buffer = '') {
	$ret = '';
	foreach ($rs as $row) {
		$ret = ListAppend($ret, $buffer . $row[$field]);
	}
	return $ret;
}








function yesNoFormat($str, $hide_no = false) {
	if ($str == '1') {
		return '<span class="text-success"><i class="fa fa-check-circle"></i></span>';
	}
	else if ($str == '0') {
		if (!$hide_no) {
			return '<span class="text-danger"><i class="fa fa-times"></i></span>';
		}
	}
	else {
		return $str;
	}
}

function getCurrentHost() {
	$url = ((!empty($_SERVER['HTTPS'])) ? 'https://'.$_SERVER['SERVER_NAME'] : 'http://'.$_SERVER['SERVER_NAME']) . '/';
	//$url = 'https://'.$_SERVER['SERVER_NAME'] . '/';
	return $url;
}

function getCurrentUrl() {
	$request = parse_url($_SERVER['REQUEST_URI']);
	$path = $request["path"];

	$result = trim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $path), '/');

	$result = explode('/', $result);
	$max_level = 2;
	while ($max_level < count($result)) {
			unset($result[0]);
	}
	$result = '/'.implode('/', $result);
	return $result;
}

function completeUrl($url) {
	return iif((strtolower(substr($url, 0, 3)) != 'http'), 'http://') . $url;
}

function getUniqueFilename ($str) {
	$str_name = getFilename($str); //substr($str,0,str_len($str)-4);
	$str_ext = getExt($str);
	
	return strtolower(uniqid(toLink($str_name)) . '.' . $str_ext);
}

function getFileSize($fn) {
	$s = filesize($fn);
	if ($s > 1000000) {
		return number_format($s/1000000, 2) . ' MB';
	}
	else if ($s > 1000) {
		
		return number_format($s/1000, 2) . ' KB';
	}
	else {
		return $s . ' B';
	}
}
function fileSizeFormat($s) {
	if ($s > 1000000) {
		return number_format($s/1000000, 2) . ' MB';
	}
	else if ($s > 1000) {
		
		return number_format($s/1000, 2) . ' KB';
	}
	else {
		return $s . ' B';
	}
}

function getFN($str) {
	return ucwords(pathinfo($str, PATHINFO_FILENAME));
}

function getFilename($str) {
	return strtolower(pathinfo($str, PATHINFO_FILENAME));
}

function getExt($str) {
	return strtolower(pathinfo($str, PATHINFO_EXTENSION));
}

function getUniqueID () {
	return uniqid();
}

function getUniqueCode($maxLength = 36) { // null
    $entropy = '';

    // try ssl first
    if (function_exists('openssl_random_pseudo_bytes')) {
        $entropy = openssl_random_pseudo_bytes(64, $strong);
        // skip ssl since it wasn't using the strong algo
        if($strong !== true) {
            $entropy = '';
        }
    }

    // add some basic mt_rand/uniqid combo
    $entropy .= uniqid(mt_rand(), true);

    // try to read from the windows RNG
	/*
    if (class_exists('COM')) {
        try {
            $com = new COM('CAPICOM.Utilities.1');
            $entropy .= base64_decode($com->GetRandom(64, 0));
        } catch (Exception $ex) {
        }
    }
	*/

    // try to read from the unix RNG
		/*
		if (is_readable('/dev/urandom')) {
        $h = fopen('/dev/urandom', 'rb');
        $entropy .= fread($h, 64);
        fclose($h);
		}
		*/

    $hash = hash('whirlpool', $entropy);
    if ($maxLength) {
        return substr($hash, 0, $maxLength);
    }
    return $hash;
}

function toLink($str, $len = 50, $tolowercase = true) {
	$str = str_replace('@', ' at ', $str);
	$str = str_replace('&amp;', ' and ', $str);
	$str = preg_replace('/[^A-Za-z0-9]/', '-', trim($str));
	$str = preg_replace('/([-])\1+/', '$1', $str);
	if ($tolowercase) {
		$str = strtolower($str);
	}
	return substr($str, 0, $len);
}

function toCode($str, $len = 50) {
	$str = str_replace('@', ' at ', $str);
	$str = str_replace('&amp;', ' and ', $str);
	$str = preg_replace('/[^A-Za-z0-9]/', '_', trim($str));
	$str = preg_replace('/([_])\1+/', '$1', $str);
	return strtolower(substr($str, 0, $len));
}

function isImage($filename) {
	$ext = getExt($filename);
	if (in_array($ext, array('jpg', 'png', 'jpeg'))) {
		return true;
	}
	else {
		return false;
	}
}

function uploadWidget($t, $f, $v = '', $url = '', $multiple = '', $btn = 'Select file &hellip;', $css = 'btn-info', $linked_input = null) {
	$ret = '
<div class="row"><div class="col-sm-12"><input type="hidden" name="TableName" id="TableName" class="fileupload-tbl" value="'  . $t . '" />
<input type="hidden" name="'  . $f . '" id="'  . $f . '" value="'  . $v . '" />
' . iif($linked_input, '<input type="hidden" class="linked-input" value="'  . $linked_input . '" />') . '
      <span class="btn ' . $css . ' fileinput-button">
        ' . $btn . '
        <input id="'  . $f . '_fileupload" type="file" class="fileupload account ' . $multiple . '" name="files[]" ' . $multiple . ' />
      </span>
      <div class="fileupload-progress"></div>
      <div class="upload-preview"' . iif(str_len($v) == 0, ' style="display:none;"') . '>';
      if (!str_len($multiple)) {
        $ret .= iif(str_len($v), '<img src="' . $url . '" alt="' . $v . '" />');
      }
      else {
        if (str_len($v)) {
          $_files = json_decode($v, true);
          foreach($_files as $_f) {
            $ret .= '<div class="media-item"><div class="row"><div class="col-sm-11"><textarea style="display:none;" name="' . $f . '_media_item_data[]">' . json_encode($_f, true) . '</textarea><a href="/media/' . $t . '/' . $_f['name'] . '" target="_blank" class="nothing">' . iif(in_array(getExt($_f['name']), array('jpg', 'jpeg', 'png', 'gif')), '<img src="/media/' . $t . '/sm/' . $_f['name'] . '" width="20" />', '<i class="fa fa-paperclip"></i>') . ' ' . $_f['original_name'] . '</a></div><div class="col-sm-1 text-right"><a href="" class="btn btn-danger btn-sm btn-remove-media-item ' . $f . '"><i class="fa fa-times"></i></a></div></div></div>';
          }
        }
      }
      $ret .= '</div><a href="javascript:void(0)" id="'  . $f . '_remove" class="btn-remove-img btn btn-danger btn-xs mt-2 ' . $f . '"' . iif(str_len($v) == 0, ' style="display:none"') . '>Remove' . iif(str_len($multiple), ' All') . '</a>
</div></div>';

return $ret;
}

function insertPlaceholders($str, $obj) {
	$ret = $str;
	foreach($obj as $p => $v) {
		if (strpos($ret, '{' . $p . '}') !== false) {
			$ret = str_replace('{' . $p . '}', $obj[$p], $ret);				
		}
	}
	return $ret;
}


function gender($gender_id) {
	if ($gender_id == 1) return 'Male';
	else if ($gender_id == 0) return 'Female';
	else return null;
}
function hisHer($gender_id) {
	return iif($gender_id == 1, 'his', iif($gender_id == 2, 'her'));
}

function himHer($gender_id) {
	return iif($gender_id == 1, 'him', iif($gender_id == 2, 'her'));
}

function heShe($gender_id) {
	return iif($gender_id == 1, 'he', iif($gender_id == 2, 'she'));
}

function belongsTo($name) {
	return $name . '\'' . iif(strtolower(substr($name, str_len($name) - 1, 1)) != 's', 's');	
}

function insertHtmlWrapper ($ret) {
	return $ret;
}

/* gets the data from a URL */
 
function getData($url, $a = false) {	
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
	$data = curl_exec($ch);
	curl_close($ch);	
	return $data;
}



function findData($resp, $seek, $before, $after, $loc = 1) {
	$val = '';
	$i = strpos($resp, $seek);
	if ($i > 100) {
		$j = strpos($resp, $before, $i + str_len($seek));
		if ($loc == 2) {
			$j = strpos($resp, $before, $j + 1);
		}
		if ($j > 100) {
			$k = strpos($resp, $after, $j + str_len($before));
	
			if ($k > 100) {
				$val = substr($resp, $j + str_len($before), $k - ($j + str_len($before)));
			}
		}
	}	
	return $val;
}

function currency_format($amount, $currency = '$', $separator = ',', $dp = 2) {
	if (!is_numeric($amount)) return '';
	return iif($amount < 0, '-') . $currency . number_format(abs($amount), $dp, '.', $separator);
}

function remove_currency_format($amount) {
	return preg_replace('/[\$,]/', '', $amount);
}


function isEmail($email) {
  if(preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email)){
    list($username,$domain)=explode('@',$email);
    if(!checkdnsrr($domain,'MX')) {
      //return false;
    }
    return true;
  }
  return false;
}

function shorten($s, $l = 60, $force = false) {
	if (str_len($s) > $l) {
		if (!$force) {
			$i = strpos($s, ' ', $l);
		}
		else {
			$i = $l;
		}
		if ($i) {
			$s = substr($s, 0, $i) . iif(!$force, iif(str_len($s), '&hellip;'));
		}
	}
	return $s;
}

function isDate($d, $format = 'j/n/Y') {
	$a_d = date_parse_from_format($format, $d ?? '');
	if ($a_d['year'] > 0 and $a_d['month'] > 0 and $a_d['day'] > 0) {
		return true;
	}
	else {
		return false;
	}
}

function make_links_clickable($text){
	return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1">$1</a>', $text);
}
function toHours($mins) {
	$hours = floor($mins / 60);
	$mins = $mins % 60;
	return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
}
function getShortDate($d = '', $us = true) {
	if (str_len($d) == 0) return '';
	$t = (str_len($d))?strtotime($d):time();
	if (!$us) return date('j/n/Y', $t);
	else return date('n/j/Y', $t);
}
function getShortDateNow() {
	return date('j/n/Y', time());
}
function getLongDate($d = '', $us = true) {
	if (str_len($d) == 0) return '';
  $t = (str_len($d) and $d != 'now')?strtotime($d):time();
  $a_d = explode(' ', $d);
  if (sizeof($a_d) > 1 or $d == 'now') {
	if (!$us) return date('j/n/Y', $t) . ' ' . date('g:i a', $t);
	else return date('n/j/Y', $t) . ' ' . date('g:i a', $t); 
  }
  else {
	if (!$us) return date('j/n/Y', $t);
	else return date('n/j/Y', $t);
  }
}
function getTime($d = '') {
	if (str_len($d) == 0) return '';
	$t = (str_len($d))?strtotime($d):time();
	return  date('g:i a', $t);
}
function getMediumDate($d) {
	//January 12, 2013 at 1:38 pm
	$t = !is_numeric($d)?strtotime($d):$d;
	return date('F j, Y', $t);
}
function formatLongDate($d) {
	//January 12, 2013 at 1:38 pm
	$t = !is_numeric($d)?strtotime($d):$d;
	return date('F j, Y', $t) . ' at ' . date('g:ia', $t);
}
function dateAdd($givendate,$day=0,$mth=0,$yr=0) {
	$cd = strtotime($givendate);
	$newdate = date('Y-m-d h:i:s', mktime(date('h',$cd),
	date('i',$cd), date('s',$cd), date('m',$cd)+$mth,
	date('d',$cd)+$day, date('Y',$cd)+$yr));
	return $newdate;
}
function isToday($d) {
	return ( date(PHP_DATE_FORMAT, time()) == date(PHP_DATE_FORMAT, $d) );
}
function getTimeDiff($start, $end) {
	$s = abs(strtotime($start) - strtotime($end));
	$days = $hrs = $mins = $secs = 0;
	$day = 60 * 60 * 24;
	$hr = 60 * 60;
	$min = 60;
	$sec = 0;
	$diff = '';
	if ($s >= ($day)) {
		$days = floor($s / $day);
		$s -= ($day * $days);
		$diff .= ' ' . $days . ' day' . iif($days != 1, 's');
	}
	if ($s >= $hr) {
		$hrs = floor($s / $hr);
		$s -= ($hr * $hrs);
		$diff .= ' ' . $hrs . ' hr' . iif($hrs != 1, 's');
	}
	if ($s >= $min) {
		$mins = round($s / $min);
		$s -= ($min * $mins);
		$diff .= ' ' . $mins . ' min' . iif($mins != 1, 's');
	}
	if ($s >= $sec) {
		$secs = round($s);
		$diff .= ' ' . $secs . ' sec' . iif($secs != 1, 's');
	}
	return $diff;
}

function formatTime($s) {
	$s = abs($s);
	if ($s < 60) return $s . ' sec' . iif($s != 1, 's');
	$s = round($s / 60);
	if ($s < 60) return $s . ' min' . iif($s != 1, 's');
	$s = round($s / 60);
	return $s . ' hr' . iif($s != 1, 's');
}


function getTimeSince($ptime, $atime = 'today') {
	$y = 0;
	$m = 0;
	$age = '-';
	if (isdate($ptime)) {
	  $from = new DateTime($ptime);
	  $to   = new DateTime($atime);
	  $y = $from->diff($to)->y;
	  $m = $from->diff($to)->m;
	  $d = $from->diff($to)->d;
	  $age = iif($y, $y . 'y') . iif($m, ' ' . $m . 'm') . iif($d, ' ' . $d . 'd');
	}
	return iif(str_len($age), $age, '...');
  }


function getTimeDiff2($ptime, $atime = '') {
    $etime = ((str_len($atime))?strtotime($atime):time()) - strtotime($ptime);

    if ($etime < 1)
    {
        return '0 seconds';
    }

    $a = array( 365 * 24 * 60 * 60  =>  'year',
                 30 * 24 * 60 * 60  =>  'month',
                      24 * 60 * 60  =>  'day',
                           60 * 60  =>  'hour',
                                60  =>  'minute',
                                 1  =>  'second'
                );
    $a_plural = array( 'year'   => 'years',
                       'month'  => 'months',
                       'day'    => 'days',
                       'hour'   => 'hours',
                       'minute' => 'minutes',
                       'second' => 'seconds'
                );

    foreach ($a as $secs => $str)
    {
        $d = $etime / $secs;
        if ($d >= 1)
        {
            $r = round($d);
            return $r . ' ' . ($r > 1 ? $a_plural[$str] : $str); // . ' ago';
        }
    }
}

function iif($a, $b, $c = '') {
	if ( $a ) return $b;
	else return $c;
}

function getCookie($n, $d = '') {
	if (isset($_COOKIE[$n])) {
		return $_COOKIE[$n];
	}
	else {
		return $d;
	}
}
function saveCookie($n, $v) {
	setcookie($n, $v, time() + (86400 * 30), '/'); // 86400 = 1 day
	return $v;
}

function active($p) {
	global $page_name;
	if ($page_name == $p) {
		return ' class="active"';
	}
	else {
		return '';
	}
}

function checkEmail($email) {
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function insertEmailWrapper ($subject, $message, $footer) {
  global $_Session;
	return '<html><body style="background:#EEEEE;font-family:Arial;font-size:14px;color:#333333;">
	<div style="Margin:0 auto;Max-Width:800px;Background:#FFFFFF;Border:3px solid #ef4923;">
	<div style="Padding:20px 20px 10px 0;font-size:12px;color:#ef4923;Text-Align:right;"><img src="cid:logo" width="100" /></div>
	<div style="Padding:0 0 20px 20px;font-size:16px;color:#666666"><b>' . $subject . '</b></div>
	<div style="Padding:20px;Border-Top:3px solid #CCCCCC;">' . $message . '</div>
	<div style="background:#DDDDDD;">
	<div style="Padding:20px 20px 20px 20px;Text-Align:center;Border-Top:1px solid #CCCCCC;Font-Size:12px;">
  ' . $footer . '
  </div>
	</div></div>
	</body></html>';
}

function sendEmail( $from_name, $from_email, $name, $email, $subject, $message, $footer, $attachments = array(), $bcc = null) {
	//$email = 'aviv@theartisttree.com';
	//$bcc = 'alieu@forte-innovations.com';
	$success = false;
	$response = '';
	
	if ( true ) {

		$mail = new PHPMailer();
		$mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->CharSet = 'UTF-8';
		$mail->Host = 'smtp.gmail.com';
		$mail->Username = 'aviv@theartisttree.com';
		$mail->Password = 'kihpheatoxgyvvyp';
		//$mail->Password = 'rbccmd324';
		$mail->Port = '587';
		$mail->SMTPSecure = 'tls';
		//$mail->SMTPDebug = 2;
		$mail->IsHTML(true);
		$mail->From = $from_email;
		$mail->FromName = $from_name;
		$mail->Subject  = $subject;
		try {
			if (strpos($email, ',') > 0) {
				$a_e = explode(',', $email);
				foreach($a_e as $e) {
					$mail->AddAddress( trim($e), $name );
				}
			}
			else {
				$mail->AddAddress( $email, $name );
			}
			
			if ( str_len($bcc) > 0) {
				$a_bcc = explode(',', $bcc);
				foreach($a_bcc as $b) {
					$mail->AddBCC( trim($b) );
				}
			}

			//put html wrappers in place
			$ret = insertEmailWrapper($subject, $message, $footer);
							
			$mail->Body = $ret;
			$mail->AddEmbeddedImage(MEDIA_PATH . 'site/at.png', 'logo', 'at.png');
			
			if (sizeof($attachments)) {
				foreach($attachments as $attachment) {
					$mail->AddAttachment($attachment['file'], $attachment['name']);
				}
			}
			
			if ($mail->Send()) {
				$success = true;
				$response = 'Message sent successfully to ' . $email . '.';
			}
			else {
				$response = 'The system is unabled to send email at this time. ' . $mail->ErrorInfo;
			}
		}
		catch(Exception $e) {
			$response = $e->getMessage();
			//exit();
		}
	}	
	
	return array('success' => $success, 'response' => $response);
}


function getAge($birthdate) {
	if (!isDate($birthdate)) return '';
		$birthdate = explode('-', $birthdate);
		//get age from date or birthdate
		$age = (date("md", date("U", mktime(0, 0, 0, $birthdate[1], $birthdate[2], $birthdate[0]))) > date("md")
			? ((date("Y") - $birthdate[0]) - 1)
			: (date("Y") - $birthdate[0]));
		return $age;
}


function resizeImage($imagePath, $imageDest, $width, $height, $filterType = Imagick::FILTER_CATROM, $blur = 1, $bestFit = true, $cropZoom = false) {
	//The blur factor where &gt; 1 is blurry, &lt; 1 is sharp.
	$imagick = new \Imagick(realpath($imagePath));

	$imagick->resizeImage($width, $height, $filterType, $blur, $bestFit);

	$cropWidth = $imagick->getImageWidth();
	$cropHeight = $imagick->getImageHeight();

	if ($cropZoom) {
			$newWidth = $cropWidth / 2;
			$newHeight = $cropHeight / 2;

			$imagick->cropimage(
					$newWidth,
					$newHeight,
					($cropWidth - $newWidth) / 2,
					($cropHeight - $newHeight) / 2
			);

			$imagick->scaleimage(
					$imagick->getImageWidth() * 4,
					$imagick->getImageHeight() * 4
			);
	}
	echo $imagick->writeImage($imageDest);
}

function block() {
	exit();	
}

session_start();

function sendMailOnError($errno, $errstr, $errfile, $errline, $errcontext) {
	global $IP;
	global $USER_AGENT;
	$subject = 'Error: ' . $errstr . ' [' . $errno . ']';
	$message = "$errstr [$errno]\n".
					"<table border='1' cellpaddding='5'>".
					((isset($_Session))?"<tr><th>Account:</th><td>" . print_r($_Session, true) . "</td></tr>":"").
					"<tr><th>IP:</th><td>$IP</td></tr>".
					"<tr><th>Browser:</th><td>$USER_AGENT</td></tr>".
					"<tr><th>File:</th><td>$errfile</td></tr>".
					"<tr><th>Line:</th><td>$errline</td></tr>".
					"<tr><th>Current local variables:</th><td>".print_r($errcontext, true) . "</td></tr></table>";
	//send the email here
	sendEmail('Error Detector', 'aviv@theartisttree.com', 'Al', 'al@wantadigital.com', $subject, $message, null);
} 
//set_error_handler('sendMailOnError');

dbConnect(dbhost, dbuser, dbpass);

?>