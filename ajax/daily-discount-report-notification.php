<?php
/**
 * Send email to brand for daily discount report (notification_type_id = 7).
 * POST: daily_discount_report_brand_id, notification_type_id, email, contact_name, subject, message.
 * Saves email/contact_name to store 1 brand table if provided; attaches report PDF.
 */
require_once(__DIR__ . '/../_config.php');

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$success = false;
$response = '';
$redirect = null;

try {

$daily_discount_report_brand_id = getVarInt('daily_discount_report_brand_id', 0, 0, 999999);
$notification_type_id = getVarNum('notification_type_id', 7, 1, 999);
$email = trim(getVar('email', ''));
$contact_name = trim(getVar('contact_name', ''));
$subject = trim(getVar('subject', ''));
$message = getVar('message', '');

if ($email === '') {
    echo json_encode(array('success' => false, 'response' => 'E-mail address is required.', 'redirect' => $redirect));
    exit;
}

$rb = getRow(getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id, rb.filename, r.date_start, r.date_end, b.name AS brand_name FROM daily_discount_report_brand rb INNER JOIN daily_discount_report r ON r.daily_discount_report_id = rb.daily_discount_report_id INNER JOIN blaze1.brand b ON b.brand_id = rb.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb,r'),
    array($daily_discount_report_brand_id)
));
if (!$rb) {
    echo json_encode(array('success' => false, 'response' => 'Report brand not found.', 'redirect' => $redirect));
    exit;
}

$rs = getRs("SELECT * FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id));
$r = $rs ? getRow($rs) : null;
if (!$r) {
    echo json_encode(array('success' => false, 'response' => 'Notification type not found.', 'redirect' => $redirect));
    exit;
}

$brand_id = (int)$rb['brand_id'];
$store1 = getRow(getRs("SELECT db, description, params FROM store WHERE store_id = 1 AND " . is_enabled(), array()));
$store1_db = ($store1 && !empty($store1['db'])) ? preg_replace('/[^a-z0-9_]/i', '', $store1['db']) : '';

// Save contact_email and contact_name to store 1 brand table if columns exist
if ($store1_db !== '') {
    $col_check = getRs("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME IN ('contact_email', 'contact_name')", array($store1_db));
    $cols = array();
    if (is_array($col_check)) {
        foreach ($col_check as $row) {
            $cols[] = $row['COLUMN_NAME'];
        }
    }
    if (in_array('contact_email', $cols) || in_array('contact_name', $cols)) {
        $updates = array();
        $params = array();
        if (in_array('contact_email', $cols)) {
            $updates[] = 'contact_email = ?';
            $params[] = $email;
        }
        if (in_array('contact_name', $cols)) {
            $updates[] = 'contact_name = ?';
            $params[] = $contact_name;
        }
        if (!empty($updates)) {
            $params[] = $brand_id;
            setRs("UPDATE `" . str_replace('`', '``', $store1_db) . "`.brand SET " . implode(', ', $updates) . " WHERE master_brand_id = ?", $params);
        }
    }
}

$from_name = ($store1 && !empty($store1['description'])) ? $store1['description'] : 'The Artist Tree';
$from_email = 'aviv@theartisttree.com';
if ($store1 && !empty($store1['params'])) {
    $params = @json_decode($store1['params'], true);
    if (is_array($params) && !empty($params['po_email'])) {
        $from_email = trim($params['po_email']);
    }
}

$attachments = array();
$pdf_path = MEDIA_PATH . 'daily_discount_report_brand/' . (isset($rb['filename']) ? $rb['filename'] : '');
if (!empty($rb['filename']) && is_file($pdf_path)) {
    $attachments[] = array('file' => $pdf_path, 'name' => $rb['filename']);
}

$to_name = $contact_name !== '' ? $contact_name : (isset($rb['brand_name']) ? $rb['brand_name'] : 'Brand');
$result = sendEmail($from_name, $from_email, $to_name, $email, $subject, $message, null, $attachments);

if (!empty($result['success'])) {
    $success = true;
    $response = 'Sent successfully to ' . $email . '.';
    $redirect = '{refresh}';
} else {
    $response = isset($result['response']) ? $result['response'] : 'Failed to send email.';
}

} catch (Throwable $e) {
    $response = 'Error: ' . $e->getMessage();
    $success = false;
}

echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit;
