<?php
/**
 * PO vs Brand Menu matching via Google Gemini.
 * Given multiple menu PDFs and the list of products currently on the PO, returns:
 * - disable_po_product_ids: PO line IDs to set is_enabled = 0 (not on menu / not available)
 * - add_products: [{ "name": "...", "price": number }, ...] to add as custom products (on menu but not on PO).
 *
 * Requires GEMINI_API_KEY. Model: gemini-2.0-flash (or GEMINI_MODEL).
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.0-flash');
}

/**
 * @param array $pdf_file_paths Array of absolute paths to PDF files (brand menu).
 * @param array $po_products Array of ['po_product_id' => int, 'product_name' => string].
 * @param array|null $debug_log Optional array to append log messages.
 * @return array|null ['disable_po_product_ids' => int[], 'add_products' => [['name'=>string,'price'=>float], ...]] or null on failure.
 */
function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null)
{
    $debug_log = $debug_log ?? [];

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : null;
    }
    if (!$apiKey) {
        $debug_log[] = 'Missing Gemini API key (GEMINI_API_KEY).';
        return null;
    }

    $parts = [];
    foreach ($pdf_file_paths as $path) {
        if (!is_string($path) || trim($path) === '' || !file_exists($path)) {
            $debug_log[] = 'Invalid or missing file: ' . (is_string($path) ? $path : 'non-string');
            continue;
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || strlen($bytes) === 0) {
            $debug_log[] = 'Could not read file or empty: ' . $path;
            continue;
        }
        $parts[] = [
            'inline_data' => [
                'mime_type' => 'application/pdf',
                'data' => base64_encode($bytes),
            ],
        ];
    }
    if (empty($parts)) {
        $debug_log[] = 'No valid PDF content.';
        return null;
    }

    $po_list = [];
    foreach ($po_products as $p) {
        $id = isset($p['po_product_id']) ? (int) $p['po_product_id'] : 0;
        $name = isset($p['product_name']) ? trim((string) $p['product_name']) : '';
        if ($id && $name !== '') {
            $po_list[] = ['po_product_id' => $id, 'product_name' => $name];
        }
    }
    $po_list_json = json_encode($po_list);

    $prompt = <<<PROMPT
You are analyzing brand/vendor menu PDFs and comparing them to the current purchase order (PO) product list.

**PO products (current lines on the order):**
{$po_list_json}

From the attached PDF(s), extract the full product/price list that appears on the brand's current menu (product name and price per unit where visible).

Then:
1) **Disable PO lines not on the menu**: Any PO product that does NOT appear on the menu (or no clear match) should be considered "not available" — include its po_product_id in disable_po_product_ids. Use fuzzy matching: e.g. "Blue Dream 1/8" on the PO can match "Blue Dream - 1/8 oz" on the menu. Only disable when there is no reasonable match.
2) **Add menu items not on the PO**: Any product that appears on the menu but does NOT already have a matching line on the PO — include it in add_products with "name" (exact or best product name from the menu) and "price" (numeric unit price from the menu; use 0 if not found).

Reply with ONLY a single JSON object, no other text. Use exactly these keys:

{
  "disable_po_product_ids": [ list of po_product_id integers to disable ],
  "add_products": [ { "name": "Product Name", "price": number }, ... ]
}

Example:
{"disable_po_product_ids": [101, 102], "add_products": [{"name": "New Strain 1g", "price": 15.00}, {"name": "Edible Pack", "price": 25.00}]}
PROMPT;

    $parts[] = ['text' => $prompt];

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $payload = [
        'contents' => [
            [
                'parts' => $parts,
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 4096,
        ],
    ];

    $payload_json = json_encode($payload);
    $payload_bytes = strlen($payload_json);
    $payload_mb = round($payload_bytes / 1024 / 1024, 2);

    $debug_log[] = '[REQUEST] ' . date('Y-m-d H:i:s') . ' URL: ' . preg_replace('/key=[^&]+/', 'key=***', $url);
    $debug_log[] = '[REQUEST] Parts: ' . count($parts) . ' (PDFs: ' . (count($parts) - 1) . ', prompt: 1). Payload size: ' . $payload_bytes . ' bytes (' . $payload_mb . ' MB)';
    $debug_log[] = '[REQUEST] PO products sent: ' . count($po_list) . ' items. Prompt length: ' . strlen($prompt) . ' chars';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload_json,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $t0 = microtime(true);
    $response = curl_exec($ch);
    $elapsed = round(microtime(true) - $t0, 2);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    $debug_log[] = '[RESPONSE] ' . date('Y-m-d H:i:s') . ' HTTP ' . $httpCode . ' in ' . $elapsed . 's' . ($curlErr ? ' | curl errno ' . $curlErrno . ': ' . $curlErr : '');
    $debug_log[] = '[RESPONSE] Body length: ' . (is_string($response) ? strlen($response) : 0) . ' bytes';
    $debug_log[] = '[RESPONSE] Raw body (first 2000 chars): ' . (is_string($response) && strlen($response) > 0
        ? substr($response, 0, 2000) . (strlen($response) > 2000 ? ' ...' : '')
        : '(empty)');

    if ($response === false || $curlErr || $httpCode >= 300) {
        return null;
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($text !== null && $text !== '') {
        $debug_log[] = '[RESPONSE] Extracted text from Gemini (first 1500 chars): ' . substr($text, 0, 1500) . (strlen($text) > 1500 ? ' ...' : '');
    }

    if ($text === null || $text === '') {
        $debug_log[] = 'No text in Gemini response. candidates: ' . (isset($json['candidates']) ? json_encode($json['candidates']) : 'none');
        return null;
    }

    $text = trim($text);
    $text = preg_replace('/^[^{]+/', '', $text);
    $text = preg_replace('/[^}]+$/', '', $text);
    // Handle array at top level: some models return [ { ... } ]
    if (preg_match('/^\[/', $text)) {
        $arr = json_decode($text, true);
        if (is_array($arr) && isset($arr[0])) {
            $text = json_encode($arr[0]);
        }
    }
    $parsed = json_decode($text, true);

    if (!is_array($parsed)) {
        $debug_log[] = 'Could not parse Gemini JSON: ' . substr($text, 0, 300);
        return null;
    }

    $disable_ids = [];
    if (isset($parsed['disable_po_product_ids']) && is_array($parsed['disable_po_product_ids'])) {
        foreach ($parsed['disable_po_product_ids'] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $disable_ids[] = $id;
            }
        }
    }

    $add_products = [];
    if (isset($parsed['add_products']) && is_array($parsed['add_products'])) {
        foreach ($parsed['add_products'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? trim((string) $item['name']) : '';
            if ($name === '') {
                continue;
            }
            $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0.0;
            $add_products[] = ['name' => $name, 'price' => $price];
        }
    }

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $add_products,
    ];
}
