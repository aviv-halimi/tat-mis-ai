<?php
/**
 * Modal: QBO connection status per store (before opening Map vendors).
 * GET/POST: c = daily_discount_report_brand_id
 * Table is filled via JS from action=connection_status API.
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
if (!$daily_discount_report_brand_id) {
    echo '<div class="alert alert-danger">Missing report brand.</div>';
    exit;
}
?>
<div class="dd-qbo-connection-status" data-daily-discount-report-brand-id="<?php echo (int)$daily_discount_report_brand_id; ?>">
  <p class="text-muted mb-2">QuickBooks Online connection status for each store. Connect any store that is not connected, then continue to map vendors.</p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered">
      <thead>
        <tr>
          <th>Store</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="dd-qbo-connection-status-tbody">
        <tr><td colspan="3" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>
      </tbody>
    </table>
  </div>
  <div class="mt-3 d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="dd-qbo-connection-refresh">Refresh</button>
    <button type="button" class="btn btn-primary" id="dd-qbo-connection-continue-to-mapping">Continue to map vendors</button>
  </div>
</div>
