<?php
require_once('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . date('r', time() + (86400 * 365)));
header('Content-type: application/json');

function _json_err($msg) {
	echo json_encode(array('success' => false, 'error' => $msg));
	exit();
}

$po_id = (int) getVar('po_id');
if ($po_id <= 0) {
	_json_err('PO ID is required.');
}

$admin_id = $_Session->admin_id;
$admin_name = '';
$ra = getRs("SELECT admin_name, email FROM {$_Session->db}.admin WHERE admin_id = ?", array($admin_id));
if ($r = getRow($ra)) {
	$admin_name = (string) $r['admin_name'];
}

$rs = getRs("SELECT s.store_name, s.params, po.po_name, po.po_number, po.vendor_name, po.po_code, st.po_status_id, st.po_status_name
	FROM {$_Session->db}.po
	INNER JOIN {$_Session->db}.store s ON s.store_id = po.store_id
	INNER JOIN {$_Session->db}.po_status st ON st.po_status_id = po.po_status_id
	WHERE po.po_id = ?", array($po_id));

if (!($r = getRow($rs))) {
	_json_err('PO not found.');
}

$store = $r['store_name'];
$host = rtrim(getCurrentHost(), '/');
$from_name = 'BOH Request';
$from_email = 'admin@theartisttree.com';
$to_name = 'BOH Request';
$to_email = 'andrewz@theartisttree.com';
$subject = "PO {$r['po_number']} Needs Your Attention! ({$store})";
$message = '<b>' . htmlspecialchars($admin_name) . '</b> has notified you that <b>PO ' . htmlspecialchars($r['po_number']) . '</b> needs your attention.<br><br>'
	. 'PO Name: <b>' . htmlspecialchars($r['po_name']) . '</b><br>'
	. 'PO Number: <b>' . htmlspecialchars($r['po_number']) . '</b><br>'
	. 'PO Status: <b>' . htmlspecialchars($r['po_status_name']) . '</b><br>'
	. 'Store Name: <b>' . htmlspecialchars($r['store_name']) . '</b><br>'
	. 'Vendor: <b>' . htmlspecialchars($r['vendor_name']) . '</b><br>'
	. 'Link: <b>' . htmlspecialchars($host . '/po/' . $r['po_code']) . '</b>';

try {
	sendEmail($from_name, $from_email, $to_name, $to_email, $subject, $message, '');
} catch (Throwable $e) {
	_json_err('Email failed: ' . $e->getMessage());
}

$note = 'Notify Andrew: ' . $admin_name . ' sent an email notification to Andrew.';
setRs("INSERT INTO {$_Session->db}.file (re_tbl, re_id, admin_id, description, is_auto) VALUES ('po', ?, ?, ?, 0)", array($po_id, $admin_id, $note));

echo json_encode(array('success' => true, 'message' => 'Email sent to Andrew.'));
exit();
