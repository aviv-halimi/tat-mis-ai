<?php
require_once ('../_config.php');

$success = false;
$response = '';

$daily_discount_id = getVarNum('id');
$is_active = getVarInt('is_active');

if ($daily_discount_id) {
    $rs = getRs("SELECT weekday_id, daily_discount_id FROM daily_discount WHERE daily_discount_id = ?", array($daily_discount_id));
    if ($r = getRow($rs)) {
		setRs("UPDATE daily_discount SET is_active = ? WHERE daily_discount_id = ?", array($is_active, $daily_discount_id));
        $success = true;
        $response = 'Deleted successfully';
		/*
        $weekday_id = date('N');
        $a_weekday_ids = array($weekday_id);
        $weekday_id -= 1;
        if ($weekday_id < 1) $weekday_id = 7;
        array_push($a_weekday_ids, $weekday_id);
        if (!in_array($r['weekday_id'], $a_weekday_ids)) {
            setRs("UPDATE daily_discount SET is_active = ? WHERE daily_discount_id = ?", array($is_active, $daily_discount_id));
            $success = true;
            $response = 'Deleted successfully';
        }
        else {
            $response = 'You cannot delete discounts for this day because they are still active. Try deactivating instead.';
        }*/
    }
    else {
        $response = 'Not found';
    }
}
else {
    $response = 'Missing info';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response));
exit();
					
?>