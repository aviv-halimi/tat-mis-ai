<?php
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';

header('Cache-Control: no-cache');
header('Content-type: application/json');

$po_product_id = isset($_POST['id'])       ? (int)    trim($_POST['id'])       : 0;
$product_name  = isset($_POST['name'])     ? trim((string) $_POST['name'])     : '';
$brand_name    = isset($_POST['brand'])    ? trim((string) $_POST['brand'])    : '';
$category_name = isset($_POST['category']) ? trim((string) $_POST['category']) : '';
$brand_id      = isset($_POST['brand_id'])    ? (int) $_POST['brand_id']    : 0;
$category_id   = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
$store_db      = isset($_POST['store_db'])    ? preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['store_db'])) : '';

if ($po_product_id <= 0 || $product_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing product id or name for enrichment.']);
    exit;
}

// ============================================================
// Step A: Description via Gemini (text-only)
// ============================================================
function tat_enrich_generate_description($product_name, $brand_name, $category_name, &$error = null)
{
    $error = null;

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
    }
    if (!$apiKey) { $error = 'Gemini API key is not configured.'; return ''; }

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $prompt = "Act as a cannabis retail copywriter. Write a compelling, 3-sentence product description for {$product_name}";
    if ($brand_name !== '')    $prompt .= " by {$brand_name}";
    if ($category_name !== '') $prompt .= " in the {$category_name} category";
    $prompt .= ". Focus on effects and quality. Output: Raw text only.";

    $payload = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 256],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErr || $httpCode >= 300) {
        $error = 'Gemini description request failed' . ($curlErr ? ': ' . $curlErr : '') . ($httpCode ? " (HTTP {$httpCode})" : '');
        return '';
    }

    $json = json_decode($response, true);
    $text = trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($text === '') $error = 'Gemini returned an empty description.';
    return $text;
}

// ============================================================
// Step B: Image discovery via Serper.dev — returns up to $num URLs
// ============================================================

/**
 * Strip parenthetical strain codes like (I), (S), (H) from product name.
 */
function tat_enrich_clean_name(string $name): string
{
    $clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name);
    return trim(preg_replace('/\s+/', ' ', $clean));
}

/**
 * POST a query to Serper.dev Images and return up to $num imageUrl strings.
 */
function tat_serper_image_search(string $query, string $apiKey, int $num = 5): array
{
    $payload = json_encode(['q' => $query, 'num' => $num]);

    $ch = curl_init('https://google.serper.dev/images');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErr || $httpCode >= 300) return [];

    $json   = json_decode($response, true);
    $images = $json['images'] ?? [];
    if (!is_array($images)) return [];

    $urls = [];
    foreach ($images as $img) {
        $url = trim((string) ($img['imageUrl'] ?? ''));
        if ($url !== '') $urls[] = $url;
        if (count($urls) >= $num) break;
    }
    return $urls;
}

// ============================================================
// Google Drive master-folder ID and Serper key constants
// ============================================================
define('GD_ROOT_FOLDER_ID', '1YQSjGTVXYiQP5jBSku2HdRtpbCUvklQx');
define('ENRICHMENT_TMP_DIR', BASE_PATH . 'public/tmp/enrichment');
define('ENRICHMENT_TMP_URL', BASE_URL . '/public/tmp/enrichment');
define('SERPER_API_KEY',     'b3c39559a928534f00749286e3b8503856c72c02');

/**
 * Step B: Image discovery.
 *
 * Priority:
 *   1. Google Drive (AI fuzzy match via Gemini) — prepended as first carousel image.
 *   2. Serper.dev trusted menu sites (weedmaps, leafly, dutchie).
 *   3. Serper.dev general web (excluding pinterest).
 *
 * Returns array of image URLs (Drive image first if found).
 * Sets $source_found, $warning, $search_query by reference.
 */
/**
 * Extract a Google Drive folder ID from a URL or raw ID string.
 * Returns null if the input doesn't look like Drive.
 */
function tat_extract_drive_folder_id(string $input): ?string
{
    $input = trim($input);
    if ($input === '') return null;

    // Full Drive URL
    if (preg_match('#/folders/([A-Za-z0-9_\-]{10,})#', $input, $m)) return $m[1];

    // Raw folder ID (no slashes, 20+ chars)
    if (preg_match('/^[A-Za-z0-9_\-]{20,}$/', $input)) return $input;

    return null;
}

/**
 * Step B: Image discovery — tiered hierarchy:
 *
 *  1. Brand-specific Drive folder (from blaze1.brand.brand_folder via master_brand_id)
 *  2. Non-Drive brand URL  → extra Serper site: search
 *  3. Global master Drive folder (fallback when brand has no folder)
 *  4. Serper trusted menu sites (weedmaps, leafly, dutchie)
 *  5. Serper broad web
 *
 * Extra params (optional):
 *   $brand_id  — local store brand_id to look up the master brand folder
 *   $store_db  — local store DB name for the master_brand_id join
 */
function tat_enrich_discover_images(
    $product_name, $brand_name, $category_name,
    &$source_found,
    &$warning      = null,
    &$search_query = null,
    &$image_sources = null,
    int $brand_id  = 0,
    string $store_db = ''
): array {
    $source_found  = null;
    $warning       = null;
    $search_query  = null;
    $image_sources = [];

    $cleanName = tat_enrich_clean_name((string) $product_name);
    if ($cleanName === '') {
        $warning = 'Product name is empty after cleaning; image search skipped.';
        return [];
    }

    $categoryPart = trim((string) $category_name);
    $namePart     = trim(implode(' ', array_filter([$cleanName, $categoryPart])));

    // --------------------------------------------------------
    // Step B1: Look up brand-specific asset folder from blaze1
    // --------------------------------------------------------
    $brand_folder_url      = null;
    $brand_drive_folder_id = null;
    $brand_site_domain     = null;   // for non-Drive URLs

    if ($brand_id > 0 && $store_db !== '') {
        // Translate local brand_id → master_brand_id
        $local_brand     = getRow(getRs(
            "SELECT master_brand_id FROM `{$store_db}`.brand WHERE brand_id = ? LIMIT 1",
            [$brand_id]
        ));
        $master_brand_id = $local_brand['master_brand_id'] ?? null;

        if ($master_brand_id) {
            $blaze_brand = getRow(getRs(
                "SELECT brand_folder FROM `blaze1`.brand
                  WHERE brand_id = ? AND is_active = 1 LIMIT 1",
                [$master_brand_id]
            ));
            $brand_folder_url = $blaze_brand['brand_folder'] ?? null;
        }
    }

    if ($brand_folder_url !== null && $brand_folder_url !== '') {
        $brand_drive_folder_id = tat_extract_drive_folder_id($brand_folder_url);

        // Non-Drive URL: extract domain for Serper site: search
        if ($brand_drive_folder_id === null && filter_var($brand_folder_url, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($brand_folder_url);
            $brand_site_domain = $parsed['host'] ?? null;
        }
    }

    // --------------------------------------------------------
    // Step B2: Google Drive image search via Gemini
    //   Priority 1: brand-specific Drive folder (if set)
    //   Priority 2: global master Drive folder (always tried as fallback)
    // --------------------------------------------------------
    $drive_image_url = null;
    $drive_source    = '';

    $creds_path = BASE_PATH . 'credentials/service-account.json';
    if (file_exists($creds_path)) {
        $creds = json_decode((string) file_get_contents($creds_path), true);
        if (is_array($creds) && !empty($creds['private_key'])) {

            $gemini_key = getenv('GEMINI_API_KEY');
            if ($gemini_key === false || $gemini_key === '') {
                $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
            }

            if ($gemini_key !== '') {
                // --- Attempt 1: brand-specific folder ---
                if ($brand_drive_folder_id !== null) {
                    $brand_index = gd_get_index($creds, $brand_drive_folder_id);
                    if (!empty($brand_index)) {
                        $file_id = gd_gemini_match($cleanName, (string) $brand_name, $brand_index, $gemini_key);
                        if ($file_id !== null) {
                            $drive_service = gd_make_drive_service($creds);
                            $local_url     = gd_download_and_resize(
                                $drive_service, $file_id, ENRICHMENT_TMP_DIR, ENRICHMENT_TMP_URL
                            );
                            if ($local_url !== null) {
                                $drive_image_url = $local_url;
                                $drive_source    = 'Brand Drive Folder';
                            }
                        }
                    }
                }

                // --- Attempt 2: global master folder (always run if brand folder gave no result) ---
                if ($drive_image_url === null) {
                    $master_index = gd_get_index($creds, GD_ROOT_FOLDER_ID);
                    if (!empty($master_index)) {
                        $file_id = gd_gemini_match($cleanName, (string) $brand_name, $master_index, $gemini_key);
                        if ($file_id !== null) {
                            $drive_service = gd_make_drive_service($creds);
                            $local_url     = gd_download_and_resize(
                                $drive_service, $file_id, ENRICHMENT_TMP_DIR, ENRICHMENT_TMP_URL
                            );
                            if ($local_url !== null) {
                                $drive_image_url = $local_url;
                                $drive_source    = 'Google Drive';
                            }
                        }
                    }
                }
            }
        }
    }

    // --------------------------------------------------------
    // Step B3: Non-Drive brand asset URL → Serper site: search
    // --------------------------------------------------------
    $brand_site_urls = [];
    if ($brand_site_domain !== null) {
        $brand_site_q    = 'site:' . $brand_site_domain . ' "' . $cleanName . '"';
        $brand_site_urls = tat_serper_image_search($brand_site_q, SERPER_API_KEY, 10);
    }

    // --------------------------------------------------------
    // Step B4+B5: Serper.dev — always run both queries
    // --------------------------------------------------------
    $trusted_q = '(site:weedmaps.com OR site:leafly.com OR site:dutchie.com) "' . $namePart . '"';
    $web_q     = '"' . $namePart . '" cannabis product packaging -site:pinterest.com';

    $trusted_urls = tat_serper_image_search($trusted_q, SERPER_API_KEY, 10);
    $web_urls     = tat_serper_image_search($web_q,     SERPER_API_KEY, 10);

    // Merge Serper results: trusted first, deduplicate
    $serper_urls = array_values(array_unique(array_merge($trusted_urls, $web_urls)));

    // --------------------------------------------------------
    // Combine: Drive image first, then Serper results
    // Build parallel image_sources[] so each URL has its own label
    // --------------------------------------------------------
    $all_urls      = [];
    $image_sources = [];

    // 1. Drive image (brand folder or master folder)
    if ($drive_image_url !== null) {
        $all_urls[]      = $drive_image_url;
        $image_sources[] = $drive_source ?: 'Google Drive';
    }

    $seen = $drive_image_url !== null ? [$drive_image_url => true] : [];

    // 2. Non-Drive brand asset site results
    foreach ($brand_site_urls as $url) {
        if (!isset($seen[$url])) {
            $all_urls[]      = $url;
            $image_sources[] = 'Brand Site';
            $seen[$url]      = true;
        }
    }

    // 3. Trusted menu results
    foreach ($trusted_urls as $url) {
        if (!isset($seen[$url])) {
            $all_urls[]      = $url;
            $image_sources[] = 'Trusted Menu';
            $seen[$url]      = true;
        }
    }

    // 4. Web search results
    foreach ($web_urls as $url) {
        if (!isset($seen[$url])) {
            $all_urls[]      = $url;
            $image_sources[] = 'Web Search';
            $seen[$url]      = true;
        }
    }

    if (empty($all_urls)) {
        $warning = 'No Image Found';
        return [];
    }

    // Overall combined source label (for status badge)
    $sources = [];
    if ($drive_source !== '')      $sources[] = $drive_source;
    if (!empty($brand_site_urls))  $sources[] = 'Brand Site';
    if (!empty($trusted_urls))     $sources[] = 'Trusted Menu';
    if (!empty($web_urls))         $sources[] = 'Web Search';
    $source_found = implode(' + ', $sources) ?: 'Web Search';

    // Populate the "Search Again" box with the plain search term only —
    // the trusted-site and web query wrappers are built automatically by
    // product-image-search.php when the user hits "Search Again".
    $search_query = $namePart;

    return $all_urls;
}

// ============================================================
// Load brands and categories from the current store DB
// ============================================================
$brands     = [];
$categories = [];
if ($store_db !== '') {
    foreach (getRs("SELECT brand_id, name FROM `{$store_db}`.brand WHERE is_active = 1 ORDER BY name", []) as $b) {
        $brands[] = ['id' => (int) $b['brand_id'], 'name' => (string) $b['name']];
    }
    foreach (getRs("SELECT category_id, name FROM `{$store_db}`.category ORDER BY name", []) as $c) {
        $categories[] = ['id' => (int) $c['category_id'], 'name' => (string) $c['name']];
    }
}

// ============================================================
// Run the enrichment
// ============================================================
$descError   = null;
$description = tat_enrich_generate_description($product_name, $brand_name, $category_name, $descError);

$source_found  = null;
$imageWarning  = null;
$search_query  = null;
$image_sources = [];
$images        = tat_enrich_discover_images($product_name, $brand_name, $category_name, $source_found, $imageWarning, $search_query, $image_sources, $brand_id, $store_db);

if ($descError && $description === '') {
    echo json_encode(['success' => false, 'error' => $descError]);
    exit;
}

echo json_encode([
    'success'        => true,
    'description'    => $description,
    'images'         => $images,
    'image_sources'  => $image_sources,   // per-image source label, parallel to images[]
    'source_found'   => $source_found ?: 'Web Search',
    'image_source'   => $source_found ?: 'Web Search',
    'search_query'   => $search_query ?: '',
    'warning'        => $imageWarning,
    'brand'          => $brand_name,
    'brand_id'       => $brand_id,
    'category'       => $category_name,
    'category_id'    => $category_id,
    'brands'         => $brands,
    'categories'     => $categories,
]);
