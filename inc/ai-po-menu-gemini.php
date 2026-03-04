<?php
/**
 * PO vs Brand Menu matching via Google Gemini.
 * Given multiple menu PDFs and the list of products currently on the PO, returns:
 * - disable_po_product_ids: PO line IDs to set is_enabled = 0 (not on menu / not available)
 * - add_products: [{ "name": "...", "price": number }, ...] to add as custom products (on menu but not on PO).
 *
 * Optimizations: structured output (responseMimeType + responseSchema), system instruction, temperature 0.0.
 * Optional: if pdftotext (poppler-utils) is installed, PDFs are converted to text for faster/smaller requests.
 * Requires GEMINI_API_KEY. Model: GEMINI_PO_MENU_MODEL if set, else GEMINI_MODEL. For 504 see doc/po-menu-sync-504-timeout.md.
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.0-flash');
}

// Faster model for PO menu sync only (reduces chance of 504). Uncomment in _config.php or set in env.
if (!defined('GEMINI_PO_MENU_MODEL')) {
    define('GEMINI_PO_MENU_MODEL', '');
}

/**
 * Extract a JSON array value for a key from a string (handles truncated/malformed JSON).
 * Finds "\"key\":", then the next '[', then the matching ']' respecting strings. Returns the substring [ ... ] or null.
 */
function _gemini_extract_json_array($text, $key)
{
    $qkey = '"' . $key . '"';
    $pos = strpos($text, $qkey);
    if ($pos === false) {
        return null;
    }
    $pos += strlen($qkey);
    $len = strlen($text);
    while ($pos < $len && strpos(" \t\n\r:", $text[$pos]) !== false) {
        $pos++;
    }
    if ($pos >= $len || $text[$pos] !== '[') {
        return null;
    }
    $start = $pos;
    $depth = 0;
    $inString = false;
    $escape = false;
    $quote = '"';
    for ($i = $pos; $i < $len; $i++) {
        $c = $text[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if ($inString) {
            if ($c === '\\') {
                $escape = true;
            } elseif ($c === $quote) {
                $inString = false;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $inString = true;
            $quote = $c;
            continue;
        }
        if ($c === '[') {
            $depth++;
        } elseif ($c === ']') {
            $depth--;
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

/**
 * @param array $pdf_file_paths Array of absolute paths to PDF files (brand menu).
 * @param array $po_products Array of ['po_product_id' => int, 'product_name' => string].
 * @param array|null $debug_log Optional array to append log messages.
 * @param array $po_brands Optional list of brands on the PO: [['brand_id'=>int,'brand_name'=>string], ...].
 * @param array $po_categories Optional list of categories on the PO: [['category_id'=>int,'category_name'=>string], ...].
 * @return array|null ['disable_po_product_ids' => int[], 'add_products' => [['name'=>string,'price'=>float,'brand_id'=>int|null,'category_id'=>int|null], ...]] or null on failure.
 */
function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
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

    // Optional: extract text from all PDFs with pdftotext (poppler-utils) for faster, smaller requests. Fall back to inline PDFs if any fail.
    $parts = [];
    $pdf_text_parts = [];
    $try_text = (function_exists('shell_exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN');
    foreach ($pdf_file_paths as $path) {
        if (!is_string($path) || trim($path) === '' || !file_exists($path)) {
            $debug_log[] = 'Invalid or missing file: ' . (is_string($path) ? $path : 'non-string');
            continue;
        }
        if ($try_text) {
            $escaped = escapeshellarg($path);
            $text = @shell_exec("pdftotext -layout -enc UTF-8 {$escaped} - 2>/dev/null");
            if ($text !== null && trim($text) !== '') {
                $pdf_text_parts[] = trim($text);
            } else {
                $pdf_text_parts = [];
                break;
            }
        }
    }
    if (count($pdf_text_parts) === count($pdf_file_paths) && count($pdf_text_parts) > 0) {
        $parts = [['text' => "Menu text from PDF(s):\n\n" . implode("\n\n---\n\n", $pdf_text_parts)]];
        $debug_log[] = '[REQUEST] Input mode: TEXT (pdftotext) — ' . count($pdf_text_parts) . ' PDF(s) converted to text. Smaller payload, faster.';
    }
    if (empty($parts)) {
        foreach ($pdf_file_paths as $path) {
            if (!is_string($path) || !file_exists($path)) {
                continue;
            }
            $bytes = @file_get_contents($path);
            if ($bytes !== false && strlen($bytes) > 0) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data' => base64_encode($bytes),
                    ],
                ];
            }
        }
        if (!empty($parts)) {
            $debug_log[] = '[REQUEST] Input mode: PDF (base64 inline) — sending raw PDFs. Larger payload; use pdftotext on server to switch to text.';
        }
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
            $row = ['po_product_id' => $id, 'product_name' => $name];
            if (isset($p['category_id']) && $p['category_id'] !== null) {
                $row['category_id'] = (int) $p['category_id'];
            }
            if (!empty($p['category_name'])) {
                $row['category_name'] = (string) $p['category_name'];
            }
            if (isset($p['brand_id']) && $p['brand_id'] !== null) {
                $row['brand_id'] = (int) $p['brand_id'];
            }
            if (!empty($p['brand_name'])) {
                $row['brand_name'] = (string) $p['brand_name'];
            }
            $po_list[] = $row;
        }
    }
    $po_list_json = json_encode($po_list);

    $brand_names_for_prompt = '';
    if (!empty($po_brands)) {
        $names = array_unique(array_filter(array_column($po_brands, 'brand_name')));
        $brand_names_for_prompt = implode('" or "', $names);
    }
    if ($brand_names_for_prompt === '') {
        $brand_names_for_prompt = 'the brand name';
    }

    $brands_text = '';
    if (!empty($po_brands)) {
        $brands_text = "**Brands on this PO (use these brand_id values when mapping new products):**\n" . json_encode($po_brands) . "\n\n";
    }
    $categories_text = '';
    if (!empty($po_categories)) {
        $categories_text = "**Categories on this PO (use these category_id values when mapping new products):**\n" . json_encode($po_categories) . "\n\n";
    }

    $prompt = <<<PROMPT
### ROLE
You are a precision inventory sync assistant. Your task is to reconcile a Purchase Order (PO) against a vendor PDF menu.

### TRANSLATION RECONCILIATION RULES
1. **Prefix/Suffix Strip:** Ignore "{$brand_names_for_prompt}" at the start of PO names. Ignore "Flower", "Preroll", "1g", "3.5g" etc. at the end for the first pass of strain matching.
2. **Fuzzy Name Matching:** Treat "Lunar Z" and "LunarZ" as an EXACT MATCH. Treat "C. Chrome" and "California Chrome" as an EXACT MATCH.
3. **Category Mapping (CRITICAL):**
   * If PO Category is **"Solventless Extracts"**, it matches Menu types: "PERSY BADDER", "PERSY ROSIN", "LIVE ROSIN", or "THUMB PRINT".
   * If PO Category is **"Vape Carts .5g"** or **"AIO"**, it matches Menu types: "PERSY POD", "SOLVENTLESS PODS", or "ALL IN ONE".
   * If PO Category is **"Flowers"**, it matches Menu types: "FLOWER", "EIGHTHS", or "HALF OUNCE".
   * If PO Category is **"Edibles"**, it matches Menu types: "GUMMIS" or "HASH ROSIN GUMMIS".

### DECISION LOGIC
- **KEEP (Do NOT Disable):** If the Strain Name AND the Functional Category (using the map above) both exist on the PDF.
- **DISABLE:** If the strain is missing OR if the strain exists but is in a completely different category (e.g., the menu only has "LunarZ" as a Badder, but the PO line is for "LunarZ Flower").

### OUTPUT
Return the JSON with 'add_products' and 'disable_po_product_ids'. Put in disable_po_product_ids the po_product_id of each PO line that should be disabled (not on menu or wrong category). Put in add_products any menu items not already on the PO, with name, price, and when possible brand_id and category_id from the lists below.

{$brands_text}{$categories_text}**PO products (current lines on the order):**
{$po_list_json}
PROMPT;

    $parts[] = ['text' => $prompt];

    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '')
        ? GEMINI_PO_MENU_MODEL
        : ((defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $response_schema = [
        'type' => 'OBJECT',
        'properties' => [
            'add_products' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'price' => ['type' => 'NUMBER'],
                        'brand_id' => ['type' => 'INTEGER', 'nullable' => true],
                        'category_id' => ['type' => 'INTEGER', 'nullable' => true],
                    ],
                    'required' => ['name', 'price'],
                ],
            ],
            'disable_po_product_ids' => [
                'type' => 'ARRAY',
                'items' => ['type' => 'INTEGER'],
            ],
        ],
        'required' => ['add_products', 'disable_po_product_ids'],
    ];

    $payload = [
        'system_instruction' => [
            'parts' => [
                ['text' => 'You are a precision inventory sync assistant. Reconcile PO to menu using translation rules. Return only valid JSON with add_products and disable_po_product_ids.'],
            ],
        ],
        'contents' => [
            [
                'parts' => $parts,
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
            'responseSchema' => $response_schema,
        ],
    ];

    $payload_json = json_encode($payload);
    $payload_bytes = strlen($payload_json);
    $payload_mb = round($payload_bytes / 1024 / 1024, 2);

    $pdf_count = 0;
    foreach ($parts as $p) {
        if (isset($p['inline_data'])) {
            $pdf_count++;
        }
    }
    $debug_log[] = '[REQUEST] ' . date('Y-m-d H:i:s') . ' URL: ' . preg_replace('/key=[^&]+/', 'key=***', $url);
    $debug_log[] = '[REQUEST] Parts: ' . count($parts) . ' (PDFs: ' . $pdf_count . ', prompt: 1). Payload size: ' . $payload_bytes . ' bytes (' . $payload_mb . ' MB)';
    $debug_log[] = '[REQUEST] PO products sent: ' . count($po_list) . ' items. Prompt length: ' . strlen($prompt) . ' chars';
    $debug_log[] = '[REQUEST] --- Full prompt sent to Gemini ---' . "\n" . $prompt . "\n" . '[REQUEST] --- End of prompt ---';

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
    // With structured output we usually get clean JSON; minimal cleanup for edge cases.
    $text = preg_replace('/^\s*```(?:json)?\s*\n?/i', '', $text);
    $text = preg_replace('/\s*```\s*$/s', '', $text);
    $text = trim($text);
    if (!preg_match('/^\s*[{\[]/', $text)) {
        $text = preg_replace('/^[^{[]+/', '', $text);
    }
    if (preg_match('/^\s*\[/', $text)) {
        $arr = json_decode($text, true);
        if (is_array($arr) && isset($arr[0])) {
            $text = json_encode($arr[0]);
        }
    }
    do {
        $prev = $text;
        $text = preg_replace('/,\s*(\s*[}\]])/s', '$1', $text);
    } while ($text !== $prev && $text !== null);
    $parsed = json_decode($text, true);

    if (!is_array($parsed)) {
        $parsed = [];
        $json_err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'unknown';
        $debug_log[] = 'JSON parse failed (' . $json_err . '). Trying fallback extraction.';
        $add_arr_str = _gemini_extract_json_array($text, 'add_products');
        if ($add_arr_str !== null) {
            $add_arr = json_decode($add_arr_str, true);
            if (is_array($add_arr)) {
                $parsed['add_products'] = $add_arr;
            }
        }
        $disable_arr_str = _gemini_extract_json_array($text, 'disable_po_product_ids');
        if ($disable_arr_str !== null) {
            $disable_arr = json_decode($disable_arr_str, true);
            if (is_array($disable_arr)) {
                $parsed['disable_po_product_ids'] = array_values(array_filter(array_map('intval', $disable_arr), function ($id) { return $id > 0; }));
            }
        }
        if (empty($parsed['disable_po_product_ids']) && preg_match('/"disable_po_product_ids"\s*:\s*\[([\d,\s]*)/s', $text, $m)) {
            if (preg_match_all('/\d+/', $m[1], $id_matches)) {
                foreach ($id_matches[0] as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $parsed['disable_po_product_ids'][] = $id;
                    }
                }
            }
        }
        if (empty($parsed['add_products'])) {
            $parsed['add_products'] = [];
        }
        if (empty($parsed['disable_po_product_ids'])) {
            $parsed['disable_po_product_ids'] = [];
        }
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
            $row = ['name' => $name, 'price' => $price];
            if (isset($item['brand_id']) && (is_int($item['brand_id']) || (is_string($item['brand_id']) && $item['brand_id'] !== '' && is_numeric($item['brand_id'])))) {
                $row['brand_id'] = (int) $item['brand_id'];
            } elseif (isset($item['brand_id']) && $item['brand_id'] === null) {
                $row['brand_id'] = null;
            }
            if (isset($item['category_id']) && (is_int($item['category_id']) || (is_string($item['category_id']) && $item['category_id'] !== '' && is_numeric($item['category_id'])))) {
                $row['category_id'] = (int) $item['category_id'];
            } elseif (isset($item['category_id']) && $item['category_id'] === null) {
                $row['category_id'] = null;
            }
            $add_products[] = $row;
        }
    }

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $add_products,
    ];
}
