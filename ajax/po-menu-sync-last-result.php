<?php
/**
 * Return the last sync result for a PO (from background run) so the UI can show it after refresh.
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-type: application/json');

$po_id = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if ($po_id <= 0) {
    echo json_encode(array('success' => false, 'result' => null));
    exit;
}

$log_dir = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
$file = $log_dir . '/po-menu-sync-last-' . $po_id . '.json';
if (!is_file($file)) {
    echo json_encode(array('success' => true, 'result' => null));
    exit;
}

$json = @file_get_contents($file);
$result = $json ? json_decode($json, true) : null;
echo json_encode(array('success' => true, 'result' => $result));
exit;
