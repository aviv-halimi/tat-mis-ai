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

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_jobs' . DIRECTORY_SEPARATOR . $job_id . '.xlsx';
if (is_file($path) && is_readable($path)) {
    $url = '/ajax/qbo-trial-balance-download-all-file.php?job_id=' . $job_id;
    echo json_encode(array('ready' => true, 'download_url' => $url));
} else {
    echo json_encode(array('ready' => false));
}
exit;
