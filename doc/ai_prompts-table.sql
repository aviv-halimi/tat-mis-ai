-- AI prompt text used by modules (e.g. PO menu extraction). Editable via AI Prompts settings page.
-- Run in your main MIS database (same DB as store/po tables).

CREATE TABLE IF NOT EXISTS ai_prompts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prompt_key VARCHAR(64) NOT NULL COMMENT 'Logical key, e.g. po_menu_category_translations, po_menu_system_instruction',
  prompt_label VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'Display name on settings page',
  content MEDIUMTEXT NULL COMMENT 'Prompt text sent to AI (NULL = use code default)',
  date_updated DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY prompt_key (prompt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Editable AI prompt fragments; used by po-menu-test and others';

-- Initial rows: category translation rules and system instruction for PO menu extraction (Gemini).
-- If table already has rows, run only the INSERTs that are missing (or use REPLACE).
INSERT INTO ai_prompts (prompt_key, prompt_label, content) VALUES
('po_menu_category_translations', 'PO Menu: Category translation rules', '- If the product type or section is "SINGLE JOINTS / 1G", "SINGLE JOINTS/1G", "SINGLE JOINTS / 1 GRAM", "PREROLL", "JOINTS", "DOINKS", or "PRE-ROLL" → category is "Pre-Rolls" (Prerolls).\n- "FLOWER" (column header): use section sub-headers ("HALF OUNCE/14G", "EIGHTHS/3.5G") to determine category → "Flowers". Do NOT map SINGLE JOINTS/1G to Flowers — that is Prerolls.\n- "PERSY BADDER","PERSY ROSIN","PERSY BADDER 1G","PERSY ROSIN 1G","LR BADDER 2.5G","LR BADDER 1G","LIVE ROSIN","THUMB PRINT","SAUCE","BADDER","ROSIN" → "Solventless Extracts"\n- "PERSY POD / .5G","PERSY POD","PERSY POD .5G","SOLVENTLESS PODS" → "Vape Carts .5g"\n- "ALL IN ONE LIVE ROSIN VAPE 1G","ALL IN ONE","AIO","LR VAPE 1G ALL-IN-ONE" → "AIO"\n- "FLOWER","EIGHTHS / 3.5 GRAMS","HALF OUNCE / 14 GRAMS","HALF OUNCE/14G","EIGHTHS/3.5G","EIGHTHS","HALF OUNCE","3.5G","14G" → "Flowers"\n- "GUMMIS","EDIBLES","HASH ROSIN GUMMIS","HASH ROSIN GUMMIS 100MG" → "Edibles"\n- "PERSY DOINKS" → "Infused Prerolls"\n- "2 PERSY DOINKS" → "Infused Preroll Packs"),
('po_menu_system_instruction', 'PO Menu: System instruction', NULL)
ON DUPLICATE KEY UPDATE prompt_label = VALUES(prompt_label);

-- content NULL for po_menu_system_instruction = use default from code. To make it editable, update that row with the full system instruction text from po-menu-test.php.
