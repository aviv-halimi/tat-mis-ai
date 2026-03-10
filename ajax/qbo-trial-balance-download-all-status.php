<?php
/**
 * Poll status of "Download All" job. Returns { ready: true, download_url: "..." } or { ready: false }.
 */
require_once dirname(__DIR__) . '/_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) {
    @session_start();
}

$job_id = isset($_GET['job_id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['job_id']) : '';
if (strlen($job_id) !== 32) {
    echo json_encode(array('ready' => false, 'error' => 'Invalid job_id.'));
    exit;
}

$stored = isset($_SESSION['qbo_tb_job_id']) ? $_SESSION['qbo_tb_job_id'] : '';
if ($job_id !== $stored) {
    echo json_encode(array('ready' => false, 'error' => 'Job not found.'));
    exit;
}

$job_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_jobs';
$path = $job_dir . DIRECTORY_SEPARATOR . $job_id . '.xlsx';
$log_path = $job_dir . DIRECTORY_SEPARATOR . $job_id . '.log';

$progress = '';
if (is_file($log_path) && is_readable($log_path)) {
    $lines = @file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $progress = implode("\n", array_slice($lines, -80));
    }
}

if (is_file($path) && is_readable($path)) {
    $url = '/ajax/qbo-trial-balance-download-all-file.php?job_id=' . $job_id;
    echo json_encode(array('ready' => true, 'download_url' => $url, 'progress' => $progress));
} else {
    echo json_encode(array('ready' => false, 'progress' => $progress));
}
exit;
