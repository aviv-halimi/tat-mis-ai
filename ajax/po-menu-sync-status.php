<?php
/**
 * Poll status of async PO menu sync. Returns job status and last result when completed.
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Cache-Control: no-cache, must-revalidate');
header('Content-type: application/json');

$po_id = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;
if ($po_id <= 0) {
    echo json_encode(array('status' => 'not_found', 'error' => 'Missing or invalid po_id'));
    exit;
}

$log_dir = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
$job_file = $log_dir . '/po-menu-sync-job-' . $po_id . '.json';
$result_file = $log_dir . '/po-menu-sync-last-' . $po_id . '.json';

$out = array('status' => 'not_found');

if (is_file($job_file)) {
    $job = @json_decode(@file_get_contents($job_file), true);
    if (is_array($job) && isset($job['status'])) {
        $out['status'] = $job['status'];
        if (!empty($job['started_at'])) {
            $out['started_at'] = $job['started_at'];
        }
        if (!empty($job['finished_at'])) {
            $out['finished_at'] = $job['finished_at'];
        }
    }
}

if ($out['status'] === 'completed' && is_file($result_file)) {
    $result = @json_decode(@file_get_contents($result_file), true);
    if (is_array($result)) {
        $out['result'] = $result;
    }
}

echo json_encode($out);
