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

header('Cache-Control: no-cache');
header('Content-type: application/json');

$query      = trim((string) ($_POST['query'] ?? ''));
$name       = trim((string) ($_POST['name']  ?? ''));
$brand      = trim((string) ($_POST['brand'] ?? ''));

if ($query === '') {
    echo json_encode(['success' => false, 'error' => 'Missing query.']);
    exit;
}

$serper_key = 'b3c39559a928534f00749286e3b8503856c72c02';

// --------------------------------------------------------
// Step 1: Google Drive fuzzy match (re-uses cached index)
// --------------------------------------------------------
$drive_image_url = null;
$drive_source    = '';

$creds_path = BASE_PATH . 'credentials/service-account.json';
if (file_exists($creds_path) && ($name !== '' || $brand !== '')) {
    $creds = json_decode((string) file_get_contents($creds_path), true);
    if (is_array($creds) && !empty($creds['private_key'])) {

        $gemini_key = getenv('GEMINI_API_KEY');
        if ($gemini_key === false || $gemini_key === '') {
            $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
        }

        if ($gemini_key !== '') {
            // Re-use cached Drive index (built by product-enrich.php, good for 4 hours)
            $file_index = gd_get_index($creds, '1YQSjGTVXYiQP5jBSku2HdRtpbCUvklQx');

            if (!empty($file_index)) {
                // Strip Serper operators from the user's typed query so Gemini
                // gets plain text (e.g. "graype" not "(site:weedmaps.com...) graype").
                $clean_query = preg_replace('/\(site:[^)]+\)|site:\S+|-site:\S+|"/', '', $query);
                $clean_query = trim(preg_replace('/\s+/', ' ', $clean_query));

                // Use the user's typed query as the primary Drive search term —
                // that's the whole point of "Search Again". Fall back to the
                // product name from the form only if the query is blank.
                $search_name  = $clean_query !== '' ? $clean_query : $name;
                $search_brand = $brand !== '' ? $brand : '';

                $file_id = gd_gemini_match($search_name, $search_brand, $file_index, $gemini_key);

                if ($file_id !== null) {
                    $drive_service = gd_make_drive_service($creds);
                    $local_url = gd_download_and_resize(
                        $drive_service,
                        $file_id,
                        BASE_PATH . 'public/tmp/enrichment',
                        BASE_URL . '/public/tmp/enrichment'
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
// Step 2: Serper.dev search with the user's custom query
// --------------------------------------------------------
$ch = curl_init('https://google.serper.dev/images');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'X-API-KEY: ' . $serper_key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => (string) json_encode(['q' => $query, 'num' => 10]),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$serper_urls = [];
if ($response !== false && !$curlErr && $httpCode < 300) {
    $json   = json_decode($response, true);
    $images = $json['images'] ?? [];
    foreach ((array) $images as $img) {
        $url = trim((string) ($img['imageUrl'] ?? ''));
        if ($url !== '') $serper_urls[] = $url;
        if (count($serper_urls) >= 10) break;
    }
}

// --------------------------------------------------------
// Combine: Drive first, then Serper — build parallel sources[]
// --------------------------------------------------------
$all_urls      = [];
$image_sources = [];

if ($drive_image_url !== null) {
    $all_urls[]      = $drive_image_url;
    $image_sources[] = 'Google Drive';
}

$seen = $drive_image_url !== null ? [$drive_image_url => true] : [];
foreach ($serper_urls as $url) {
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
if ($drive_source !== '')  $sources[] = 'Google Drive';
if (!empty($serper_urls))  $sources[] = 'Web Search';
$image_source = implode(' + ', $sources) ?: 'Web Search';

echo json_encode([
    'success'       => true,
    'images'        => $all_urls,
    'image_sources' => $image_sources,
    'search_query'  => $query,
    'image_source'  => $image_source,
]);
