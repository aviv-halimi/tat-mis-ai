-- Run in main app DB. Marks PO as pushed to QuickBooks when a bill is created successfully.
ALTER TABLE po ADD COLUMN in_qbo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 when bill was pushed to QBO successfully';
