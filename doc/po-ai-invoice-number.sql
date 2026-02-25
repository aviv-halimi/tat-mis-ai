-- Run in main app DB. Stores AI-extracted invoice number from PDF for validation.
ALTER TABLE po ADD COLUMN ai_invoice_number VARCHAR(255) NULL DEFAULT NULL COMMENT 'Invoice number extracted from PDF by AI';
