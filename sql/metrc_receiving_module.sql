-- =============================================================================
-- Metrc Receiving Module Registration
-- Database: theartisttree
-- Run this once to add the module to the site navigation and permissions system.
-- =============================================================================

-- 1. Insert the module record.
--    Adjust parent_module_id and sort to place it where you want in the sidebar.
--    Set site_id = NULL to make it available across all sites, or supply your
--    specific site_id integer if the site uses one.
INSERT INTO `module`
    (module_code, module_name, icon, is_nav, is_enabled, sort, parent_module_id, site_id, date_created)
VALUES
    ('metrc-receiving', 'Metrc Receiving', 'fa fa-truck', 1, 1, 100, NULL, NULL, CURRENT_TIMESTAMP);

-- 2. Grant access to the relevant admin_group(s).
--    Replace <module_id> with the auto-generated ID from the INSERT above,
--    and replace <admin_group_id> with the group(s) that should see this module.
--
--    Example (run after confirming the new module_id):
--
--    UPDATE admin_group
--    SET module_ids = JSON_ARRAY_APPEND(module_ids, '$', <module_id>)
--    WHERE admin_group_id IN (<admin_group_id_1>, <admin_group_id_2>);
--
--    Or to grant access to ALL groups:
--
--    UPDATE admin_group
--    SET module_ids = JSON_ARRAY_APPEND(module_ids, '$', <module_id>)
--    WHERE is_enabled = 1;

-- 3. Verify the insert.
SELECT module_id, module_code, module_name, is_nav, is_enabled
FROM `module`
WHERE module_code = 'metrc-receiving';
