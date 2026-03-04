<?php
/**
 * Modal: Verify rebate totals (current + previous 3) then Push to QBO for all stores.
 * GET/POST: c = daily_discount_report_brand_id, format = pdf|xlsx
 * Table and "Push to QBO" button are filled by JS from verification_data.
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
$report_format = (isset($_REQUEST['format']) && strtolower(trim($_REQUEST['format'])) === 'xlsx') ? 'xlsx' : 'pdf';
if (!$daily_discount_report_brand_id) {
    echo '<div class="alert alert-danger">Missing report brand.</div>';
    exit;
}
$rb = getRow(getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id, b.name AS brand_name, b.QBO_Brand_Name FROM daily_discount_report_brand rb INNER JOIN blaze1.brand b ON b.brand_id = rb.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb'),
    array($daily_discount_report_brand_id)
));
if (!$rb) {
    echo '<div class="alert alert-danger">Report brand not found.</div>';
    exit;
}
$qbo_brand_name = isset($rb['QBO_Brand_Name']) ? trim((string)$rb['QBO_Brand_Name']) : '';
?>
<div class="dd-qbo-push-verify" data-daily-discount-report-brand-id="<?php echo (int)$daily_discount_report_brand_id; ?>" data-format="<?php echo htmlspecialchars($report_format); ?>">
  <div class="row m-b-10">
    <div class="col-sm-4 col-form-label">QBO Brand Name:</div>
    <div class="col-sm-8"><input type="text" name="qbo_brand_name" id="dd-qbo-brand-name" class="form-control" value="<?php echo htmlspecialchars($qbo_brand_name); ?>" placeholder="Override brand name for QBO doc number (saved per brand)" /></div>
  </div>
  <p class="text-muted">Verify rebate totals below, then push to QuickBooks for all stores.</p>
  <div id="dd-qbo-verify-table-wrap" class="table-responsive mb-3">
    <div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
  </div>
  <div id="dd-qbo-push-verify-result" class="mb-2" style="display:none;">
    <div id="dd-qbo-push-verify-log" class="mb-2" style="display:none;"></div>
    <div id="dd-qbo-push-verify-msg"></div>
  </div>
  <div class="form-btns">
    <button type="button" class="btn btn-primary" id="dd-qbo-push-verify-btn">Push to QBO</button>
    <button type="button" class="btn btn-secondary" id="dd-qbo-push-verify-close">Close</button>
  </div>
</div>
