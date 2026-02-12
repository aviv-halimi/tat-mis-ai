<?php
require_once('../_config.php');
$po_code = getVar('c');
$back = getVarInt('d');
$is_non_conforming = false;
$_po_type_id = $_po_type_name = null;
$rs = getRs("SELECT p.po_id, p.po_type_id, p.po_status_id, COALESCE(p.discount,0) AS discount, COALESCE(p.r_discount,0) AS r_discount, COALESCE(p.at_discount,0) AS at_discount, COALESCE(p.r_at_discount,0) AS r_at_discount, COALESCE(t.po_type_name, 'Purchase Order') AS po_type_name FROM po_type t RIGHT JOIN po p ON p.po_type_id = t.po_type_id WHERE p.po_code = ?", array($po_code));

if ($r = getRow($rs)) {
$po_id = $r['po_id'];
$_po_status_id = $r['po_status_id'];
$_po_type_id = $r['po_type_id'];
$_po_type_name = $r['po_type_name'];
if ($back) $po_status_id = $r['po_status_id'] - 1;
else {
    $po_status_id = $r['po_status_id'] + 1;
    $_po_status_id = $po_status_id;
}
$rp = getRs("SELECT po_status_id, module_code, po_status_name, admin_field FROM po_status WHERE po_status_id = ?", array($_po_status_id));
if ($p = getRow($rp)) {
$permission_module_code = $p['module_code'];
// error checking
if ($_po_status_id == 4) {
    if ($r['r_discount'] < $r['discount']) $is_non_conforming = true;
    if ($r['r_at_discount'] < $r['at_discount']) $is_non_conforming = true;
    $rt = $_PO->GetSavedPOProducts($po_id);
    foreach($rt as $t) {
        if ($t['received_qty'] > $t['order_qty']) $is_non_conforming = true;
        if ($t['paid'] > ($t['price'] ?: $t['cost'])) $is_non_conforming = true;
    }
    if ($is_non_conforming) $permission_module_code = 'po-receive-non-conforming';
}

if ($_po_type_id == 2) {
    $permission_module_code = str_replace('po', 'cr', $permission_module_code);
}

if (!$_Session->HasModulePermission($permission_module_code)) {
    echo '
    <input type="hidden" name="po_code" value="' . $po_code . '" />
    <input type="hidden" name="_permission" value="1" />
    <input type="hidden" name="back" value="' . $back . '" />
    <div class="alert alert-info">Hi ' . $_Session->first_name . ', </p><p>Unfortunately, you <b>do not have appropriate access rights</b> to change the status of this ' . $_po_type_name . ' to <b>' . getDisplayName('po_status', $po_status_id) . '</b>.</p><p>You can send the link to this ' . $_po_type_name . ' to an admin for approval:
    <code>' . getCurrentHost() . '/po/' . $po_code . '</code><p>Or you can click the link below to automatically send an authorization request to the purchasing team...</div>

	<div class="col-lg-12 col-form-label" style="font-size:30px;text-align: center; border: 2px solid black; background:black; color:white;"  ><a href="' . getCurrentHost() . 'module/send-approval.php?poid=' . $po_id . '&back=' . $back . '&nc=' . $is_non_conforming . '" style="color:white;">REQUEST APPROVAL</a></div>
	<br>
	<div class="alert alert-info">Alternatively, an admin with appropriate accesss can authorize the approval by providing their credentials below.</div>
	<div class="row form-input-flat mb-2">
    <div class="col-sm-2 col-form-label">E-mail address:</div>
    <div class="col-sm-10"><input type="text" name="_email" value="" class="form-control" placeholder="" /></div>
    </div>
    <div class="row form-input-flat mb-2">
    <div class="col-sm-2 col-form-label">Password:</div>
    <div class="col-sm-10"><input type="password" name="_password" value="" class="form-control" placeholder="" /></div>
	
    </div>
    ';
}
else {
    echo '<div class="alert alert-success">You have authorization to perform this update. Please consult administrator if you arrived on this page by clicking a button in the ' . $_po_type_name . ' module. (' . $permission_module_code . ')</div>';   
}
}
else {
    echo '<div class="alert alert-danger">Status cannot be updated</div>';
}
}
else {
    echo '<div class="alert alert-danger">' . $_po_type_name . ' not found</div>';
}
?>