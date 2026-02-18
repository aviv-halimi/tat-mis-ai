<?php
/**
 * Modal: Map PO vendor to QuickBooks Online vendor (when pushing a bill and vendor has no QBO_ID).
 * GET/POST: c = po_code
 */
require_once('../_config.php');
$po_code = getVar('c');
if (!$po_code) {
    echo '<div class="alert alert-danger">Missing PO code.</div>';
    exit;
}
$rs = getRs(
    "SELECT p.po_id, p.po_code, p.store_id, p.vendor_id, s.db AS store_db FROM po p INNER JOIN store s ON s.store_id = p.store_id WHERE p.po_code = ? AND " . is_enabled('p,s'),
    array($po_code)
);
$po = getRow($rs);
if (!$po) {
    echo '<div class="alert alert-danger">PO not found.</div>';
    exit;
}
$store_db = isset($po['store_db']) ? $po['store_db'] : '';
$vendor_rs = getRs("SELECT vendor_id, name FROM {$store_db}.vendor WHERE vendor_id = ?", array($po['vendor_id']));
$vendor = getRow($vendor_rs);
if (!$vendor) {
    echo '<div class="alert alert-danger">Vendor not found.</div>';
    exit;
}
$store_id = (int)$po['store_id'];
?>
<form method="post" id="f_po-qbo-map-vendor" class="ajax-form">
  <input type="hidden" name="c" value="<?php echo htmlspecialchars($po_code); ?>" />
  <input type="hidden" name="action" value="save_mapping" />
  <input type="hidden" name="vendor_id" value="<?php echo (int)$po['vendor_id']; ?>" />
  <input type="hidden" name="store_id" id="qbo_map_store_id" value="<?php echo $store_id; ?>" />
  <div class="alert alert-info">Map this vendor to a QuickBooks Online vendor, then the bill will be created.</div>
  <div class="form-group">
    <label class="col-form-label">Our vendor</label>
    <div><strong><?php echo htmlspecialchars($vendor['name']); ?></strong></div>
  </div>
  <div class="form-group">
    <label for="qbo_vendor_id" class="col-form-label">QuickBooks vendor</label>
    <select name="qbo_vendor_id" id="qbo_vendor_id" class="form-control select2" required>
      <option value="">— Loading… —</option>
    </select>
    <small class="form-text text-muted">Type in the dropdown to search and filter vendors.</small>
  </div>
  <div class="form-btns">
    <button type="submit" class="btn btn-primary btn-submit">Save &amp; Push to QuickBooks</button>
  </div>
  <div id="status_po-qbo-map-vendor" class="status mt-2"></div>
</form>
