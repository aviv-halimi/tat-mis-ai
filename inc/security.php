<?php

$goToLogin = false;

$_admin_token = getVar('admin_token');
if (strlen($_admin_token)) {
	$rs = getRs("SELECT a.account_id, u.admin_id FROM account a INNER JOIN admin u ON u.account_id = a.account_id WHERE " . is_enabled('a,u') . " AND u.admin_token = ?", array($_admin_token));
	if ($row = getRow($rs)) {
		setRs("UPDATE admin SET admin_token = NULL WHERE admin_id = ?", array($row['admin_id']));
		$a = $_Session->Login('', '', 0, $row['admin_id'], false); //login but don't save interaction
	}
}

if (!defined('SkipAuth')) {
	if (!$_Session->admin_id) {
		$goToLogin = true;
		$access_token = getCookie('tat_mis_access_token');
		if (strlen($access_token)) {
			$rs = getRs("SELECT a.admin_id FROM admin a WHERE " . is_enabled('a') . " AND a.access_token = ?", array($access_token));
			if ($row = getRow($rs)) {
				$a = $_Session->Login('', '', 1, $row['admin_id']);
				if ($a['success']) {
					$goToLogin = false;
				}
			}
			else {
				$_Session->Logout();
			}
		}
	}	
}

if ($goToLogin == true and $page_name != 'blocked') {
	$r = getCurrentUrl();
	if ($r != '/') $r = '?r=' . urlencode(encrypt($r));
	else $r = '';
  redirectTo('/login' . $r);
  exit();
}

$request = parse_url($_SERVER['REQUEST_URI']);
$path = $request["path"];

$result = strtolower(trim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $path), '/'));
$a__path = explode('/', $result);
if ($a__path[0] == 'api') {
	if (!in_array($IP, array('18.215.197.194', '197.242.141.74', '197.242.130.10', '127.0.0.1', '41.223.213.86', '212.60.74.90', '100.20.209.199','76.50.144.94','76.174.149.62','99.95.100.34'))) {
		echo 'Unauthorized access. IP logged: ' . $IP;
		exit();
	}
}

?>