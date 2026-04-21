<?php
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';
require_once BASE_PATH . 'inc/DropboxHelper.php';

// Progress is written to a temp JSON file so the frontend can poll it.
// This avoids all server-side output-buffering issues with streaming approaches.
@ini_set('memory_limit', '512M');   // GD + large Dropbox images need headroom

header('Cache-Control: no-cache');
header('Content-Type: application/json');

$po_product_id = isset($_POST['id'])       ? (int)    trim($_POST['id'])       : 0;
$product_name  = isset($_POST['name'])     ? trim((string) $_POST['name'])     : '';
$brand_name    = isset($_POST['brand'])    ? trim((string) $_POST['brand'])    : '';
$category_name = isset($_POST['category']) ? trim((string) $_POST['category']) : '';
$brand_id      = isset($_POST['brand_id'])    ? (int) $_POST['brand_id']    : 0;
$category_id   = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
$store_db      = isset($_POST['store_db'])    ? preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['store_db'])) : '';
$job_id        = isset($_POST['job_id'])       ? preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['job_id'])) : '';

if ($po_product_id <= 0 || $product_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing product id or name for enrichment.']);
    exit;
}

// Release the session lock now — auth is verified, and the rest of this script
// only does DB queries, API calls, and file writes. Releasing the lock lets the
// browser's concurrent polling requests to enrich-progress.php run freely.
session_write_close();

// ── Progress helpers ──────────────────────────────────────────────────────────
define('ENRICH_PROGRESS_DIR', BASE_PATH . 'public/tmp/enrichment');

function enrich_progress_file(): string {
    global $job_id;
    return ENRICH_PROGRESS_DIR . '/progress_' . $job_id . '.json';
}

/** Write/update a single step in the progress file. */
function enrich_step(string $step_id, array $data): void {
    global $job_id;
    if ($job_id === '') return;
    @mkdir(ENRICH_PROGRESS_DIR, 0755, true);
    $file    = enrich_progress_file();
    $current = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?? []) : [];
    if (!isset($current['steps'])) $current['steps'] = [];
    $current['steps'][$step_id] = $data;
    @file_put_contents($file, json_encode($current));
}

/** Mark a step as active (spinner). */
function enrich_step_start(string $step_id, string $label, array $extra = []): void {
    enrich_step($step_id, array_merge(['state' => 'active', 'label' => $label], $extra));
}

/** Mark a step as done (checkmark). */
function enrich_step_done(string $step_id, ?int $count = null): void {
    $data = ['state' => 'done'];
    if ($count !== null) $data['count'] = $count;
    enrich_step($step_id, $data);
}

/** Mark a step as skipped (strikethrough). */
function enrich_step_skip(string $step_id): void {
    enrich_step($step_id, ['state' => 'skipped']);
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

    // Retry on 429 (rate limit) / 503 (overload) with exponential backoff.
    // Honors Gemini's own retryDelay hint from the response body when present.
    $max_attempts   = 6;
    $backoff_ms     = 1500;   // starts at 1.5s, doubles each time
    $response       = false;
    $curlErr        = '';
    $httpCode       = 0;

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
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

        if ($response !== false && !$curlErr && $httpCode < 300) break;          // success
        if ($httpCode !== 429 && $httpCode !== 503) break;                       // non-retryable
        if ($attempt === $max_attempts) break;                                   // final attempt failed

        // If Gemini included a retryDelay in the 429 body, honor it (capped at 30s).
        $delay_ms = $backoff_ms;
        if ($response) {
            $body = json_decode($response, true);
            if (is_array($body)) {
                $details = $body['error']['details'] ?? [];
                foreach ($details as $d) {
                    if (!empty($d['retryDelay']) && preg_match('/^(\d+(?:\.\d+)?)s$/', $d['retryDelay'], $m)) {
                        $hinted = (int) round(((float) $m[1]) * 1000);
                        if ($hinted > 0 && $hinted < 30000) $delay_ms = max($delay_ms, $hinted);
                        break;
                    }
                }
            }
        }

        usleep($delay_ms * 1000);
        $backoff_ms *= 2;
    }

    if ($response === false || $curlErr || $httpCode >= 300) {
        // Log the full 429/error body so we can diagnose which quota hit
        // (per-minute, per-day, token-based, etc.) against the Google Cloud quota page.
        $log_dir = dirname(__FILE__) . '/../public/tmp/enrichment';
        @mkdir($log_dir, 0755, true);
        @file_put_contents(
            $log_dir . '/gemini-errors.log',
            '[' . date('Y-m-d H:i:s') . "] model={$model} http={$httpCode} curlErr={$curlErr}\n"
            . "response: " . substr((string) $response, 0, 2000) . "\n\n",
            FILE_APPEND
        );

        // Pull the quota-violation detail out of the body if present (paid-tier users
        // typically hit this when a specific RPM/TPM quota on the key/project is too low).
        $detail = '';
        if ($response) {
            $body = json_decode($response, true);
            if (is_array($body)) {
                foreach (($body['error']['details'] ?? []) as $d) {
                    if (($d['@type'] ?? '') === 'type.googleapis.com/google.rpc.QuotaFailure') {
                        foreach (($d['violations'] ?? []) as $v) {
                            $detail = trim(($v['quotaId'] ?? '') . ' ' . ($v['quotaMetric'] ?? ''));
                            if ($detail !== '') break 2;
                        }
                    }
                }
                if ($detail === '' && !empty($body['error']['message'])) {
                    $detail = substr((string) $body['error']['message'], 0, 200);
                }
            }
        }

        $error = 'Gemini description request failed'
               . ($curlErr ? ': ' . $curlErr : '')
               . ($httpCode ? " (HTTP {$httpCode})" : '')
               . ($detail  ? " — {$detail}" : '');
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
 * Extract the Blaze flowerType from parenthetical hints in the product name.
 * (I/H) → Indica-Dominant, (S/H) → Sativa-Dominant,
 * (I) → Indica, (S) → Sativa, (H) → Hybrid.
 * Returns one of the accepted Blaze values, or '' if unrecognised.
 */
function tat_extract_flower_type(string $name): string
{
    if (preg_match('/\(I\/H\)/i', $name)) return 'Indica-Dominant';
    if (preg_match('/\(S\/H\)/i', $name)) return 'Sativa-Dominant';
    if (preg_match('/\(I\)/i',    $name)) return 'Indica';
    if (preg_match('/\(S\)/i',    $name)) return 'Sativa';
    if (preg_match('/\(H\)/i',    $name)) return 'Hybrid';
    return '';
}

/**
 * Extract weight information from a product name (e.g., "3.5g", "100mg").
 * Maps to Blaze weightPerUnit values; returns customGramType/customWeight only
 * when weightPerUnit = "Custom Weight".
 *
 * Returns array with keys: weight_per_unit, custom_gram_type, custom_weight.
 */
function tat_extract_weight_info(string $name): array
{
    $grams = null;

    // mg first (100mg → 0.1g using factor 0.001)
    if (preg_match('/(\d+(?:\.\d+)?)\s*mg/i', $name, $m)) {
        $grams = round((float) $m[1] * 0.001, 4);
    }
    // grams — word-boundary safe so "1g" doesn't clash with "10g"
    elseif (preg_match('/(\d+(?:\.\d+)?)\s*g(?:ram)?s?(?!\w)/i', $name, $m)) {
        $grams = (float) $m[1];
    }

    if ($grams === null) {
        return ['weight_per_unit' => 'Each', 'custom_gram_type' => null, 'custom_weight' => null];
    }

    // Exact matches for standard Blaze units (epsilon comparison for floats)
    if (abs($grams - 0.5) < 0.0001) return ['weight_per_unit' => 'Half Gram Unit',  'custom_gram_type' => null, 'custom_weight' => null];
    if (abs($grams - 1.0) < 0.0001) return ['weight_per_unit' => 'Full Gram Unit',   'custom_gram_type' => null, 'custom_weight' => null];
    if (abs($grams - 3.5) < 0.0001) return ['weight_per_unit' => 'Eighth Per Unit',  'custom_gram_type' => null, 'custom_weight' => null];

    return ['weight_per_unit' => 'Custom Weight', 'custom_gram_type' => 'Gram', 'custom_weight' => $grams];
}

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
    &$warning       = null,
    &$search_query  = null,
    &$image_sources = null,
    int $brand_id   = 0,
    string $store_db = '',
    ?callable $on_progress = null
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

    $brand_dropbox_url = null;

    if ($brand_folder_url !== null && $brand_folder_url !== '') {
        if (dbx_is_dropbox_url($brand_folder_url)) {
            $brand_dropbox_url = $brand_folder_url;
        } else {
            $brand_drive_folder_id = tat_extract_drive_folder_id($brand_folder_url);

            // Non-Drive, non-Dropbox URL: extract domain for Serper site: search
            if ($brand_drive_folder_id === null && filter_var($brand_folder_url, FILTER_VALIDATE_URL)) {
                $parsed = parse_url($brand_folder_url);
                $brand_site_domain = $parsed['host'] ?? null;
            }
        }
    }

    // --------------------------------------------------------
    // Step B2: Brand folder image search (Drive OR Dropbox) + master Drive fallback
    //
    //   Priority 1a: Brand Dropbox Folder  — if brand_folder is a Dropbox link
    //   Priority 1b: Brand Drive Folder    — if brand_folder is a Google Drive link
    //   Priority 2:  Global master Drive   — only when priority 1 found nothing
    // --------------------------------------------------------
    $brand_folder_urls    = [];   // from brand-specific folder (Drive or Dropbox)
    $brand_folder_source  = '';   // 'Brand Drive Folder' or 'Brand Dropbox Folder'
    $master_drive_urls    = [];
    $drive_service_obj    = null; // lazy-init; reused across both Drive searches

    $gemini_key = getenv('GEMINI_API_KEY');
    if ($gemini_key === false || $gemini_key === '') {
        $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
    }

    // --- Priority 1a: Brand Dropbox Folder ---
    $brand_folder_type = $brand_dropbox_url ? 'dropbox' : ($brand_drive_folder_id ? 'drive' : '');
    if ($brand_folder_type !== '') {
        $folder_label_start = $brand_folder_type === 'dropbox' ? 'Searching brand Dropbox folder…' : 'Searching brand Drive folder…';
        if ($on_progress) $on_progress('brand_images', ['state' => 'active', 'label' => $folder_label_start]);
    }

    if ($brand_dropbox_url !== null && $gemini_key !== '') {
        $dbx_token = defined('DROPBOX_ACCESS_TOKEN') ? DROPBOX_ACCESS_TOKEN : '';
        $dbx_files = dbx_get_file_list($brand_dropbox_url, $dbx_token, $gemini_key, trim($brand_name . ' ' . $cleanName));
        if (!empty($dbx_files)) {
            $matched_files = dbx_gemini_match_multi($cleanName, (string) $brand_name, $dbx_files, $gemini_key, 5);
            foreach ($matched_files as $mf) {
                $local_url = dbx_download_and_resize(
                    $brand_dropbox_url,
                    $mf['path'],
                    $mf['name'],
                    $dbx_token,
                    ENRICHMENT_TMP_DIR,
                    ENRICHMENT_TMP_URL
                );
                if ($local_url !== null) $brand_folder_urls[] = $local_url;
            }
        }
        if (!empty($brand_folder_urls)) $brand_folder_source = 'Brand Dropbox Folder';
    }

    // --- Priority 1b: Brand Google Drive Folder ---
    if (empty($brand_folder_urls)) {
        $creds_path = BASE_PATH . 'credentials/service-account.json';
        if (file_exists($creds_path)) {
            $creds = json_decode((string) file_get_contents($creds_path), true);
            if (is_array($creds) && !empty($creds['private_key']) && $gemini_key !== '') {

                if ($brand_drive_folder_id !== null) {
                    $brand_index = gd_get_index($creds, $brand_drive_folder_id);
                    if (!empty($brand_index)) {
                        $file_ids = gd_gemini_match_multi($cleanName, (string) $brand_name, $brand_index, $gemini_key, 5);
                        if (!empty($file_ids)) {
                            $drive_service_obj = gd_make_drive_service($creds);
                            foreach ($file_ids as $fid) {
                                $url = gd_download_and_resize($drive_service_obj, $fid, ENRICHMENT_TMP_DIR, ENRICHMENT_TMP_URL);
                                if ($url !== null) $brand_folder_urls[] = $url;
                            }
                        }
                    }
                }
                if (!empty($brand_folder_urls)) $brand_folder_source = 'Brand Drive Folder';

                // --- Priority 2: Global master Drive folder (only if brand folder gave nothing) ---
                if (empty($brand_folder_urls)) {
                    if ($on_progress) $on_progress('master_images', ['state' => 'active', 'label' => 'Searching master Drive folder…']);
                    $master_index = gd_get_index($creds, GD_ROOT_FOLDER_ID);
                    if (!empty($master_index)) {
                        $file_ids = gd_gemini_match_multi($cleanName, (string) $brand_name, $master_index, $gemini_key, 5);
                        if (!empty($file_ids)) {
                            if ($drive_service_obj === null) $drive_service_obj = gd_make_drive_service($creds);
                            foreach ($file_ids as $fid) {
                                $url = gd_download_and_resize($drive_service_obj, $fid, ENRICHMENT_TMP_DIR, ENRICHMENT_TMP_URL);
                                if ($url !== null) $master_drive_urls[] = $url;
                            }
                        }
                    }
                    if ($on_progress) $on_progress('master_images', ['state' => 'done', 'count' => count($master_drive_urls)]);
                } else {
                    if ($on_progress) $on_progress('master_images', ['state' => 'skipped']);
                }
            }
        }
    }

    if ($brand_folder_type !== '') {
        if ($on_progress) $on_progress('brand_images', ['state' => 'done', 'count' => count($brand_folder_urls)]);
    } else {
        if ($on_progress) $on_progress('brand_images', ['state' => 'skipped']);
    }

    // --------------------------------------------------------
    // Step B3: Non-Drive brand asset URL → Serper site: search
    // --------------------------------------------------------
    $brand_site_urls = [];
    if ($brand_site_domain !== null) {
        $brand_site_q    = 'site:' . $brand_site_domain . ' "' . $cleanName . '"';
        $brand_site_urls = tat_serper_image_search($brand_site_q, SERPER_API_KEY, 5);
    }

    // --------------------------------------------------------
    // Step B4+B5: Serper.dev — always run both queries (5 each)
    // --------------------------------------------------------
    $trusted_q = '(site:weedmaps.com OR site:leafly.com OR site:dutchie.com) "' . $namePart . '"';
    $web_q     = '"' . $namePart . '" cannabis product packaging -site:pinterest.com';

    if ($on_progress) $on_progress('trusted_search', ['state' => 'active', 'label' => 'Searching Weedmaps, Leafly & Dutchie…']);
    $trusted_urls = tat_serper_image_search($trusted_q, SERPER_API_KEY, 5);
    if ($on_progress) $on_progress('trusted_search', ['state' => 'done', 'count' => count($trusted_urls)]);

    if ($on_progress) $on_progress('web_search', ['state' => 'active', 'label' => 'Searching the web…']);
    $web_urls = tat_serper_image_search($web_q, SERPER_API_KEY, 5);
    if ($on_progress) $on_progress('web_search', ['state' => 'done', 'count' => count($web_urls)]);

    // --------------------------------------------------------
    // Combine all sources — up to 5 per source, deduped
    // Order: Brand Folder (Drive or Dropbox) → Master Drive → Brand Site → Trusted Menu → Web
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
        $warning = 'No Image Found';
        return [];
    }

    // Overall combined source label (for status badge)
    $sources = [];
    if (!empty($brand_folder_urls)) $sources[] = $folder_label;
    if (!empty($master_drive_urls)) $sources[] = 'Google Drive';
    if (!empty($brand_site_urls))   $sources[] = 'Brand Site';
    if (!empty($trusted_urls))      $sources[] = 'Trusted Menu';
    if (!empty($web_urls))          $sources[] = 'Web Search';
    $source_found = implode(' + ', $sources) ?: 'Web Search';

    // Populate the "Search Again" box with the plain search term only —
    // the trusted-site and web query wrappers are built automatically by
    // product-image-search.php when the user hits "Search Again".
    $search_query = $namePart;

    return $all_urls;
}

// ============================================================
// Step 1 — Brand & category lookup (DB queries, fast)
// ============================================================
enrich_step_start('brand_lookup', 'Looking up brand & category info…');

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
enrich_step_done('brand_lookup');

// ============================================================
// Step 2 — Generate description via Gemini
// ============================================================
enrich_step_start('description', 'Generating AI description…');
$descError   = null;
$description = tat_enrich_generate_description($product_name, $brand_name, $category_name, $descError);

// Non-fatal: if Gemini fails (rate limit, etc.), continue with empty description
// and surface the error as a warning in the response so the user can retype or retry.
if ($descError && $description === '') {
    enrich_step_skip('description');
} else {
    enrich_step_done('description');
}

// ============================================================
// Step 3–5 — Image discovery (brand folder, trusted, web)
// ============================================================
$source_found  = null;
$imageWarning  = null;
$search_query  = null;
$image_sources = [];

/** Progress callback passed into tat_enrich_discover_images */
function _enrich_progress_cb(string $step_id, array $data): void {
    enrich_step($step_id, $data);
}

$images = tat_enrich_discover_images(
    $product_name, $brand_name, $category_name,
    $source_found, $imageWarning, $search_query, $image_sources,
    $brand_id, $store_db,
    '_enrich_progress_cb'
);

$flower_type = tat_extract_flower_type($product_name);
$weight_info = tat_extract_weight_info($product_name);

// Clean up progress file (job is complete, no need to keep it)
if ($job_id !== '' && file_exists(enrich_progress_file())) {
    @unlink(enrich_progress_file());
}

// ============================================================
// Final JSON response — full enrichment result
// ============================================================
// Combine description error + image warning into a single user-visible warning
$combinedWarning = trim(implode(' | ', array_filter([$descError, $imageWarning])));

echo json_encode([
    'success'          => true,
    'description'      => $description,
    'images'           => $images,
    'image_sources'    => $image_sources,
    'source_found'     => $source_found ?: 'Web Search',
    'image_source'     => $source_found ?: 'Web Search',
    'search_query'     => $search_query ?: '',
    'warning'          => $combinedWarning !== '' ? $combinedWarning : null,
    'brand'            => $brand_name,
    'brand_id'         => $brand_id,
    'category'         => $category_name,
    'category_id'      => $category_id,
    'brands'           => $brands,
    'categories'       => $categories,
    'flower_type'      => $flower_type,
    'weight_per_unit'  => $weight_info['weight_per_unit'],
    'custom_gram_type' => $weight_info['custom_gram_type'],
    'custom_weight'    => $weight_info['custom_weight'],
]);
