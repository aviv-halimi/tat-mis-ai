<?php
include_once('_config.php');

if (!$_Session->HasModulePermission('cron-vendors')) exit('Access denied');

include_once('inc/header.php');
?>
<form class="form display-options" action="">
<div class="panel">
    <div class="panel-body">
        <p>Sync vendors. <b>Please note this process can take up to 10 mins to complete. You can leave this page after the process starts, but it is recommended that you wait for the completion message.</b></p>
        <div class="row form-input-flat mb-2">
            <div class="col-sm-12">
                <h3>Vendors</h3>
                <?php
                $rs = getRs("SELECT store_id, store_name FROM store WHERE " . is_enabled() . " ORDER BY sort, store_id");
                foreach($rs as $r) {
                    echo '<div class="m-5"><a href="/api/sync.php?_store_id=' . $r['store_id'] . '&_i=2&_start=0" target="_blank" class="btn btn-primary">' . $r['store_name'] . '</a></div>';
                }
                ?>
            </div>
            <div class="col-sm-12">
                <hr />
                <h3>Employees</h3>
                <?php
                $rs = getRs("SELECT store_id, store_name FROM store WHERE " . is_enabled() . " ORDER BY sort, store_id");
                foreach($rs as $r) {
                    echo '<div class="m-5"><a href="/api/sync.php?_store_id=' . $r['store_id'] . '&_i=6" target="_blank" class="btn btn-primary">' . $r['store_name'] . '</a></div>';
                }
                ?>
            </div>
            <div class="col-sm-12">
                <hr />
                <h3>Categories</h3>
                <?php
                $rs = getRs("SELECT store_id, store_name FROM store WHERE " . is_enabled() . " ORDER BY sort, store_id");
                foreach($rs as $r) {
                    echo '<div class="m-5"><a href="/api/sync.php?_store_id=' . $r['store_id'] . '&_i=1" target="_blank" class="btn btn-primary">' . $r['store_name'] . '</a></div>';
                }
                ?>
            </div>
			<div class="col-sm-12">
                <hr />
                <h3>Brands</h3>
                <?php
                $rs = getRs("SELECT store_id, store_name FROM store WHERE " . is_enabled() . " ORDER BY sort, store_id");
                foreach($rs as $r) {
                    echo '<div class="m-5"><a href="/api/brand_sync.php?_store_id=' . $r['store_id'] . '" target="_blank" class="btn btn-primary">' . $r['store_name'] . '</a></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
</form>


<?php
require_once('inc/footer.php');
?>