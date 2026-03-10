<?php
if (!isset($page_title)) $page_title = 'QBO Trial Balance';
if (!isset($page_icon))  $page_icon  = '<i class="fa fa-balance-scale"></i>';

$footer = '
<script>
$(document).ready(function () {
    $(".btn-save-start-date").on("click", function () {
        var $row = $(this).closest("tr");
        var store_id = $row.data("store-id");
        var start_date = $row.find(".start-date-input").val();
        var $btn = $(this);
        $btn.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin\"></i>");
        $.post("/ajax/qbo-trial-balance-save-start-date.php", { store_id: store_id, start_date: start_date }, function (data) {
            if (data.success) {
                $btn.removeClass("btn-primary").addClass("btn-success").html("<i class=\"fa fa-check\"></i> Saved");
                setTimeout(function () { $btn.removeClass("btn-success").addClass("btn-primary").html("<i class=\"fa fa-save\"></i> Save").prop("disabled", false); }, 2500);
            } else {
                alert("Error saving start date:\\n" + (data.error || "Unknown error"));
                $btn.prop("disabled", false).html("<i class=\"fa fa-save\"></i> Save");
            }
        }, "json").fail(function () {
            alert("Request failed. Please try again.");
            $btn.prop("disabled", false).html("<i class=\"fa fa-save\"></i> Save");
        });
    });

    $(".btn-save-extra-start-date").on("click", function () {
        var $row = $(this).closest("tr");
        var extra_entity_id = $row.data("extra-entity-id");
        var start_date = $row.find(".extra-start-date-input").val();
        var $btn = $(this);
        $btn.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin\"></i>");
        $.post("/ajax/qbo-trial-balance-save-extra-start-date.php", { extra_entity_id: extra_entity_id, start_date: start_date }, function (data) {
            if (data.success) {
                $btn.removeClass("btn-primary").addClass("btn-success").html("<i class=\"fa fa-check\"></i> Saved");
                setTimeout(function () { $btn.removeClass("btn-success").addClass("btn-primary").html("<i class=\"fa fa-save\"></i> Save").prop("disabled", false); }, 2500);
            } else {
                alert("Error saving start date:\\n" + (data.error || "Unknown error"));
                $btn.prop("disabled", false).html("<i class=\"fa fa-save\"></i> Save");
            }
        }, "json").fail(function () {
            alert("Request failed. Please try again.");
            $btn.prop("disabled", false).html("<i class=\"fa fa-save\"></i> Save");
        });
    });

    $(".btn-download-tb").on("click", function (e) {
        e.preventDefault();
        var $row = $(this).closest("tr");
        var store_id = $row.data("store-id");
        var endDate = $("#end_date").val();
        if (!endDate || !/^\\d{4}-\\d{2}-\\d{2}$/.test(endDate)) {
            alert("Please set a valid End Date above (YYYY-MM-DD) before downloading.");
            return;
        }
        var url = "/ajax/qbo-trial-balance-download-one.php?store_id=" + store_id + "&end_date=" + encodeURIComponent(endDate);
        window.open(url, "_blank");
    });

    $("#qbo-tb-form").on("submit", function (e) {
        e.preventDefault();
        var endDate = $("#end_date").val();
        if (!endDate || !/^\\d{4}-\\d{2}-\\d{2}$/.test(endDate)) {
            alert("Please set a valid End Date (YYYY-MM-DD).");
            return;
        }
        var $btn = $(this).find("button[type=submit]");
        var $msg = $("#qbo-tb-download-all-msg");
        var $log = $("#qbo-tb-download-all-log");
        $btn.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin mr-1\"></i> Starting...");
        $msg.removeClass("alert-danger alert-success").addClass("alert-info").show();
        $msg.find(".qbo-tb-status").html("Starting generation...");
        $log.text("");
        $.post("/ajax/qbo-trial-balance-download-all-start.php", { end_date: endDate }, function (data) {
            if (!data.started) {
                $msg.removeClass("alert-info").addClass("alert-danger").find(".qbo-tb-status").html(data.error || "Failed to start.");
                $log.text("");
                $btn.prop("disabled", false).html("<i class=\"fa fa-file-excel-o mr-1\"></i> Download All Trial Balances");
                return;
            }
            var jobId = data.job_id;
            $msg.find(".qbo-tb-status").html("<i class=\"fa fa-spinner fa-spin mr-1\"></i> Generating your file... (see log below)");
            function poll() {
                $.getJSON("/ajax/qbo-trial-balance-download-all-status.php?job_id=" + jobId, function (st) {
                    if (st.progress) {
                        $log.text(st.progress);
                        $log.scrollTop($log[0].scrollHeight);
                    }
                    if (st.ready && st.download_url) {
                        $msg.removeClass("alert-info").addClass("alert-success").find(".qbo-tb-status").html("<i class=\"fa fa-check mr-1\"></i> Ready! Downloading...");
                        window.location.href = st.download_url;
                        $btn.prop("disabled", false).html("<i class=\"fa fa-file-excel-o mr-1\"></i> Download All Trial Balances");
                        return;
                    }
                    if (st.error) {
                        $msg.removeClass("alert-info").addClass("alert-danger").find(".qbo-tb-status").html(st.error);
                        $btn.prop("disabled", false).html("<i class=\"fa fa-file-excel-o mr-1\"></i> Download All Trial Balances");
                        return;
                    }
                    setTimeout(poll, 2000);
                }).fail(function () {
                    $msg.removeClass("alert-info").addClass("alert-danger").find(".qbo-tb-status").html("Connection error. Please try again.");
                    $btn.prop("disabled", false).html("<i class=\"fa fa-file-excel-o mr-1\"></i> Download All Trial Balances");
                });
            }
            setTimeout(poll, 1500);
        }, "json").fail(function () {
            $msg.removeClass("alert-info").addClass("alert-danger").find(".qbo-tb-status").html("Failed to start. Please try again.");
            $log.text("");
            $btn.prop("disabled", false).html("<i class=\"fa fa-file-excel-o mr-1\"></i> Download All Trial Balances");
        });
    });
});
</script>';

require_once(BASE_PATH . 'inc/qbo.php');
include_once('inc/header.php');

// Check whether the qbo_tb_start_date column exists yet.
$has_start_date_col = false;
try {
    getRs("SELECT qbo_tb_start_date FROM store LIMIT 1");
    $has_start_date_col = true;
} catch (Exception $e) {
    // Column not added yet — show setup notice below.
}

$stores = $has_start_date_col
    ? getRs("SELECT store_id, store_name, qbo_realm_id, qbo_tb_start_date FROM store WHERE " . is_enabled() . " ORDER BY store_id")
    : getRs("SELECT store_id, store_name, qbo_realm_id, NULL AS qbo_tb_start_date FROM store WHERE " . is_enabled() . " ORDER BY store_id");

$extra_entities = array();
try {
    $extra_entities = getRs("SELECT id, entity_name, qbo_realm_id, qbo_refresh_token, qbo_tb_start_date FROM qbo_tb_extra_entity WHERE is_enabled = 1 ORDER BY sort_order, id");
    if (!is_array($extra_entities)) {
        $extra_entities = array();
    }
} catch (Exception $e) {
    // Table may not exist yet.
}
?>

<style>
.tb-status-badge { font-size: 11px; }
.tb-date-input   { max-width: 160px; display: inline-block; }
.tb-action-col   { width: 140px; white-space: nowrap; }
.panel-generate  { border-top: 3px solid #116066; }
.panel-generate .panel-heading { background: #116066; color: #fff; }
.panel-generate .panel-heading h4 { color: #fff; margin: 0; }
</style>

<?php if (!$has_start_date_col): ?>
<div class="alert alert-warning alert-bordered">
    <strong><i class="fa fa-exclamation-triangle mr-1"></i> Setup Required:</strong>
    The <code>qbo_tb_start_date</code> column has not been added to the <code>store</code> table yet.
    Run this SQL then refresh the page:
    <pre class="m-t-10 m-b-0" style="background:#fff;padding:8px;border-radius:4px;">ALTER TABLE theartisttree.store
  ADD COLUMN qbo_tb_start_date DATE NULL DEFAULT NULL;</pre>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 col-lg-6">
        <div class="panel panel-generate">
            <div class="panel-heading">
                <h4><i class="fa fa-download mr-1"></i> Generate Trial Balances</h4>
            </div>
            <div class="panel-body">
                <p class="text-muted" style="margin-bottom:16px;">
                    Downloads one Excel file with a tab for each store (tab name = store name).
                    Each store uses its own configured <strong>TB Start Date</strong> from the table below.
                </p>
                <form id="qbo-tb-form" method="get" action="/">
                    <div class="form-inline" style="align-items:flex-end; gap:12px; display:flex; flex-wrap:wrap;">
                        <div class="form-group">
                            <label class="control-label mr-2" for="end_date"><strong>End Date</strong></label>
                            <input type="date"
                                   name="end_date"
                                   id="end_date"
                                   class="form-control"
                                   value="<?php echo date('Y-m-d', strtotime('last day of last month')); ?>"
                                   required
                                   style="width:160px;" />
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fa fa-file-excel-o mr-1"></i> Download All Trial Balances
                            </button>
                        </div>
                    </div>
                    <div id="qbo-tb-download-all-msg" class="alert alert-info m-t-10" style="display:none;">
                        <div class="qbo-tb-status font-bold m-b-5"></div>
                        <pre id="qbo-tb-download-all-log" class="m-b-0 p-10 bg-white border rounded" style="max-height:220px; overflow:auto; font-size:12px; white-space:pre-wrap; word-break:break-all;"></pre>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-cog mr-1"></i> Store TB Start Dates
                </h4>
            </div>
            <div class="panel-body p-0">
                <table class="table table-bordered table-hover m-b-0">
                    <thead>
                        <tr>
                            <th>Store</th>
                            <th>QBO</th>
                            <th>TB Start Date</th>
                            <th class="tb-action-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stores)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No stores found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($stores as $r):
                            $qbo_ok   = !empty($r['qbo_realm_id']);
                            $start_dt = isset($r['qbo_tb_start_date']) ? $r['qbo_tb_start_date'] : '';
                        ?>
                        <tr data-store-id="<?php echo (int)$r['store_id']; ?>">
                            <td><?php echo htmlspecialchars($r['store_name']); ?></td>
                            <td>
                                <?php if ($qbo_ok): ?>
                                    <span class="label label-success tb-status-badge">Connected</span>
                                <?php else: ?>
                                    <span class="label label-danger tb-status-badge">Not connected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="date"
                                       class="form-control input-sm tb-date-input start-date-input"
                                       value="<?php echo htmlspecialchars($start_dt); ?>" />
                            </td>
                            <td class="tb-action-col">
                                <?php if ($qbo_ok): ?>
                                <a href="#" class="btn btn-xs btn-success btn-download-tb mr-1" title="Download this store&apos;s Trial Balance (uses End Date above)">
                                    <i class="fa fa-download"></i> Download
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-xs btn-primary btn-save-start-date">
                                    <i class="fa fa-save"></i> Save
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($extra_entities)): ?>
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-building-o mr-1"></i> Extra Entities (included in Download All)
                </h4>
            </div>
            <div class="panel-body p-0">
                <table class="table table-bordered table-hover m-b-0">
                    <thead>
                        <tr>
                            <th>Entity</th>
                            <th>QBO</th>
                            <th>TB Start Date</th>
                            <th class="tb-action-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extra_entities as $r):
                            $qbo_ok = !empty(trim((string)$r['qbo_realm_id'])) && !empty(trim((string)$r['qbo_refresh_token']));
                            $start_dt = isset($r['qbo_tb_start_date']) ? $r['qbo_tb_start_date'] : '';
                        ?>
                        <tr data-extra-entity-id="<?php echo (int)$r['id']; ?>">
                            <td><?php echo htmlspecialchars($r['entity_name']); ?></td>
                            <td>
                                <?php if ($qbo_ok): ?>
                                    <span class="label label-success tb-status-badge">Connected</span>
                                <?php else: ?>
                                    <span class="label label-warning tb-status-badge">Not connected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="date"
                                       class="form-control input-sm tb-date-input extra-start-date-input"
                                       value="<?php echo htmlspecialchars($start_dt); ?>" />
                            </td>
                            <td class="tb-action-col">
                                <button class="btn btn-xs btn-primary btn-save-extra-start-date">
                                    <i class="fa fa-save"></i> Save
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-muted p-10 m-b-0" style="font-size:12px;">These entities are included when you click <strong>Download All Trial Balances</strong>. Set QBO credentials and TB Start Date in the database (<code>qbo_tb_extra_entity</code>).</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once('inc/footer.php'); ?>
