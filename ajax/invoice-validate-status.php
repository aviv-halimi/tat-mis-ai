<?php
/**
 * Return current invoice validation run log and whether it is still running.
 * JSON: { running, content }
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$logFile = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'invoice-validate-run.log';

$content = '';
$running = false;

if (is_file($logFile)) {
    $content = @file_get_contents($logFile);
    if ($content === false) {
        $content = '';
    }
    $running = (strpos($content, 'RUN_COMPLETE') === false);
}

echo json_encode(['running' => $running, 'content' => $content]);
