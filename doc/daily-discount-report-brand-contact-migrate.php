<?php
/**
 * One-off migration: add contact_name and contact_email to store_id=1 brand table for "Email to Brand".
 * Run from project root: php doc/daily-discount-report-brand-contact-migrate.php
 */
require_once dirname(__DIR__) . '/_config.php';

$store1 = getRow(getRs("SELECT store_id, db FROM store WHERE store_id = 1 AND " . is_enabled(), array()));
if (!$store1 || empty($store1['db'])) {
    echo "Store 1 not found or has no db.\n";
    exit(1);
}
$db = preg_replace('/[^a-z0-9_]/i', '', $store1['db']);

foreach (array('contact_name', 'contact_email') as $col) {
    $check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = ?", array($db, $col));
    $row = getRow($check);
    if ($row && (int)$row['c'] === 0) {
        $sql = "ALTER TABLE `" . str_replace('`', '``', $db) . "`.`brand` ADD COLUMN " . $col . " VARCHAR(255) DEFAULT NULL";
        getRs($sql);
        echo "Added {$col} to {$db}.brand (store_id 1).\n";
    } else {
        echo "{$db}.brand.{$col} already exists.\n";
    }
}
echo "Done.\n";
