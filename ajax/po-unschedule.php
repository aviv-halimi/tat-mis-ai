<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . date('r', time() + (86400 * 365)));
header('Content-type: application/json');

$success = false;
$response = 'Sorry, the delivery could not be un-scheduled.';
$redirect = null;

$po_code = getVar('po_code');

if (!$po_code) {
    $response = 'Please select PO';
}
else {
    $rs = getRs("SELECT po_id, store_id, po_event_status_id FROM po WHERE po_code = ? AND " . is_enabled(), array($po_code));
    if ($r = getRow($rs)) {
        if ($r['store_id'] != $_Session->store_id) {
            $response = 'You do not have access to this PO.';
        }
        else {
            setRs("UPDATE po_event SET po_event_status_id = 5 WHERE FIND_IN_SET(po_event_status_id, '1,2') AND po_id = ? AND " . is_enabled(), array($r['po_id']));
            setRs("UPDATE po SET is_confirmed = 0, po_event_status_id = 1, date_po_event_scheduled = NULL WHERE po_id = ?", array($r['po_id']));
            $success = true;
            $response = 'Delivery has been un-scheduled.';
            $redirect = '/po/' . $po_code;
        }
    }
    else {
        $response = 'Invalid PO code.';
    }
}

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
?>
