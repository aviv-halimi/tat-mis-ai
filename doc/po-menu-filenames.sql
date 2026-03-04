-- Add menu_filenames to po for brand menu PDFs (used with AI to sync PO with current menu).
-- JSON array of {name, original_name, size} like coa_filenames.
ALTER TABLE po ADD COLUMN menu_filenames JSON NULL DEFAULT NULL AFTER coa_filenames;
