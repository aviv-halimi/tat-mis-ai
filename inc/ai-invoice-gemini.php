<?php
/**
 * Invoice extraction via Google Gemini (inline PDF): total amount due and payment terms.
 * Returns array ['total' => float, 'payment_terms' => int|null] on success, null on failure.
 * payment_terms is the numeric part only (e.g. 20 from "NET 20"); null if not found.
 * Second parameter receives a debug log array when provided.
 *
 * Requires GEMINI_API_KEY. Model: gemini-2.0-flash (or set GEMINI_MODEL).
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.0-flash');
}

function parseInvoiceFromPdfGemini($file_path, &$debug_log = null)
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

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $prompt = <<<PROMPT
Analyze this invoice PDF and extract exactly two values. Reply with ONLY a single JSON object, no other text.

Required JSON structure (use exactly these keys):
{
  "total_amount_due": <number>,
  "payment_terms": <integer or null>
}

Rules:
- total_amount_due: The final amount owed (number, e.g. 1234.56). Consider totals, balances, taxes, credits, adjustments.
- payment_terms: The payment due period as an integer number of days only. Invoice often shows "NET 20", "NET 30", "Due in 15 days", etc. Extract ONLY the numeric part (e.g. 20, 30, 15). If not found or unclear, use null. Do not include the word "NET" or any text—only the integer or null.

Example responses:
{"total_amount_due": 2454.37, "payment_terms": 20}
{"total_amount_due": 1500.00, "payment_terms": null}
PROMPT;

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
            'maxOutputTokens' => 256,
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
        . " response: " . (is_string($response) && strlen($response) < 1500 ? $response : substr((string) $response, 0, 1500) . (strlen((string) $response) > 1500 ? '...' : ''));

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
    $text = preg_replace('/^[^{]+/', '', $text);
    $text = preg_replace('/[^}]+$/', '', $text);
    $parsed = json_decode($text, true);

    if (!is_array($parsed) || !isset($parsed['total_amount_due'])) {
        $debug_log[] = "Could not parse JSON or missing total_amount_due: " . substr($text, 0, 200);
        return null;
    }

    $total = isset($parsed['total_amount_due']) && is_numeric($parsed['total_amount_due'])
        ? (float) $parsed['total_amount_due']
        : null;
    if ($total === null || $total < 0) {
        $debug_log[] = "Invalid total_amount_due.";
        return null;
    }

    $paymentTerms = null;
    if (array_key_exists('payment_terms', $parsed)) {
        if ($parsed['payment_terms'] === null) {
            $paymentTerms = null;
        } elseif (is_numeric($parsed['payment_terms'])) {
            $paymentTerms = (int) $parsed['payment_terms'];
            if ($paymentTerms < 0) {
                $paymentTerms = null;
            }
        } else {
            $paymentTerms = null;
        }
    }

    return [
        'total' => $total,
        'payment_terms' => $paymentTerms,
    ];
}

/**
 * Run invoice validation for a single PO (e.g. when status is pushed to 5).
 * Loads PO r_total and invoice file, calls Gemini, updates invoice_validated and payment_terms.
 * Returns array('matched' => bool, 'ai_total' => float|null, 'payment_terms' => int|null) for optional use.
 */
function runInvoiceValidationForPO($po_id)
{
    $po_id = (int) $po_id;
    if ($po_id <= 0) {
        return array('matched' => false, 'ai_total' => null, 'payment_terms' => null);
    }
    $rs = getRs("SELECT po_id, r_total, invoice_filename FROM po WHERE " . is_enabled() . " AND po_id = ? AND LENGTH(invoice_filename) > 0 AND r_total > 0", array($po_id));
    $r = getRow($rs);
    if (!$r) {
        return array('matched' => false, 'ai_total' => null, 'payment_terms' => null);
    }
    $full_path = MEDIA_PATH . 'po/' . $r['invoice_filename'];
    if (!file_exists($full_path)) {
        return array('matched' => false, 'ai_total' => null, 'payment_terms' => null);
    }
    $debug_log = null;
    $result = parseInvoiceFromPdfGemini($full_path, $debug_log);
    $r_total = (float) $r['r_total'];
    if ($result === null || !isset($result['total'])) {
        dbUpdate('po', array('invoice_validated' => 0), $po_id);
        return array('matched' => false, 'ai_total' => null, 'payment_terms' => null);
    }
    $ai_total = (float) $result['total'];
    $payment_terms = array_key_exists('payment_terms', $result) && $result['payment_terms'] !== null ? (int) $result['payment_terms'] : null;
    $matched = (abs($ai_total - $r_total) <= 5);
    if ($matched) {
        $update = array('invoice_validated' => 1);
        if ($payment_terms !== null) {
            $update['payment_terms'] = $payment_terms;
        }
        dbUpdate('po', $update, $po_id);
    } else {
        dbUpdate('po', array('invoice_validated' => 0), $po_id);
    }
    return array('matched' => $matched, 'ai_total' => $ai_total, 'payment_terms' => $payment_terms);
}
