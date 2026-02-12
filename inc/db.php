<?php
/* Config parameters */
	
$dbconn;

$IP = '';
$USER_AGENT = '';
if (isset($_SERVER['REMOTE_ADDR'])) {
	$IP = $_SERVER['REMOTE_ADDR'];
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
	$USER_AGENT = substr($_SERVER['HTTP_USER_AGENT'], 0, 244);
}

$passMask = '{encrypted}';

//************************************************
//*************** Database functions **************
//*************************************************

function getOffset() {
	$now = new DateTime();
	$mins = $now->getOffset() / 60;
	$sgn = ($mins < 0 ? -1 : 1);
	$mins = abs($mins);
	$hrs = floor($mins / 60);
	$mins -= $hrs * 60;
	$offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
	return $offset;
}

function dbConnect($dbhost, $dbuser, $dbpass) {
	global $dbconn;
  $dbconn = new PDO($dbhost, $dbuser, $dbpass);
	$dbconn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	$dbconn->exec("SET time_zone='" . getOffset() . "';");
}

function dbClose() {
	global $dbconn;
	
	if (isset($dbconn)) {
		$dbconn = null;
	}
}

function getRs($sql, $params = array()) {
	global $dbconn;
	
	if (!is_array($params)) $params = array($params);
	
	try {
		$stmt = $dbconn->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}
	catch( PDOExecption $e ) {
		trigger_error('Error!: ' . $e->getMessage());
	}
}

function setRs($sql, $params = array(), $tbl = '') {
	global $dbconn;

	if (!is_array($params)) $params = array($params);
	
	try {
		$stmt = $dbconn->prepare($sql);	
		try {
			//$dbconn->beginTransaction();
			$stmt->execute($params);
			//$dbconn->commit();
			$stmt = $dbconn->prepare('SELECT @@IDENTITY' . iif(str_len($tbl), ' FROM ' . $tbl));
			$stmt->execute();
			foreach($stmt->fetchAll() as $row) {
				return $row[0];
			}
		}
		catch(PDOExecption $e) {
			$dbconn->rollback();
			trigger_error('Error!: ' . $e->getMessage());
		}
	}
	catch( PDOExecption $e ) {
		trigger_error('Error!: ' . $e->getMessage());
	}
	return 0;
}

function getRow($rs) {
	if (sizeof($rs)) {
		return $rs[0];
	}
	else {
		return false;
	}
}

function is_enabled($n = '') {
	if ( str_len($n) == 0) {
		return 'is_active = 1 AND is_enabled = 1';
	}
	else {
		$a = explode(',', $n);
		$i = 0;
		$s = '';
		foreach ($a as $v) {
			$i++;
			$s .= (($i > 1)?' AND ':'') . $v . '.is_active = 1 AND ' . $v . '.is_enabled = 1';
		}
		return $s;
	}
}

function is_active($n = '') {
	if ( str_len($n) == 0) {
		return 'is_active = 1';
	}
	else {
		$a = explode(',', $n);
		$i = 0;
		$s = '';
		foreach ($a as $v) {
			$i++;
			$s .= (($i > 1)?' AND ':'') . $v . '.is_active = 1';
		}
		return $s;
	}
}

function dbInsert($tbl, $p = array()) {
	$params = $fields = $values = array();
	if (is_array($p)) {
		foreach($p as $k => $v) {
			array_push($fields, $k);
			if ($v === 'CURDATE()') {
				array_push($values, 'CURDATE()');
			}
			else if ($v === 'NOW()') {
				array_push($values, 'NOW()');
			}
			else {				
				array_push($values, '?');
				array_push($params, $v);
			}
		}
	}
	else {
		array_push($fields, $tbl . '_name');
		array_push($values, '?');
		array_push($params, $p);
	}
	return setRs("INSERT INTO {$tbl} (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")", $params);
}

function dbUpdate($tbl, $p = array(), $id = null, $id_name = null) {
	$params = $fields = array();
	$a_tbl = explode('.', $tbl);
	if (sizeof($a_tbl) == 2) {
		$db = $a_tbl[0] . '.';
		$tbl = $a_tbl[1];
	}
	else {
		$db = '';
	}
	if (!$id_name) $id_name = $tbl . '_id';
	foreach($p as $k => $v) {
		if ($v === 'CURDATE()') {
			array_push($fields, $k . " = CURDATE()");
		}
		else if ($v === 'NOW()') {
			array_push($fields, $k . " = NOW()");
		}
		else {
			array_push($fields, $k . " = ?");
			array_push($params, $v);
		}
	}
	array_push($params, $id);
	return setRs("UPDATE {$db}{$tbl} SET " . implode(',', $fields) . " WHERE {$id_name} = ?", $params);
}

function dbPut($tbl, $p = array()) {
	return dbInsert($tbl, $p);
}

function dbGet($tbl, $p = array(), $id = null) {
	$params = array();
	if ($id) $params = array($id);
	return getRs("SELECT * FROM {$tbl} WHERE " . is_enabled() . iif($id, " AND {$tbl}_id = ?") . " ORDER BY sort", $params);
}

function dbFetch($tbl) {
	$rs = getRs("SELECT * FROM {$tbl} WHERE " . is_active() . " ORDER BY sort, {$tbl}_id");
	$a = array();
	foreach($rs as $r) {
		$a[$tbl . '_' . $r[$tbl . '_id']] = $r[$tbl . '_name'];
	}
	return $a;
}

function dbFieldName($tbl, $id, $_name = null, $_db = null) {
	if (str_len($_db)) $_db .= '.';
	if (!str_len($_name)) $_name = $tbl . '_name';
	$rs = getRs("SELECT {$_name} FROM {$_db}{$tbl} WHERE {$tbl}_id = ? AND " . is_active(), array($id));
	if ($r = getRow($rs)) {
		return $r[$_name];
	}
}

function dbFieldNames($tbl, $json, $return_rs = false, $d = ', ') {
	$a = $rs = array();
	if (isJson($json)) {
		$ids = implode(',', json_decode($json));
		if (str_len($ids)) {
			$rs = getRs("SELECT {$tbl}_id, {$tbl}_name FROM {$tbl} WHERE FIND_IN_SET({$tbl}_id, ?) AND " . is_active() . " ORDER BY sort, {$tbl}_id", array($ids));
			foreach ($rs as $r) {
				array_push($a, $r[$tbl . '_name']);
			}
		}
	}
	if ($return_rs) return $rs;
	else return implode($d, $a);
}

function dbLog($description) {
	dbInsert('log', array('description' => $description));
}

function addData($tbl, $data, $code = false) {
	if (is_array($data)) {
	  foreach($data as $d) {
		$a = explode('|', $d);
		if (sizeof($a) == 1) {
		  setRs("INSERT INTO {$tbl} ({$tbl}_name) VALUES (?)", array($d));
		}
		else if ($code) {
		  setRs("INSERT INTO {$tbl} ({$tbl}_code, {$tbl}_name) VALUES (?, ?)", array($a[0], $a[1]));
		}
		else if (sizeof($a) == 2) {
		  setRs("INSERT INTO {$tbl} ({$tbl}_category_id, {$tbl}_name) VALUES (?, ?)", array($a[0], $a[1]));
		}
		else if (sizeof($a) == 3) {
		  setRs("INSERT INTO {$tbl} ({$tbl}_code, {$tbl}_name, css) VALUES (?, ?, ?)", array($a[0], $a[1], $a[2]));
		}
		else if (sizeof($a) == 4) {
		  setRs("INSERT INTO {$tbl} ({$tbl}_code, {$tbl}_name, css, color) VALUES (?, ?, ?, ?)", array($a[0], $a[1], $a[2], $a[3]));
		}
	  }
	}
  }

  function getIdName($tbl, $id) {
    $rs = getRs("SELECT {$tbl}_name FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_id = ?", array($id));
    if ($row = getRow($rs)) {
      return $row[$tbl . '_name'];
    }
    else {
      return null;
		}
	}

  function getCodeId($tbl, $code) {
    $rs = getRs("SELECT {$tbl}_id FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_code = ?", array($code));
    if ($row = getRow($rs)) {
      return $row[$tbl . '_id'];
    }
    else {
      return null;
    }
  }
  function getIdCode($tbl, $id) {
    $rs = getRs("SELECT {$tbl}_code FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_id = ?", array($id));
    if ($row = getRow($rs)) {
      return $row[$tbl . '_code'];
    }
    else {
      return null;
    }
  }
?>