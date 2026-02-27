<?php
/**
 * Modal: Test push to QBO — show data that would be pushed for store_id=1 and allow pushing that one store.
 * GET/POST: c = daily_discount_report_brand_id
 * Content filled via JS from action=preview_push.
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
if (!$daily_discount_report_brand_id) {
    echo '<div class="alert alert-danger">Missing report brand.</div>';
    exit;
}
?>
<div class="dd-qbo-push-test" data-daily-discount-report-brand-id="<?php echo (int)$daily_discount_report_brand_id; ?>">
  <p class="text-muted mb-2">Preview of data that would be pushed to QBO for the <strong>first store</strong> in this report. Push this one store to test, or close to skip.</p>
  <div id="dd-qbo-push-test-preview" class="mb-3">
    <div class="text-center text-muted py-2"><i class="fa fa-spinner fa-spin"></i> Loading preview…</div>
  </div>
  <div class="mt-3 d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-primary" id="dd-qbo-push-test-push-one">Push first store</button>
    <button type="button" class="btn btn-outline-secondary" id="dd-qbo-push-test-close">Close</button>
  </div>
  <div id="dd-qbo-push-test-result" class="mt-3 small" style="display:none;"></div>
</div>
