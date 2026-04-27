<?php
/**
 * Re-run image search with a custom query.
 *
 * POST params:
 *   query  string  Serper.dev search query (shown in the "Search Again" box)
 *   name   string  Product name (used for Google Drive fuzzy match)
 *   brand  string  Brand name   (used for Google Drive fuzzy match)
 *
 * Returns images with the Drive match prepended (if found), then Serper results.
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';
require_once BASE_PATH . 'inc/DropboxHelper.php';

@ini_set('memory_limit', '512M');   // GD + large Dropbox images need headroom

header('Cache-Control: no-cache');
header('Content-type: application/json');

// Catch PHP errors / exceptions and return them as JSON so the caller
// never receives a broken response that jQuery can't parse.
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!($errno & error_reporting())) return false;
    $short = basename($errfile) . ':' . $errline . ' — ' . $errstr;
    echo json_encode(['success' => false, 'error' => 'PHP error: ' . $short]);
    exit;
});
register_shutdown_function(function(): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . basename($e['file']) . ':' . $e['line'] . ' — ' . $e['message']]);
    }
});

$query           = trim((string) ($_POST['query']    ?? ''));
$name            = trim((string) ($_POST['name']     ?? ''));
$brand           = trim((string) ($_POST['brand']    ?? ''));
$brand_id        = (int) ($_POST['brand_id'] ?? 0);
$category_id     = (int) ($_POST['category_id'] ?? 0);
$weight_per_unit = trim((string) ($_POST['weight_per_unit'] ?? ''));
$store_db        = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) ($_POST['store_db'] ?? '')));

if ($query === '') {
    echo json_encode(['success' => false, 'error' => 'Missing query.']);
    exit;
}

define('PIS_ROOT_FOLDER_ID', '1YQSjGTVXYiQP5jBSku2HdRtpbCUvklQx');

$serper_key = 'b3c39559a928534f00749286e3b8503856c72c02';

// --------------------------------------------------------
// Step 1: Brand folder fuzzy match (Dropbox OR Drive) + master Drive fallback
//   1a. Brand Dropbox Folder  — if brand_folder is a Dropbox link
//   1b. Brand Drive Folder    — if brand_folder is a Google Drive link
//   1c. Global master Drive   — only when 1a/1b found nothing
// --------------------------------------------------------
$brand_folder_urls   = [];   // from brand-specific folder (Drive or Dropbox)
$brand_folder_source = '';   // 'Brand Drive Folder' or 'Brand Dropbox Folder'
$master_drive_urls   = [];

// Strip Serper operators so Gemini gets plain text
$clean_query = preg_replace('/\(site:[^)]+\)|site:\S+|-site:\S+|"/', '', $query);
$clean_query = trim(preg_replace('/\s+/', ' ', $clean_query));

// User's typed query takes priority for Drive/Dropbox matching
$search_name  = $clean_query !== '' ? $clean_query : $name;
$search_brand = $brand !== '' ? $brand : '';

$gemini_key = getenv('GEMINI_API_KEY');
if ($gemini_key === false || $gemini_key === '') {
    $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
}

// Resolve brand folder URL
$brand_folder_url      = null;
$brand_drive_folder_id = null;
$brand_dropbox_url     = null;

if ($brand_id > 0 && $store_db !== '') {
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

$brand_site_domain = null;

if ($brand_folder_url !== null && $brand_folder_url !== '') {
    if (dbx_is_dropbox_url($brand_folder_url)) {
        $brand_dropbox_url = $brand_folder_url;
    } elseif (preg_match('#/folders/([A-Za-z0-9_\-]{10,})#', $brand_folder_url, $m)) {
        $brand_drive_folder_id = $m[1];
    } elseif (preg_match('/^[A-Za-z0-9_\-]{20,}$/', $brand_folder_url)) {
        $brand_drive_folder_id = $brand_folder_url;
    } elseif (filter_var($brand_folder_url, FILTER_VALIDATE_URL)) {
        // Other URL (e.g. Brandfolder) — extract domain for Serper site: search
        $parsed = parse_url($brand_folder_url);
        $brand_site_domain = $parsed['host'] ?? null;
    }
}

// --- 1a: Brand Dropbox Folder ---
if ($brand_dropbox_url !== null && $gemini_key !== '') {
    $dbx_token = defined('DROPBOX_ACCESS_TOKEN') ? DROPBOX_ACCESS_TOKEN : '';
    $dbx_files = dbx_get_file_list($brand_dropbox_url, $dbx_token, $gemini_key, trim($search_brand . ' ' . $search_name));
    if (!empty($dbx_files)) {
        $matched_files = dbx_gemini_match_multi($search_name, $search_brand, $dbx_files, $gemini_key, 5);
        foreach ($matched_files as $mf) {
            $local_url = dbx_download_and_resize(
                $brand_dropbox_url,
                $mf['path'],
                $mf['name'],
                $dbx_token,
                BASE_PATH . 'public/tmp/enrichment',
                BASE_URL  . '/public/tmp/enrichment'
            );
            if ($local_url !== null) $brand_folder_urls[] = $local_url;
        }
    }
    if (!empty($brand_folder_urls)) $brand_folder_source = 'Brand Dropbox Folder';
}

// --- 1b: Brand Drive Folder + 1c: Master Drive fallback ---
if (empty($brand_folder_urls)) {
    $creds_path = BASE_PATH . 'credentials/service-account.json';
    if (file_exists($creds_path) && $gemini_key !== '') {
        $creds = json_decode((string) file_get_contents($creds_path), true);
        if (is_array($creds) && !empty($creds['private_key'])) {
            $svc_obj = null;

            if ($brand_drive_folder_id !== null) {
                $brand_index = gd_get_index($creds, $brand_drive_folder_id);
                if (!empty($brand_index)) {
                    $file_ids = gd_gemini_match_multi($search_name, $search_brand, $brand_index, $gemini_key, 5);
                    if (!empty($file_ids)) {
                        $svc_obj = gd_make_drive_service($creds);
                        foreach ($file_ids as $fid) {
                            $local_url = gd_download_and_resize(
                                $svc_obj, $fid,
                                BASE_PATH . 'public/tmp/enrichment',
                                BASE_URL . '/public/tmp/enrichment'
                            );
                            if ($local_url !== null) $brand_folder_urls[] = $local_url;
                        }
                    }
                }
            }
            if (!empty($brand_folder_urls)) $brand_folder_source = 'Brand Drive Folder';

            // --- 1c: global master folder (only if brand folder gave nothing) ---
            if (empty($brand_folder_urls)) {
                $master_index = gd_get_index($creds, PIS_ROOT_FOLDER_ID);
                if (!empty($master_index)) {
                    $file_ids = gd_gemini_match_multi($search_name, $search_brand, $master_index, $gemini_key, 5);
                    if (!empty($file_ids)) {
                        if ($svc_obj === null) $svc_obj = gd_make_drive_service($creds);
                        foreach ($file_ids as $fid) {
                            $local_url = gd_download_and_resize(
                                $svc_obj, $fid,
                                BASE_PATH . 'public/tmp/enrichment',
                                BASE_URL . '/public/tmp/enrichment'
                            );
                            if ($local_url !== null) $master_drive_urls[] = $local_url;
                        }
                    }
                }
            }
        }
    }
}

// --------------------------------------------------------
// Step 2: Serper.dev — run trusted-site + broad-web queries
// from the plain search term, exactly like product-enrich.php
// --------------------------------------------------------
function pis_serper_search(string $query, string $apiKey, int $num = 10): array
{
    $ch = curl_init('https://google.serper.dev/images');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => (string) json_encode(['q' => $query, 'num' => $num]),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp    = curl_exec($ch);
    $curlErr = curl_error($ch);
    $http    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $curlErr || $http >= 300) return [];

    $urls = [];
    foreach ((array) (json_decode($resp, true)['images'] ?? []) as $img) {
        $url = trim((string) ($img['imageUrl'] ?? ''));
        if ($url !== '') $urls[] = $url;
        if (count($urls) >= $num) break;
    }
    return $urls;
}

// --------------------------------------------------------
// Step 1.5: Similar products already in our store DB
// Looks up `{store_db}.product` for the same brand_id / category_id /
// weightPerUnit and returns up to 5 existing product.photo AWS URLs so
// the user can re-use a pre-approved photo from a sibling product.
// Mirrors the lookup in ajax/product-enrich.php so Search Again behaves
// the same as the initial enrichment.
// --------------------------------------------------------
$PIS_WEIGHT_PER_UNIT_BLAZE = [
    'Each'            => 'EACH',
    'Half Gram Unit'  => 'HALF_GRAM',
    'Full Gram Unit'  => 'FULL_GRAM',
    'Eighth Per Unit' => 'EIGHTH',
    'Custom Weight'   => 'CUSTOM_GRAMS',
];
$similar_product_urls = [];
$blaze_wpu_for_lookup = $PIS_WEIGHT_PER_UNIT_BLAZE[$weight_per_unit] ?? '';
if ($store_db !== '' && $brand_id > 0 && $category_id > 0 && $blaze_wpu_for_lookup !== '') {
    $sim_rows = getRs(
        "SELECT photo
           FROM `{$store_db}`.product
          WHERE brand_id      = ?
            AND category_id   = ?
            AND weightPerUnit = ?
            AND photo IS NOT NULL
            AND photo <> ''
            AND is_active     = 1
          ORDER BY product_id DESC
          LIMIT 50",
        [$brand_id, $category_id, $blaze_wpu_for_lookup]
    );
    $sim_seen = [];
    foreach ($sim_rows as $sr) {
        $u = trim((string) ($sr['photo'] ?? ''));
        if ($u === '' || isset($sim_seen[$u])) continue;
        $sim_seen[$u] = true;
        $similar_product_urls[] = $u;
        if (count($similar_product_urls) >= 5) break;
    }
}

// --------------------------------------------------------
// Step 2b: Non-Drive / non-Dropbox brand folder URL → Serper site: search
// --------------------------------------------------------
$brand_site_urls = [];
if ($brand_site_domain !== null) {
    $brand_site_q    = 'site:' . $brand_site_domain . ' "' . $search_name . '"';
    $brand_site_urls = pis_serper_search($brand_site_q, $serper_key, 5);
}

$trusted_q    = '(site:weedmaps.com OR site:leafly.com OR site:dutchie.com) "' . $query . '"';
$web_q        = '"' . $query . '" cannabis product packaging -site:pinterest.com';
$trusted_urls = pis_serper_search($trusted_q, $serper_key, 5);
$web_urls     = pis_serper_search($web_q,     $serper_key, 5);

// --------------------------------------------------------
// Combine: Brand Folder (Drive or Dropbox) → Master Drive → Similar Products → Brand Site → Trusted Menu → Web
// --------------------------------------------------------
$all_urls      = [];
$image_sources = [];
$seen          = [];

$folder_label = $brand_folder_source ?: 'Brand Drive Folder';
foreach ($brand_folder_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = $folder_label;
        $seen[$url]      = true;
    }
}

foreach ($master_drive_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = 'Google Drive';
        $seen[$url]      = true;
    }
}

foreach ($similar_product_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = 'Similar Product in Blaze';
        $seen[$url]      = true;
    }
}

foreach ($brand_site_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = 'Brand Site';
        $seen[$url]      = true;
    }
}

foreach ($trusted_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = 'Trusted Menu';
        $seen[$url]      = true;
    }
}

foreach ($web_urls as $url) {
    if (!isset($seen[$url])) {
        $all_urls[]      = $url;
        $image_sources[] = 'Web Search';
        $seen[$url]      = true;
    }
}

if (empty($all_urls)) {
    echo json_encode(['success' => false, 'error' => 'No images found for that query.']);
    exit;
}

// Overall source label for the status badge
$sources = [];
if (!empty($brand_folder_urls))    $sources[] = $folder_label;
if (!empty($master_drive_urls))    $sources[] = 'Google Drive';
if (!empty($similar_product_urls)) $sources[] = 'Similar Product in Blaze';
if (!empty($brand_site_urls))      $sources[] = 'Brand Site';
if (!empty($trusted_urls))         $sources[] = 'Trusted Menu';
if (!empty($web_urls))             $sources[] = 'Web Search';
$image_source = implode(' + ', $sources) ?: 'Web Search';

echo json_encode([
    'success'       => true,
    'images'        => $all_urls,
    'image_sources' => $image_sources,
    'search_query'  => $query,
    'image_source'  => $image_source,
]);
