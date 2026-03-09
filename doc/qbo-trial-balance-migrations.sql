-- QBO Trial Balance module: schema and module registration.
-- Run steps 1 and 2 in order.

-- ────────────────────────────────────────────────────────────────────────────
-- 1) Add qbo_tb_start_date column to store table (run once).
-- ────────────────────────────────────────────────────────────────────────────
ALTER TABLE theartisttree.store
  ADD COLUMN qbo_tb_start_date DATE NULL DEFAULT NULL
  COMMENT 'Start date for QBO Trial Balance report pull';

-- ────────────────────────────────────────────────────────────────────────────
-- 2) Register the module pages.
--    First, find the parent_module_id used by your existing QBO/Reports section:
-- ────────────────────────────────────────────────────────────────────────────
SELECT module_id, parent_module_id, module_code, module_name
  FROM module
 WHERE module_code LIKE '%qbo%' OR module_code LIKE '%daily-discount%'
 ORDER BY parent_module_id, module_id;

-- Then insert the two new modules, replacing @parent_id with the correct value.
-- If this module should appear at the top level (no parent), leave parent_module_id NULL.

-- Main UI page:
INSERT INTO module (parent_module_id, module_code, module_name, icon, is_enabled, is_active)
VALUES (@parent_id, 'qbo-trial-balance', 'QBO Trial Balance', 'fa fa-balance-scale', 1, 1);

-- Download/ZIP generator (hidden from nav — no parent needed):
INSERT INTO module (parent_module_id, module_code, module_name, icon, is_enabled, is_active)
VALUES (NULL, 'qbo-trial-balance-download', 'QBO Trial Balance Download', 'fa fa-download', 1, 1);
