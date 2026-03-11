-- Add AI Prompts to the module table (under Admin Tools).
-- Run in the same DB as the module table (theartisttree or your app DB).
-- Adjust parent_module_id (38 = Admin Tools) and sort if your menu differs.

INSERT INTO module (site_id, module_code, module_name, module_code_alt, module_ref, parent_module_id, tbl, css, image, icon, content, params, is_label, is_nav, is_hidden, sort, is_enabled, is_active, date_created, date_modified)
VALUES (NULL, 'ai-prompts', 'AI Prompts', NULL, NULL, 38, NULL, NULL, NULL, 'fa fa-robot', NULL, NULL, '0', '1', '0', 3, '1', '1', NOW(), NOW());
