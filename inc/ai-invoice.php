<?php
/**
 * Invoice PDF total extraction: send the PDF directly to OpenAI (Files API + Chat Completions).
 * No local PDF parsing. Requires OPENAI_API_KEY (env or constant below).
 */

// Optional: set key here if not using env. Leave empty to use OPENAI_API_KEY env var only.
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}

/**
 * Extract the total amount due from an invoice PDF by sending the PDF to OpenAI.
 * Uses Files API (upload) then Chat Completions with file_id. Returns float on success, null on failure.
 */
function parseInvoiceTotalFromPdf($file_path, &$raw_response = null)
{
    $raw_response = null;

    if (!is_string($file_path) || strlen(trim($file_path)) === 0 || !file_exists($file_path)) {
        return null;
    }

    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') ? OPENAI_API_KEY : null;
    }
    if (!$apiKey) {
        return null;
    }

    $filename = basename($file_path);

    // Step 1: Upload PDF to OpenAI Files API
    $ch = curl_init('https://api.openai.com/v1/files');
    if ($ch === false) {
        return null;
    }

    $cfile = new CURLFile($file_path, 'application/pdf', $filename);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS    => [
            'purpose' => 'user_data',
            'file'    => $cfile,
        ],
    ]);

    $uploadResponse = curl_exec($ch);
    $uploadCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr        = curl_error($ch);
    curl_close($ch);

    if ($uploadResponse === false || $curlErr !== '') {
        return null;
    }

    $uploadJson = is_string($uploadResponse) ? json_decode($uploadResponse, true) : null;
    if (!is_array($uploadJson) || $uploadCode < 200 || $uploadCode >= 300) {
        return null;
    }

    $fileId = isset($uploadJson['id']) ? $uploadJson['id'] : null;
    if (!$fileId || !is_string($fileId)) {
        return null;
    }

    // Step 2: Chat Completions with the uploaded file (vision-capable model required for PDF)
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        return null;
    }

    $payload = [
        'model'       => 'gpt-4o',
        'messages'    => [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type' => 'file',
                        'file' => ['file_id' => $fileId],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Extract the total amount due from this invoice. Reply with only that number, no currency symbols or words.',
                    ],
                ],
            ],
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
    $result       = null;

    if ($response !== false && $curlErr === '') {
        $json = is_string($response) ? json_decode($response, true) : null;
        if (is_array($json) && !isset($json['error']['message']) && $httpCode >= 200 && $httpCode < 300) {
            $content = isset($json['choices'][0]['message']['content']) && is_string($json['choices'][0]['message']['content'])
                ? trim($json['choices'][0]['message']['content'])
                : '';
            if (preg_match_all('/[\d,]+\.?\d*/', $content, $matches) && !empty($matches[0])) {
                $last   = str_replace(',', '', end($matches[0]));
                $result = (float) $last;
            }
        }
    }

    // Step 3: Delete the uploaded file from OpenAI
    $ch = curl_init('https://api.openai.com/v1/files/' . $fileId);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    return $result;
}
