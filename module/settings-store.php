<?php
require_once ('./_config.php');

// Editable keys only. qbo_realm_id and qbo_refresh_token are set by OAuth (Connect to QuickBooks), not here.
$ks = array('po_email', 'po_scheduling_email', 'max_daily_deliveries', 'target_days_of_inventory', 'daily_sales_lookback_period', 'appointment_duration', 'po_scheduled_email_bcc','boh_email', 'nabis_vendor_id', 'default_markup', 'qbo_account_id_products', 'qbo_account_id_rebates');

if (isset($_POST['TableName'])) {
    $rs = getRs("SELECT params FROM store WHERE store_id = ?", array($_Session->store_id));
    $params = ($rs && ($row = getRow($rs)) && !empty($row['params'])) ? json_decode($row['params'], true) : array();
    if (!is_array($params)) {
        $params = array();
    }
    foreach ($ks as $k) {
        $params[$k] = getVar($k);
    }
    dbUpdate('store', array('params' => json_encode($params)), $_Session->store_id);
	echo json_encode(array('success' => true, 'response' => 'Saved successfully.'));
	exit();
}

include_once ('./inc/header.php');

$rs = getRs("SELECT params FROM store WHERE store_id = ?", array($_Session->store_id));
if ($s = getRow($rs)) {
$_p = json_decode($s['params'], true);


echo '<div class="row">
<div class="col-12">
	<div class="card shadow-base">
		<div class="card-header tx-medium"><b>Edit Settings</b></div>
		<div class="card-body">
			<form method="post" action="" class="form-horizontal f-tbl" role="form" id="f_tbl">
            <input type="hidden" name="TableName" id="TableName" value="' . $_Session->store_id . '" />
            <input type="hidden" name="PageName" id="PageName" value="settings-store" />
				';
foreach($ks as $r) {
	echo '<div class="form-group">
	<label class="col-sm-2 control-label">' . nicefy($r) . ':</label>
	<div class="col-sm-10">';
    
	echo '<input type="text" id="' . $r. '" name="' . $r . '" value="' . (isset($_p[$r])?$_p[$r]:null) . '" class="form-control" />';
	
	echo '</div></div>';
}

echo '<div class="form-group">
			<label class="col-sm-2 control-label"></label>
				<div class="col-sm-10"><div id="tbl_status" class="status mb-1"></div>
					<input type="submit" value="Save Changes" class="btn btn-primary" />
				</div>
			</div>
			</form>
		</div>
	</div>
</div>
</div>';
}

include_once ('./inc/footer.php');

?>