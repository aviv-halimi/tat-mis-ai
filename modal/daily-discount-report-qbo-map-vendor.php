<?php
/**
 * Modal: Map daily discount report brand to QBO vendor per store (for Push to QBO).
 * GET/POST: c = daily_discount_report_brand_id
 * Same config/bootstrap as PO vendor mapping modal (po-qbo-map-vendor.php).
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
if (!$daily_discount_report_brand_id) {
    echo '<div class="alert alert-danger">Missing report brand.</div>';
    exit;
}
$rs = getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id, b.name AS brand_name FROM daily_discount_report_brand rb INNER JOIN blaze1.brand b ON b.brand_id = rb.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb'),
    array($daily_discount_report_brand_id)
);
$report_brand = getRow($rs);
if (!$report_brand) {
    echo '<div class="alert alert-danger">Report brand not found.</div>';
    exit;
}
$brand_id = (int)$report_brand['brand_id'];
$stores_rs = getRs(
    "SELECT s.store_id, s.store_name, s.db AS store_db FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s') . " ORDER BY s.store_name",
    array($daily_discount_report_brand_id)
);
$stores = $stores_rs ?: array();
$store_rows = array();
foreach ($stores as $s) {
    $store_db = isset($s['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s['store_db']) : '';
    $current = '';
    if ($store_db !== '') {
        try {
            $col_check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'qbo_vendor_id'", array($store_db));
            $has_col = $col_check && (int)getRow($col_check)['c'] > 0;
            if ($has_col) {
                $br = getRow(getRs("SELECT qbo_vendor_id FROM `" . str_replace('`', '``', $store_db) . "`.brand WHERE brand_id = ?", array($brand_id)));
                $current = isset($br['qbo_vendor_id']) ? trim((string)$br['qbo_vendor_id']) : '';
            }
        } catch (Exception $e) {
            $current = '';
        }
    }
    $store_rows[] = array(
        'store_id' => (int)$s['store_id'],
        'store_name' => isset($s['store_name']) ? $s['store_name'] : 'Store ' . $s['store_id'],
        'qbo_vendor_id' => $current,
    );
}
?>
<form method="post" id="f_daily-discount-report-qbo-map-vendor" class="ajax-form">
  <input type="hidden" name="action" value="save_mapping" />
  <input type="hidden" name="daily_discount_report_brand_id" value="<?php echo (int)$daily_discount_report_brand_id; ?>" />
  <div class="alert alert-info">Map this brand to a QuickBooks Online vendor for each store. Save to continue, then push to QBO.</div>
  <div class="form-group">
    <label class="col-form-label">Brand</label>
    <div><strong><?php echo htmlspecialchars($report_brand['brand_name']); ?></strong></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Store</th>
          <th>QuickBooks vendor</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($store_rows as $row): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['store_name']); ?></td>
          <td>
            <select name="qbo_vendor_id[<?php echo $row['store_id']; ?>]" class="form-control dd-report-qbo-vendor-select" data-store-id="<?php echo $row['store_id']; ?>" style="min-width:200px;">
              <option value="">— Loading… —</option>
            </select>
            <input type="hidden" class="dd-report-qbo-vendor-current" data-store-id="<?php echo $row['store_id']; ?>" value="<?php echo htmlspecialchars($row['qbo_vendor_id']); ?>" />
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (empty($store_rows)): ?>
  <p class="text-muted">No stores with data for this report brand.</p>
  <?php endif; ?>
  <div class="form-btns mt-2">
    <button type="submit" class="btn btn-primary btn-submit">Save mapping</button>
  </div>
  <div id="status_daily-discount-report-qbo-map-vendor" class="status mt-2"></div>
</form>
