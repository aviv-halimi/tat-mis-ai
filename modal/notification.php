<?php
require_once('../_config.php');
$po_code = getVar('c');
$notification_type_id = getVarNum('a');

$email = '';
$subject = '';
$message = '';
$rs = getRs("SELECT * FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id));
if ($r = getRow($rs)) {
$rv = getRs("SELECT v.id AS vendor_code, v.name AS vendor_name, po.email, CONCAT(v.firstName, ' ', v.lastName) AS contact_name, CONCAT('<b>', s.description, '</b><br />', s.address, '<br />', s.city, ', ', s.state, ' ', s.zip, '<br />Ph: ', s.phone) AS store_address, po.po_code, po.po_id, po.po_number, po.po_event_status_id, DATE_FORMAT(po.date_scheduled, '%b %D, %Y %l:%i %p') AS date_scheduled, DATE_FORMAT(po.date_created, '%b %D, %Y %l:%i %p') AS date_created, CONCAT('https://scheduling.theartisttree.com/po/', v.id, '/', po.po_code) AS link FROM store s INNER JOIN ({$_Session->db}.vendor v INNER JOIN po ON po.vendor_id = v.vendor_id) ON s.store_id = po.store_id WHERE po.po_code = ? AND " . is_enabled('v,po'), array($po_code));
if ($v = getRow($rv)) {
$notification_file = getUniqueCode();
$po_id = $v['po_id'];
$po_event_status_id = $v['po_event_status_id'];
$_po_event_status_id = null;

$rn = getRs("SELECT s.po_event_status_id, s.po_event_status_name, p.date_start FROM po_event p INNER JOIN po_event_status s ON s.po_event_status_id = p.po_event_status_id WHERE " . is_enabled('p') . " AND p.po_id = ? ORDER BY p.po_event_id DESC", array($po_id));
if ($n = getRow($rn)) {
  $_po_event_status_id = $n['po_event_status_id'];
}

if ($_po_event_status_id == 2 and $notification_type_id == 3) {
  $_po_event_status_id = 1;
}
if ($_po_event_status_id == 2 and $notification_type_id == 4) {
  $_po_event_status_id = 5;
}
echo '
<input type="hidden" id="notification_file" name="notification_file" value="' . $notification_file . '" />
<input type="hidden" name="notification_type_id" value="' . $notification_type_id . '" />
<input type="hidden" name="po_code" value="' . $v['po_code'] . '" />
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Notification Type:</div>
  <div class="col-sm-10 col-form-label"><b>' . $r['notification_type_name'] . '</b></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Vendor:</div>
  <div class="col-sm-10 col-form-label"><b>' . $v['vendor_name'] . '</b></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">PO:</div>
  <div class="col-sm-10 col-form-label"><b>' . $v['po_number'] . '</b></div>
</div>';
if ($_po_event_status_id) {
echo '
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Change Status:</div>
  <div class="col-sm-10 col-form-label">' . displayKey('po_event_status_id', $_po_event_status_id) . '</div>
</div>';
}
echo '
<div class="email">
  <div class="row m-b-10">
    <div class="col-sm-2 col-form-label">Recipient E-mail:</div>
    <div class="col-sm-10"><input type="text" name="email" class="form-control" value="' . $v['email'] . '" placeholder="Recipient E-mail Address ..." /><small>If you modify this e-mail address, it will be updated in the po</small></div>
  </div>
</div>

<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Subject:</div>
  <div class="col-sm-10"><input type="text" name="subject" class="form-control" value="' . insertPlaceholders($r['subject'], $v) . '" placeholder="Subject ..." /></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Message:</div>
  <div class="col-sm-10"><textarea name="message" rows="10" id="message" class="ckeditor form-control" placeholder="Your message here ...">' . insertPlaceholders($r['message'], $v) . '</textarea></div>
</div>' . iif($r['attachment'], '
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Attachment:</div>
  <div class="col-sm-10 col-form-label"><a href="/po-download/' . $v['po_code'] . '" target="_blank"><i class="fa fa-paperclip"></i> ' . $v['po_number'] . '.pdf</a></div>
</div>') . '

<div class="row m-b-10">
  <div class="col-sm-2 col-form-label"></div>
  <div class="col-sm-10">
  <div class="form-status" id="status_notification"></div>
  <div class="form-btns">
    <button type="submit" class="btn btn-primary">Submit</button>
  </div>
  </div>
</div>';
}
}
?>