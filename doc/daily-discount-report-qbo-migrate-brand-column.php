<?php
/**
 * One-off migration: add qbo_vendor_id to each store's brand table, and qbo_push_log to daily_discount_report_brand.
 * Run from project root: php doc/daily-discount-report-qbo-migrate-brand-column.php
 */
require_once dirname(__DIR__) . '/_config.php';

$done = array('daily_discount_report_brand' => false, 'brand' => array());

// 1) Add qbo_push_log to daily_discount_report_brand (main DB)
$check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_discount_report_brand' AND COLUMN_NAME = 'qbo_push_log'");
$row = getRow($check);
if ($row && (int)$row['c'] === 0) {
    getRs("ALTER TABLE daily_discount_report_brand ADD COLUMN qbo_push_log TEXT DEFAULT NULL");
    $done['daily_discount_report_brand'] = true;
    echo "Added qbo_push_log to daily_discount_report_brand.\n";
} else {
    echo "daily_discount_report_brand.qbo_push_log already exists.\n";
}

// 2) Add qbo_vendor_id to each store's brand table
$stores = getRs("SELECT store_id, db FROM store WHERE " . is_enabled() . " ORDER BY store_id");
if (!$stores) {
    echo "No stores found.\n";
    exit(0);
}
foreach ($stores as $s) {
    $db = isset($s['db']) ? trim($s['db']) : '';
    if ($db === '') {
        continue;
    }
    $check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'qbo_vendor_id'", array($db));
    $row = getRow($check);
    if ($row && (int)$row['c'] === 0) {
        $sql = "ALTER TABLE `" . str_replace('`', '``', $db) . "`.`brand` ADD COLUMN qbo_vendor_id VARCHAR(64) DEFAULT NULL";
        getRs($sql);
        $done['brand'][$s['store_id']] = $db;
        echo "Added qbo_vendor_id to {$db}.brand (store_id {$s['store_id']}).\n";
    } else {
        echo "{$db}.brand.qbo_vendor_id already exists.\n";
    }
}

echo "Done.\n";
