<?php
/**
 * Returns the current status/result of a background po-menu-test run.
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Content-type: application/json');

$po_id = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if (!$po_id) {
    echo json_encode(['found' => false, 'error' => 'Missing po_id']);
    exit;
}

$log_dir     = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
$result_file = $log_dir . '/po-menu-test-' . $po_id . '.json';

if (!file_exists($result_file)) {
    echo json_encode(['found' => false]);
    exit;
}

$raw = @file_get_contents($result_file);
$data = $raw ? json_decode($raw, true) : null;
if (!$data) {
    echo json_encode(['found' => false, 'error' => 'Could not read result file']);
    exit;
}

echo json_encode(['found' => true, 'data' => $data]);
