<?php
/**
 * Start "Download All" Trial Balances via CLI (avoids web timeout).
 * Returns JSON: { started: true, job_id: "..." } or { started: false, error: "..." }
 */
require_once dirname(__DIR__) . '/_config.php';

header('Content-Type: application/json');

$end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : (isset($_GET['end_date']) ? trim($_GET['end_date']) : '');
if (!$end_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    echo json_encode(array('started' => false, 'error' => 'Invalid or missing end_date (use YYYY-MM-DD).'));
    exit;
}

$job_id = bin2hex(random_bytes(16));
$job_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qbo_tb_jobs';
$output_path = $job_dir . DIRECTORY_SEPARATOR . $job_id . '.xlsx';

if (!is_dir($job_dir)) {
    @mkdir($job_dir, 0755, true);
}
if (!is_dir($job_dir) || !is_writable($job_dir)) {
    echo json_encode(array('started' => false, 'error' => 'Could not create or write to temp directory.'));
    exit;
}

$script = BASE_PATH . 'cli' . DIRECTORY_SEPARATOR . 'qbo-trial-balance-download-all.php';
if (!is_file($script)) {
    echo json_encode(array('started' => false, 'error' => 'CLI script not found.'));
    exit;
}

$phpBin = 'php';
if (defined('INVOICE_VALIDATE_PHP_CLI') && INVOICE_VALIDATE_PHP_CLI !== '' && file_exists(INVOICE_VALIDATE_PHP_CLI)) {
    $phpBin = INVOICE_VALIDATE_PHP_CLI;
} elseif (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    $try = array('/usr/bin/php', '/opt/plesk/php/8.3/bin/php', '/opt/plesk/php/8.2/bin/php', '/opt/plesk/php/8.1/bin/php');
    foreach ($try as $path) {
        if (file_exists($path) && is_executable($path)) {
            $phpBin = $path;
            break;
        }
    }
}

$is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if ($is_win) {
    $cmd = 'start /B "" ' . $phpBin . ' ' . escapeshellarg($script)
        . ' --end_date=' . escapeshellarg($end_date)
        . ' --output=' . escapeshellarg($output_path)
        . ' > NUL 2>&1';
} else {
    $cmd = $phpBin . ' ' . escapeshellarg($script)
        . ' --end_date=' . escapeshellarg($end_date)
        . ' --output=' . escapeshellarg($output_path)
        . ' > /dev/null 2>&1 &';
}
@popen($cmd, 'r');

if (!isset($_SESSION)) {
    @session_start();
}
$_SESSION['qbo_tb_job_id'] = $job_id;
$_SESSION['qbo_tb_job_end_date'] = $end_date;

echo json_encode(array('started' => true, 'job_id' => $job_id));
exit;
