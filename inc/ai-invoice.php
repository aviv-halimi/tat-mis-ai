<?php
/**
 * Invoice total extraction via OpenAI (upload PDF, extract total, delete file).
 * Returns float (final_amount_due) on success, null on failure.
 * Second parameter receives a debug log array when provided.
 */

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}

function parseInvoiceTotalFromPdf($file_path, &$debug_log = null)
{
    $debug_log = [];

    if (!is_string($file_path) || strlen(trim($file_path)) === 0 || !file_exists($file_path)) {
        $debug_log[] = "Invalid file path.";
        return null;
    }

    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') ? OPENAI_API_KEY : null;
    }
    if (!$apiKey) {
        $debug_log[] = "Missing API key.";
        return null;
    }

    $filename = basename($file_path);

    // ---------------------------
    // STEP 1: Upload PDF
    // ---------------------------
    $ch = curl_init('https://api.openai.com/v1/files');

    $cfile = new CURLFile($file_path, 'application/pdf', $filename);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => [
            'purpose' => 'user_data',
            'file' => $cfile,
        ],
    ]);

    $uploadResponse = curl_exec($ch);
    $uploadCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($uploadResponse === false || $curlErr || $uploadCode >= 300) {
        $debug_log[] = "Upload failed: HTTP $uploadCode" . ($curlErr ? " curl: $curlErr" : "")
            . " response: " . (is_string($uploadResponse) ? $uploadResponse : json_encode($uploadResponse));
        return null;
    }

    $uploadJson = json_decode($uploadResponse, true);
    $fileId = $uploadJson['id'] ?? null;

    if (!$fileId) {
        $debug_log[] = "Missing file ID.";
        return null;
    }

    // ---------------------------
    // STEP 2: Extraction with retries
    // ---------------------------
    $maxRetries = 2;
    $result = null;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {

        $payload = [
            'model' => 'gpt-4o',
            'temperature' => 0,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'file',
                        'file' => ['file_id' => $fileId],
                    ],
                    [
                        'type' => 'text',
                        'text' =>
                            "Analyze this invoice and determine the final payable amount owed.
                             Consider totals, balances, taxes, credits, and adjustments.
                             Return ONLY a valid nunber.  no additional text."
                    ]
                ]
            ]]
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $debug_log[] = "Attempt $attempt HTTP $httpCode – full response: " . (is_string($response) ? $response : json_encode($response));

        if ($response === false || $curlErr || $httpCode >= 300) {
            continue;
        }

        $json = json_decode($response, true);

        $content = $json['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            continue;
        }

        $amount = $parsed['final_amount_due'] ?? null;
        $confidence = $parsed['confidence'] ?? 0;

        // sanity checks
        if (is_numeric($amount) && $amount > 0 && $confidence >= 0.5) {
            $result = $parsed;
            break;
        }
    }

    // ---------------------------
    // STEP 3: Cleanup
    // ---------------------------
    $ch = curl_init("https://api.openai.com/v1/files/$fileId");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
    ]);

    curl_exec($ch);
    curl_close($ch);

    if ($result !== null && isset($result['final_amount_due']) && is_numeric($result['final_amount_due'])) {
        return (float) $result['final_amount_due'];
    }
    return null;
}
