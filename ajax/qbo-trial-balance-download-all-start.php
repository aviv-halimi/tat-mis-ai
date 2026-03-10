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
$log_path = $job_dir . DIRECTORY_SEPARATOR . $job_id . '.log';

if (!is_dir($job_dir)) {
    @mkdir($job_dir, 0755, true);
}
if (!is_dir($job_dir) || !is_writable($job_dir)) {
    echo json_encode(array('started' => false, 'error' => 'Could not create or write to temp directory.'));
    exit;
}

// QBO credentials: web process has them (Apache SetEnv); CLI does not inherit them, so pass via env file
$qbo_client_id = (defined('QBO_CLIENT_ID') && QBO_CLIENT_ID !== '') ? QBO_CLIENT_ID : (getenv('QBO_CLIENT_ID') ?: '');
$qbo_client_secret = (defined('QBO_CLIENT_SECRET') && QBO_CLIENT_SECRET !== '') ? QBO_CLIENT_SECRET : (getenv('QBO_CLIENT_SECRET') ?: '');
$qbo_client_id = is_string($qbo_client_id) ? trim($qbo_client_id) : '';
$qbo_client_secret = is_string($qbo_client_secret) ? trim($qbo_client_secret) : '';
if ($qbo_client_id === '' || $qbo_client_secret === '') {
    echo json_encode(array('started' => false, 'error' => 'QBO client credentials not set. Set QBO_CLIENT_ID and QBO_CLIENT_SECRET in Apache/env or _config.php.'));
    exit;
}
$env_file = $job_dir . DIRECTORY_SEPARATOR . $job_id . '.env';
$env_content = 'QBO_CLIENT_ID=' . $qbo_client_id . "\n" . 'QBO_CLIENT_SECRET=' . $qbo_client_secret . "\n";
if (@file_put_contents($env_file, $env_content, LOCK_EX) === false) {
    echo json_encode(array('started' => false, 'error' => 'Could not write credentials file.'));
    exit;
}
@chmod($env_file, 0600);

$script = BASE_PATH . 'cli' . DIRECTORY_SEPARATOR . 'qbo-trial-balance-download-all.php';
if (!is_file($script)) {
    @unlink($env_file);
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
} else {
    // Windows: try common PHP locations so background process can find it
    $try = array();
    if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
        $try[] = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
    }
    $try = array_merge($try, array(
        'C:\\php\\php.exe',
        'C:\\xampp\\php\\php.exe',
        'C:\\wamp64\\bin\\php\\php8.2\\php.exe',
        'C:\\wamp\\bin\\php\\php8.2\\php.exe',
    ));
    foreach ($try as $path) {
        if ($path && file_exists($path)) {
            $phpBin = $path;
            break;
        }
    }
}

// Write to log immediately so first status poll shows something; helps debug if CLI never starts
$log_line = '[' . date('H:i:s') . '] Web: spawning CLI (PHP: ' . $phpBin . '). If no "CLI process started" below, the background process did not start—check that PHP CLI is installed and in PATH or set INVOICE_VALIDATE_PHP_CLI in _config.php.' . "\n";
@file_put_contents($log_path, $log_line, LOCK_EX);

$is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if ($is_win) {
    $cmd = 'start /B "" ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script)
        . ' --end_date=' . escapeshellarg($end_date)
        . ' --output=' . escapeshellarg($output_path)
        . ' --log=' . escapeshellarg($log_path)
        . ' --env_file=' . escapeshellarg($env_file)
        . ' > NUL 2>&1';
} else {
    $cmd = $phpBin . ' ' . escapeshellarg($script)
        . ' --end_date=' . escapeshellarg($end_date)
        . ' --output=' . escapeshellarg($output_path)
        . ' --log=' . escapeshellarg($log_path)
        . ' --env_file=' . escapeshellarg($env_file)
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
