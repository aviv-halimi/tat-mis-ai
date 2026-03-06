<?php
/**
 * TEST/diagnostic endpoint: CLI parallel Gemini batches with FULL verbose logging.
 * Gemini does ALL matching (PO products ↔ menu). No DB writes – diagnostic only.
 * HTTP POST async=1 → spawns CLI background. CLI → runs batches, writes result JSON.
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

$log_dir  = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
$result_file = $log_dir . '/po-menu-test-' . $po_id . '.json';

// ---- HTTP dry-run: build payloads but do NOT call Gemini ----
$is_dry_run = !$is_cli && !empty($_POST['dry_run']);

// ---- HTTP async: spawn CLI and return immediately ----
if (!$is_cli && !$is_dry_run && !empty($_POST['async'])) {
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode(['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]), LOCK_EX);
    $php  = defined('INVOICE_VALIDATE_PHP_CLI') ? INVOICE_VALIDATE_PHP_CLI : 'php';
    $script = realpath(__DIR__ . '/po-menu-test.php') ?: __DIR__ . '/po-menu-test.php';
    $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' ' . $po_id;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen('start /B ' . $cmd, 'r'));
    } else {
        exec($cmd . ' > /dev/null 2>&1 &');
    }
    echo json_encode(['success' => true, 'started' => true, 'po_id' => $po_id]);
    exit;
}

// ---- CLI or synchronous: run the test ----
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

// Only the brands and categories present on this PO (keeps Gemini prompt small)
$seen_brand_ids = $seen_cat_ids = [];
$all_brands = $all_categories = [];
foreach ($po_products as $p) {
    if ($p['brand_id'] && !isset($seen_brand_ids[$p['brand_id']])) {
        $seen_brand_ids[$p['brand_id']] = true;
        $all_brands[] = ['brand_id' => $p['brand_id'], 'name' => $p['brand_name']];
    }
    if ($p['category_id'] && !isset($seen_cat_ids[$p['category_id']])) {
        $seen_cat_ids[$p['category_id']] = true;
        $all_categories[] = ['category_id' => $p['category_id'], 'name' => $p['category_name']];
    }
}
// Sort for readability
usort($all_brands,     fn($a, $b) => strcmp($a['name'],     $b['name']));
usort($all_categories, fn($a, $b) => strcmp($a['name'], $b['name']));

// Extract menu text from PDFs once; send as text in every batch prompt
$menu_text = '';
foreach ($pdf_paths as $path) {
    $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
    if ($out) { $menu_text .= "--- MENU ---\n" . trim($out) . "\n\n"; }
}
if (!trim($menu_text)) {
    $err = ['status' => 'failed', 'error' => 'pdftotext returned empty. Is it installed?'];
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($result_file, json_encode($err), LOCK_EX);
    if (!$is_cli) echo json_encode(['success' => false, 'error' => $err['error']]);
    exit($is_cli ? 1 : 0);
}

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
$model  = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
$url    = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

$batch_size = 100;
$timeout_s  = 120;

$batches   = array_chunk($po_products, $batch_size);
$n_batches = count($batches);
$concurrency = $n_batches ?: 20; // run all batches fully in parallel

$schema_primary = [
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
$schema_ids_only = [
    'type' => 'OBJECT',
    'properties' => [
        'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
    ],
    'required' => ['found_po_product_ids'],
];

$system_instr = <<<SYS
You are a cannabis dispensary PO reconciler. Work through these steps in order:

STEP 1 — DECODE THE MENU
Read the menu text carefully. For each menu item build an internal record:
  - strain name (exact, as shown)
  - product type / category (map using the CATEGORY TRANSLATION RULES provided)
  - price
  - best matching brand_id and category_id from the provided lists

STEP 2 — MATCH PO PRODUCTS
For each PO product in the batch, look it up against your Step-1 decoded menu.
Rules:
  • Strip the brand prefix (e.g. "710 Labs") before comparing strain names.
  • Strip weight/size (e.g. "3.5g", "1g", "14g") before comparing.
  • A product is FOUND if BOTH the strain name AND the product type/category match a menu item.
  • When in doubt, do NOT mark as found (conservative: keep the item on the PO).
Return found_po_product_ids — the po_product_id values of PO products that ARE on the menu.

STEP 3 — IDENTIFY NEW MENU ITEMS (primary batch only)
List every menu item from Step 1 that has no equivalent anywhere in the FULL PO PRODUCT LIST provided.
For each, return: name (as on menu), price, brand_id, category_id.
SYS;

// Build a shared prompt for any batch. Primary batch also receives all PO names for add_products context.
$all_po_names_json = json_encode(array_column($po_products, 'product_name'), JSON_UNESCAPED_UNICODE);

$brands_json     = json_encode($all_brands,     JSON_UNESCAPED_UNICODE);
$categories_json = json_encode($all_categories, JSON_UNESCAPED_UNICODE);

$category_translations = <<<TRANS
- "PERSY BADDER","PERSY ROSIN","LIVE ROSIN","THUMB PRINT","SAUCE" → "Solventless Extracts"
- "PERSY POD","SOLVENTLESS PODS","ALL IN ONE","AIO" → "Vape Carts .5g" or "AIO"
- "FLOWER","EIGHTHS","HALF OUNCE","3.5G","14G" → "Flowers"
- "GUMMIS","EDIBLES","HASH ROSIN GUMMIS" → "Edibles"
- "PREROLL","JOINTS","DOINKS" → "Pre-Rolls"
TRANS;

/**
 * Build the Gemini prompt for a single batch.
 * Primary batch (idx 0) also includes all PO product names so Gemini can
 * accurately identify add_products (menu items absent from the full PO).
 */
function _build_batch_prompt(
    int    $batch_index,
    int    $n_batches,
    bool   $is_primary,
    string $brands_json,
    string $categories_json,
    string $category_translations,
    string $batch_json,
    string $menu_text,
    string $all_po_names_json
): string {
    $step3 = $is_primary
        ? "\n\n--- STEP 3: FULL PO PRODUCT LIST ---\n"
          . "All {$n_batches} batches together cover these PO product names (for add_products identification only — do NOT use these IDs):\n"
          . $all_po_names_json
        : "\n\n(STEP 3 — add_products: skip, non-primary batch)";

    return "AVAILABLE BRANDS (use brand_id in your response):\n{$brands_json}"
        . "\n\nAVAILABLE CATEGORIES (use category_id in your response):\n{$categories_json}"
        . "\n\nCATEGORY TRANSLATION RULES:\n{$category_translations}"
        . "\n\n--- STEP 1: DECODE THIS MENU ---\n{$menu_text}"
        . "\n\n--- STEP 2: MATCH THESE PO PRODUCTS (Batch " . ($batch_index + 1) . " of {$n_batches}) ---\n{$batch_json}"
        . $step3;
}

// ---- SINGLE BATCH: send exactly one batch to Gemini and return full verbose response ----
$is_single_batch = !$is_cli && !$is_dry_run && !empty($_POST['single_batch']);
if ($is_single_batch) {
    $batch_index = max(0, (int) ($_POST['batch_index'] ?? 0));
    if (!isset($batches[$batch_index])) {
        echo json_encode(['success' => false, 'error' => "Batch index {$batch_index} does not exist (total: {$n_batches})"]);
        exit;
    }
    $is_primary  = ($batch_index === 0);
    $batch       = $batches[$batch_index];
    $schema      = $is_primary ? $schema_primary : $schema_ids_only;
    $batch_json  = json_encode($batch, JSON_UNESCAPED_UNICODE);

    $prompt = _build_batch_prompt(
        $batch_index, $n_batches, $is_primary,
        $brands_json, $categories_json, $category_translations,
        $batch_json, $menu_text, $all_po_names_json
    );

    $payload      = [
        'system_instruction' => ['parts' => [['text' => $system_instr]]],
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'      => 0.0,
            'maxOutputTokens'  => $is_primary ? 8192 : 4096,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $schema,
        ],
    ];
    $payload_json = json_encode($payload);

    $start = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload_json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
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
        $res  = json_decode($raw, true);
        $response_text = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $finish_reason = $res['candidates'][0]['finishReason'] ?? null;
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
    $batch_ids   = array_column($batch, 'po_product_id');
    $found_set   = array_flip($parsed_found);
    $disable_ids = array_values(array_filter($batch_ids, fn($id) => !isset($found_set[$id])));

    echo json_encode([
        'success'            => true,
        'batch_index'        => $batch_index,
        'is_primary'         => $is_primary,
        'product_count'      => count($batch),
        'duration_s'         => $elapsed,
        'http_status'        => $http_code,
        'curl_error'         => $curl_err ?: null,
        'payload_size'       => strlen($payload_json),
        'finish_reason'      => $finish_reason,
        'response_size'      => strlen($raw ?? ''),
        'raw_response'       => $raw ?? '',
        'response_text'      => $response_text,
        'parse_error'        => $parse_error,
        'parsed_found_ids'   => $parsed_found,
        'parsed_disable_ids' => $disable_ids,
        'parsed_add_products'=> $parsed_add,
        'po_products_sent'   => $batch,
        'prompt'             => $prompt,
        'schema'             => $schema,
    ]);
    exit;
}

// ---- DRY RUN: build payloads and return without calling Gemini ----
if ($is_dry_run) {
    $dry_batches = [];
    $total_payload_bytes = 0;
    foreach ($batches as $idx => $batch) {
        $is_primary  = ($idx === 0);
        $batch_json  = json_encode($batch, JSON_UNESCAPED_UNICODE);
        $schema      = $is_primary ? $schema_primary : $schema_ids_only;

        $prompt = _build_batch_prompt(
            $idx, $n_batches, $is_primary,
            $brands_json, $categories_json, $category_translations,
            $batch_json, $menu_text, $all_po_names_json
        );

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system_instr]]],
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.0,
                'maxOutputTokens'  => $is_primary ? 8192 : 4096,
                'responseMimeType' => 'application/json',
                'responseSchema'   => $schema,
            ],
        ];
        $payload_json = json_encode($payload);
        $total_payload_bytes += strlen($payload_json);

        $dry_batches[] = [
            'batch_index'        => $idx,
            'is_primary'         => $is_primary,
            'product_count'      => count($batch),
            'url'                => $url,
            'payload_size'       => strlen($payload_json),
            'system_instruction' => $system_instr,
            'brands_array'       => $all_brands,
            'categories_array'   => $all_categories,
            'po_products_array'  => $batch,
            'menu_text'          => $menu_text,
            'schema'             => $schema,
            'prompt'             => $prompt,
            'full_payload_json'  => $payload_json,
        ];
    }
    echo json_encode([
        'success'   => true,
        'dry_run'   => true,
        'po_id'     => $po_id,
        'po_code'   => $po_code,
        'summary'   => [
            'total_products'       => count($po_products),
            'total_batches'        => $n_batches,
            'batch_size'           => $batch_size,
            'concurrency'          => $concurrency,
            'total_payload_bytes'  => $total_payload_bytes,
            'menu_text_chars'      => strlen($menu_text),
            'brands_count'         => count($all_brands),
            'categories_count'     => count($all_categories),
        ],
        'batches'   => $dry_batches,
    ]);
    exit;
}

$batch_results = [];
$overall_start = microtime(true);
$global_found  = [];
$global_add    = [];

// Process in waves of $concurrency
$wave_groups = array_chunk(array_keys($batches), $concurrency);

foreach ($wave_groups as $wave_indices) {
    $mh      = curl_multi_init();
    $handles = [];
    $meta    = [];

    foreach ($wave_indices as $idx) {
        $is_primary  = ($idx === 0);
        $batch       = $batches[$idx];
        $batch_json  = json_encode($batch, JSON_UNESCAPED_UNICODE);

        $prompt = _build_batch_prompt(
            $idx, $n_batches, $is_primary,
            $brands_json, $categories_json, $category_translations,
            $batch_json, $menu_text, $all_po_names_json
        );

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system_instr]]],
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.0,
                'maxOutputTokens'  => $is_primary ? 8192 : 4096,
                'responseMimeType' => 'application/json',
                'responseSchema'   => $is_primary ? $schema_primary : $schema_ids_only,
            ],
        ];
        $payload_json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload_json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $timeout_s,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$idx] = $ch;
        $meta[$idx] = [
            'batch_index'      => $idx,
            'is_primary'       => $is_primary,
            'product_count'    => count($batch),
            'started_at'       => date('Y-m-d H:i:s'),
            'start_microtime'  => microtime(true),
            'payload_size'     => strlen($payload_json),
            // Store full data for the verbose log
            'system_instruction' => $system_instr,
            'brands_array'     => $all_brands,
            'categories_array' => $all_categories,
            'po_products_array'=> $batch,
            'menu_text'        => $menu_text,
            'schema'           => $is_primary ? $schema_primary : $schema_ids_only,
        ];
    }

    // Run wave
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
        if ($mrc === CURLM_CALL_MULTI_PERFORM) { continue; }
        if ($mrc !== CURLM_OK) { break; }
        if ($active > 0) { curl_multi_select($mh, 1.0); }
    } while ($active > 0);

    // Collect wave results
    foreach ($handles as $idx => $ch) {
        $raw      = curl_multi_getcontent($ch);
        $curl_err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $elapsed  = round(microtime(true) - $meta[$idx]['start_microtime'], 3);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $parsed_found  = [];
        $parsed_add    = [];
        $finish_reason = null;
        $parse_error   = null;

        if (!$curl_err && $raw) {
            $res  = json_decode($raw, true);
            $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $finish_reason = $res['candidates'][0]['finishReason'] ?? null;
            if ($text !== '') {
                $data = json_decode($text, true);
                if (is_array($data)) {
                    $parsed_found = array_map('intval', $data['found_po_product_ids'] ?? []);
                    $parsed_add   = $data['add_products'] ?? [];
                } else {
                    $parse_error = json_last_error_msg();
                }
            }
        }

        // Compute disable_ids for this batch: all ids NOT in found
        $batch_ids     = array_column($meta[$idx]['po_products_array'], 'po_product_id');
        $found_set     = array_flip($parsed_found);
        $disable_ids   = array_values(array_filter($batch_ids, fn($id) => !isset($found_set[$id])));

        $global_found  = array_merge($global_found, $parsed_found);
        $global_add    = array_merge($global_add, $parsed_add);

        $batch_results[] = [
            'batch_index'       => $idx,
            'is_primary'        => $meta[$idx]['is_primary'],
            'product_count'     => $meta[$idx]['product_count'],
            'started_at'        => $meta[$idx]['started_at'],
            'duration_s'        => $elapsed,
            'http_status'       => $http_code,
            'curl_error'        => $curl_err ?: null,
            'payload_size'      => $meta[$idx]['payload_size'],
            'finish_reason'     => $finish_reason,
            'response_size'     => strlen($raw ?? ''),
            'raw_response'      => $raw ?? '',
            'system_instruction'=> $meta[$idx]['system_instruction'],
            'brands_array'      => $meta[$idx]['brands_array'],
            'categories_array'  => $meta[$idx]['categories_array'],
            'po_products_array' => $meta[$idx]['po_products_array'],
            'menu_text'         => $meta[$idx]['menu_text'],
            'schema'            => $meta[$idx]['schema'],
            'parsed_found_ids'  => $parsed_found,
            'parsed_disable_ids'=> $disable_ids,
            'parsed_add_products'=> $parsed_add,
            'parse_error'       => $parse_error,
        ];
    }
    curl_multi_close($mh);
}

// Dedupe add_products by name
$seen_add = [];
$deduped_add = [];
foreach ($global_add as $item) {
    $key = strtolower(trim($item['name'] ?? ''));
    if ($key !== '' && !isset($seen_add[$key])) {
        $seen_add[$key] = true;
        $deduped_add[] = $item;
    }
}

// All po_product_ids not in global found = disable
$all_po_ids    = array_column($po_products, 'po_product_id');
$found_set     = array_flip($global_found);
$all_disable   = array_values(array_filter($all_po_ids, fn($id) => !isset($found_set[$id])));

$total_elapsed = round(microtime(true) - $overall_start, 2);

$result = [
    'status'          => 'completed',
    'started_at'      => date('Y-m-d H:i:s', (int)($overall_start)),
    'finished_at'     => date('Y-m-d H:i:s'),
    'po_id'           => $po_id,
    'po_code'         => $po_code,
    'summary' => [
        'total_products'   => count($po_products),
        'total_batches'    => $n_batches,
        'batch_size'       => $batch_size,
        'concurrency'      => $concurrency,
        'total_time_s'     => $total_elapsed,
        'total_found_ids'  => count($global_found),
        'total_disable_ids'=> count($all_disable),
        'total_add_products'=> count($deduped_add),
    ],
    'batches'         => $batch_results,
    // Omit global arrays from top-level; each batch has them
];

if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
@file_put_contents($result_file, json_encode($result), LOCK_EX);

if (!$is_cli) {
    echo json_encode(['success' => true, 'result' => $result]);
}
exit(0);
