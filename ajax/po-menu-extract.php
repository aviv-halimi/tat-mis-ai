<?php
/**
 * Step 1+2 of PO menu sync (new flow):
 * 1. Send PDFs to Gemini with all brands/categories → get structured menu item list.
 * 2. For each menu item, fuzzy-match against the store's product catalogue (same brand + category).
 * Returns matches (menu item ↔ DB product) and unmatched (custom) items for the UI modal.
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Content-type: application/json');

$po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
if (!$po_id) {
    echo json_encode(['success' => false, 'error' => 'Missing po_id']);
    exit;
}

$po = getRow(getRs(
    "SELECT po_id, po_code, po_status_id, menu_filenames FROM po WHERE " . is_enabled() . " AND po_id = ?",
    [$po_id]
));
if (!$po) {
    echo json_encode(['success' => false, 'error' => 'PO not found']);
    exit;
}
if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(['success' => false, 'error' => 'PO must be in Draft (status 1)']);
    exit;
}

$files = json_decode($po['menu_filenames'] ?: '[]', true);
if (!is_array($files) || empty($files)) {
    echo json_encode(['success' => false, 'error' => 'No menu PDFs saved. Upload menu PDFs first.']);
    exit;
}
$base_path = defined('MEDIA_PATH') ? rtrim(MEDIA_PATH, '/\\') . '/po/' : '';
$pdf_paths = [];
foreach ($files as $f) {
    $name = $f['name'] ?? '';
    if ($name !== '' && file_exists($base_path . $name)) {
        $pdf_paths[] = $base_path . $name;
    }
}
if (empty($pdf_paths)) {
    echo json_encode(['success' => false, 'error' => 'Menu PDF files not found on disk']);
    exit;
}

// ALL brands and categories from this store (not just the ones on the PO)
$db = $_Session->db;
$all_brands = [];
foreach (getRs("SELECT brand_id, name FROM {$db}.brand WHERE is_active = 1 ORDER BY name") as $r) {
    $all_brands[] = ['brand_id' => (int) $r['brand_id'], 'name' => (string) $r['name']];
}
$all_categories = [];
foreach (getRs("SELECT category_id, name FROM {$db}.category WHERE is_active = 1 ORDER BY name") as $r) {
    $all_categories[] = ['category_id' => (int) $r['category_id'], 'name' => (string) $r['name']];
}

// --- Step 1: Gemini extraction ---
require_once dirname(__FILE__) . '/../inc/ai-po-menu-gemini.php';
$debug_log = [];
$menu_items = extractMenuItemsFromPDF($pdf_paths, $all_brands, $all_categories, $debug_log);
if ($menu_items === null) {
    echo json_encode(['success' => false, 'error' => 'Gemini could not extract menu items.', 'debug_log' => $debug_log]);
    exit;
}

// Index for quick lookup
$brands_by_id     = array_column($all_brands,     null, 'brand_id');
$categories_by_id = array_column($all_categories, null, 'category_id');

// --- Step 2: PHP DB matching ---
$matches   = [];
$unmatched = [];

foreach ($menu_items as $item) {
    $name        = trim((string) ($item['name'] ?? ''));
    $price       = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0.0;
    $brand_id    = isset($item['brand_id'])    && (int) $item['brand_id']    > 0 ? (int) $item['brand_id']    : null;
    $category_id = isset($item['category_id']) && (int) $item['category_id'] > 0 ? (int) $item['category_id'] : null;
    if ($name === '') {
        continue;
    }

    $brand_name    = $brand_id    ? ($brands_by_id[$brand_id]['name']         ?? '') : '';
    $category_name = $category_id ? ($categories_by_id[$category_id]['name']  ?? '') : '';

    $entry = [
        'menu_name'     => $name,
        'menu_price'    => $price,
        'brand_id'      => $brand_id,
        'brand_name'    => $brand_name,
        'category_id'   => $category_id,
        'category_name' => $category_name,
    ];

    $match = ($brand_id && $category_id) ? _po_find_product($name, $brand_id, $category_id, $db) : null;
    if ($match) {
        $entry['product_id']   = $match['product_id'];
        $entry['product_name'] = $match['product_name'];
        $entry['match_score']  = $match['score'];
        $matches[] = $entry;
    } else {
        $unmatched[] = $entry;
    }
}

$debug_log[] = '[MATCH] Matches: ' . count($matches) . ', Unmatched (custom): ' . count($unmatched);

echo json_encode([
    'success'   => true,
    'matches'   => $matches,
    'unmatched' => $unmatched,
    'debug_log' => $debug_log,
]);

// --- Helpers ---

function _po_normalize($name)
{
    $name = strtolower((string) $name);
    // Strip common brand prefixes
    $name = preg_replace('/^(710\s*labs?|710labs?|\.)\s*/i', '', $name);
    // Strip weight/size suffixes
    $name = preg_replace('/\s*\d+\.?\d*\s*(g|gram|grams?|oz|mg)\b/i', '', $name);
    $name = preg_replace('/\s*(preroll|joint|pack|pk|each)\b/i', '', $name);
    // Keep only alphanumeric
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

function _po_find_product($menu_name, $brand_id, $category_id, $db)
{
    $products_rs = getRs(
        "SELECT product_id, name FROM {$db}.product WHERE is_active = 1 AND brand_id = ? AND category_id = ? LIMIT 500",
        [$brand_id, $category_id]
    );

    $clean_menu  = _po_normalize($menu_name);
    $best_score  = 0;
    $best        = null;

    foreach ($products_rs as $p) {
        $clean_db = _po_normalize($p['name']);
        if ($clean_menu === '' || $clean_db === '') {
            continue;
        }
        // Exact after normalization
        if ($clean_menu === $clean_db) {
            return ['product_id' => (int) $p['product_id'], 'product_name' => $p['name'], 'score' => 100];
        }
        // Containment (one is substring of the other)
        if (strpos($clean_db, $clean_menu) !== false || strpos($clean_menu, $clean_db) !== false) {
            return ['product_id' => (int) $p['product_id'], 'product_name' => $p['name'], 'score' => 95];
        }
        // Fuzzy similarity
        similar_text($clean_menu, $clean_db, $pct);
        if ($pct >= 65 && $pct > $best_score) {
            $best_score = $pct;
            $best = ['product_id' => (int) $p['product_id'], 'product_name' => $p['name'], 'score' => (int) round($pct)];
        }
    }
    return $best;
}
