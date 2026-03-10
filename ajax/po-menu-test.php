<?php
/**
 * TEST/diagnostic endpoint: Gemini Phase 1 (PDF→menu items) + PHP matcher.
 * Phase 2 (Gemini matching) has been removed — PHP does all matching deterministically.
 * No DB writes — diagnostic only.
 * Modes:
 *   dry_run=1  → build Phase 1 payload, return without calling Gemini
 *   run=1      → call Gemini Phase 1, PHP-match, return verbose result
 *   (CLI)      → same as run=1, writes result to log file
 */
set_time_limit(0);
$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
    if (!isset($argv[1])) { exit(1); }
    chdir(dirname(__FILE__) . '/..');
    require_once __DIR__ . '/../_config.php';
    $po_id   = (int) $argv[1];
    $po_code = '';
} else {
    require_once dirname(__FILE__) . '/../_config.php';
    header('Cache-Control: no-cache');
    header('Content-type: application/json');
    $po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
}

if (!$po_id) {
    if (!$is_cli) echo json_encode(['success' => false, 'error' => 'Missing po_id']);
    exit($is_cli ? 1 : 0);
}

$po = getRow(getRs(
    "SELECT po_id, po_code, po_status_id, menu_filenames FROM po WHERE " . is_enabled() . " AND po_id = ? LIMIT 1",
    [$po_id]
));
if (!$po) {
    if (!$is_cli) echo json_encode(['success' => false, 'error' => 'PO not found']);
    exit($is_cli ? 1 : 0);
}
if ((int) $po['po_status_id'] !== 1) {
    if (!$is_cli) echo json_encode(['success' => false, 'error' => 'PO must be in Draft status (1)']);
    exit($is_cli ? 1 : 0);
}
$po_code = $po['po_code'];

$files = json_decode($po['menu_filenames'] ?: '[]', true);
if (!is_array($files) || empty($files)) {
    if (!$is_cli) echo json_encode(['success' => false, 'error' => 'No menu PDFs saved. Upload PDFs first.']);
    exit($is_cli ? 1 : 0);
}
$base_path = defined('MEDIA_PATH') ? rtrim(MEDIA_PATH, '/\\') . '/po/' : '';
$pdf_paths = [];
foreach ($files as $f) {
    $n = $f['name'] ?? '';
    if ($n && file_exists($base_path . $n)) { $pdf_paths[] = $base_path . $n; }
}
if (empty($pdf_paths)) {
    if (!$is_cli) echo json_encode(['success' => false, 'error' => 'Menu PDF files not found on disk.']);
    exit($is_cli ? 1 : 0);
}

$log_dir     = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
$result_file = $log_dir . '/po-menu-test-' . $po_id . '.json';

$is_dry_run       = !$is_cli && !empty($_POST['dry_run']);
$parse_pdf_locally = !$is_cli && !empty($_POST['parse_pdf_locally']);

// ---- Load data ----
$db = $_Session->db;

$po_products = [];
foreach (getRs(
    "SELECT t.po_product_id, COALESCE(s.name, t.po_product_name) AS product_name,
            t.category_id, c.name AS category_name, t.brand_id, b.name AS brand_name
     FROM po_product t
     LEFT JOIN {$db}.product  s ON s.product_id  = t.product_id
     LEFT JOIN {$db}.category c ON c.category_id = t.category_id
     LEFT JOIN {$db}.brand    b ON b.brand_id    = t.brand_id
     WHERE t.po_id = ? AND t.is_active = 1 AND t.is_enabled = 1",
    [$po_id]
) as $r) {
    $po_products[] = [
        'po_product_id' => (int) $r['po_product_id'],
        'product_name'  => (string) $r['product_name'],
        'category_id'   => isset($r['category_id']) ? (int) $r['category_id'] : null,
        'category_name' => (string) ($r['category_name'] ?? ''),
        'brand_id'      => isset($r['brand_id']) ? (int) $r['brand_id'] : null,
        'brand_name'    => (string) ($r['brand_name'] ?? ''),
    ];
}

// All active brands from the store DB (so Gemini can match any menu brand, not just those on the PO)
$all_brands = [];
foreach (getRs("SELECT brand_id, name FROM {$db}.brand WHERE is_active = 1 ORDER BY name", []) as $r) {
    $all_brands[] = ['brand_id' => (int) $r['brand_id'], 'name' => (string) $r['name']];
}

// All active/enabled categories from the store DB
$all_categories = [];
foreach (getRs(
    "SELECT category_id, name FROM {$db}.category WHERE is_active = 1 AND is_enabled = 1 ORDER BY name",
    []
) as $r) {
    $all_categories[] = ['category_id' => (int) $r['category_id'], 'name' => (string) $r['name']];
}

// Load PDFs: either as base64 for Gemini (native PDF) or extract text locally
$pdf_parts    = [];
$pdf_summary  = [];
$extracted_text = null; // when parse_pdf_locally: full text to send to Gemini

if ($parse_pdf_locally) {
    $text_chunks = [];
    foreach ($pdf_paths as $path) {
        $cmd = "pdftotext -layout -enc UTF-8 " . escapeshellarg($path) . " - 2>/dev/null";
        $out = @shell_exec($cmd);
        if ($out === null || trim($out) === '') {
            if (!function_exists('shell_exec') || ini_get('disable_functions') !== '') {
                $err = 'Parse PDF locally is selected but pdftotext is not available (shell_exec may be disabled). Install poppler-utils (e.g. apt-get install poppler-utils) or uncheck the option.';
            } else {
                $err = 'Parse PDF locally: could not extract text from ' . basename($path) . '. Is pdftotext installed? (e.g. apt-get install poppler-utils)';
            }
            if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
            @file_put_contents($result_file, json_encode(['status' => 'failed', 'error' => $err]), LOCK_EX);
            if (!$is_cli) echo json_encode(['success' => false, 'error' => $err]);
            exit($is_cli ? 1 : 0);
        }
        $text_chunks[] = "--- " . basename($path) . " ---\n" . $out;
        $pdf_summary[] = basename($path) . " (text)";
    }
    $extracted_text = implode("\n\n", $text_chunks);
} else {
    foreach ($pdf_paths as $path) {
        $bytes = @file_get_contents($path);
        if ($bytes !== false && strlen($bytes) > 0) {
            $pdf_parts[]   = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode($bytes)]];
            $pdf_summary[] = basename($path) . ' (' . round(strlen($bytes) / 1024, 1) . ' KB)';
        }
    }
}

if (!$parse_pdf_locally && empty($pdf_parts)) {
    $err = 'Could not read any PDF files.';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode(['status' => 'failed', 'error' => $err]), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err]);
    exit($is_cli ? 1 : 0);
}

$apiKey  = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
$p1_model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
$url      = 'https://generativelanguage.googleapis.com/v1beta/models/' . $p1_model . ':generateContent?key=' . urlencode($apiKey);

// ---- Shared data ----
$brands_json     = json_encode($all_brands,     JSON_UNESCAPED_UNICODE);
$categories_json = json_encode($all_categories, JSON_UNESCAPED_UNICODE);

$category_translations = <<<TRANS
- If the product type or section is "SINGLE JOINTS / 1G", "SINGLE JOINTS/1G", "SINGLE JOINTS / 1 GRAM", "PREROLL", "JOINTS", "DOINKS", or "PRE-ROLL" → category is "Pre-Rolls" (Prerolls).
- "FLOWER" (column header): use section sub-headers ("HALF OUNCE/14G", "EIGHTHS/3.5G") to determine category → "Flowers". Do NOT map SINGLE JOINTS/1G to Flowers — that is Prerolls.
- "PERSY BADDER","PERSY ROSIN","PERSY BADDER 1G","PERSY ROSIN 1G","LR BADDER 2.5G","LR BADDER 1G","LIVE ROSIN","THUMB PRINT","SAUCE","BADDER","ROSIN" → "Solventless Extracts"
- "PERSY POD / .5G","PERSY POD","PERSY POD .5G","SOLVENTLESS PODS" → "Vape Carts .5g"
- "ALL IN ONE LIVE ROSIN VAPE 1G","ALL IN ONE","AIO","LR VAPE 1G ALL-IN-ONE" → "AIO"
- "FLOWER","EIGHTHS / 3.5 GRAMS","HALF OUNCE / 14 GRAMS","HALF OUNCE/14G","EIGHTHS/3.5G","EIGHTHS","HALF OUNCE","3.5G","14G" → "Flowers"
- "GUMMIS","EDIBLES","HASH ROSIN GUMMIS","HASH ROSIN GUMMIS 100MG" → "Edibles"
- "PERSY DOINKS" → "Infused Prerolls"
- "2 PERSY DOINKS" → "Infused Preroll Packs"
TRANS;

// ============================================================
// PHASE 1 — Extract menu items from PDF (Gemini)
// ============================================================
// Compact format: columns + rows (no brand_name — we look it up from brand_id in PHP)
$p1_schema = [
    'type' => 'OBJECT',
    'properties' => [
        'columns' => [
            'type' => 'ARRAY',
            'items' => ['type' => 'STRING'],
            'description' => 'Field names in order: name, price, brand_id, category_id, product_type, weight_token',
        ],
        'rows' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'ARRAY',
                'items' => ['type' => 'STRING', 'nullable' => true],
            ],
            'description' => 'One array per menu item; 6 values per row matching column order.',
        ],
    ],
    'required' => ['columns', 'rows'],
];

$p1_system_instr = <<<SYS
You are a cannabis dispensary menu extraction assistant.

Read the attached PDF menu carefully. Extract EVERY product.

## Table Extraction Logic (Persistence Rules)
1. **Vertical Propagation**: Many columns only list a value in the first row of a section (e.g., PRODUCT TYPE, TIER, or PRICE). You MUST remember the last seen value for these fields and apply it to every subsequent row until a new value or section header appears.
2. **Category is in the HEADINGS above the strain names**: The category is the column or section HEADING (e.g. "FLOWER", "SOLVENTLESS", "EDIBLES") — the text above the list of products, not in each row. Map that heading to category_id using the CATEGORY TRANSLATION RULES and the AVAILABLE CATEGORIES table below. The sub-headers (e.g. "HALF OUNCE/14G", "PERSY BADDER 1G") are the product_type, not the category. **Important:** If the product type or section heading is "SINGLE JOINTS / 1G" (or "SINGLE JOINTS/1G", "SINGLE JOINTS / 1 GRAM", "PREROLL", "JOINTS", "DOINKS", "PRE-ROLL"), the category must be Prerolls (Pre-Rolls), not Flowers.
3. **Brand is often at the top of the page and applies to everything on that page**: If the brand name appears once as a page or section heading (e.g. at the top of the menu), that brand applies to ALL products on that page/section. Use the same brand_id for every product on the page. Match the brand name to the AVAILABLE BRANDS table below and return its brand_id.
4. **Price — use the UNIT column**: The price for each product is in the column named "UNIT". Always take the price value from the UNIT column. Price must be a number for every row; never return null for price. If the same price applies to multiple rows (e.g. only the first row shows it), propagate that value to every row in that section.
5. **Genetic Filtering**: Ignore the "GENETICS" and "%" columns. Do not include their contents in the name field.

You MUST set brand_id and category_id for every row using the AVAILABLE BRANDS and AVAILABLE CATEGORIES tables provided in the prompt. Those tables list every valid brand_id and category_id — pick the numeric ID that matches the menu (category from the section heading, brand from the page/section brand heading). Only use null when the brand or category truly does not appear in the provided tables.

For the name field, provide ONLY the strain name exactly as written on the menu (e.g. "C. Chrome #27", "SB36 #1", "Marshmallow OG + Guava"). Do NOT include the brand name, category, weight, genetics, or percentage in this field.

For the product_type field, provide the menu section or sub-header in proper/title case (e.g. "Half Ounce/14g", "Eighths/3.5g", "Persy Badder 1g", "LR Vape 1g All-In-One"). Do NOT return it in ALL CAPS. Set to null if not identifiable.

## Weight Token Logic
- "HALF OUNCE/14G" or "Half Ounce/14g" → weight_token = "14g"
- "EIGHTHS/3.5G" or "Eighths/3.5g" → weight_token = "3.5g"
- "SINGLE JOINTS/1G" or "Single Joints/1g" → weight_token = "1g"
- "PERSY BADDER 1G", "PERSY ROSIN 1G", "Persy Badder 1g", "Persy Rosin 1g" → weight_token = "1g"
- "2.5G COLD CURE" or "2.5g Cold Cure" → weight_token = "2.5g"
- "PERSY POD .5G" or "Persy Pod .5g" → weight_token = ".5g"
- "LR VAPE 1G ALL-IN-ONE", "All In One", "AIO" → weight_token = "1g"
- "PERSY DOINKS", "2 PERSY DOINKS" → weight_token = "1g"
- Any combined weight like "1.5g F + .5g R" → sum the parts (e.g. 2g total → weight_token = "2g")
- If the item has no weight in its section heading → weight_token = null

**Output format (token-saving):** Return a JSON object with "columns" and "rows" only. Do NOT include brand_name — we look it up from brand_id.
- "columns": exactly ["name", "price", "brand_id", "category_id", "product_type", "weight_token"]
- "rows": array of arrays; each inner array has exactly 6 values in that order. brand_id and category_id must be the numeric IDs from the provided lists (or null only if no match). price must be a number from the UNIT column — never null (use 0 only if the menu has no price).

Example: {"columns":["name","price","brand_id","category_id","product_type","weight_token"],"rows":[["Donny Burger",100,121,7,"Half Ounce/14g","14g"],["C. Chrome #27",100,121,7,"Half Ounce/14g","14g"]]}

Return every menu item. Do not skip any.
SYS;

$p1_prompt_base = "REFERENCE TABLES — use these to fill brand_id and category_id for every row. Return the numeric id from the matching row.\n\n"
    . "AVAILABLE BRANDS (return brand_id from this list):\n{$brands_json}\n\n"
    . "AVAILABLE CATEGORIES (return category_id from this list; map menu section headings using the rules below):\n{$categories_json}\n\n"
    . "CATEGORY TRANSLATION RULES (map menu headings like FLOWER, SOLVENTLESS, EDIBLES to the category names in the table above):\n{$category_translations}\n\n";

if ($extracted_text !== null) {
    $p1_prompt = $p1_prompt_base . "Below is the extracted text from the menu PDF(s). Extract all items.\n\n" . $extracted_text;
} else {
    $p1_prompt = $p1_prompt_base . "[The menu PDF(s) are attached above. Extract all items.]";
}

$content_parts = ($extracted_text !== null)
    ? [['text' => $p1_prompt]]
    : array_merge($pdf_parts, [['text' => $p1_prompt]]);

$p1_payload = [
    'system_instruction' => ['parts' => [['text' => $p1_system_instr]]],
    'contents' => [['parts' => $content_parts]],
    'generationConfig' => [
        'temperature'      => 0.0,
        'maxOutputTokens'  => 32768,
        'responseMimeType' => 'application/json',
        'responseSchema'   => $p1_schema,
    ],
];
$p1_payload_json = json_encode($p1_payload);

// ---- DRY RUN: return Phase 1 payload without calling Gemini ----
if ($is_dry_run) {
    echo json_encode([
        'success'                   => true,
        'dry_run'                   => true,
        'po_id'                     => $po_id,
        'po_code'                   => $po_code,
        'url'                       => $url,
        'pdf_files'                 => $pdf_summary,
        'brands_array'              => $all_brands,
        'categories_array'          => $all_categories,
        'po_products_array'         => $po_products,
        'phase1_system_instruction' => $p1_system_instr,
        'phase1_prompt'             => $p1_prompt,
        'phase1_schema'             => $p1_schema,
        'phase1_payload_size'       => strlen($p1_payload_json),
        'matching'                  => 'PHP (deterministic — no Phase 2 Gemini call)',
        'summary' => [
            'total_products'   => count($po_products),
            'pdf_count'        => count($pdf_summary),
            'pdf_files'        => implode(', ', $pdf_summary),
            'brands_count'     => count($all_brands),
            'categories_count' => count($all_categories),
            'p1_payload_kb'    => round(strlen($p1_payload_json) / 1024, 1),
        ],
    ]);
    exit;
}

// ---- LIVE RUN: Phase 1 — extract menu from PDF ----
$p1_start  = microtime(true);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $p1_payload_json,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$p1_raw      = curl_exec($ch);
$p1_curl_err = curl_error($ch);
$p1_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$p1_elapsed = round(microtime(true) - $p1_start, 3);

// Expand compact columns+rows into array of objects for downstream use
function _po_menu_compact_to_items(array $parsed): array {
    $cols = $parsed['columns'] ?? null;
    $rows = $parsed['rows'] ?? null;
    if (!is_array($cols) || !is_array($rows)) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $obj = [];
        foreach ($cols as $i => $key) {
            $v = array_key_exists($i, $row) ? $row[$i] : null;
            if ($v === null && $key !== 'name') {
                $obj[$key] = null;
            } elseif ($key === 'price') {
                $obj[$key] = is_numeric($v) ? (float) $v : 0;
            } elseif ($key === 'brand_id' || $key === 'category_id') {
                $obj[$key] = ($v !== null && $v !== '') ? (int) $v : null;
            } else {
                $obj[$key] = $v !== null ? (string) $v : null;
            }
        }
        $items[] = $obj;
    }
    return $items;
}

$menu_items = [];
$p1_response_text = $p1_finish_reason = $p1_parse_error = null;
if (!$p1_curl_err && $p1_raw && $p1_http === 200) {
    // Sanitize outer API response so json_decode does not hit control character error
    $p1_raw_clean = preg_replace('/[\x00-\x1F]/', ' ', $p1_raw);
    $p1_data          = json_decode($p1_raw_clean, true);
    $p1_response_text = $p1_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $p1_finish_reason = $p1_data['candidates'][0]['finishReason'] ?? null;
    if ($p1_response_text !== '') {
        // Strip control characters in inner JSON that cause "Control character error"
        $p1_response_text = preg_replace('/[\x00-\x1F]/', ' ', $p1_response_text);
        $p1_parsed = json_decode($p1_response_text, true);
        if (is_array($p1_parsed)) {
            if (!empty($p1_parsed['columns']) && isset($p1_parsed['rows'])) {
                $menu_items = _po_menu_compact_to_items($p1_parsed);
            } elseif (!empty($p1_parsed['menu_items'])) {
                $menu_items = $p1_parsed['menu_items'];
            }
        }
        if (empty($menu_items)) {
            $p1_parse_error = json_last_error_msg() ?: 'Missing columns/rows or menu_items';
            // Salvage truncated JSON: compact format — find last complete row and close
            $trimmed = trim($p1_response_text);
            if (preg_match('/"rows"\s*:\s*\[/', $trimmed) && !preg_match('/\]\s*]\s*}\s*$/', $trimmed)) {
                $last_row_end = strrpos($trimmed, '],');
                if ($last_row_end !== false) {
                    $salvage = substr($trimmed, 0, $last_row_end + 1) . ']]}';
                    $p1_parsed = json_decode($salvage, true);
                    if (is_array($p1_parsed) && !empty($p1_parsed['columns']) && isset($p1_parsed['rows'])) {
                        $menu_items = _po_menu_compact_to_items($p1_parsed);
                        if (!empty($menu_items)) {
                            $p1_parse_error = null;
                        }
                    }
                }
            }
            if (empty($menu_items) && preg_match('/"menu_items"\s*:\s*\[/', $trimmed)) {
                $last_brace_comma = strrpos($trimmed, '},');
                if ($last_brace_comma !== false) {
                    $salvage = substr($trimmed, 0, $last_brace_comma + 1) . ']}';
                    $p1_parsed = json_decode($salvage, true);
                    if (is_array($p1_parsed) && !empty($p1_parsed['menu_items'])) {
                        $menu_items = $p1_parsed['menu_items'];
                        $p1_parse_error = null;
                    }
                }
            }
        }
        // Fill brand_name from brand_id (we no longer ask Gemini for brand_name to save tokens)
        $brand_id_to_name = array_column($all_brands, 'name', 'brand_id');
        foreach ($menu_items as &$item) {
            if (!empty($item['brand_id']) && (string) ($item['brand_name'] ?? '') === '') {
                $item['brand_name'] = $brand_id_to_name[(int) $item['brand_id']] ?? '';
            }
        }
        unset($item);

        // Never accept partial results: if Gemini hit MAX_TOKENS, the menu was truncated and items are missing
        if ($p1_finish_reason === 'MAX_TOKENS' && !empty($menu_items)) {
            $menu_items = [];
            $p1_parse_error = 'Response was truncated (MAX_TOKENS). Some menu items were not returned. No partial results are used.';
        }
    }
}

if (empty($menu_items)) {
    $err = ($p1_parse_error && strpos($p1_parse_error, 'MAX_TOKENS') !== false)
        ? 'Menu extraction stopped because the response was truncated (output limit reached). The full menu did not fit in one response, so no items were applied. Try splitting the menu into smaller PDFs or use a shorter menu.'
        : ('Phase 1 failed: could not extract menu items from PDF.'
            . ($p1_curl_err ? ' cURL: ' . $p1_curl_err : '')
            . ($p1_http !== 200 ? ' HTTP ' . $p1_http : '')
            . ($p1_parse_error ? ' Parse: ' . $p1_parse_error : ''));
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode([
        'status' => 'failed', 'error' => $err,
        'phase1_raw' => $p1_raw, 'phase1_response_text' => $p1_response_text,
    ]), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err,
        'phase1_raw' => $p1_raw, 'phase1_response_text' => $p1_response_text]);
    exit($is_cli ? 1 : 0);
}

// ============================================================
// PHASE 2 (PHP) — Deterministic matching: menu items → PO products
// Rules (all must pass):
//   1. category_id must match exactly
//   2. PO product name must contain weight_token (if not null)
//   3. All stripped strain parts must appear in PO product name
//   4. Combo vs single-strain consistency
//   5. Each po_product_id used at most once (first match wins)
// ============================================================

function _po_strip_strain(string $name): string {
    // Remove brand prefix: "710 Labs", numeric brand ID prefix (e.g. "121 "), Close Friends, x Connected wrappers
    $name = preg_replace('/^(?:710\s+Labs\s+|\d+\s+)(?:\(Close\s+Friends\)\s+)?(?:x\s+Connected\s+)?/iu', '', $name);
    // Remove (I/H), (S/H), (H), (S), (I) type qualifiers
    $name = preg_replace('/\([A-Z\/]+\)/iu', ' ', $name);
    // Remove product-type descriptor words
    $name = preg_replace('/\b(?:flower|persy|live\s+rosin|infused|hash\s+rosin|rosin|badder|sauce|pod|battery|prerolls?|pre-rolls?|aio|vape\s+carts?|solventless\s+extracts?|edibles?|gummies?|tinctures?|rso|water\s+hash|thumbprint)\b/iu', ' ', $name);
    // Remove weight tokens (handle ".5g" separately — no word boundary before the dot)
    $name = preg_replace('/(?<![a-z0-9])\.?\d+(?:\.\d+)?g\b|\b\d+mg\b|\b\d+pk\b/iu', ' ', $name);
    return strtolower(preg_replace('/\s+/u', ' ', trim($name)));
}

$matched_pairs   = [];   // [{menu_item_index, po_product_id, menu_name, po_name}]
$unmatched_items = [];   // menu items with no matching PO product
$used_po_ids     = [];   // enforce uniqueness: each po_product_id used at most once

foreach ($menu_items as $idx => $item) {
    $menu_cat      = isset($item['category_id']) ? (int) $item['category_id'] : null;
    $wt            = $item['weight_token'] ?? null;
    $menu_core     = _po_strip_strain($item['name']);
    $menu_is_combo = str_contains($menu_core, '+');
    $strain_parts  = array_filter(
        array_map('trim', preg_split('/\s*\+\s*/u', $menu_core)),
        fn($p) => strlen($p) >= 2
    );

    $best = null;
    foreach ($po_products as $po) {
        $ppid = (int) $po['po_product_id'];
        if (isset($used_po_ids[$ppid])) { continue; }

        // 1. Category
        if ($menu_cat !== null && (int) $po['category_id'] !== $menu_cat) { continue; }

        // 2. Weight token
        if ($wt !== null && stripos($po['product_name'], $wt) === false) { continue; }

        // 3. Combo consistency
        $po_core     = _po_strip_strain($po['product_name']);
        $po_is_combo = str_contains($po_core, '+');
        if ($menu_is_combo !== $po_is_combo) { continue; }

        // 4. All strain parts must appear in the original PO product name
        $all_match = true;
        foreach ($strain_parts as $strain) {
            if (stripos($po['product_name'], $strain) === false) {
                $all_match = false;
                break;
            }
        }
        if (!$all_match) { continue; }

        $best = $po;
        break; // first product that passes all checks is the match
    }

    if ($best) {
        $ppid = (int) $best['po_product_id'];
        $matched_pairs[] = [
            'menu_item_index' => $idx,
            'po_product_id'   => $ppid,
            'menu_name'       => $item['name'],
            'po_name'         => $best['product_name'],
            'weight_token'    => $wt,
            'category_id'     => $menu_cat,
        ];
        $used_po_ids[$ppid] = true;
    } else {
        $unmatched_items[] = array_merge(['index' => $idx], $item);
    }
}

// ---- Derive final sets ----
$found_ids   = array_values(array_unique(array_map(fn($p) => (int) $p['po_product_id'], $matched_pairs)));
$found_set   = array_flip($found_ids);
$all_po_ids  = array_column($po_products, 'po_product_id');
$disable_ids = array_values(array_filter($all_po_ids, fn($id) => !isset($found_set[$id])));

// add_products = unmatched menu items (become new custom products)
$add_products = array_map(fn($item) => [
    'name'         => $item['name'],           // strain name only (AI-provided)
    'brand_name'   => $item['brand_name'] ?? null,
    'product_type' => $item['product_type'] ?? null,
    'price'        => $item['price'] ?? 0,
    'brand_id'     => $item['brand_id'] ?? null,
    'category_id'  => $item['category_id'] ?? null,
    'weight_token' => $item['weight_token'] ?? null,
], $unmatched_items);

$total_elapsed = round(microtime(true) - $p1_start, 3);

$result = [
    'success'              => true,
    'status'               => 'completed',
    'po_id'                => $po_id,
    'po_code'              => $po_code,
    'started_at'           => date('Y-m-d H:i:s', (int) $p1_start),
    'finished_at'          => date('Y-m-d H:i:s'),
    'duration_s'           => $total_elapsed,
    // Phase 1 (Gemini)
    'phase1_http'          => $p1_http,
    'phase1_curl_error'    => $p1_curl_err ?: null,
    'phase1_elapsed_s'     => $p1_elapsed,
    'phase1_finish_reason' => $p1_finish_reason,
    'phase1_response_text' => $p1_response_text,
    'phase1_raw_response'  => $p1_raw ?? '',
    'phase1_menu_items'    => $menu_items,
    // PHP matching results
    'php_matched_pairs'    => $matched_pairs,
    'php_unmatched_items'  => $unmatched_items,
    // Final results
    'parsed_found_ids'     => $found_ids,
    'parsed_disable_ids'   => $disable_ids,
    'parsed_add_products'  => $add_products,
    // Prompts (for "View last result" debugging)
    'phase1_system_instr'  => $p1_system_instr,
    'phase1_prompt'        => $p1_prompt,
    // Context
    'parse_pdf_locally'    => $parse_pdf_locally,
    'pdf_files'            => $pdf_summary,
    'brands_array'         => $all_brands,
    'categories_array'     => $all_categories,
    'po_products_sent'     => $po_products,
    'summary' => [
        'total_po_products'    => count($po_products),
        'menu_items_extracted' => count($menu_items),
        'php_matched_pairs'    => count($matched_pairs),
        'php_unmatched_items'  => count($unmatched_items),
        'total_found_ids'      => count($found_ids),
        'total_disable_ids'    => count($disable_ids),
        'total_add_products'   => count($add_products),
        'phase1_elapsed_s'     => $p1_elapsed,
        'total_duration_s'     => $total_elapsed,
        'p1_payload_kb'        => round(strlen($p1_payload_json) / 1024, 1),
        'p1_model'             => $p1_model,
        'pdf_count'            => count($pdf_summary),
        'pdf_files'            => implode(', ', $pdf_summary),
    ],
];

if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
@file_put_contents($result_file, json_encode($result), LOCK_EX);

if (!$is_cli) {
    echo json_encode($result);
}
exit(0);
