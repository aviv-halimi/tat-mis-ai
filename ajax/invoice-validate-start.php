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

// Run script in background (Linux/Unix)
$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cmd    = $phpBin . ' ' . escapeshellarg($script) . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows: start detached (no & support in cmd)
    $cmd = 'start /B "" ' . $phpBin . ' ' . escapeshellarg($script) . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
}

@popen($cmd, 'r');

echo json_encode(['started' => true, 'message' => 'Validation started.']);
