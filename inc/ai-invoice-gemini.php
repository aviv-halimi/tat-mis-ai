<?php
/**
 * Invoice total extraction via Google Gemini (inline PDF in request).
 * Returns float (total amount due) on success, null on failure.
 * Second parameter receives a debug log array when provided.
 *
 * Requires GEMINI_API_KEY in env or constant. Model: gemini-1.5-flash (or set GEMINI_MODEL).
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-1.5-flash');
}

function parseInvoiceTotalFromPdfGemini($file_path, &$debug_log = null)
{
    $debug_log = [];

    if (!is_string($file_path) || strlen(trim($file_path)) === 0 || !file_exists($file_path)) {
        $debug_log[] = "Invalid file path.";
        return null;
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : null;
    }
    if (!$apiKey) {
        $debug_log[] = "Missing Gemini API key (GEMINI_API_KEY).";
        return null;
    }

    $pdfBytes = @file_get_contents($file_path);
    if ($pdfBytes === false || strlen($pdfBytes) === 0) {
        $debug_log[] = "Could not read file or file empty.";
        return null;
    }

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-1.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $prompt = "Analyze this invoice and determine the final payable amount owed. "
        . "Consider totals, balances, taxes, credits, and adjustments. "
        . "Reply with ONLY a valid number (no currency symbol, no extra text). Example: 1234.56";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'inline_data' => [
                            'mime_type' => 'application/pdf',
                            'data' => base64_encode($pdfBytes),
                        ],
                    ],
                    [
                        'text' => $prompt,
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0,
            'maxOutputTokens' => 64,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $debug_log[] = "Gemini HTTP $httpCode" . ($curlErr ? " curl: $curlErr" : "")
        . " response: " . (is_string($response) && strlen($response) < 2000 ? $response : substr((string) $response, 0, 2000) . (strlen((string) $response) > 2000 ? '...' : ''));

    if ($response === false || $curlErr || $httpCode >= 300) {
        return null;
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($text === null || $text === '') {
        $debug_log[] = "No text in Gemini response.";
        return null;
    }

    $text = trim($text);
    $text = preg_replace('/^[^\d.-]+/', '', $text);
    $text = preg_replace('/[^\d.-].*$/s', '', $text);
    if (preg_match('/-?\d+\.?\d*/', $text, $m)) {
        $amount = (float) $m[0];
        if ($amount >= 0) {
            return $amount;
        }
    }

    $debug_log[] = "Could not parse number from: " . $text;
    return null;
}
