<?php
require_once ('../_config.php');

//require_once(dirname(__FILE__) . '/../plugins/libmergepdf/vendor/autoload.php');

//use iio\libmergepdf\Merger;
//use iio\libmergepdf\Pages;

$success = false;
$response = $redirect = null;

$po_code = getVar('po_code');
$notification_type_id = getVarNum('notification_type_id');

$email = getVar('email');
$subject = getVar('subject');
$message = getVar('message');
$po_event_status_id = getVarNum('po_event_status_id');

if (!$email) {
    $response = 'E-mail address is required';
}

if (!$response) {
    $rs = getRs("SELECT * FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id));
    if ($r = getRow($rs)) {
        $rv = getRs("SELECT po.po_id, v.vendor_id, v.id AS vendor_code, v.name AS vendor_name, po.email, s.description, CONCAT('<b>', s.description, '</b><br />', s.address, '<br />', s.city, ', ', s.state, ' ', s.zip, '<br />Ph: ', s.phone) AS store_address, po.po_code, po.po_id, po.po_number, po.po_filename, DATE_FORMAT(po.date_created, '%b %D, %Y %l:%i %p') AS date_created, CONCAT('https://scheduling.theartisttree.com/po/', v.id, '/', po.po_code) AS link, s.params FROM store s INNER JOIN ({$_Session->db}.vendor v INNER JOIN po ON po.vendor_id = v.vendor_id) ON s.store_id = po.store_id WHERE po.po_code = ? AND " . is_enabled('v,po'), array($po_code));
        if ($v = getRow($rv)) {
            $params = json_decode($v['params'], true);
            $from_name = $v['description'];
            $from_email = $params['po_email'];
            $notification_id = dbPut('notification', array('admin_id' => $_Session->admin_id, 'notification_type_id' => $r['notification_type_id'],  'po_id' => $v['po_id'], 'vendor_id' => $v['vendor_id'], 'email' => $email, 'subject' => $subject, 'message' => $message));
            
            $attachments = array();
            if ($r['attachment'] and $v['po_filename']) {
                array_push($attachments, array('file' => MEDIA_PATH . 'po/' . $v['po_filename'], 'name' => $v['po_filename']));
            }
            sendEmail($from_name, $from_email, $v['vendor_name'], $email, $subject, $message, $v['store_address'], $attachments);
            $success = true;
            $response = 'Sent successfully';
            $redirect = '{refresh}';
            setRs("UPDATE po SET email = ? WHERE (email IS NULL OR email <> ?) AND po_id = ?", array($email, $email, $v['po_id']));
            setRs("UPDATE {$_Session->db}.vendor SET email = ? WHERE LENGTH(COALESCE(email, '')) = 0 AND vendor_id = ?", array($email, $v['vendor_id']));
            
            $_PO->SavePONote($v['po_id'], $r['notification_type_name'] .  ' notification sent to ' . $email);

            if ($po_event_status_id) {
                setRs("UPDATE po SET po_event_status_id = ?, date_po_event_notified = NOW() WHERE po_id = ?", array($po_event_status_id, $v['po_id']));
                $_PO->SavePONote($v['po_id'], 'Delivery status changed to: ' . getDisplayName('po_event_status', $po_event_status_id));

                
                if (in_array($r['notification_type_id'], array(3,4))) {
                    setRs("UPDATE po_event SET po_event_status_id = 5 WHERE FIND_IN_SET(po_event_status_id, '1,2') AND po_id = ? AND " . is_enabled(), array($v['po_id']));
                }

                //$po_event_id = dbPut('po_event', array('store_id' => $r['store_id'], 'po_id' => $r['po_id'], 'vendor_id' => $v['vendor_id'], 'po_number' => $po_number, 'vendor_name' => $vendor_name, 'po_event_status_id' => 2, 'date_start' => $date_start, 'description' => $description));

            }
            else {                
                setRs("UPDATE po SET po_event_status_id = 1, date_po_event_notified = NOW() WHERE po_id = ?", array($v['po_id']));
                $_PO->SavePONote($v['po_id'], 'Delivery status changed to: ' . getDisplayName('po_event_status', 1));
            }
        }
    }
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
					
?>