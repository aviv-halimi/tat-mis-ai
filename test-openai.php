<?php
/**
 * Standalone OpenAI API test – no DB, no session, no PDF.
 * Use this to verify your API key and connectivity in isolation.
 *
 * Usage:
 *   - Browser: open /test-openai.php
 *   - CLI:     php test-openai.php
 *
 * Set your key below or via environment OPENAI_API_KEY.
 * Remove or restrict access to this file in production.
 */

// Optional: paste key here for testing (otherwise uses OPENAI_API_KEY env var)
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false || $apiKey === '') {
    $apiKey = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') ? OPENAI_API_KEY : null;
}

$isCli = (php_sapi_name() === 'cli');

function out($msg, $isCli)
{
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo htmlspecialchars($msg) . "<br>\n";
    }
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>\n";
}

if (!$apiKey) {
    out('OpenAI API key not set.', $isCli);
    out('Set OPENAI_API_KEY in this file (define at top) or in your environment.', $isCli);
    if (!$isCli) {
        echo "</pre>\n";
    }
    exit(1);
}

out('Key found (length ' . strlen($apiKey) . '). Calling OpenAI...', $isCli);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
if ($ch === false) {
    out('curl_init failed', $isCli);
    exit(1);
}

$payload = [
    'model'       => 'gpt-4',
    'messages'    => [
        ['role' => 'user', 'content' => 'Reply with exactly: OK'],
    ],
    'temperature' => 0,
    'max_tokens'  => 10,
];

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr !== '') {
    out('cURL error: ' . $curlErr, $isCli);
    exit(1);
}

out('HTTP code: ' . $httpCode, $isCli);

$json = json_decode($response, true);

if (isset($json['error']['message'])) {
    out('OpenAI error: ' . $json['error']['message'], $isCli);
    if (isset($json['error']['code'])) {
        out('Code: ' . $json['error']['code'], $isCli);
    }
    exit(1);
}

if ($httpCode < 200 || $httpCode >= 300) {
    out('Unexpected HTTP status. Response: ' . substr($response, 0, 500), $isCli);
    exit(1);
}

$content = $json['choices'][0]['message']['content'] ?? '(no content)';
out('Success. Model reply: ' . $content, $isCli);
out('OpenAI API is working.', $isCli);

if (!$isCli) {
    echo "</pre>\n";
}
