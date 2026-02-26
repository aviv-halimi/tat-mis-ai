-- Daily Discount Report → QBO Credit Memos: schema changes.
-- Run the main-DB change below once. For brand.qbo_vendor_id, run the per-store ALTER
-- for each store database (or use doc/daily-discount-report-qbo-migrate-brand-column.php).

-- 1) Log for last push result per report brand (main DB).
ALTER TABLE daily_discount_report_brand
  ADD COLUMN qbo_push_log TEXT DEFAULT NULL;

-- 2) Per-store brand → QBO vendor mapping. Run for EACH store database (e.g. blaze1, blaze2, …).
-- Replace {store_db} with the actual database name.
-- ALTER TABLE `{store_db}`.brand ADD COLUMN qbo_vendor_id VARCHAR(64) DEFAULT NULL;
