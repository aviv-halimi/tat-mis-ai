<?php
/**
 * Invoice extraction via Google Gemini (inline PDF): total amount due, payment terms, invoice number, and contact.
 * Returns array ['total' => float, 'payment_terms' => int|null, 'invoice_number' => string|null, 'contact' => string|null] on success, null on failure.
 * payment_terms is the numeric part only (e.g. 20 from "NET 20"); null if not found.
 * invoice_number is the invoice/reference number as shown on the PDF; null if not found.
 * contact is the contact/recipient name on the invoice; null if not found. Used for Nabis vendor formatting.
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
Analyze this invoice PDF and extract exactly four values. Reply with ONLY a single JSON object, no other text.

Required JSON structure (use exactly these keys):
{
  "total_amount_due": <number>,
  "payment_terms": <integer or null>,
  "invoice_number": <string or null>,
  "contact": <string or null>
}

Rules:
- total_amount_due: The final amount owed (number, e.g. 1234.56). Consider totals, balances, taxes, credits, adjustments.
- payment_terms: The payment due period as an integer number of days only. Invoice often shows "NET 20", "NET 30", "Due in 15 days", etc. Extract ONLY the numeric part (e.g. 20, 30, 15). If not found or unclear, use null. Do not include the word "NET" or any text—only the integer or null.
- invoice_number: The invoice number, reference number, or bill number as shown on the invoice (e.g. "INV-12345", "2024-001"). Use a string or null if not found. Trim spaces; do not include labels like "Invoice #".
- contact: The contact name, recipient name, or primary account/entity name on the invoice (e.g. a person name, store name, or "Bill To" name). Use a string or null if not found. Trim spaces.

Example responses:
{"total_amount_due": 2454.37, "payment_terms": 20, "invoice_number": "INV-12345", "contact": "Acme Store"}
{"total_amount_due": 1500.00, "payment_terms": null, "invoice_number": null, "contact": null}
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

    $invoiceNumber = null;
    if (array_key_exists('invoice_number', $parsed) && $parsed['invoice_number'] !== null && $parsed['invoice_number'] !== '') {
        $invoiceNumber = is_string($parsed['invoice_number']) ? trim($parsed['invoice_number']) : (string) $parsed['invoice_number'];
        if ($invoiceNumber === '') {
            $invoiceNumber = null;
        }
    }

    $contact = null;
    if (array_key_exists('contact', $parsed) && $parsed['contact'] !== null && $parsed['contact'] !== '') {
        $contact = is_string($parsed['contact']) ? trim($parsed['contact']) : (string) $parsed['contact'];
        if ($contact === '') {
            $contact = null;
        }
    }

    return [
        'total' => $total,
        'payment_terms' => $paymentTerms,
        'invoice_number' => $invoiceNumber,
        'contact' => $contact,
    ];
}

/**
 * Run invoice validation for a single PO (e.g. when status is pushed to 5).
 * Loads PO r_total and invoice file, calls Gemini, updates invoice_validated and payment_terms (unless $check_only).
 * Returns array('matched' => bool, 'ai_total' => float|null, 'r_total' => float|null, 'payment_terms' => int|null, 'debug_log' => array) for optional use.
 * When $check_only is true, no DB updates are performed; r_total is always set when PO row exists (for mismatch modal).
 */
function runInvoiceValidationForPO($po_id, $check_only = false)
{
    $po_id = (int) $po_id;
    if ($po_id <= 0) {
        return array('matched' => false, 'ai_total' => null, 'r_total' => null, 'payment_terms' => null, 'debug_log' => array());
    }
    $rs = getRs(
        "SELECT po.po_id, po.invoice_number, po.vendor_id, (po.r_total - COALESCE(SUM(pd.discount_amount), 0)) AS r_total, po.invoice_filename, v.name AS vendor_name
         FROM po
         LEFT JOIN po_discount pd ON pd.po_id = po.po_id AND pd.is_receiving = 1 AND pd.is_enabled = 1 AND pd.is_active = 1
         LEFT JOIN vendor v ON v.vendor_id = po.vendor_id AND " . is_enabled('v') . "
         WHERE " . is_enabled('po') . " AND po.po_id = ? AND LENGTH(po.invoice_filename) > 0 AND po.r_total > 0
         GROUP BY po.po_id, po.invoice_number, po.vendor_id, po.r_total, po.invoice_filename, v.name",
        array($po_id)
    );
    $r = getRow($rs);
    if (!$r) {
        return array('matched' => false, 'ai_total' => null, 'r_total' => null, 'payment_terms' => null, 'debug_log' => array());
    }
    $r_total = (float) $r['r_total'];
    $po_invoice_number = isset($r['invoice_number']) ? trim((string) $r['invoice_number']) : '';
    $full_path = MEDIA_PATH . 'po/' . $r['invoice_filename'];
    if (!file_exists($full_path)) {
        return array('matched' => false, 'ai_total' => null, 'r_total' => $r_total, 'payment_terms' => null, 'debug_log' => array('Invoice file not found: ' . $full_path));
    }
    $debug_log = array();
    $result = parseInvoiceFromPdfGemini($full_path, $debug_log);
    if ($result === null || !isset($result['total'])) {
        if (!$check_only) {
            dbUpdate('po', array('invoice_validated' => 0, 'ai_total' => null, 'ai_invoice_number' => null), $po_id);
        }
        return array('matched' => false, 'ai_total' => null, 'r_total' => $r_total, 'payment_terms' => null, 'debug_log' => $debug_log);
    }
    $ai_total = (float) $result['total'];
    $payment_terms = array_key_exists('payment_terms', $result) && $result['payment_terms'] !== null ? (int) $result['payment_terms'] : null;
    $ai_invoice_number = isset($result['invoice_number']) && $result['invoice_number'] !== null && $result['invoice_number'] !== '' ? trim((string) $result['invoice_number']) : null;
    if ($ai_invoice_number !== null && $ai_invoice_number === '') {
        $ai_invoice_number = null;
    }
    $contact = isset($result['contact']) && $result['contact'] !== null && $result['contact'] !== '' ? trim((string) $result['contact']) : null;
    if ($contact !== null && $contact === '') {
        $contact = null;
    }

    $vendor_name = isset($r['vendor_name']) ? trim((string) $r['vendor_name']) : '';
    if (stripos($vendor_name, 'Nabis') !== false && $contact !== null && $contact !== '' && $ai_invoice_number !== null && $ai_invoice_number !== '') {
        $inv_num = $ai_invoice_number;
        $max_len = 21;
        $dash_len = 1;
        $inv_len = strlen($inv_num);
        $max_contact_len = $max_len - $dash_len - $inv_len;
        if ($max_contact_len >= 0) {
            $contact_trimmed = substr($contact, 0, $max_contact_len);
            $ai_invoice_number = $contact_trimmed . '-' . $inv_num;
        }
    }

    $total_matched = (abs($ai_total - $r_total) <= 5);
    $invoice_number_ok = ($ai_invoice_number === null || $ai_invoice_number === '' || $po_invoice_number === '' || stripos($po_invoice_number, $ai_invoice_number) !== false);
    $matched = $total_matched && $invoice_number_ok;
    $variance = $r_total - $ai_total;
    $add_auto_discount = ($total_matched && $variance != 0 && abs($variance) < 5);

    $debug_log[] = 'Result: AI total=' . $ai_total . ', DB r_total=' . $r_total . ', payment_terms=' . ($payment_terms !== null ? $payment_terms : 'null')
        . ', AI invoice#=' . ($ai_invoice_number !== null ? $ai_invoice_number : 'null') . ', PO invoice#=' . ($po_invoice_number !== '' ? $po_invoice_number : 'null')
        . ', ' . ($matched ? 'Match' : 'No match') . ($add_auto_discount ? ', adding auto-adjustment ' . number_format($variance, 2) . ' (negative = credit to match invoice)' : '');

    if ($check_only) {
        return array('matched' => $matched, 'ai_total' => $ai_total, 'r_total' => $r_total, 'payment_terms' => $payment_terms, 'debug_log' => $debug_log);
    }

    $update = array('ai_total' => $ai_total, 'ai_invoice_number' => $ai_invoice_number);
    if ($payment_terms !== null) {
        $update['payment_terms'] = $payment_terms;
    }
    $update['invoice_validated'] = $matched ? 1 : 0;
    dbUpdate('po', $update, $po_id);

    $out = array('matched' => $matched, 'ai_total' => $ai_total, 'r_total' => $r_total, 'payment_terms' => $payment_terms, 'debug_log' => $debug_log);
    if ($add_auto_discount) {
        $out['add_auto_discount'] = true;
        $out['discount_amount'] = round($variance, 2);
    }
    return $out;
}
