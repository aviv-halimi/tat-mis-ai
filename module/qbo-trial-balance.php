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
    ? getRs("SELECT store_id, store_name, qbo_realm_id, qbo_tb_start_date FROM store WHERE " . is_enabled() . " ORDER BY store_name")
    : getRs("SELECT store_id, store_name, qbo_realm_id, NULL AS qbo_tb_start_date FROM store WHERE " . is_enabled() . " ORDER BY store_name");
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
                    Downloads one Excel file per store, all packaged in a single ZIP.
                    Each store uses its own configured <strong>TB Start Date</strong> from the table below.
                </p>
                <form id="qbo-tb-form" method="get" action="/" target="_blank">
                    <input type="hidden" name="_module_code" value="qbo-trial-balance-download" />
                    <div class="form-inline" style="align-items:flex-end; gap:12px; display:flex; flex-wrap:wrap;">
                        <div class="form-group">
                            <label class="control-label mr-2" for="end_date"><strong>End Date</strong></label>
                            <input type="date"
                                   name="end_date"
                                   id="end_date"
                                   class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   required
                                   style="width:160px;" />
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fa fa-file-archive-o mr-1"></i> Download All Trial Balances
                            </button>
                        </div>
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
                                <?php if ($qbo_ok && $start_dt !== ''): ?>
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

<?php include_once('inc/footer.php'); ?>
