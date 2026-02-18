-- Run this in each store database (e.g. blaze1, etc.) to create the payment_terms
-- table for QBO payment term mapping. Ranges (min_days, max_days) map to a QBO Term.

CREATE TABLE IF NOT EXISTS payment_terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  min_days INT UNSIGNED NOT NULL COMMENT 'Inclusive lower bound (days)',
  max_days INT UNSIGNED NOT NULL COMMENT 'Inclusive upper bound (days)',
  qbo_term_id VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'QBO Term Id (SalesTermRef)',
  qbo_term_name VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'Display name from QBO',
  date_created DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY is_enabled_active (is_enabled, is_active),
  KEY min_max (min_days, max_days)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps day ranges to QBO payment terms for bills';
