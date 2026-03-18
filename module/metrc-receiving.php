<?php
include_once('_config.php');
require_once('inc/MetrcHelper.php');

// ─── AJAX handlers ───────────────────────────────────────────────────────────
$_ajax = getVar('_ajax');

// ── Fetch incoming Metrc transfers ──────────────────────────────────────────
if ($_ajax === 'get_transfers') {

    $date_start = getVar('date_start');
    $date_end   = getVar('date_end');

    // Load store Metrc credentials
    $rs_store = getRs(
        "SELECT metrc_api_key, store_name, params FROM store WHERE store_id = ?",
        array($_Session->store_id)
    );
    $store_row = getRow($rs_store);

    if (!$store_row) {
        echo json_encode(array('success' => false, 'response' => 'Store not found.'));
        exit();
    }

    $store_params    = isJson($store_row['params']) ? json_decode($store_row['params'], true) : array();
    $metrc_api_key   = trim((string)$store_row['metrc_api_key']);
    $metrc_license   = isset($store_params['metrc_license_number']) ? trim($store_params['metrc_license_number']) : '';

    if (!strlen($metrc_api_key)) {
        echo json_encode(array('success' => false, 'response' => 'Metrc API key is not configured for this store. Go to Store Settings → Metrc to add it.'));
        exit();
    }
    if (!strlen($metrc_license)) {
        echo json_encode(array('success' => false, 'response' => 'Metrc License Number is not configured for this store. Go to Store Settings → Metrc to add it.'));
        exit();
    }

    // Convert m/d/Y → ISO-8601 with time bounds
    $start_iso = $end_iso = null;
    if ($date_start) {
        $d = DateTime::createFromFormat('m/d/Y', $date_start);
        if ($d) $start_iso = $d->format('Y-m-d') . 'T00:00:00.000Z';
    }
    if ($date_end) {
        $d = DateTime::createFromFormat('m/d/Y', $date_end);
        if ($d) $end_iso = $d->format('Y-m-d') . 'T23:59:59.000Z';
    }

    $metrc  = new MetrcHelper($metrc_api_key, $metrc_license);
    $result = $metrc->getIncomingTransfers($start_iso, $end_iso);

    if (!$result['success']) {
        echo json_encode(array('success' => false, 'response' => $result['error']));
        exit();
    }

    // Normalise fields for the UI
    $transfers = array();
    foreach ((array)$result['data'] as $t) {
        // Parse ETA — prefer estimated delivery, fall back to departure
        $eta_raw = '';
        foreach (array('EstimatedArrivalDateTime','EstimatedDepartureDateTime','ReceivedDateTime','CreatedDateTime') as $f) {
            if (!empty($t[$f])) { $eta_raw = $t[$f]; break; }
        }
        $eta_fmt = '';
        if ($eta_raw) {
            $dt = new DateTime($eta_raw);
            $eta_fmt = $dt->format('m/d/Y g:i A');
        }

        $transfers[] = array(
            'id'              => isset($t['Id'])                         ? (int)$t['Id']                       : null,
            'manifest_number' => isset($t['ManifestNumber'])             ? $t['ManifestNumber']                : '',
            'shipper_name'    => isset($t['ShipperFacilityName'])        ? $t['ShipperFacilityName']           : '—',
            'shipper_license' => isset($t['ShipperFacilityLicenseNumber']) ? $t['ShipperFacilityLicenseNumber'] : '',
            'eta'             => $eta_fmt,
            'package_count'   => isset($t['PackageCount'])               ? (int)$t['PackageCount']            : 0,
            'shipment_type'   => isset($t['ShipmentTypeName'])           ? $t['ShipmentTypeName']              : '—',
            'received'        => !empty($t['ReceivedDateTime']),
            'created_date'    => isset($t['CreatedDateTime'])            ? $t['CreatedDateTime']               : '',
        );
    }

    echo json_encode(array(
        'success'  => true,
        'response' => count($transfers) . ' transfer' . (count($transfers) !== 1 ? 's' : '') . ' found.',
        'data'     => $transfers,
    ));
    exit();
}

// ── Fetch open POs for the active store ─────────────────────────────────────
if ($_ajax === 'get_open_pos') {

    $rs_po = getRs(
        "SELECT p.po_id, p.po_code, p.po_name, p.vendor_name,
                p.date_schedule_delivery, p.r_total, p.num_products,
                s.po_status_name
           FROM po p
           LEFT JOIN po_status s ON s.po_status_id = p.po_status_id
          WHERE " . is_enabled('p') . "
            AND p.store_id = ?
            AND p.po_status_id < 5
            AND p.po_type_id = 1
          ORDER BY p.po_id DESC
          LIMIT 100",
        array($_Session->store_id)
    );

    $pos = array();
    foreach ($rs_po as $r) {
        $pos[] = array(
            'po_id'                  => (int)$r['po_id'],
            'po_code'                => $r['po_code'],
            'po_name'                => $r['po_name'],
            'vendor_name'            => $r['vendor_name'],
            'date_schedule_delivery' => $r['date_schedule_delivery'] ? date('m/d/Y', strtotime($r['date_schedule_delivery'])) : '',
            'po_status_name'         => $r['po_status_name'],
            'r_total'                => $r['r_total'] ? '$' . number_format((float)$r['r_total'], 2) : '—',
            'num_products'           => (int)$r['num_products'],
        );
    }

    echo json_encode(array('success' => true, 'data' => $pos));
    exit();
}

// ─── Page render ─────────────────────────────────────────────────────────────
$store_name = '';
$metrc_configured = false;

$rs_store = getRs("SELECT store_name, metrc_api_key, params FROM store WHERE store_id = ?", array($_Session->store_id));
if ($row_store = getRow($rs_store)) {
    $store_name = $row_store['store_name'];
    $store_params = isJson($row_store['params']) ? json_decode($row_store['params'], true) : array();
    $metrc_configured = strlen(trim((string)$row_store['metrc_api_key'])) > 0
                     && !empty($store_params['metrc_license_number']);
}

$meta_title = 'Metrc Receiving';
$page_icon  = '<i class="fa fa-truck"></i>';
include_once('inc/header.php');
?>

<!-- ── Config warning ────────────────────────────────────────────────────── -->
<?php if (!$metrc_configured): ?>
<div class="alert alert-warning alert-bordered mb-3">
    <i class="fa fa-exclamation-triangle mr-1"></i>
    <strong>Metrc credentials missing.</strong>
    Go to <a href="/settings-store"><strong>Store Settings</strong></a> and enter your
    <em>Metrc API Key</em> and <em>Metrc License Number</em> before fetching transfers.
</div>
<?php endif; ?>

<!-- ── Store context badge ───────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3">
    <span class="badge badge-info px-3 py-2" style="font-size:13px;">
        <i class="fa fa-store mr-1"></i>
        <?php echo htmlspecialchars($store_name); ?>
        &nbsp;<small class="opacity-5">#<?php echo (int)$_Session->store_id; ?></small>
    </span>
</div>

<!-- ── Filters ───────────────────────────────────────────────────────────── -->
<div class="panel panel-default-1 mb-3" id="panel-filters">
    <div class="panel-heading">
        <div class="panel-heading-btn">
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning"
               data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        </div>
        <h4 class="panel-title"><i class="fa fa-filter mr-1"></i> Transfer Filters</h4>
    </div>
    <div class="panel-body pb-2">
        <div class="row form-input-flat align-items-end">
            <div class="col-sm-3 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-calendar mr-1"></i> From</span>
                    </div>
                    <input type="text" id="filter_date_start" class="form-control datepicker"
                           placeholder="mm/dd/yyyy" value="<?php echo date('m/01/Y'); ?>" />
                </div>
            </div>
            <div class="col-sm-3 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-calendar mr-1"></i> To</span>
                    </div>
                    <input type="text" id="filter_date_end" class="form-control datepicker"
                           placeholder="mm/dd/yyyy" value="<?php echo date('m/d/Y'); ?>" />
                </div>
            </div>
            <div class="col-sm-6 mb-2">
                <button id="btn-fetch-transfers" class="btn btn-primary btn-sm mr-2"<?php echo !$metrc_configured ? ' disabled title="Configure Metrc credentials first"' : ''; ?>>
                    <i class="fa fa-sync-alt mr-1"></i> Fetch Transfers
                </button>
                <button id="btn-clear-selection" class="btn btn-default btn-sm">
                    <i class="fa fa-times mr-1"></i> Clear Selection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Status bar ────────────────────────────────────────────────────────── -->
<div id="metrc-status" class="mb-3" style="display:none;"></div>

<!-- ── Two-panel: Transfers + POs ───────────────────────────────────────── -->
<div class="row">

    <!-- Left: Incoming Metrc Transfers -->
    <div class="col-lg-7 mb-3">
        <div class="panel panel-inverse h-100 mb-0">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-truck mr-1"></i> Incoming Metrc Transfers
                    <span id="transfer-count-badge" class="badge badge-secondary ml-2" style="display:none;"></span>
                </h4>
            </div>
            <div class="panel-body p-0">

                <div id="transfers-loading" class="text-center py-5" style="display:none;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2 mb-0">Fetching from Metrc&hellip;</p>
                </div>

                <div id="transfers-empty" class="text-center py-5 text-muted">
                    <i class="fa fa-inbox fa-3x mb-2 d-block"></i>
                    Set a date range and click <strong>Fetch Transfers</strong>.
                </div>

                <div id="transfers-table-wrapper" style="display:none; overflow-x:auto;">
                    <table id="tbl-transfers" class="table table-bordered table-hover table-sm mb-0" style="font-size:13px;">
                        <thead class="thead-dark">
                            <tr>
                                <th style="width:130px;">Manifest #</th>
                                <th>Shipper</th>
                                <th style="width:130px;">ETA</th>
                                <th class="text-center" style="width:70px;">Pkgs</th>
                                <th style="width:90px;">Type</th>
                                <th class="text-center" style="width:60px;">Rcvd</th>
                            </tr>
                        </thead>
                        <tbody id="tbl-transfers-body"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Right: Open Purchase Orders -->
    <div class="col-lg-5 mb-3">
        <div class="panel panel-inverse h-100 mb-0">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-file-invoice mr-1"></i> Open Purchase Orders
                    <span id="po-count-badge" class="badge badge-secondary ml-2" style="display:none;"></span>
                </h4>
            </div>
            <div class="panel-body p-0">

                <div id="pos-loading" class="text-center py-4" style="display:none;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2 mb-0">Loading POs&hellip;</p>
                </div>

                <div id="pos-empty" class="text-center py-5 text-muted" style="display:none;">
                    <i class="fa fa-folder-open fa-3x mb-2 d-block"></i>
                    No open purchase orders found for this store.
                </div>

                <div id="pos-table-wrapper" style="display:none; overflow-x:auto; max-height:480px; overflow-y:auto;">
                    <table id="tbl-pos" class="table table-bordered table-hover table-sm mb-0" style="font-size:13px;">
                        <thead class="thead-dark" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th>PO / Vendor</th>
                                <th style="width:95px;">Sched. Delivery</th>
                                <th style="width:80px;">Status</th>
                                <th class="text-right" style="width:75px;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="tbl-pos-body"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</div><!-- /.row -->

<!-- ── Match & Proceed CTA ───────────────────────────────────────────────── -->
<div id="match-cta" class="panel panel-default-1" style="display:none;">
    <div class="panel-body py-3 d-flex align-items-center flex-wrap" style="gap:12px;">
        <div class="d-flex align-items-center flex-wrap" style="gap:8px; flex:1;">
            <span class="text-muted mr-1">Selected:</span>
            <span id="cta-transfer-badge" class="badge badge-info px-3 py-2" style="font-size:12px;">
                <i class="fa fa-truck mr-1"></i> <span id="cta-transfer-label">—</span>
            </span>
            <i class="fa fa-plus text-muted"></i>
            <span id="cta-po-badge" class="badge badge-success px-3 py-2" style="font-size:12px;">
                <i class="fa fa-file-invoice mr-1"></i> <span id="cta-po-label">—</span>
            </span>
        </div>
        <button id="btn-match-proceed" class="btn btn-lg btn-yellow" disabled>
            <i class="fa fa-link mr-1"></i> Match &amp; Proceed
        </button>
    </div>
</div>

<?php
// ── Footer JS ────────────────────────────────────────────────────────────────
$footer = '<script>
(function () {
    "use strict";

    var selectedTransfer = null; // {id, manifest_number, shipper_name}
    var selectedPO       = null; // {po_id, po_code, po_name, vendor_name}

    // ── Status helper ────────────────────────────────────────────────────────
    function showStatus(msg, type) {
        var map = {ok:"success", info:"info", warning:"warning", error:"danger"};
        $("#metrc-status")
            .removeClass()
            .addClass("alert alert-" + (map[type] || "info") + " alert-bordered")
            .html(msg)
            .show();
    }

    // ── Match CTA ────────────────────────────────────────────────────────────
    function updateCTA() {
        if (!selectedTransfer && !selectedPO) {
            $("#match-cta").hide();
            return;
        }
        $("#match-cta").show();
        if (selectedTransfer) {
            $("#cta-transfer-label").text(selectedTransfer.manifest_number || ("Transfer #" + selectedTransfer.id));
        } else {
            $("#cta-transfer-label").text("(none selected)");
        }
        if (selectedPO) {
            $("#cta-po-label").text(selectedPO.po_name || ("PO #" + selectedPO.po_code));
        } else {
            $("#cta-po-label").text("(none selected)");
        }
        $("#btn-match-proceed").prop("disabled", !(selectedTransfer && selectedPO));
    }

    // ── Fetch transfers ───────────────────────────────────────────────────────
    function fetchTransfers() {
        var ds = $("#filter_date_start").val();
        var de = $("#filter_date_end").val();
        if (!ds || !de) { showStatus("Please enter both a start and end date.", "warning"); return; }

        $("#transfers-empty").hide();
        $("#transfers-table-wrapper").hide();
        $("#transfers-loading").show();
        $("#metrc-status").hide();
        $("#transfer-count-badge").hide();

        $.post("/metrc-receiving", { _ajax: "get_transfers", date_start: ds, date_end: de, _r: Math.random() }, null, "json")
            .done(function (res) {
                $("#transfers-loading").hide();
                if (res && res.success) {
                    showStatus(res.response, "ok");
                    renderTransfers(res.data || []);
                } else {
                    showStatus((res && res.response) || "An error occurred.", "error");
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
        var $tbody = $("#tbl-transfers-body").empty();
        if (!rows.length) { $("#transfers-empty").show(); return; }

        $.each(rows, function (i, r) {
            var receivedIcon = r.received
                ? \'<i class="fa fa-check-circle text-success"></i>\'
                : \'<i class="fa fa-clock text-warning"></i>\';

            var $tr = $("<tr>")
                .addClass("tr-transfer")
                .data("transfer", r)
                .css("cursor", "pointer")
                .append($("<td>").text(r.manifest_number || "—"))
                .append($("<td>").html("<strong>" + escHtml(r.shipper_name) + "</strong>" +
                    (r.shipper_license ? "<br><small class=\'text-muted\'>" + escHtml(r.shipper_license) + "</small>" : "")))
                .append($("<td>").text(r.eta || "—"))
                .append($("<td>").addClass("text-center").text(r.package_count))
                .append($("<td>").text(r.shipment_type || "—"))
                .append($("<td>").addClass("text-center").html(receivedIcon));

            $tbody.append($tr);
        });

        $("#transfer-count-badge").text(rows.length).show();
        $("#transfers-table-wrapper").show();
    }

    // ── Load open POs ────────────────────────────────────────────────────────
    function loadOpenPOs() {
        $("#pos-loading").show();
        $("#pos-table-wrapper").hide();
        $("#pos-empty").hide();

        $.post("/metrc-receiving", { _ajax: "get_open_pos", _r: Math.random() }, null, "json")
            .done(function (res) {
                $("#pos-loading").hide();
                if (res && res.success) {
                    renderPOs(res.data || []);
                } else {
                    $("#pos-empty").show();
                }
            })
            .fail(function () {
                $("#pos-loading").hide();
                $("#pos-empty").show();
            });
    }

    function renderPOs(rows) {
        var $tbody = $("#tbl-pos-body").empty();
        if (!rows.length) { $("#pos-empty").show(); return; }

        $.each(rows, function (i, r) {
            var $tr = $("<tr>")
                .addClass("tr-po")
                .data("po", r)
                .css("cursor", "pointer")
                .append($("<td>").html(
                    "<strong>" + escHtml(r.po_name || r.po_code) + "</strong>" +
                    "<br><small class=\'text-muted\'>" + escHtml(r.vendor_name) + "</small>"
                ))
                .append($("<td>").text(r.date_schedule_delivery || "—"))
                .append($("<td>").html("<span class=\'badge badge-default\'>" + escHtml(r.po_status_name) + "</span>"))
                .append($("<td>").addClass("text-right").text(r.r_total));

            $tbody.append($tr);
        });

        $("#po-count-badge").text(rows.length).show();
        $("#pos-table-wrapper").show();
    }

    // ── Row selection ─────────────────────────────────────────────────────────
    $(document).on("click", ".tr-transfer", function () {
        var r = $(this).data("transfer");
        if (selectedTransfer && selectedTransfer.id === r.id) {
            selectedTransfer = null;
            $(this).removeClass("table-active").css("background","");
        } else {
            $(".tr-transfer").removeClass("table-active").css("background","");
            selectedTransfer = r;
            $(this).addClass("table-active").css("background","#d4edda");
        }
        updateCTA();
    });

    $(document).on("click", ".tr-po", function () {
        var r = $(this).data("po");
        if (selectedPO && selectedPO.po_id === r.po_id) {
            selectedPO = null;
            $(this).removeClass("table-active").css("background","");
        } else {
            $(".tr-po").removeClass("table-active").css("background","");
            selectedPO = r;
            $(this).addClass("table-active").css("background","#cce5ff");
        }
        updateCTA();
    });

    // ── Buttons ───────────────────────────────────────────────────────────────
    $("#btn-fetch-transfers").on("click", fetchTransfers);

    $("#btn-clear-selection").on("click", function () {
        selectedTransfer = selectedPO = null;
        $(".tr-transfer, .tr-po").removeClass("table-active").css("background","");
        updateCTA();
    });

    $("#btn-match-proceed").on("click", function () {
        if (!selectedTransfer || !selectedPO) return;
        // Next prompt will wire this up — pass IDs to the receiving workflow
        alert("Match confirmed!\nTransfer: " + (selectedTransfer.manifest_number || selectedTransfer.id)
            + "\nPO: " + (selectedPO.po_name || selectedPO.po_code)
            + "\n\nNext step: receiving workflow.");
    });

    // ── Utility ───────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str || "")
            .replace(/&/g,"&amp;").replace(/</g,"&lt;")
            .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    loadOpenPOs();

}());
</script>';
?>

<?php include_once('inc/footer.php'); ?>
