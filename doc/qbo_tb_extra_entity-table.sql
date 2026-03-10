-- Extra QBO entities for Trial Balance "Download All" (not in store table).
-- Run in your main MIS database.

CREATE TABLE IF NOT EXISTS qbo_tb_extra_entity (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_name VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'Display name (sheet tab, log)',
  qbo_realm_id VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'QBO company/realm ID',
  qbo_refresh_token TEXT NULL COMMENT 'OAuth refresh token for this company',
  qbo_tb_start_date DATE NULL DEFAULT NULL COMMENT 'First day for trial balance report (YYYY-MM-DD)',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Order in Download All (lower first)',
  date_created DATETIME NULL DEFAULT NULL,
  date_updated DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY is_enabled (is_enabled),
  KEY sort (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Extra QBO companies included in Trial Balance Download All (TAT Holdings, TAT Management, etc.)';

-- Initial rows: fill in qbo_realm_id and qbo_refresh_token after connecting each company via OAuth.
INSERT INTO qbo_tb_extra_entity (entity_name, qbo_realm_id, qbo_refresh_token, qbo_tb_start_date, is_enabled, sort_order, date_created) VALUES
('TAT Holdings',  '', '', '2020-01-01', 1, 1, NOW()),
('TAT Management', '', '', '2020-01-01', 1, 2, NOW());
