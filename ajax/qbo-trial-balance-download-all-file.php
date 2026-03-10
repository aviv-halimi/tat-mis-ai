<?php
/**
 * Stream the generated "Download All" Excel file (one-time download; file is deleted after).
 */
require_once dirname(__DIR__) . '/_config.php';

if (!isset($_SESSION)) {
    @session_start();
}

$job_id = isset($_GET['job_id']) ? preg_replace('/[^a-f0-9]/', '', $_GET['job_id']) : '';
if (strlen($job_id) !== 32) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('Invalid job_id.');
}

$stored = isset($_SESSION['qbo_tb_job_id']) ? $_SESSION['qbo_tb_job_id'] : '';
if ($job_id !== $stored) {
    http_response_code(403);
    header('Content-Type: text/plain');
    die('Job not found or already downloaded.');
}

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_jobs' . DIRECTORY_SEPARATOR . $job_id . '.xlsx';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    die('File not found or no longer available.');
}

$end_date = isset($_SESSION['qbo_tb_job_end_date']) ? $_SESSION['qbo_tb_job_end_date'] : date('Y-m-d');
$filename = 'TrialBalances_' . preg_replace('/[^0-9\-]/', '', $end_date) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');
readfile($path);

@unlink($path);
unset($_SESSION['qbo_tb_job_id'], $_SESSION['qbo_tb_job_end_date']);
exit;
