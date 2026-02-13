-- Add QBO_ID to vendor table for mapping to QuickBooks Online vendors.
-- Run this on each store database (blaze1, blaze2, blaze3, ...).

ALTER TABLE vendor ADD COLUMN QBO_ID VARCHAR(32) NULL DEFAULT NULL COMMENT 'QuickBooks Online Vendor Id';
-- Optional: index for lookups
-- CREATE INDEX idx_vendor_qbo_id ON vendor (QBO_ID);
