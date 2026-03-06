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

// Extract menu text
$menu_text = '';
foreach ($pdf_paths as $path) {
    $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
    if ($out) { $menu_text .= "--- MENU ---\n" . trim($out) . "\n\n"; }
}
if (!trim($menu_text)) {
    $err = 'pdftotext returned empty. Is it installed?';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode(['status' => 'failed', 'error' => $err]), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err]);
    exit($is_cli ? 1 : 0);
}

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
$model  = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
$url    = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

// ---- Schema ----
$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
        'add_products' => [
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
    'required' => ['found_po_product_ids'],
];

// ---- System instruction ----
$system_instr = <<<SYS
You are a cannabis dispensary PO reconciler. Work through these steps in order:

STEP 1 — DECODE THE MENU
Read the menu text carefully. For each menu item build an internal record:
  - strain name (exact, as shown)
  - product type / category (map using the CATEGORY TRANSLATION RULES provided)
  - price
  - best matching brand_id and category_id from the provided lists

STEP 2 — MATCH PO PRODUCTS
For each PO product in the list, look it up against your Step-1 decoded menu.
Rules:
  • Strip the brand prefix (e.g. "710 Labs") before comparing strain names.
  • A product is FOUND if BOTH the strain name AND the product type/category match a menu item.
  • When in doubt, do NOT mark as found (conservative: keep the item on the PO).

WEIGHT MATCHING RULES (both conditions must be met):
  • Menu items under "Eighths / 3.5 Grams" or similar: the PO product name must contain "3.5g" (case-insensitive) to be a match.
  • Menu items under "Half Ounce / 14 Grams" or similar: the PO product name must contain "14g" (case-insensitive) to be a match.

Return found_po_product_ids — the po_product_id values of PO products that ARE on the menu.

STEP 3 — IDENTIFY NEW MENU ITEMS
List every menu item from Step 1 that has no equivalent in the PO PRODUCTS list.
For each, return:
  - name: concatenate as "{Brand Name} {Strain Name} {Menu Category} {Weight}" (e.g. "710 Labs C. Chrome #27 Flower 3.5g")
  - price, brand_id, category_id
SYS;

// ---- Build prompt ----
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

$prompt = "AVAILABLE BRANDS (use brand_id in your response):\n{$brands_json}"
    . "\n\nAVAILABLE CATEGORIES (use category_id in your response):\n{$categories_json}"
    . "\n\nCATEGORY TRANSLATION RULES:\n{$category_translations}"
    . "\n\n--- STEP 1: DECODE THIS MENU ---\n{$menu_text}"
    . "\n\n--- STEP 2 & 3: ALL PO PRODUCTS ---\n{$po_products_json}";

$payload = [
    'system_instruction' => ['parts' => [['text' => $system_instr]]],
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => [
        'temperature'      => 0.0,
        'maxOutputTokens'  => 8192,
        'responseMimeType' => 'application/json',
        'responseSchema'   => $schema,
    ],
];
$payload_json = json_encode($payload);

// ---- DRY RUN: return payload without calling Gemini ----
if ($is_dry_run) {
    echo json_encode([
        'success'            => true,
        'dry_run'            => true,
        'po_id'              => $po_id,
        'po_code'            => $po_code,
        'url'                => $url,
        'payload_size'       => strlen($payload_json),
        'system_instruction' => $system_instr,
        'brands_array'       => $all_brands,
        'categories_array'   => $all_categories,
        'po_products_array'  => $po_products,
        'menu_text'          => $menu_text,
        'schema'             => $schema,
        'prompt'             => $prompt,
        'full_payload_json'  => $payload_json,
        'summary' => [
            'total_products'      => count($po_products),
            'payload_kb'          => round(strlen($payload_json) / 1024, 1),
            'menu_text_chars'     => strlen($menu_text),
            'brands_count'        => count($all_brands),
            'categories_count'    => count($all_categories),
        ],
    ]);
    exit;
}

// ---- LIVE RUN: send to Gemini ----
$start = microtime(true);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload_json,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$raw      = curl_exec($ch);
$curl_err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$elapsed = round(microtime(true) - $start, 3);

$parsed_found = $parsed_add = [];
$finish_reason = $parse_error = null;
$response_text = '';
if (!$curl_err && $raw && $http_code === 200) {
    $res_data      = json_decode($raw, true);
    $response_text = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $finish_reason = $res_data['candidates'][0]['finishReason'] ?? null;
    if ($response_text !== '') {
        $data = json_decode($response_text, true);
        if (is_array($data)) {
            $parsed_found = array_map('intval', $data['found_po_product_ids'] ?? []);
            $parsed_add   = $data['add_products'] ?? [];
        } else {
            $parse_error = json_last_error_msg();
        }
    }
}

$all_po_ids  = array_column($po_products, 'po_product_id');
$found_set   = array_flip($parsed_found);
$disable_ids = array_values(array_filter($all_po_ids, fn($id) => !isset($found_set[$id])));

$result = [
    'success'             => true,
    'status'              => 'completed',
    'po_id'               => $po_id,
    'po_code'             => $po_code,
    'started_at'          => date('Y-m-d H:i:s', (int) $start),
    'finished_at'         => date('Y-m-d H:i:s'),
    'duration_s'          => $elapsed,
    'http_status'         => $http_code,
    'curl_error'          => $curl_err ?: null,
    'payload_size'        => strlen($payload_json),
    'finish_reason'       => $finish_reason,
    'response_size'       => strlen($raw ?? ''),
    'raw_response'        => $raw ?? '',
    'response_text'       => $response_text,
    'parse_error'         => $parse_error,
    'parsed_found_ids'    => $parsed_found,
    'parsed_disable_ids'  => $disable_ids,
    'parsed_add_products' => $parsed_add,
    'system_instruction'  => $system_instr,
    'brands_array'        => $all_brands,
    'categories_array'    => $all_categories,
    'po_products_sent'    => $po_products,
    'menu_text'           => $menu_text,
    'schema'              => $schema,
    'prompt'              => $prompt,
    'summary' => [
        'total_products'      => count($po_products),
        'total_found_ids'     => count($parsed_found),
        'total_disable_ids'   => count($disable_ids),
        'total_add_products'  => count($parsed_add),
        'duration_s'          => $elapsed,
        'payload_kb'          => round(strlen($payload_json) / 1024, 1),
        'menu_text_chars'     => strlen($menu_text),
    ],
];

if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
@file_put_contents($result_file, json_encode($result), LOCK_EX);

if (!$is_cli) {
    echo json_encode($result);
}
exit(0);
