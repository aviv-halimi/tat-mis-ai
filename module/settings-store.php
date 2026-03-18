<?php
require_once ('./_config.php');

// Keys saved into store.params JSON.
// qbo_realm_id and qbo_refresh_token are set by OAuth, not here.
$ks = array('po_email', 'po_scheduling_email', 'max_daily_deliveries', 'target_days_of_inventory', 'daily_sales_lookback_period', 'appointment_duration', 'po_scheduled_email_bcc', 'boh_email', 'nabis_vendor_id', 'default_markup', 'qbo_account_id_products', 'qbo_account_id_rebates', 'qbo_account_id_daily_discount', 'metrc_license_number');

if (isset($_POST['TableName'])) {
    $rs = getRs("SELECT params FROM store WHERE store_id = ?", array($_Session->store_id));
    $params = ($rs && ($row = getRow($rs)) && !empty($row['params'])) ? json_decode($row['params'], true) : array();
    if (!is_array($params)) $params = array();

    foreach ($ks as $k) {
        $params[$k] = getVar($k);
    }
    dbUpdate('store', array('params' => json_encode($params)), $_Session->store_id);

    // metrc_api_key lives in its own dedicated column
    $new_api_key = trim(getVar('metrc_api_key'));
    if (strlen($new_api_key)) {
        dbUpdate('store', array('metrc_api_key' => $new_api_key), $_Session->store_id);
    }

    echo json_encode(array('success' => true, 'response' => 'Saved successfully.'));
    exit();
}

include_once ('./inc/header.php');

$rs = getRs("SELECT params, metrc_api_key FROM store WHERE store_id = ?", array($_Session->store_id));
if ($s = getRow($rs)) {
    $_p          = isJson($s['params']) ? json_decode($s['params'], true) : array();
    $_metrc_key  = $s['metrc_api_key'];

echo '<div class="row">
<div class="col-12">
    <div class="card shadow-base">
        <div class="card-header tx-medium"><b>Edit Settings</b></div>
        <div class="card-body">
            <form method="post" action="" class="form-horizontal f-tbl" role="form" id="f_tbl">
            <input type="hidden" name="TableName" id="TableName" value="' . $_Session->store_id . '" />
            <input type="hidden" name="PageName"  id="PageName"  value="settings-store" />';

// ── Metrc section ────────────────────────────────────────────────────────────
echo '<div class="form-group">
    <label class="col-sm-12"><hr /><h6 class="text-primary"><i class="fa fa-leaf mr-1"></i> Metrc Integration</h6></label>
</div>';

// metrc_api_key — dedicated column, rendered as password field with toggle
echo '<div class="form-group">
    <label class="col-sm-2 control-label">Metrc API Key:</label>
    <div class="col-sm-10">
        <div class="input-group">
            <input type="password" id="metrc_api_key" name="metrc_api_key"
                   value="' . htmlspecialchars((string)$_metrc_key) . '"
                   class="form-control" autocomplete="new-password"
                   placeholder="Paste your store Metrc API key here" />
            <div class="input-group-append">
                <button type="button" class="btn btn-default btn-sm" id="btn-toggle-metrc-key"
                        title="Show / hide key">
                    <i class="fa fa-eye" id="icon-toggle-metrc-key"></i>
                </button>
            </div>
        </div>
        <small class="text-muted">
            The user/business API key issued by Metrc for this license.
            Stored encrypted in the database.
        </small>
    </div>
</div>';

// metrc_license_number — stored in params JSON
echo '<div class="form-group">
    <label class="col-sm-2 control-label">Metrc License #:</label>
    <div class="col-sm-10">
        <input type="text" id="metrc_license_number" name="metrc_license_number"
               value="' . htmlspecialchars((string)(isset($_p['metrc_license_number']) ? $_p['metrc_license_number'] : '')) . '"
               class="form-control" placeholder="e.g. C10-0000123-LIC" />
        <small class="text-muted">The facility license number used in Metrc API calls.</small>
    </div>
</div>';

echo '<div class="form-group">
    <label class="col-sm-12"><hr /><h6 class="text-muted"><i class="fa fa-cog mr-1"></i> General Settings</h6></label>
</div>';

// ── All other params keys ─────────────────────────────────────────────────────
foreach ($ks as $r) {
    if ($r === 'metrc_license_number') continue; // already rendered above
    echo '<div class="form-group">
    <label class="col-sm-2 control-label">' . nicefy($r) . ':</label>
    <div class="col-sm-10">
        <input type="text" id="' . $r . '" name="' . $r . '"
               value="' . htmlspecialchars((string)(isset($_p[$r]) ? $_p[$r] : '')) . '"
               class="form-control" />
    </div>
</div>';
}

echo '<div class="form-group">
    <label class="col-sm-2 control-label"></label>
    <div class="col-sm-10">
        <div id="tbl_status" class="status mb-1"></div>
        <input type="submit" value="Save Changes" class="btn btn-primary" />
    </div>
</div>
</form>
        </div>
    </div>
</div>
</div>';

// Show / hide API key toggle
$footer = '<script>
$("#btn-toggle-metrc-key").on("click", function () {
    var $inp  = $("#metrc_api_key");
    var $icon = $("#icon-toggle-metrc-key");
    if ($inp.attr("type") === "password") {
        $inp.attr("type", "text");
        $icon.removeClass("fa-eye").addClass("fa-eye-slash");
    } else {
        $inp.attr("type", "password");
        $icon.removeClass("fa-eye-slash").addClass("fa-eye");
    }
});
</script>';

}

include_once ('./inc/footer.php');

?>