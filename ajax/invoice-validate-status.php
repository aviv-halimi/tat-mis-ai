<?php
/**
 * Return current invoice validation run log and whether it is still running.
 * JSON: { running, content }
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$logDir  = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'invoice-validate-run.log';
$pidFile = $logDir . DIRECTORY_SEPARATOR . 'invoice-validate.pid';

$content = '';
$running = false;

if (is_file($logFile)) {
    $content = @file_get_contents($logFile);
    if ($content === false) {
        $content = '';
    }
    $running = (strpos($content, 'RUN_COMPLETE') === false);

    // If log says not complete, only consider running if PID file exists and process is alive
    if ($running && is_file($pidFile)) {
        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid > 0 && function_exists('posix_kill')) {
            $running = @posix_kill($pid, 0);
        }
        if (!$running) {
            @unlink($pidFile);
        }
    } elseif ($running && !is_file($pidFile)) {
        $running = false;
    }
}

echo json_encode(['running' => $running, 'content' => $content]);
