-- Product push propagation queue
-- Run once to create the table.

CREATE TABLE IF NOT EXISTS `product_push_queue` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_product_id`    INT          NOT NULL,
    `blaze_product_id` VARCHAR(64)  NOT NULL COMMENT 'Blaze ObjectId returned on initial POST (store_id=1)',
    `blaze_sku`        VARCHAR(64)  NOT NULL COMMENT 'SKU returned by Blaze; used to search for the product via the API in other stores',
    `product_name`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Product name; used as fallback search term',
    `store_db`         VARCHAR(64)  NOT NULL COMMENT 'Originating store DB name (e.g. blaze5)',
    `davis_price`      DECIMAL(10,2) NULL,
    `dixon_price`      DECIMAL(10,2) NULL,
    `status`           ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    `pushed_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`     DATETIME     NULL,
    `last_error`       TEXT         NULL,
    `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_status`        (`status`),
    INDEX `idx_po_product_id` (`po_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If the table already exists, add the product_name column:
-- ALTER TABLE `product_push_queue` ADD COLUMN `product_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `blaze_sku`;
