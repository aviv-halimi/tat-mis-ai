<?php
require_once('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . date('r', time() + (86400 * 365)));
header('Content-type: application/json');

$po_id = getVarInt('po_id', 0);
if (!$po_id) {
	echo json_encode(array('success' => false, 'error' => 'PO ID is required.'));
	exit();
}

$admin_id = $_Session->admin_id;
$admin_name = null;
$admin_email = null;
$ra = getRs("SELECT admin_name, email FROM {$_Session->db}.admin WHERE admin_id = ?", array($admin_id));
if ($r = getRow($ra)) {
	$admin_name = $r['admin_name'];
	$admin_email = $r['email'];
}

$rs = getRs("SELECT s.store_name, s.params, po.po_name, po.po_number, po.vendor_name, po.po_code, st.po_status_id, st.po_status_name
	FROM {$_Session->db}.po
	INNER JOIN {$_Session->db}.store s ON s.store_id = po.store_id
	INNER JOIN {$_Session->db}.po_status st ON st.po_status_id = po.po_status_id
	WHERE po.po_id = ?", array($po_id));

if (!($r = getRow($rs))) {
	echo json_encode(array('success' => false, 'error' => 'PO not found.'));
	exit();
}

$store = $r['store_name'];
$params = $r['params'] ? json_decode($r['params'], true) : array();

$from_name = 'BOH Request';
$from_email = 'admin@theartisttree.com';
$to_name = 'BOH Request';
$to_email = 'andrewz@theartisttree.com';
$subject = "PO {$r['po_number']} Needs Your Attention! ({$store})";
$message = "
	<b>{$admin_name}</b> has notified you that <b>PO {$r['po_number']}</b> needs your attention.<br><br>
	PO Name:  <b>" . htmlspecialchars($r['po_name']) . "</b><br>
	PO Number:  <b>{$r['po_number']}</b><br>
	PO Status:  <b>{$r['po_status_name']}</b><br>
	Store Name:  <b>{$r['store_name']}</b><br>
	Vendor:  <b>" . htmlspecialchars($r['vendor_name']) . "</b><br>
	Link:  <b>" . rtrim(getCurrentHost(), '/') . "/po/" . $r['po_code'] . "</b>";
$footer = '';

$send = sendEmail($from_name, $from_email, $to_name, $to_email, $subject, $message, $footer);

$note = 'Notify Andrew: ' . $admin_name . ' sent an email notification to Andrew.';
dbPut('file', array('re_tbl' => 'po', 're_id' => $po_id, 'admin_id' => $admin_id, 'description' => $note, 'is_auto' => 0));

echo json_encode(array('success' => true, 'message' => 'Email sent to Andrew.'));
exit();
