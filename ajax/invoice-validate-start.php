<?php
/**
 * Start invoice validation script in the background. Writes output to log/invoice-validate-run.log.
 * Returns JSON: { started, message?, running? }
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$logDir  = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'invoice-validate-run.log';
$script  = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'invoice-validate.php';

$maxRunSeconds = 600; // consider "still running" if log was updated in last 10 min and no RUN_COMPLETE

// Check if a run is already in progress
if (file_exists($logFile)) {
    $content = @file_get_contents($logFile);
    $mtime   = @filemtime($logFile);
    if ($content !== false && $mtime !== false && (time() - $mtime) < $maxRunSeconds) {
        if (strpos($content, 'RUN_COMPLETE') === false) {
            echo json_encode(['started' => false, 'running' => true, 'message' => 'A run is already in progress.']);
            exit;
        }
    }
}

if (!is_file($script)) {
    echo json_encode(['started' => false, 'message' => 'Script not found.']);
    exit;
}

// Ensure log directory exists
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Start fresh log for this run
@file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] Run started from UI.\n");

// Use PHP CLI binary (not php-fpm). On Plesk/some hosts, "php" in PATH is php-fpm.
$phpBin = null;
if (defined('INVOICE_VALIDATE_PHP_CLI') && INVOICE_VALIDATE_PHP_CLI !== '' && file_exists(INVOICE_VALIDATE_PHP_CLI)) {
    $phpBin = INVOICE_VALIDATE_PHP_CLI;
} elseif (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    // Attempt to find PHP executable (common Plesk paths)
    $possiblePhpPaths = [
        '/usr/bin/php',
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.1/bin/php',
        '/opt/plesk/php/8.0/bin/php',
        '/opt/plesk/php/7.4/bin/php',
    ];
    foreach ($possiblePhpPaths as $path) {
        if (file_exists($path) && is_executable($path)) {
            $phpBin = $path;
            break;
        }
    }
    if (!$phpBin) {
        $phpBin = trim((string) shell_exec('which php 2>/dev/null'));
        if ($phpBin !== '' && strpos($phpBin, 'php-fpm') !== false) {
            $phpBin = '';
        }
    }
}
if (!$phpBin || !file_exists($phpBin)) {
    $phpBin = 'php';
}

// Log which PHP we're using so you can fix INVOICE_VALIDATE_PHP_CLI if it's wrong
@file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] Using PHP: " . $phpBin . "\n", FILE_APPEND);
if ($phpBin === 'php') {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] WARNING: 'php' is often php-fpm here. In _config.php uncomment and set: define('INVOICE_VALIDATE_PHP_CLI', '/opt/plesk/php/7.4/bin/php');\n", FILE_APPEND);
}

$cmd = $phpBin . ' ' . escapeshellarg($script) . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cmd = 'start /B "" ' . $phpBin . ' ' . escapeshellarg($script) . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
}

@popen($cmd, 'r');

echo json_encode(['started' => true, 'message' => 'Validation started.']);
