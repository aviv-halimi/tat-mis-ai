-- =============================================================
-- Brand Asset Library — Schema & Module Registration
-- Run once on the server.
-- =============================================================

-- 1. Add brand_folder column to blaze1.brand
--    Stores a Google Drive folder URL, a raw folder ID, or any
--    other asset-library URL (Dropbox, Brandfolder, etc.)
ALTER TABLE `blaze1`.`brand`
  ADD COLUMN `brand_folder` VARCHAR(512) NULL DEFAULT NULL
  COMMENT 'Asset library URL or Google Drive folder ID for this brand';

-- 2. Register the Brand Asset Library module
--    Adjust parent_module_id / sort to place it in the right nav section.
INSERT INTO `module`
  (`module_code`, `module_name`, `icon`, `is_nav`, `is_active`, `is_enabled`, `sort`)
VALUES
  ('brand-assets-manager', 'Brand Asset Library', 'fa fa-folder-open-o', 1, 1, 1, 95)
ON DUPLICATE KEY UPDATE
  module_name = VALUES(module_name),
  icon        = VALUES(icon);
