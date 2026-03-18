<?php
include_once('_config.php');

// ─── AJAX / POST handlers ────────────────────────────────────────────────────
$_ajax = getVar('_ajax');

if ($_ajax === 'get_transfers') {
    // Placeholder: will call the Metrc API and return JSON results.
    // $_Session->store_id  → used to scope results to the active store
    // $_Session->api_url   → Metrc API base URL for this store
    // $_Session->auth_code → API auth header value
    echo json_encode(array(
        'success' => true,
        'response' => 'Metrc API integration not yet implemented.',
        'data' => array()
    ));
    exit();
}

if ($_ajax === 'receive_transfer') {
    // Placeholder: will mark a specific transfer as received in the DB.
    echo json_encode(array(
        'success' => false,
        'response' => 'Receive action not yet implemented.'
    ));
    exit();
}

// ─── Page-level data ────────────────────────────────────────────────────────
$store_name = '';
$rs_store = getRs("SELECT store_name FROM store WHERE store_id = ?", array($_Session->store_id));
if ($row_store = getRow($rs_store)) {
    $store_name = $row_store['store_name'];
}

$meta_title  = 'Metrc Receiving';
$page_icon   = '<i class="fa fa-truck"></i>';

include_once('inc/header.php');
?>

<!-- ── Store context badge ─────────────────────────────────────────────── -->
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info alert-bordered d-flex align-items-center py-2 mb-0">
            <i class="fa fa-store mr-2"></i>
            <span>
                Active store:&nbsp;<strong><?php echo htmlspecialchars($store_name); ?></strong>
                &nbsp;<small class="text-muted">(store_id: <?php echo (int)$_Session->store_id; ?>)</small>
            </span>
        </div>
    </div>
</div>

<!-- ── Filters panel ──────────────────────────────────────────────────────── -->
<div class="panel panel-default-1" id="panel-filters">
    <div class="panel-heading">
        <div class="panel-heading-btn">
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning"
               data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        </div>
        <h4 class="panel-title"><i class="fa fa-filter mr-1"></i> Filters</h4>
    </div>
    <div class="panel-body pb-2">
        <div class="row form-input-flat">

            <div class="col-sm-4 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-calendar mr-1"></i> Date From</span>
                    </div>
                    <input type="text" id="filter_date_start" name="date_start"
                           class="form-control datepicker"
                           placeholder="mm/dd/yyyy"
                           value="<?php echo date('m/01/Y'); ?>" />
                </div>
            </div>

            <div class="col-sm-4 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-calendar mr-1"></i> Date To</span>
                    </div>
                    <input type="text" id="filter_date_end" name="date_end"
                           class="form-control datepicker"
                           placeholder="mm/dd/yyyy"
                           value="<?php echo date('m/d/Y'); ?>" />
                </div>
            </div>

            <div class="col-sm-4 mb-2 d-flex align-items-center">
                <button id="btn-fetch-transfers" class="btn btn-primary btn-sm mr-2">
                    <i class="fa fa-sync-alt mr-1"></i> Fetch Transfers
                </button>
                <button id="btn-clear-filters" class="btn btn-default btn-sm">
                    <i class="fa fa-times mr-1"></i> Clear
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ── Status / feedback ──────────────────────────────────────────────────── -->
<div id="metrc-status" class="mb-3" style="display:none;"></div>

<!-- ── Incoming transfers table ──────────────────────────────────────────── -->
<div class="panel panel-inverse">
    <div class="panel-heading">
        <div class="panel-heading-btn">
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success"
               data-click="panel-expand"><i class="fa fa-expand"></i></a>
        </div>
        <h4 class="panel-title"><i class="fa fa-truck mr-1"></i> Incoming Metrc Transfers</h4>
    </div>
    <div class="panel-body">

        <div id="transfers-loading" class="text-center py-5" style="display:none;">
            <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="text-muted mt-2">Loading transfers from Metrc&hellip;</p>
        </div>

        <div id="transfers-empty" class="text-center py-5">
            <i class="fa fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted">Use the filters above and click <strong>Fetch Transfers</strong> to load incoming transfers.</p>
        </div>

        <div id="transfers-table-wrapper" style="display:none;">
            <table id="tbl-metrc-transfers"
                   class="table table-bordered table-hover table-striped w-100">
                <thead class="thead-dark">
                    <tr>
                        <th>Manifest #</th>
                        <th>Shipper</th>
                        <th>Estimated Arrival</th>
                        <th>Packages</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="tbl-metrc-transfers-body">
                    <!-- rows injected by JS -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- ── Page-level JavaScript ──────────────────────────────────────────────── -->
<?php $footer = '
<script>
(function () {

    var storeId = <?php echo (int)$_Session->store_id; ?>;

    function showStatus(msg, type) {
        var cls = { ok: "success", info: "info", warning: "warning", error: "danger" };
        $("#metrc-status")
            .removeClass()
            .addClass("alert alert-" + (cls[type] || "info") + " alert-bordered")
            .html(msg)
            .show();
    }

    function fetchTransfers() {
        var dateStart = $("#filter_date_start").val();
        var dateEnd   = $("#filter_date_end").val();

        if (!dateStart || !dateEnd) {
            showStatus("Please select both a start and end date.", "warning");
            return;
        }

        $("#transfers-empty").hide();
        $("#transfers-table-wrapper").hide();
        $("#transfers-loading").show();
        $("#metrc-status").hide();

        $.ajax({
            url: "/metrc-receiving",
            type: "POST",
            data: {
                _ajax: "get_transfers",
                date_start: dateStart,
                date_end: dateEnd,
                _r: Math.random()
            },
            dataType: "json"
        })
        .done(function (res) {
            $("#transfers-loading").hide();
            if (res && res.success) {
                renderTransfers(res.data || []);
            } else {
                showStatus(res.response || "An error occurred.", "error");
                $("#transfers-empty").show();
            }
        })
        .fail(function (xhr, status, err) {
            $("#transfers-loading").hide();
            showStatus("Request failed: " + (err || status), "error");
            $("#transfers-empty").show();
        });
    }

    function renderTransfers(rows) {
        var $tbody = $("#tbl-metrc-transfers-body").empty();

        if (!rows.length) {
            $("#transfers-empty").show();
            return;
        }

        $.each(rows, function (i, r) {
            var row = "<tr>" +
                "<td>" + (r.manifest_number || "—") + "</td>" +
                "<td>" + (r.shipper_name    || "—") + "</td>" +
                "<td>" + (r.eta             || "—") + "</td>" +
                "<td class=\"text-center\">" + (r.package_count || 0) + "</td>" +
                "<td>" + (r.status          || "—") + "</td>" +
                "<td class=\"text-center\">" +
                    "<button class=\"btn btn-xs btn-success btn-receive-transfer\" " +
                    "data-id=\"" + r.id + "\">Receive</button>" +
                "</td>" +
                "</tr>";
            $tbody.append(row);
        });

        $("#transfers-table-wrapper").show();
    }

    // Fetch button
    $("#btn-fetch-transfers").on("click", function () {
        fetchTransfers();
    });

    // Clear filters
    $("#btn-clear-filters").on("click", function () {
        $("#filter_date_start").val("");
        $("#filter_date_end").val("");
        $("#transfers-empty").show();
        $("#transfers-table-wrapper").hide();
        $("#metrc-status").hide();
    });

    // Receive action (delegated — rows are injected dynamically)
    $(document).on("click", ".btn-receive-transfer", function () {
        var transferId = $(this).data("id");
        if (!confirm("Mark this transfer as received?")) return;

        $.ajax({
            url: "/metrc-receiving",
            type: "POST",
            data: { _ajax: "receive_transfer", transfer_id: transferId, _r: Math.random() },
            dataType: "json"
        })
        .done(function (res) {
            showStatus(res.response || "Done.", res.success ? "ok" : "error");
            if (res.success) fetchTransfers();
        })
        .fail(function () {
            showStatus("Request failed.", "error");
        });
    });

}());
</script>
'; ?>

<?php include_once('inc/footer.php'); ?>
