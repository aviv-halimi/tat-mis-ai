<?php
include_once('_config.php');

if (!$_Session->HasModulePermission('cron-sales')) exit('Access denied');

include_once('inc/header.php');
?>
<form class="form display-options" action="">
<div class="panel">
    <div class="panel-body">
        <p>Re-run script to push daily deals into Blaze. <b>Please note this process can take up to 10 mins to complete. You can leave this page after the process starts, but it is recommended that you wait for the completion message.</b></p>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-2">Select Brand</div>
            <div class="col-sm-10">
                <select name="brand_id" id="brand_id" class="form-control select2">
                <option value="">- SELECT -</option>
                <?php
                $rs = getRs("SELECT brand_id, name FROM blaze1.brand WHERE " . is_enabled() . " ORDER BY name");
                foreach($rs as $r) {
                    echo '<option value="' . $r['brand_id'] . '">' . $r['name'] . '</option>';
                }
                ?>
                </select>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="row">
            <div class="col-sm-6">
                <div class="status" id="status_dd"></div>
            </div>
            <div class="col-sm-6 text-right">
                <button type="button" class="btn btn-primary btn-trigger-dd">Re-run</button>
            </div>
        </div>
    </div>
</div>
</form>


<?php
require_once('inc/footer.php');
?>