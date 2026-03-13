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
function tat_enrich_discover_images($product_name, $brand_name, $category_name, &$source_found, &$warning = null, &$search_query = null): array
{
    $source_found = null;
    $warning      = null;
    $search_query = null;

    $cleanName = tat_enrich_clean_name((string) $product_name);
    if ($cleanName === '') {
        $warning = 'Product name is empty after cleaning; image search skipped.';
        return [];
    }

    $categoryPart = trim((string) $category_name);
    $namePart     = trim(implode(' ', array_filter([$cleanName, $categoryPart])));

    // --------------------------------------------------------
    // Step B1: Google Drive fuzzy match via Gemini
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
                // Build / load cached file index
                $file_index = gd_get_index($creds, GD_ROOT_FOLDER_ID);

                if (!empty($file_index)) {
                    // Ask Gemini to pick the best match
                    $file_id = gd_gemini_match($cleanName, (string) $brand_name, $file_index, $gemini_key);

                    if ($file_id !== null) {
                        // Reuse the authenticated service to download + resize
                        $drive_service = gd_make_drive_service($creds);
                        $local_url = gd_download_and_resize(
                            $drive_service,
                            $file_id,
                            ENRICHMENT_TMP_DIR,
                            ENRICHMENT_TMP_URL
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

    // --------------------------------------------------------
    // Step B2+B3: Serper.dev — always run both queries
    // --------------------------------------------------------
    $trusted_q = '(site:weedmaps.com OR site:leafly.com OR site:dutchie.com) "' . $namePart . '"';
    $web_q     = '"' . $namePart . '" cannabis product packaging -site:pinterest.com';

    $trusted_urls = tat_serper_image_search($trusted_q, SERPER_API_KEY, 10);
    $web_urls     = tat_serper_image_search($web_q,     SERPER_API_KEY, 10);

    // Merge Serper results: trusted first, deduplicate
    $serper_urls = array_values(array_unique(array_merge($trusted_urls, $web_urls)));

    // --------------------------------------------------------
    // Combine: Drive image first, then Serper results
    // --------------------------------------------------------
    $all_urls = $serper_urls;
    if ($drive_image_url !== null) {
        // Prepend Drive image, remove if it accidentally appeared in Serper list
        $all_urls = array_values(array_unique(
            array_merge([$drive_image_url], $serper_urls)
        ));
    }

    if (empty($all_urls)) {
        $warning = 'No Image Found';
        return [];
    }

    // Build source label
    $sources = [];
    if ($drive_source !== '')          $sources[] = 'Google Drive';
    if (!empty($trusted_urls))         $sources[] = 'Trusted Menu';
    if (!empty($web_urls))             $sources[] = 'Web Search';
    $source_found = implode(' + ', $sources) ?: 'Web Search';

    // Report both Serper queries for the user's "Search Again" box
    $search_query = $trusted_q . ' | ' . $web_q;

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

$source_found = null;
$imageWarning = null;
$search_query = null;
$images       = tat_enrich_discover_images($product_name, $brand_name, $category_name, $source_found, $imageWarning, $search_query);

if ($descError && $description === '') {
    echo json_encode(['success' => false, 'error' => $descError]);
    exit;
}

echo json_encode([
    'success'       => true,
    'description'   => $description,
    'images'        => $images,
    'source_found'  => $source_found ?: 'Web Search',
    'image_source'  => $source_found ?: 'Web Search',
    'search_query'  => $search_query ?: '',
    'warning'       => $imageWarning,
    'brand'         => $brand_name,
    'brand_id'      => $brand_id,
    'category'      => $category_name,
    'category_id'   => $category_id,
    'brands'        => $brands,
    'categories'    => $categories,
]);
