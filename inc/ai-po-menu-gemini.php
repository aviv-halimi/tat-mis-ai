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

    // Load PDFs as base64 inline parts for Gemini native PDF understanding
    $pdf_parts = [];
    foreach ($pdf_paths as $path) {
        if (!file_exists($path)) { continue; }
        $bytes = @file_get_contents($path);
        if ($bytes !== false && strlen($bytes) > 0) {
            $pdf_parts[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode($bytes)]];
            $debug_log[] = '[EXTRACT] PDF loaded: ' . basename($path) . ' (' . round(strlen($bytes) / 1024, 1) . ' KB)';
        }
    }
    if (empty($pdf_parts)) {
        $debug_log[] = '[EXTRACT] Could not read any PDF files.';
        return null;
    }

    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    $brands_json = json_encode($all_brands, JSON_UNESCAPED_UNICODE);
    $categories_json = json_encode($all_categories, JSON_UNESCAPED_UNICODE);

    $prompt = <<<PROMPT
AVAILABLE BRANDS (use brand_id in your response, or null):
{$brands_json}

AVAILABLE CATEGORIES (use category_id in your response, or null):
{$categories_json}

CATEGORY TRANSLATION RULES (map vendor menu section headings → our category names):
- "PERSY BADDER","PERSY ROSIN","LIVE ROSIN","THUMB PRINT","SAUCE","BADDER","ROSIN" → "Solventless Extracts"
- "PERSY POD / .5G","PERSY POD","SOLVENTLESS PODS" → "Vape Carts .5g"
- "ALL IN ONE LIVE ROSIN VAPE 1G","ALL IN ONE","AIO" → "AIO"
- "FLOWER","EIGHTHS / 3.5 GRAMS","HALF OUNCE / 14 GRAMS","EIGHTHS","HALF OUNCE","3.5G","14G" → "Flowers"
- "GUMMIS","EDIBLES","HASH ROSIN GUMMIS" → "Edibles"
- "SINGLE JOINTS / 1 GRAM","PREROLL","JOINTS","DOINKS","PRE-ROLL" → "Pre-Rolls"

WEIGHT MATCHING RULES — a PO product is only a match if its name contains the required weight token (case-insensitive):
- Menu section "EIGHTHS / 3.5 GRAMS" or any "3.5g" section → PO product name MUST contain "3.5g"
- Menu section "HALF OUNCE / 14 GRAMS" or any "14g" section → PO product name MUST contain "14g"
- Menu section "SINGLE JOINTS / 1 GRAM" or "1g" preroll → PO product name MUST contain "1g"
- Menu section "ALL IN ONE LIVE ROSIN VAPE 1G" → PO product name MUST contain "1g" (AIO category)
- Menu section "PERSY POD / .5G" → PO product name MUST contain ".5g" (Vape Carts .5g category)

--- STEP 1: DECODE THIS MENU ---
[The menu PDF(s) are attached above. Read them carefully.]

--- STEP 2: MAP BRAND & CATEGORY ---
For every item decoded in Step 1, assign:
  - brand_id: best matching brand from AVAILABLE BRANDS, or null
  - category_id: best matching category from AVAILABLE CATEGORIES (using CATEGORY TRANSLATION RULES), or null
  - price: numeric value only (no $ sign), 0 if not shown

Extract EVERY product. Do not skip any.
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

    $system_instruction_text = <<<SYS
You are a cannabis dispensary menu extraction assistant.

STEP 1 — DECODE THE MENU: Read the menu text and identify every product. Record the strain name exactly as written, the section heading (product type), and the price.

STEP 2 — MAP BRAND & CATEGORY: For each decoded product, match it to the best brand_id from AVAILABLE BRANDS and the best category_id from AVAILABLE CATEGORIES using the CATEGORY TRANSLATION RULES. Use null if no confident match exists.

For the name field, concatenate as: "{Brand Name} {Strain Name} {Menu Category} {Weight}" (e.g. "710 Labs C. Chrome #27 Flower 3.5g"). Use the exact values from the menu; include weight only if shown.

Return all menu items in the structured JSON format. Do not skip any items.
SYS;

    $payload = [
        'system_instruction' => ['parts' => [['text' => $system_instruction_text]]],
        'contents' => [['parts' => array_merge($pdf_parts, [['text' => $prompt]])]],
        'generationConfig' => [
            'temperature'     => 0.0,
            'maxOutputTokens' => 16384,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $schema,
        ],
    ];

    $debug_log[] = '[EXTRACT] Sending request to Gemini (' . $model . '). PDFs: ' . count($pdf_parts) . ', brands: ' . count($all_brands) . ', categories: ' . count($all_categories) . '.';

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
