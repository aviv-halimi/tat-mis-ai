<?php
include_once('_config.php');

if (!$_Session->HasModulePermission('cron-sales')) exit('Access denied');

include_once('inc/header.php');
?>
<form class="form display-options" action="">
<div class="panel">
    <div class="panel-body">
        <p>Sync daily sales and product inventory levels for all products. <b>Please note this process can take up to 10 mins to complete. You can leave this page after the process starts, but it is recommended that you wait for the completion message.</b></p>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-2">Select Store</div>
            <div class="col-sm-10">
                <select name="store_id" id="store_id" class="form-control select2">
                <option value="">- All -</option>
                <?php
                $rs = getRs("SELECT store_id, store_name FROM store WHERE " . is_enabled() . " ORDER BY sort, store_id");
                foreach($rs as $r) {
                    echo '<option value="' . $r['store_id'] . '"' . iif($r['store_id'] == $_Session->store_id, ' selected') . '>' . $r['store_name'] . '</option>';
                }
                ?>
                </select>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="row">
            <div class="col-sm-6">
                <div class="status" id="status_cron"></div>
            </div>
            <div class="col-sm-6 text-right">
                <button type="button" class="btn btn-primary btn-cron-sales">Sync</button>
            </div>
        </div>
    </div>
</div>
</form>


<?php
require_once('inc/footer.php');
?>