<?php
/**
 * PO Menu Sync – Gemini menu extraction.
 * Single Gemini call: parse PDFs and map menu items to brand_id/category_id from our DB.
 */
set_time_limit(0);

/**
 * Extract all menu items from the given PDF files.
 * Sends ALL brands and categories from the store DB to Gemini so it can map them.
 * Returns: array of {name, price, brand_id|null, category_id|null}  or null on failure.
 */
function extractMenuItemsFromPDF(array $pdf_paths, array $all_brands, array $all_categories, array &$debug_log = null)
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) {
        $debug_log[] = '[EXTRACT] Missing GEMINI_API_KEY.';
        return null;
    }

    $menu_text = '';
    foreach ($pdf_paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($out) {
            $menu_text .= "--- MENU START ---\n" . trim($out) . "\n--- MENU END ---\n\n";
        }
    }
    if (trim($menu_text) === '') {
        $debug_log[] = '[EXTRACT] pdftotext returned no text. Check that pdftotext is installed.';
        return null;
    }

    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    $brands_json = json_encode($all_brands, JSON_UNESCAPED_UNICODE);
    $categories_json = json_encode($all_categories, JSON_UNESCAPED_UNICODE);

    $prompt = <<<PROMPT
Extract every product listed on the vendor menu below.

AVAILABLE BRANDS — pick the best matching brand_id for each product, or null:
{$brands_json}

AVAILABLE CATEGORIES — pick the best matching category_id for each product using the translation rules below, or null:
{$categories_json}

CATEGORY TRANSLATION RULES (map vendor menu section headings → our category names):
- "PERSY BADDER", "PERSY ROSIN", "LIVE ROSIN", "THUMB PRINT", "SAUCE", "BADDER", "ROSIN" → match category "Solventless Extracts"
- "PERSY POD", "SOLVENTLESS PODS", "ALL IN ONE", "AIO", "VAPE CART" → match category "Vape Carts .5g" or "AIO"
- "FLOWER", "EIGHTHS", "HALF OUNCE", "3.5G", "14G" → match category "Flowers"
- "GUMMIS", "EDIBLES", "HASH ROSIN GUMMIS" → match category "Edibles"
- "PREROLL", "SINGLE JOINTS", "JOINTS", "DOINKS", "PRE-ROLL" → match category "Pre-Rolls" or similar

INSTRUCTIONS:
- Extract EVERY product. Do not skip any.
- Use the exact product name as shown on the menu.
- price: numeric value only (no $ sign). Use 0 if not shown.
- brand_id: best matching brand from the list above, or null.
- category_id: best matching category from the list above using translation rules, or null.

MENU:
{$menu_text}
PROMPT;

    $schema = [
        'type' => 'OBJECT',
        'properties' => [
            'menu_items' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name'        => ['type' => 'STRING'],
                        'price'       => ['type' => 'NUMBER'],
                        'brand_id'    => ['type' => 'INTEGER', 'nullable' => true],
                        'category_id' => ['type' => 'INTEGER', 'nullable' => true],
                    ],
                    'required' => ['name', 'price'],
                ],
            ],
        ],
        'required' => ['menu_items'],
    ];

    $payload = [
        'system_instruction' => ['parts' => [['text' => 'You are a precise menu extraction assistant. Return valid JSON only. Extract every product.']]],
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'     => 0.0,
            'maxOutputTokens' => 16384,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $schema,
        ],
    ];

    $debug_log[] = '[EXTRACT] Sending request to Gemini (' . $model . '). Menu text: ' . strlen($menu_text) . ' chars, brands: ' . count($all_brands) . ', categories: ' . count($all_categories) . '.';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $raw === false) {
        $debug_log[] = '[EXTRACT] cURL error: ' . ($err ?: 'empty response');
        return null;
    }
    if ($code !== 200) {
        $debug_log[] = '[EXTRACT] HTTP ' . $code . ': ' . substr($raw, 0, 500);
        return null;
    }

    $res         = json_decode($raw, true);
    $text        = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $finishReason = $res['candidates'][0]['finishReason'] ?? '?';
    $debug_log[] = '[EXTRACT] Response: ' . strlen($text) . ' chars, finishReason=' . $finishReason;
    $debug_log[] = $text ?: '(empty)';

    $data = json_decode($text, true);
    if (!is_array($data) || empty($data['menu_items'])) {
        $debug_log[] = '[EXTRACT] Could not parse menu_items from Gemini response.';
        return null;
    }

    $debug_log[] = '[EXTRACT] Extracted ' . count($data['menu_items']) . ' menu items.';
    return $data['menu_items'];
}
