<?php
require_once ('../_config.php');

$success = false;
$response = '';
$redirect = isset($redirect)?$redirect:'';

$code = getVar('c');
$tbl = getVar('t');

if ($tbl == 'account') {
    $rs = getRs("SELECT account_id, admin_id, account_category_id FROM account WHERE " . is_active() . " AND account_code = ?", array($code));
    if ($r = getRow($rs)) {
      $account_id = $r['account_id'];
      $rs_1 = getRs("SELECT * FROM account WHERE account_id = ?", array($account_id));

      setRs("UPDATE account SET is_active = 0 WHERE account_id = ?", array($account_id));  

      $success = true;
      $response = 'Archived';
      
      saveActivity('archive', $account_id, 'account', 'Account archived', getRow($rs_1));
      dbPut('file', array('re_tbl' => 'account', 're_id' => $account_id, 'admin_id' => $_Session->admin_id, 'description' => 'Account archived'));
    }
    else {
        $response = 'Failed';
    }
}
else if ($tbl == 'gamis') {
  $rs = getRs("SELECT p.*, m.gamis_market_type_id FROM gamis_market_price p INNER JOIN gamis_market m ON m.gamis_market_id = p.gamis_market_id WHERE " . is_active('p,m') . " AND p.gamis_market_price_code = ?", array($code));
  if ($r = getRow($rs)) {

    $gamis_market_price_id = $r['gamis_market_price_id'];
    setRs("UPDATE gamis_market_price SET is_active = 0 WHERE gamis_market_price_id = ?", array($gamis_market_price_id));

    $success = true;
    $response = 'Approved';

    saveActivity('archive', $gamis_market_price_id, 'gamis_market_price', 'Market prices (' . $gamis_market_price_id . ') archived', $r);
    dbPut('file', array('re_tbl' => 'gamis_market_price', 're_id' => $gamis_market_price_id, 'admin_id' => $_Session->admin_id, 'description' => 'Market prices (' . $gamis_market_price_id . ') archived'));
  }
  else {
      $response = 'Failed';
  }
}
else if ($tbl == 'nass') {
    $rs = getRs("SELECT * FROM nass WHERE " . is_active() . " AND nass_code = ?", array($code));
    if ($r = getRow($rs)) {
        $nass_id = $r['nass_id'];
        $rs_1 = getRs("SELECT * FROM nass WHERE nass_id = ?", array($nass_id));
        setRs("UPDATE nass SET is_active = 0 WHERE nass_id = ?", array($nass_id));

        $success = true;
        $response = 'Archived';
        $description = 'NASS ' . $r['nass_form_id'] . ' (' . $r['nass_id'] . ') archived';
        saveActivity('archive', $nass_id, 'nass', $description, getRow($rs));
        dbPut('file', array('re_tbl' => 'account', 're_id' => $r['account_id'], 'admin_id' => $_Session->admin_id, 'description' => $description));
    }
    else {
        $response = 'Failed';
    }
}
else if ($tbl == 'livestock') {
    $rs = getRs("SELECT account_livestock_id, account_id, account_livestock_survey_type_id FROM account_livestock WHERE " . is_active() . " AND is_approved = 0 AND account_livestock_code = ?", array($code));
    if ($r = getRow($rs)) {
        $account_livestock_id = $r['account_livestock_id'];
        $rs_1 = getRs("SELECT * FROM account_livestock WHERE account_livestock_id = ?", array($account_livestock_id));
        setRs("UPDATE account_livestock SET is_active = 0 WHERE account_livestock_id = ?", array($account_livestock_id));

        $success = true;
        $response = 'Archived';
        $description = iif($r['account_livestock_survey_type_id'] == 1, 'Livestock price survey', 'Livestock sales survey') . ' (' . $r['account_livestock_id'] . ') archived';
        saveActivity('archive', $account_livestock_id, 'account_livestock', $description, getRow($rs_1));
        dbPut('file', array('re_tbl' => 'account', 're_id' => $r['account_id'], 'admin_id' => $_Session->admin_id, 'description' => $description));
    }
    else {
        $response = 'Failed';
    }
}
else {
    $response = 'Missing info';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
					
?>