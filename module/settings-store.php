<?php
require_once ('./_config.php');

// Keys saved into store.params JSON only. 
// qbo_realm_id and qbo_refresh_token are set by OAuth, not here.
// metrc_api_key and metrc_license are saved to their own dedicated store columns, not params.
$ks = array('po_email', 'po_scheduling_email', 'max_daily_deliveries', 'target_days_of_inventory', 'daily_sales_lookback_period', 'appointment_duration', 'po_scheduled_email_bcc', 'boh_email', 'nabis_vendor_id', 'default_markup', 'qbo_account_id_products', 'qbo_account_id_rebates', 'qbo_account_id_daily_discount');

if (isset($_POST['TableName'])) {
    $rs = getRs("SELECT params FROM store WHERE store_id = ?", array($_Session->store_id));
    $params = ($rs && ($row = getRow($rs)) && !empty($row['params'])) ? json_decode($row['params'], true) : array();
    if (!is_array($params)) $params = array();

    foreach ($ks as $k) {
        $params[$k] = getVar($k);
    }
    dbUpdate('store', array('params' => json_encode($params)), $_Session->store_id);

    // Metrc credentials — dedicated columns, guarded in case they haven't been created yet
    try {
        $metrc_update = array();
        $new_api_key  = trim(getVar('metrc_api_key'));
        $new_license  = trim(getVar('metrc_license'));
        if (strlen($new_api_key)) $metrc_update['metrc_api_key'] = $new_api_key;
        if (strlen($new_license))  $metrc_update['metrc_license'] = $new_license;
        if (!empty($metrc_update)) dbUpdate('store', $metrc_update, $_Session->store_id);
    } catch (Exception $e) {
        // Column(s) not yet created — silently skip
    }

    echo json_encode(array('success' => true, 'response' => 'Saved successfully.'));
    exit();
}

include_once ('./inc/header.php');

$rs = getRs("SELECT params FROM store WHERE store_id = ?", array($_Session->store_id));
if ($s = getRow($rs)) {
    $_p           = isJson($s['params']) ? json_decode($s['params'], true) : array();
    $_metrc_key   = '';
    $_metrc_lic   = '';
    try {
        $_rs_mk = getRs("SELECT metrc_api_key, metrc_license FROM store WHERE store_id = ?", array($_Session->store_id));
        if ($_mk = getRow($_rs_mk)) {
            $_metrc_key = (string)$_mk['metrc_api_key'];
            $_metrc_lic = (string)$_mk['metrc_license'];
        }
    } catch (Exception $e) {
        // Columns may not exist yet — fields will render empty
    }

echo '<div class="row">
<div class="col-12">
    <div class="card shadow-base">
        <div class="card-header tx-medium"><b>Edit Settings</b></div>
        <div class="card-body">
            <form method="post" action="" class="form-horizontal f-tbl" role="form" id="f_tbl">
            <input type="hidden" name="TableName" id="TableName" value="' . $_Session->store_id . '" />
            <input type="hidden" name="PageName"  id="PageName"  value="settings-store" />';

// ── General settings ──────────────────────────────────────────────────────────
foreach ($ks as $r) {
    echo '<div class="form-group">
    <label class="col-sm-2 control-label">' . nicefy($r) . ':</label>
    <div class="col-sm-10">
        <input type="text" id="' . $r . '" name="' . $r . '"
               value="' . htmlspecialchars((string)(isset($_p[$r]) ? $_p[$r] : '')) . '"
               class="form-control" />
    </div>
</div>';
}

// ── Metrc Integration (bottom) ────────────────────────────────────────────────
echo '<div class="form-group mt-3">
    <label class="col-sm-12"><hr /><h6 class="text-primary"><i class="fa fa-leaf mr-1"></i> Metrc Integration</h6></label>
</div>';

echo '<div class="form-group">
    <label class="col-sm-2 control-label">Metrc API Key:</label>
    <div class="col-sm-10">
        <div class="input-group">
            <input type="password" id="metrc_api_key" name="metrc_api_key"
                   value="' . htmlspecialchars($_metrc_key) . '"
                   class="form-control" autocomplete="new-password"
                   placeholder="Paste your store Metrc API key here" />
            <div class="input-group-append">
                <button type="button" class="btn btn-default btn-sm" id="btn-toggle-metrc-key"
                        title="Show / hide key">
                    <i class="fa fa-eye" id="icon-toggle-metrc-key"></i>
                </button>
            </div>
        </div>
    </div>
</div>';

echo '<div class="form-group">
    <label class="col-sm-2 control-label">Metrc License #:</label>
    <div class="col-sm-10">
        <input type="text" id="metrc_license" name="metrc_license"
               value="' . htmlspecialchars($_metrc_lic) . '"
               class="form-control" placeholder="e.g. C10-0000123-LIC" />
    </div>
</div>';

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
