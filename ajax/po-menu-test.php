<?php
/**
 * TEST/diagnostic endpoint: single Gemini call for full PO reconciliation.
 * No batching — all PO products sent in one request. No DB writes — diagnostic only.
 * Modes:
 *   dry_run=1  → build prompt/payload, return without calling Gemini
 *   run=1      → call Gemini synchronously, return verbose result
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

$is_dry_run = !$is_cli && !empty($_POST['dry_run']);
$is_run     = $is_cli || !$is_dry_run; // all non-dry-run HTTP also do a real run

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

// Brands present on this PO
$seen_brand_ids = [];
$all_brands = [];
foreach ($po_products as $p) {
    if ($p['brand_id'] && !isset($seen_brand_ids[$p['brand_id']])) {
        $seen_brand_ids[$p['brand_id']] = true;
        $all_brands[] = ['brand_id' => $p['brand_id'], 'name' => $p['brand_name']];
    }
}
usort($all_brands, fn($a, $b) => strcmp($a['name'], $b['name']));

// All active/enabled categories from the store DB
$all_categories = [];
foreach (getRs(
    "SELECT category_id, name FROM {$db}.category WHERE is_active = 1 AND is_enabled = 1 ORDER BY name",
    []
) as $r) {
    $all_categories[] = ['category_id' => (int) $r['category_id'], 'name' => (string) $r['name']];
}

// Load PDFs as base64 inline parts for Gemini (native PDF understanding)
$pdf_parts   = [];
$pdf_summary = [];
foreach ($pdf_paths as $path) {
    $bytes = @file_get_contents($path);
    if ($bytes !== false && strlen($bytes) > 0) {
        $pdf_parts[]   = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode($bytes)]];
        $pdf_summary[] = basename($path) . ' (' . round(strlen($bytes) / 1024, 1) . ' KB)';
    }
}
if (empty($pdf_parts)) {
    $err = 'Could not read any PDF files.';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode(['status' => 'failed', 'error' => $err]), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err]);
    exit($is_cli ? 1 : 0);
}

$apiKey   = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
// Phase 1 (PDF extraction): standard flash model
$p1_model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
$url      = 'https://generativelanguage.googleapis.com/v1beta/models/' . $p1_model . ':generateContent?key=' . urlencode($apiKey);
// Phase 2 (matching): gemini-2.0-flash with strict CoT prompt + structured output
$p2_model             = (defined('GEMINI_PO_MENU_MATCH_MODEL') && GEMINI_PO_MENU_MATCH_MODEL !== '') ? GEMINI_PO_MENU_MATCH_MODEL : 'gemini-2.0-flash';
$p2_url               = 'https://generativelanguage.googleapis.com/v1beta/models/' . $p2_model . ':generateContent?key=' . urlencode($apiKey);
$p2_is_old_thinking   = (stripos($p2_model, 'thinking-exp') !== false);
$p2_use_thinking_cfg  = false;
$p2_is_thinking       = $p2_is_old_thinking;

// ---- Shared data ----
$brands_json     = json_encode($all_brands,     JSON_UNESCAPED_UNICODE);
$categories_json = json_encode($all_categories, JSON_UNESCAPED_UNICODE);
$po_products_json = json_encode($po_products,   JSON_UNESCAPED_UNICODE);

$category_translations = <<<TRANS
- "PERSY BADDER","PERSY ROSIN","LIVE ROSIN","THUMB PRINT","SAUCE","BADDER","ROSIN" → "Solventless Extracts"
- "PERSY POD / .5G","PERSY POD","SOLVENTLESS PODS" → "Vape Carts .5g"
- "ALL IN ONE LIVE ROSIN VAPE 1G","ALL IN ONE","AIO" → "AIO"
- "FLOWER","EIGHTHS / 3.5 GRAMS","HALF OUNCE / 14 GRAMS","EIGHTHS","HALF OUNCE","3.5G","14G" → "Flowers"
- "GUMMIS","EDIBLES","HASH ROSIN GUMMIS" → "Edibles"
- "SINGLE JOINTS / 1 GRAM","PREROLL","JOINTS","DOINKS","PRE-ROLL" → "Pre-Rolls"
TRANS;

$weight_matching_rules = <<<WMATCH
WEIGHT MATCHING RULES — a PO product is only a match if its name contains the required weight token (case-insensitive):
- Menu section "EIGHTHS / 3.5 GRAMS" or any "3.5g" section → PO product name MUST contain "3.5g"
- Menu section "HALF OUNCE / 14 GRAMS" or any "14g" section → PO product name MUST contain "14g"
- Menu section "SINGLE JOINTS / 1 GRAM" or "1g" preroll → PO product name MUST contain "1g"
- Menu section "ALL IN ONE LIVE ROSIN VAPE 1G" → PO product name MUST contain "1g" (AIO category)
- Menu section "PERSY POD / .5G" → PO product name MUST contain ".5g" (Vape Carts .5g category)
WMATCH;

// ============================================================
// PHASE 1 — Extract menu items from PDF
// ============================================================
$p1_schema = [
    'type' => 'OBJECT',
    'properties' => [
        'menu_items' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'name'         => ['type' => 'STRING'],
                    'price'        => ['type' => 'NUMBER'],
                    'brand_id'     => ['type' => 'INTEGER', 'nullable' => true],
                    'category_id'  => ['type' => 'INTEGER', 'nullable' => true],
                    'weight_token' => ['type' => 'STRING', 'nullable' => true],
                ],
                'required' => ['name', 'price'],
            ],
        ],
    ],
    'required' => ['menu_items'],
];

$p1_system_instr = <<<SYS
You are a cannabis dispensary menu extraction assistant.

Read the attached PDF menu carefully. Extract EVERY product. For each product:
- Identify the strain name exactly as written
- Identify the section heading (product type)
- Record the price

Then map each product to brand_id (from AVAILABLE BRANDS) and category_id (from AVAILABLE CATEGORIES) using the CATEGORY TRANSLATION RULES.

For the name field, concatenate as: "{Brand Name} {Strain Name} {Menu Category} {Weight}" (e.g. "710 Labs C. Chrome #27 Flower 3.5g"). Include weight only if shown.

For the weight_token field, extract the canonical weight abbreviation from the menu section heading:
- "Eighths / 3.5 Grams", "3.5G", or any 3.5g flower section → weight_token = "3.5g"
- "Half Ounce / 14 Grams", "14G", or any 14g section → weight_token = "14g"
- "Single Joints / 1 Gram", "DOINKS", "PRE-ROLL" or any 1g preroll section → weight_token = "1g"
- "ALL IN ONE LIVE ROSIN VAPE 1G", "AIO", "ALL IN ONE" → weight_token = "1g"  ← AIO is always 1g
- "PERSY POD / .5G", "SOLVENTLESS PODS .5G" or any .5g vape/pod section → weight_token = ".5g"
- If the item has no weight in its section heading → weight_token = null

Return every menu item. Do not skip any.
SYS;

$p1_prompt = "AVAILABLE BRANDS (use brand_id in your response, or null):\n{$brands_json}"
    . "\n\nAVAILABLE CATEGORIES (use category_id in your response, or null):\n{$categories_json}"
    . "\n\nCATEGORY TRANSLATION RULES:\n{$category_translations}"
    . "\n\n[The menu PDF(s) are attached above. Extract all items.]";

$p1_payload = [
    'system_instruction' => ['parts' => [['text' => $p1_system_instr]]],
    'contents' => [['parts' => array_merge($pdf_parts, [['text' => $p1_prompt]])]],
    'generationConfig' => [
        'temperature'      => 0.0,
        'maxOutputTokens'  => 8192,
        'responseMimeType' => 'application/json',
        'responseSchema'   => $p1_schema,
    ],
];
$p1_payload_json = json_encode($p1_payload);

// ============================================================
// PHASE 2 — Match menu items to PO products
// Output: matched_pairs indexed by menu_item_index (immune to name drift between phases)
// PHP derives: found_ids, disable_ids, add_products
// ============================================================
$p2_schema = [
    'type' => 'OBJECT',
    'properties' => [
        'matched_pairs' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'menu_item_index' => ['type' => 'INTEGER'],
                    'po_product_id'   => ['type' => 'INTEGER'],
                ],
                'required' => ['menu_item_index', 'po_product_id'],
            ],
        ],
    ],
    'required' => ['matched_pairs'],
];

$p2_system_instr = <<<SYS
You are a cannabis dispensary PO reconciler. For each menu item, follow this exact 4-step protocol before returning any match.

STRICT MATCHING PROTOCOL — execute these steps IN ORDER for every menu item:

STEP 1 — IDENTIFY CORE STRAIN
  Extract the unique strain name and number from the menu item name.
  Strip the brand prefix (e.g. "710 Labs") and ignore descriptor words: Flower, Persy, Live Rosin, (I/H), (S), (I), (H).
  The core identifier is what remains (e.g. "SB36 #1", "Ztan Lee #5", "Sherb Fumez #14", "Super Freak").

STEP 2 — FILTER PO LIST BY STRAIN
  Search the PO PRODUCTS list for entries whose product_name contains that EXACT core strain identifier (case-insensitive).
  If ZERO PO products contain the strain identifier → return NO MATCH for this menu item. Stop here.

STEP 3 — VALIDATE WEIGHT
  From the filtered list in Step 2, check the weight_token (from the menu item).
  If weight_token is not null, the PO product name MUST contain that exact token (e.g. "14g", "3.5g", "1g", ".5g").
  If no filtered product passes the weight check → return NO MATCH. Stop here.

STEP 4 — VALIDATE CATEGORY
  The menu item's category_id must equal the PO product's category_id.
  If no match remains after category check → return NO MATCH.

FORBIDDEN SUBSTITUTIONS — these are always wrong:
  ✗ Matching "Sherb Fumez #14" to any product that does not contain "Sherb Fumez" in its name
  ✗ Matching "SB36 #1 Flower 14g" to a product with "SB36 #1" in a different category (e.g. AIO)
  ✗ Matching "Super Freak Flower 14g" to a "Super Freak" product whose name contains "3.5g"
  ✗ Substituting one strain for another because they share the same weight and category

UNIQUENESS: Each po_product_id may appear at most once in matched_pairs. If two menu items pass all steps for the same PO product, keep only the better match; leave the other as NO MATCH (it becomes a new custom product).

DEFAULT: When in doubt → NO MATCH. An unmatched menu item safely becomes a new custom product. A wrong match corrupts the PO.

Return matched_pairs — one entry per menu item that passed all four steps:
  - menu_item_index: the "index" value from the menu item
  - po_product_id: the po_product_id of the matched PO product
SYS;

// p2_prompt is built dynamically after Phase 1 completes (uses $indexed_menu_items_json)
// Stored here as a template; placeholder filled after Phase 1.
$p2_prompt_template = "WEIGHT MATCHING RULES (reference only — the weight_token field in each menu item is the canonical source):\n{$weight_matching_rules}"
    . "\n\n--- MENU ITEMS (each has an \"index\" and \"weight_token\" — use both in matching) ---\n{{MENU_ITEMS_JSON}}"
    . "\n\n--- PO PRODUCTS ---\n{$po_products_json}";

// ---- DRY RUN: return both payloads without calling Gemini ----
if ($is_dry_run) {
    $p2_payload_preview = [
        'system_instruction' => ['parts' => [['text' => $p2_system_instr]]],
        'contents' => [['parts' => [['text' => str_replace('{{MENU_ITEMS_JSON}}', '(indexed items populated after Phase 1)', $p2_prompt_template)]]]],
        'generationConfig' => [
            'temperature'      => 0.0,
            'maxOutputTokens'  => 4096,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $p2_schema,
        ],
    ];
    echo json_encode([
        'success'                  => true,
        'dry_run'                  => true,
        'po_id'                    => $po_id,
        'po_code'                  => $po_code,
        'url'                      => $url,
        'pdf_files'                => $pdf_summary,
        'brands_array'             => $all_brands,
        'categories_array'         => $all_categories,
        'po_products_array'        => $po_products,
        'phase1_system_instruction'=> $p1_system_instr,
        'phase1_prompt'            => $p1_prompt,
        'phase1_schema'            => $p1_schema,
        'phase1_payload_size'      => strlen($p1_payload_json),
        'phase2_system_instruction'=> $p2_system_instr,
        'phase2_prompt_template'   => $p2_prompt_template,
        'phase2_schema'            => $p2_schema,
        'phase2_payload_preview'   => $p2_payload_preview,
        'summary' => [
            'total_products'   => count($po_products),
            'pdf_count'        => count($pdf_parts),
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

$menu_items = [];
$p1_response_text = $p1_finish_reason = $p1_parse_error = null;
if (!$p1_curl_err && $p1_raw && $p1_http === 200) {
    $p1_data          = json_decode($p1_raw, true);
    $p1_response_text = $p1_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $p1_finish_reason = $p1_data['candidates'][0]['finishReason'] ?? null;
    if ($p1_response_text !== '') {
        $p1_parsed = json_decode($p1_response_text, true);
        if (is_array($p1_parsed) && !empty($p1_parsed['menu_items'])) {
            $menu_items = $p1_parsed['menu_items'];
        } else {
            $p1_parse_error = json_last_error_msg();
        }
    }
}

if (empty($menu_items)) {
    $err = 'Phase 1 failed: could not extract menu items from PDF.'
        . ($p1_curl_err ? ' cURL: ' . $p1_curl_err : '')
        . ($p1_http !== 200 ? ' HTTP ' . $p1_http : '')
        . ($p1_parse_error ? ' Parse: ' . $p1_parse_error : '');
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode([
        'status' => 'failed', 'error' => $err,
        'phase1_raw' => $p1_raw, 'phase1_response_text' => $p1_response_text,
    ]), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err,
        'phase1_raw' => $p1_raw, 'phase1_response_text' => $p1_response_text]);
    exit($is_cli ? 1 : 0);
}

// ---- Phase 2 — match menu items to PO products ----
// Add numeric index to each menu item so Phase 2 can reference by index (not name)
$indexed_menu_items = array_map(
    fn($item, $idx) => array_merge(['index' => $idx], $item),
    $menu_items,
    array_keys($menu_items)
);
$indexed_menu_items_json = json_encode($indexed_menu_items, JSON_UNESCAPED_UNICODE);
$p2_prompt = str_replace('{{MENU_ITEMS_JSON}}', $indexed_menu_items_json, $p2_prompt_template);

// Old experimental thinking models don't support structured output — add JSON instruction to prompt instead
$p2_prompt_final = $p2_prompt;
if ($p2_is_old_thinking) {
    $p2_prompt_final .= "\n\nIMPORTANT: Respond with a single valid JSON object only. No markdown, no code fences, no explanation text. Format:\n{\"matched_pairs\":[{\"menu_item_index\":0,\"po_product_id\":12345},...]}\nIf nothing matches, return {\"matched_pairs\":[]}";
}

// 2.5-flash supports thinking AND structured output simultaneously
$p2_gen_config = ['maxOutputTokens' => 16000];
if (!$p2_is_old_thinking) {
    $p2_gen_config['temperature']      = 0.0;
    $p2_gen_config['responseMimeType'] = 'application/json';
    $p2_gen_config['responseSchema']   = $p2_schema;
}

$p2_payload = [
    'system_instruction' => ['parts' => [['text' => $p2_system_instr]]],
    'contents' => [['parts' => [['text' => $p2_prompt_final]]]],
    'generationConfig' => $p2_gen_config,
];
$p2_payload_json = json_encode($p2_payload);

$p2_start = microtime(true);
$ch = curl_init($p2_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $p2_payload_json,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 300,  // thinking model needs more time
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$p2_raw      = curl_exec($ch);
$p2_curl_err = curl_error($ch);
$p2_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If model not found (404), fall back to standard flash with structured output
if ($p2_http === 404) {
    $p2_model            = $p1_model;
    $p2_url              = 'https://generativelanguage.googleapis.com/v1beta/models/' . $p2_model . ':generateContent?key=' . urlencode($apiKey);
    $p2_is_old_thinking  = false;
    $p2_use_thinking_cfg = false;
    $p2_is_thinking      = false;
    $p2_payload['generationConfig'] = [
        'temperature'      => 0.0,
        'maxOutputTokens'  => 4096,
        'responseMimeType' => 'application/json',
        'responseSchema'   => $p2_schema,
    ];
    $p2_payload['contents'][0]['parts'][0]['text'] = $p2_prompt;
    $p2_payload_json = json_encode($p2_payload);
    $ch = curl_init($p2_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $p2_payload_json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $p2_raw      = curl_exec($ch);
    $p2_curl_err = curl_error($ch);
    $p2_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

$p2_elapsed = round(microtime(true) - $p2_start, 3);

$matched_pairs = [];
$p2_response_text = $p2_finish_reason = $p2_parse_error = null;
if (!$p2_curl_err && $p2_raw && $p2_http === 200) {
    $p2_data          = json_decode($p2_raw, true);
    $p2_finish_reason = $p2_data['candidates'][0]['finishReason'] ?? null;
    // Thinking models emit thought parts (thought:true) before the answer — skip them
    $p2_response_text = '';
    foreach ($p2_data['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (!($part['thought'] ?? false)) {
            $p2_response_text .= $part['text'] ?? '';
        }
    }
    if ($p2_response_text !== '') {
        // Strip markdown code fences if present (thinking models sometimes add them)
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($p2_response_text));
        $clean = preg_replace('/\s*```\s*$/', '', $clean);
        $p2_parsed = json_decode($clean, true);
        if (is_array($p2_parsed)) {
            $matched_pairs = $p2_parsed['matched_pairs'] ?? [];
        } else {
            $p2_parse_error = json_last_error_msg() . ' (cleaned text: ' . substr($clean, 0, 200) . ')';
        }
    }
}

// ---- PHP-side verification of Gemini's matched_pairs ----
// Gemini sometimes matches by category+weight alone — PHP enforces strain name, category, and weight.
function _po_strip_strain(string $name): string {
    // Remove brand prefix: "710 Labs", numeric brand ID (e.g. "121 "), Close Friends, x Connected wrappers
    $name = preg_replace('/^(?:710\s+Labs\s+|\d+\s+)(?:\(Close\s+Friends\)\s+)?(?:x\s+Connected\s+)?/iu', '', $name);
    // Remove (I/H), (S/H), (H), (S), (I) type qualifiers
    $name = preg_replace('/\([A-Z\/]+\)/iu', ' ', $name);
    // Remove product-type descriptor words
    $name = preg_replace('/\b(?:flower|persy|live\s+rosin|infused|hash\s+rosin|rosin|badder|sauce|pod|battery|prerolls?|pre-rolls?|aio|vape\s+carts?|solventless\s+extracts?|edibles?|gummies?|tinctures?|rso|water\s+hash|thumbprint)\b/iu', ' ', $name);
    // Remove weight tokens — handle ".5g" separately (no leading \b before the dot)
    $name = preg_replace('/(?<![a-z0-9])\.?\d+(?:\.\d+)?g\b|\b\d+mg\b|\b\d+pk\b/iu', ' ', $name);
    return strtolower(preg_replace('/\s+/u', ' ', trim($name)));
}

$po_by_id = array_column($po_products, null, 'po_product_id');

$verified_pairs  = [];
$rejected_pairs  = [];

foreach ($matched_pairs as $pair) {
    $idx   = (int)($pair['menu_item_index'] ?? -1);
    $po_id = (int)($pair['po_product_id']   ?? 0);

    $menu_item  = $menu_items[$idx]     ?? null;
    $po_product = $po_by_id[$po_id]     ?? null;

    if (!$menu_item || !$po_product) {
        $rejected_pairs[] = $pair + ['reject_reason' => 'not_found'];
        continue;
    }

    // 1. Category check
    if ((int)$menu_item['category_id'] !== (int)$po_product['category_id']) {
        $rejected_pairs[] = $pair + ['reject_reason' => 'category_mismatch',
            'menu_cat' => $menu_item['category_id'], 'po_cat' => $po_product['category_id']];
        continue;
    }

    // 2. Weight token check
    $wt = $menu_item['weight_token'] ?? null;
    if ($wt !== null && stripos($po_product['product_name'], $wt) === false) {
        $rejected_pairs[] = $pair + ['reject_reason' => 'weight_mismatch',
            'weight_token' => $wt, 'po_name' => $po_product['product_name']];
        continue;
    }

    // 3. Strain name check
    $menu_core = _po_strip_strain($menu_item['name']);
    $po_core   = _po_strip_strain($po_product['product_name']);

    // Single-strain menu item must not match a combo PO product (and vice versa)
    $menu_is_combo = str_contains($menu_core, '+');
    $po_is_combo   = str_contains($po_core,   '+');
    if ($menu_is_combo !== $po_is_combo) {
        $rejected_pairs[] = $pair + ['reject_reason' => 'combo_mismatch',
            'menu_core' => $menu_core, 'po_core' => $po_core];
        continue;
    }

    // Split on " + " — each part from the menu must appear in the PO name
    $parts = array_filter(array_map('trim', preg_split('/\s*\+\s*/u', $menu_core)), fn($p) => strlen($p) >= 2);

    $strain_ok = true;
    foreach ($parts as $strain) {
        if (stripos($po_product['product_name'], $strain) === false) {
            $strain_ok = false;
            break;
        }
    }
    if (!$strain_ok) {
        $rejected_pairs[] = $pair + ['reject_reason' => 'strain_mismatch',
            'menu_strains' => implode(' + ', $parts), 'po_name' => $po_product['product_name']];
        continue;
    }

    $verified_pairs[] = $pair;
}

// ---- Derive final sets (using PHP-verified pairs only) ----
$found_ids_raw = array_column($verified_pairs, 'po_product_id');
$found_ids     = array_values(array_unique(array_map('intval', $found_ids_raw)));
$found_set     = array_flip($found_ids);
$all_po_ids    = array_column($po_products, 'po_product_id');
$disable_ids   = array_values(array_filter($all_po_ids, fn($id) => !isset($found_set[$id])));

// add_products = menu items not in any verified pair
$matched_indices_set = array_flip(array_column($verified_pairs, 'menu_item_index'));
$add_products = [];
foreach ($menu_items as $idx => $item) {
    if (!isset($matched_indices_set[$idx])) {
        $add_products[] = $item;
    }
}

$result = [
    'success'              => true,
    'status'               => 'completed',
    'po_id'                => $po_id,
    'po_code'              => $po_code,
    'started_at'           => date('Y-m-d H:i:s', (int) $p1_start),
    'finished_at'          => date('Y-m-d H:i:s'),
    'duration_s'           => round($p1_elapsed + $p2_elapsed, 3),
    // Phase 1
    'phase1_http'          => $p1_http,
    'phase1_curl_error'    => $p1_curl_err ?: null,
    'phase1_elapsed_s'     => $p1_elapsed,
    'phase1_finish_reason' => $p1_finish_reason,
    'phase1_response_text' => $p1_response_text,
    'phase1_raw_response'  => $p1_raw ?? '',
    'phase1_menu_items'    => $menu_items,
    // Phase 2
    'phase2_http'          => $p2_http,
    'phase2_curl_error'    => $p2_curl_err ?: null,
    'phase2_elapsed_s'     => $p2_elapsed,
    'phase2_finish_reason' => $p2_finish_reason,
    'phase2_response_text' => $p2_response_text,
    'phase2_raw_response'  => $p2_raw ?? '',
    'phase2_matched_pairs'  => $matched_pairs,
    'phase2_parse_error'    => $p2_parse_error,
    'phase2_verified_pairs' => $verified_pairs,
    'phase2_rejected_pairs' => $rejected_pairs,
    // Final results
    'parsed_found_ids'     => $found_ids,
    'parsed_disable_ids'   => $disable_ids,
    'parsed_add_products'  => $add_products,
    // Full prompts (for debugging via "View last result")
    'phase1_system_instr'  => $p1_system_instr,
    'phase1_prompt'        => $p1_prompt,
    'phase2_system_instr'  => $p2_system_instr,
    'phase2_prompt'        => $p2_prompt,
    // Context
    'pdf_files'            => $pdf_summary,
    'brands_array'         => $all_brands,
    'categories_array'     => $all_categories,
    'po_products_sent'     => $po_products,
    'summary' => [
        'total_po_products'    => count($po_products),
        'menu_items_extracted' => count($menu_items),
        'gemini_matched_pairs'  => count($matched_pairs),
        'php_verified_pairs'    => count($verified_pairs),
        'php_rejected_pairs'    => count($rejected_pairs),
        'total_found_ids'       => count($found_ids),
        'total_disable_ids'    => count($disable_ids),
        'total_add_products'   => count($add_products),
        'phase1_elapsed_s'     => $p1_elapsed,
        'phase2_elapsed_s'     => $p2_elapsed,
        'total_duration_s'     => round($p1_elapsed + $p2_elapsed, 3),
        'p1_payload_kb'        => round(strlen($p1_payload_json) / 1024, 1),
        'p2_payload_kb'        => round(strlen($p2_payload_json) / 1024, 1),
        'p1_model'             => $p1_model,
        'p2_model'             => $p2_model,
        'pdf_count'            => count($pdf_parts),
        'pdf_files'            => implode(', ', $pdf_summary),
    ],
];

if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
@file_put_contents($result_file, json_encode($result), LOCK_EX);

if (!$is_cli) {
    echo json_encode($result);
}
exit(0);
