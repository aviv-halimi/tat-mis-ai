-- Daily Discount Report → QBO Credit Memos: schema changes.
-- Run the main-DB change below once. For brand.qbo_vendor_id, run the per-store ALTER
-- for each store database (or use doc/daily-discount-report-qbo-migrate-brand-column.php).

-- 1) Log for last push result per report brand (main DB).
ALTER TABLE daily_discount_report_brand
  ADD COLUMN qbo_push_log TEXT DEFAULT NULL;

-- 2) Per-store brand → QBO vendor mapping. Run for EACH store database (e.g. blaze1, blaze2, …).
-- Replace {store_db} with the actual database name.
-- App looks up/updates by master_brand_id (daily_discount_report_brand.brand_id = store brand.master_brand_id).
-- ALTER TABLE `{store_db}`.brand ADD COLUMN qbo_vendor_id VARCHAR(64) DEFAULT NULL;

-- 3) Brand contact for "Email to Brand" (notification). Run only for store_id = 1's database.
-- Get store 1 DB: SELECT db FROM store WHERE store_id = 1;
-- Then: ALTER TABLE `{store_db}`.brand ADD COLUMN contact_name VARCHAR(255) DEFAULT NULL, ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL;
-- Contact is stored only on store 1's brand row per master_brand_id; used when sending daily discount report notification.

-- 4) QBO pushed and Email sent indicators (main DB). Run once.
ALTER TABLE daily_discount_report_brand ADD COLUMN qbo_pushed_at DATETIME DEFAULT NULL, ADD COLUMN email_sent_at DATETIME DEFAULT NULL;
