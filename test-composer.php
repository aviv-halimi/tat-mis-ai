<?php
/**
 * Standalone Composer + PDF parser test – no app config, no DB, no session.
 * Use this to verify vendor/autoload.php and Smalot\PdfParser load correctly.
 *
 * Usage:
 *   - Browser: open /test-composer.php
 *   - CLI:     php test-composer.php
 *
 * Remove or restrict access in production.
 */

$isCli = (php_sapi_name() === 'cli');

function out($msg, $isCli)
{
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo htmlspecialchars($msg) . "\n";
    }
}

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

out('=== Composer / PDF Parser test ===', $isCli);

// 1. Resolve project root (this script is in project root)
$projectRoot = __DIR__;
$autoload    = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

out('Project root: ' . $projectRoot, $isCli);
out('Autoload path: ' . $autoload, $isCli);

if (!is_file($autoload)) {
    out('FAIL: vendor/autoload.php not found. Run: composer install', $isCli);
    exit(1);
}

out('OK: vendor/autoload.php exists', $isCli);

// 2. Require autoload
try {
    require_once $autoload;
    out('OK: require_once vendor/autoload.php succeeded', $isCli);
} catch (\Throwable $e) {
    out('FAIL: require_once threw: ' . $e->getMessage(), $isCli);
    out('File: ' . $e->getFile() . ' Line: ' . $e->getLine(), $isCli);
    exit(1);
}

// 3. Check if Smalot\PdfParser\Parser exists
if (!class_exists('Smalot\PdfParser\Parser', true)) {
    out('FAIL: Class Smalot\\PdfParser\\Parser not found after autoload. Is smalot/pdfparser installed?', $isCli);
    exit(1);
}

out('OK: Smalot\\PdfParser\\Parser class exists', $isCli);

// 4. Instantiate parser (no PDF file needed)
try {
    $parser = new \Smalot\PdfParser\Parser();
    out('OK: new Parser() succeeded', $isCli);
} catch (\Throwable $e) {
    out('FAIL: new Parser() threw: ' . $e->getMessage(), $isCli);
    out('File: ' . $e->getFile() . ' Line: ' . $e->getLine(), $isCli);
    exit(1);
}

out('=== All checks passed. Composer and PDF parser are working. ===', $isCli);
