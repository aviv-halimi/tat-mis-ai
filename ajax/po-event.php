<?php
require_once ('../_config.php');

$success = false;
$response = 'Sorry, appointment could not be set.';
$redirect = null;

$po_code = getVar('po_code');
$date_start = getVar('date_start');
$description = getVar('description');

if (!$date_start) $response = 'Please specify date and time';
else if (!$po_code) $response = 'Please select PO';
else {

    $rs = getRs("SELECT po.*, s.db FROM po INNER JOIN store s ON s.store_id = po.store_id WHERE po.po_code = ? AND " . is_enabled('s,po'), array($po_code));
    if ($r = getRow($rs)) {
        $vendor_name = $contact_name = null;
        $rv = getRs("SELECT v.vendor_id, v.id AS vendor_code, v.name AS vendor_name, v.firstName, v.lastName, v.email, s.description, CONCAT('<b>', s.description, '</b><br />', s.address, '<br />', s.city, ', ', s.state, ' ', s.zip, '<br />Ph: ', s.phone) AS store_address, po.po_code, po.po_id, po.po_number, po.po_filename, DATE_FORMAT(po.date_created, '%b %D, %Y %l:%i %p') AS date_created, CONCAT('https://scheduling.theartisttree.com/po/', v.id, '/', po.po_code) AS link, s.params FROM store s INNER JOIN ({$r['db']}.vendor v INNER JOIN po ON po.vendor_id = v.vendor_id) ON s.store_id = po.store_id WHERE po.po_code = ? AND " . is_enabled('v,po'), array($po_code));
        if ($v = getRow($rv)) {

            if (checkAvailability($date_start, $r['store_id'], $r['po_id'])) {
                $po_number = $v['po_number'];
                $vendor_name = $v['vendor_name'];
                $contact_name = $v['firstName'] . ' ' . $v['lastName'];
                $v['date'] = date('D, M jS, Y g:i A', strtotime($date_start));

                setRs("UPDATE po_event SET po_event_status_id = 5 WHERE FIND_IN_SET(po_event_status_id, '1,2') AND po_id = ? AND " . is_enabled(), array($r['po_id']));

                $po_event_id = dbPut('po_event', array('store_id' => $r['store_id'], 'po_id' => $r['po_id'], 'vendor_id' => $v['vendor_id'], 'po_number' => $po_number, 'vendor_name' => $vendor_name, 'po_event_status_id' => 2, 'date_start' => $date_start, 'description' => $description));
                setRs("UPDATE po_event SET date_end = DATE_ADD(date_start, INTERVAL 1 HOUR) WHERE po_event_id = ?", array($po_event_id));
                setRs("UPDATE po SET po_event_status_id = 2, date_po_event_scheduled = ? WHERE po_id = ?", array($date_start, $r['po_id']));
                $success = true;
                $response = 'Thank you. Your appointment has been successfully set.';
                $redirect = '/po/' . $po_code; //{refresh}';

                $rn = getRs("SELECT * FROM notification_type WHERE " . is_enabled() . " AND notification_type_code = ?", array('po-scheduled'));
                if ($n = getRow($rn)) {
                    $v['date_scheduled'] = getLongDate($date_start);
                    $params = json_decode($v['params'], true);
                    $from_name = $v['description'];
                    $from_email = $params['po_scheduling_email'];
                    $bcc = isset($params['po_scheduled_email_bcc'])?$params['po_scheduled_email_bcc']:null;
                    $email = $v['email'];
                    $subject = insertPlaceholders($n['subject'], $v);
                    $message = insertPlaceholders($n['message'], $v);
                    $notification_id = dbPut('notification', array('notification_type_id' => $n['notification_type_id'],  'po_id' => $v['po_id'], 'vendor_id' => $v['vendor_id'], 'email' => $email, 'subject' => $subject, 'message' => $message));

                    sendEmail($from_name, $from_email, $v['vendor_name'], $email, $subject, $message, $v['store_address'], $bcc);
                }
            }
            else {
                $response = 'Sorry, this slot is no longer available. Please select another slot.';
            }
        }
        else {
            $response = 'Invalid code';
        }
    }
}

function checkAvailability($date, $store_id, $po_id) {
    if (strtotime($date) < time()) {
        return false;
    }
    $_duration = 60;
    $rss = getRs("SELECT value FROM setting WHERE setting_code = 'po-event-duration'");
    if ($ss = getRow($rss)) {
        $_duration = $ss['value'];
    }
    $date_start = strtotime($date);
    $date_end = $date_start + ($_duration * 60);

    $date_start = date('Y-m-d H:i', $date_start);
    $date_end = date('Y-m-d H:i', $date_end);
    $rs = getRS("SELECT po_event_id FROM po_event WHERE FIND_IN_SET(po_event_status_id, '1,2') AND ? >= date_start AND ? <= date_end AND store_id = ? AND po_id <> ? AND " . is_enabled(), array($date_start, $date_end, $store_id, $po_id));
    if ($r = getRow($rs)) {
        return false;
    }
    else {
        return true;
    }
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
					
?>