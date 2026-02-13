<?php
/**
 * Stop the running invoice validation script (sends SIGTERM, then removes PID file).
 * JSON: { stopped, message? }
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$logDir  = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log';
$pidFile = $logDir . DIRECTORY_SEPARATOR . 'invoice-validate.pid';

if (!is_file($pidFile)) {
    echo json_encode(['stopped' => true, 'message' => 'No run in progress.']);
    exit;
}

$pid = (int) trim((string) @file_get_contents($pidFile));
if ($pid <= 0) {
    @unlink($pidFile);
    echo json_encode(['stopped' => true]);
    exit;
}

$killed = false;
if (function_exists('posix_kill')) {
    if (posix_kill($pid, 0)) {
        $killed = posix_kill($pid, SIGTERM);
    }
} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $killed = @exec('taskkill /PID ' . $pid . ' /F 2>nul') !== '';
}

@unlink($pidFile);

echo json_encode(['stopped' => true, 'message' => $killed ? 'Run stopped.' : 'Process may have already finished.']);