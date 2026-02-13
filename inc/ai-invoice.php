<?php
/**
 * Invoice PDF total extraction via Smalot PDF parser + OpenAI.
 * Requires: Composer + smalot/pdfparser, and OPENAI_API_KEY set (env or constant below).
 */

// Autoload path: from inc/ so project root is parent
$autoload = defined('BASE_PATH')
    ? (rtrim(BASE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')
    : (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

if (!is_file($autoload)) {
    function parseInvoiceTotalFromPdf($file_path, &$raw_response = null)
    {
        $raw_response = null;
        return null;
    }
    return;
}

require_once $autoload;

// Optional: set key here if not using env. Leave empty to use OPENAI_API_KEY env var only.
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}

/**
 * Extract the total amount due from an invoice PDF using PDF text + OpenAI.
 * Returns float on success, null on failure.
 */
function parseInvoiceTotalFromPdf($file_path, &$raw_response = null)
{
    $raw_response = null;

    if (!is_string($file_path) || strlen(trim($file_path)) === 0 || !file_exists($file_path)) {
        return null;
    }

    // Step 1: Extract text from PDF (use FQCN so no top-level "use" needed)
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf   = $parser->parseFile($file_path);
        $text  = $pdf->getText();
    } catch (\Exception $e) {
        return null;
    }

    if (!is_string($text) || strlen(trim($text)) === 0) {
        return null;
    }

    // Step 2: Truncate long text to stay under context limits
    $maxChars = 12000;
    if (strlen($text) > $maxChars) {
        $text = substr($text, 0, $maxChars) . "\n\n[Text truncated...]";
    }
    $prompt = "Extract ONLY the numeric total amount due from this invoice text. Respond with a single number only, no currency symbols or words.\n\n" . $text;

    // Step 3: API key (env first, then constant)
    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') ? OPENAI_API_KEY : null;
    }
    if (!$apiKey) {
        return null;
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        return null;
    }

    $payload = [
        'model'       => 'gpt-4',
        'messages'    => [
            ['role' => 'system', 'content' => 'You extract the total amount due from invoices. Reply with only that number, no other text.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0,
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS    => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $raw_response = $response;

    if ($response === false || $curlErr !== '') {
        return null;
    }

    $json = is_string($response) ? json_decode($response, true) : null;
    if (!is_array($json)) {
        return null;
    }

    if (isset($json['error']['message']) || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    if (!isset($json['choices'][0]['message']['content']) || !is_string($json['choices'][0]['message']['content'])) {
        return null;
    }

    $content = trim($json['choices'][0]['message']['content']);

    // Step 4: Extract numeric value (last number in response)
    if (preg_match_all('/[\d,]+\.?\d*/', $content, $matches) && !empty($matches[0])) {
        $last = str_replace(',', '', end($matches[0]));
        return (float) $last;
    }

    return null;
}
