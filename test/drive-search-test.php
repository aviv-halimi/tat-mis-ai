<?php
/**
 * Google Drive Search Diagnostic
 * --------------------------------
 * Standalone test page — walks through every step of the Drive image
 * discovery flow with real-time log output.
 *
 * Usage: /test/drive-search-test.php
 *   Fill in Product Name and (optionally) Brand, hit "Run Test".
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';

define('GD_TEST_ROOT_FOLDER', '1YQSjGTVXYiQP5jBSku2HdRtpbCUvklQx');

$submitted   = isset($_POST['run']);
$input_name  = trim((string) ($_POST['product_name'] ?? ''));
$input_brand = trim((string) ($_POST['brand']        ?? ''));
$force_recrawl = !empty($_POST['force_recrawl']);

// ---- helper: flush a log line to the browser immediately ----
function log_line(string $level, string $msg, string $detail = ''): void
{
    $icons = ['info' => '🔵', 'ok' => '✅', 'warn' => '⚠️', 'error' => '❌', 'step' => '🔷'];
    $icon  = $icons[$level] ?? '•';
    echo '<div class="log-' . htmlspecialchars($level) . '">';
    echo $icon . ' ' . htmlspecialchars($msg);
    if ($detail !== '') {
        echo '<pre class="detail">' . htmlspecialchars($detail) . '</pre>';
    }
    echo '</div>';
    if (ob_get_level()) ob_flush();
    flush();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Google Drive Search Diagnostic</title>
<style>
  body { font-family: monospace; font-size: 13px; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 20px; }
  h1   { color: #9cdcfe; margin-bottom: 4px; font-size: 18px; }
  p.sub { color: #888; margin-top: 0; font-size: 11px; }
  form { background: #252526; border: 1px solid #3c3c3c; border-radius: 6px; padding: 16px; margin-bottom: 20px; max-width: 620px; }
  label { display: block; color: #9cdcfe; margin-bottom: 4px; font-size: 12px; }
  input[type=text] { width: 100%; box-sizing: border-box; background: #3c3c3c; border: 1px solid #555; color: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-bottom: 10px; }
  .row { display: flex; gap: 10px; }
  .row > div { flex: 1; }
  .check-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #ccc; font-size: 12px; }
  button { background: #0e639c; color: #fff; border: none; border-radius: 4px; padding: 8px 18px; font-size: 13px; cursor: pointer; }
  button:hover { background: #1177bb; }
  #log  { background: #1e1e1e; border: 1px solid #3c3c3c; border-radius: 6px; padding: 14px; max-width: 900px; line-height: 1.9; }
  .log-step  { color: #569cd6; font-weight: bold; margin-top: 10px; }
  .log-info  { color: #d4d4d4; }
  .log-ok    { color: #4ec9b0; }
  .log-warn  { color: #ce9178; }
  .log-error { color: #f44747; }
  pre.detail { background: #252526; border: 1px solid #3c3c3c; border-radius: 4px; padding: 8px; margin: 4px 0 0 22px; font-size: 11px; white-space: pre-wrap; word-break: break-all; color: #b5cea8; max-height: 200px; overflow: auto; }
  .result-img { margin-top: 16px; border: 2px solid #4ec9b0; border-radius: 6px; max-width: 300px; display: block; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; margin-left: 8px; }
  .badge-ok   { background: #1e4620; color: #4ec9b0; }
  .badge-fail { background: #4c1f1f; color: #f44747; }
</style>
</head>
<body>

<h1>🔍 Google Drive Search Diagnostic</h1>
<p class="sub">Tests every step of the Drive → Gemini → resize pipeline. No database writes.</p>

<form method="post">
  <div class="row">
    <div>
      <label>Product Name</label>
      <input type="text" name="product_name" value="<?= htmlspecialchars($input_name) ?>" placeholder="e.g. Lemon Cherry Runtz Flower 3.5g" autofocus>
    </div>
    <div>
      <label>Brand (optional)</label>
      <input type="text" name="brand" value="<?= htmlspecialchars($input_brand) ?>" placeholder="e.g. Maven">
    </div>
  </div>
  <div class="check-row">
    <input type="checkbox" name="force_recrawl" id="force_recrawl" <?= $force_recrawl ? 'checked' : '' ?>>
    <label for="force_recrawl" style="margin:0;">Force re-crawl Drive (ignore 4-hour cache)</label>
  </div>
  <button type="submit" name="run" value="1">▶ Run Test</button>
</form>

<?php if ($submitted): ?>
<div id="log">
<?php

// ================================================================
// STEP 1: Validate inputs
// ================================================================
log_line('step', 'STEP 1 — Input validation');

if ($input_name === '') {
    log_line('error', 'Product Name is required.');
    echo '</div></body></html>';
    exit;
}
log_line('ok',   'Product name: "' . $input_name . '"');
log_line('info', 'Brand: ' . ($input_brand !== '' ? '"' . $input_brand . '"' : '(none)'));

// Strip parenthetical strain codes — same logic as product-enrich.php
$clean_name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $input_name);
$clean_name = trim(preg_replace('/\s+/', ' ', $clean_name));
if ($clean_name !== $input_name) {
    log_line('info', 'Clean name (parentheticals stripped): "' . $clean_name . '"');
} else {
    log_line('info', 'Clean name: "' . $clean_name . '" (unchanged)');
}

// ================================================================
// STEP 2: Credentials
// ================================================================
log_line('step', 'STEP 2 — Service account credentials');

$creds_path = BASE_PATH . 'credentials/service-account.json';
if (!file_exists($creds_path)) {
    log_line('error', 'credentials/service-account.json not found at: ' . $creds_path);
    echo '</div></body></html>';
    exit;
}
$creds = json_decode((string) file_get_contents($creds_path), true);
if (!is_array($creds) || empty($creds['private_key'])) {
    log_line('error', 'Credentials file is invalid or missing private_key.');
    echo '</div></body></html>';
    exit;
}
log_line('ok',   'Credentials loaded — service account: ' . ($creds['client_email'] ?? '?'));

// ================================================================
// STEP 3: Build authenticated Drive service
// ================================================================
log_line('step', 'STEP 3 — Authenticate with Google Drive API');

try {
    $drive_service = gd_make_drive_service($creds);
    log_line('ok', 'Google_Service_Drive created successfully.');
} catch (Exception $e) {
    log_line('error', 'Failed to create Drive service: ' . $e->getMessage());
    echo '</div></body></html>';
    exit;
}

// ================================================================
// STEP 4: File index (cache or fresh crawl)
// ================================================================
log_line('step', 'STEP 4 — Build / load Drive file index');
log_line('info', 'Root folder ID: ' . GD_TEST_ROOT_FOLDER);

$cache_file = BASE_PATH . 'public/tmp/enrichment/.drive_index_cache.json';
$cache_ttl  = 4 * 3600;
$cache_age  = null;
$from_cache = false;

if ($force_recrawl && file_exists($cache_file)) {
    unlink($cache_file);
    log_line('warn', 'Cache cleared (force re-crawl requested).');
}

if (!$force_recrawl && file_exists($cache_file)) {
    $cached    = json_decode((string) file_get_contents($cache_file), true);
    $cache_age = is_array($cached) ? (time() - (int) ($cached['built_at'] ?? 0)) : null;
    if (
        is_array($cached) &&
        isset($cached['folder'], $cached['files']) &&
        $cached['folder'] === GD_TEST_ROOT_FOLDER &&
        $cache_age !== null && $cache_age < $cache_ttl
    ) {
        $file_index = (array) $cached['files'];
        $from_cache = true;
        log_line('ok', sprintf(
            'Loaded from cache (%d files, %s old).',
            count($file_index),
            gmdate('H\hi\ms\s', $cache_age)
        ));
    } else {
        log_line('warn', 'Cache exists but is stale or for a different folder — re-crawling.');
    }
}

if (!$from_cache) {
    log_line('info', 'Crawling Drive folder (this may take 10–60 seconds for large folders)…');

    $crawl_start = microtime(true);
    try {
        $index   = [];
        $visited = [];
        gd_get_flat_file_index($drive_service, GD_TEST_ROOT_FOLDER, $index, $visited);
        $file_index = $index;
    } catch (Exception $e) {
        log_line('error', 'Drive crawl failed: ' . $e->getMessage());
        echo '</div></body></html>';
        exit;
    }
    $crawl_ms = round((microtime(true) - $crawl_start) * 1000);

    log_line('ok', sprintf(
        'Crawl complete — %d files indexed in %d ms (%d folders visited).',
        count($file_index),
        $crawl_ms,
        count($visited)
    ));

    // Save to cache
    @mkdir(dirname($cache_file), 0755, true);
    file_put_contents($cache_file, (string) json_encode([
        'built_at' => time(),
        'folder'   => GD_TEST_ROOT_FOLDER,
        'files'    => $file_index,
    ]));
    log_line('info', 'Index saved to cache for next 4 hours.');
}

if (empty($file_index)) {
    log_line('error', 'Index is empty — the service account may not have access to the folder. Make sure you shared it with: ' . ($creds['client_email'] ?? '?'));
    echo '</div></body></html>';
    exit;
}

// Show a sample of the index
$sample = array_slice($file_index, 0, 10);
$sample_text = implode("\n", array_map(fn($f) => $f['id'] . '  |  ' . $f['name'], $sample));
if (count($file_index) > 10) {
    $sample_text .= "\n… and " . (count($file_index) - 10) . ' more';
}
log_line('info', 'Index sample (first 10 entries):', $sample_text);

// ================================================================
// STEP 5: Keyword pre-filter
// ================================================================
log_line('step', 'STEP 5 — Keyword pre-filter');

$search_words = array_filter(
    preg_split('/\s+/', strtolower($input_brand . ' ' . $clean_name)),
    fn(string $w) => strlen($w) >= 3
);
log_line('info', 'Search keywords: ' . implode(', ', array_map(fn($w) => '"' . $w . '"', $search_words)));

$filtered = array_values(array_filter(
    $file_index,
    function (array $f) use ($search_words): bool {
        $lower = strtolower($f['name']);
        foreach ($search_words as $w) {
            if (str_contains($lower, $w)) return true;
        }
        return false;
    }
));

if (empty($filtered)) {
    log_line('warn', 'Pre-filter matched 0 files — Gemini will search the full index (' . count($file_index) . ' files).');
    $gemini_index = count($file_index) <= 500 ? $file_index : array_slice($file_index, 0, 500);
    if (count($file_index) > 500) {
        log_line('warn', 'Full index exceeds 500 entries — truncated to 500 for Gemini.');
    }
} else {
    log_line('ok', count($filtered) . ' files matched the keyword filter.');
    $filtered_text = implode("\n", array_map(fn($f) => $f['id'] . '  |  ' . $f['name'], $filtered));
    log_line('info', 'Filtered candidates:', $filtered_text);
    $gemini_index = $filtered;
}

// ================================================================
// STEP 6: Gemini fuzzy match
// ================================================================
log_line('step', 'STEP 6 — Gemini AI fuzzy match');

$gemini_key = getenv('GEMINI_API_KEY');
if ($gemini_key === false || $gemini_key === '') {
    $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
}
if ($gemini_key === '') {
    log_line('error', 'GEMINI_API_KEY is not set — cannot run fuzzy match.');
    echo '</div></body></html>';
    exit;
}
log_line('info', 'Gemini key: ' . substr($gemini_key, 0, 6) . str_repeat('*', max(0, strlen($gemini_key) - 6)));

$model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
log_line('info', 'Model: ' . $model);

// Build the exact prompt so the user can see it
$filename_list = implode(
    "\n",
    array_map(fn(array $f) => $f['id'] . ' | ' . $f['name'], $gemini_index)
);
$prompt =
    "You are an expert inventory librarian for a cannabis company.\n" .
    "Goal: Match the product \"{$input_brand} {$clean_name}\" to the best possible image file from the following list.\n" .
    "Filename List:\n{$filename_list}\n\n" .
    "Rules:\n" .
    "- Prioritize files that match both brand and strain name.\n" .
    "- Ignore file extensions when matching.\n" .
    "- If multiple versions exist (e.g., 'Product_Final' vs 'Product_V1'), pick the one that looks most like a final asset.\n" .
    "- Return ONLY the Google Drive File ID (the part before the ' | '). " .
    "If no reasonable match exists, return the single word NULL.";

log_line('info', 'Prompt sent to Gemini:', $prompt);

// Call Gemini
$gemini_start = microtime(true);
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($gemini_key);
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => (string) json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 64],
    ]),
    CURLOPT_TIMEOUT => 60,
]);
$resp     = curl_exec($ch);
$curl_err = curl_error($ch);
$http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$gemini_ms = round((microtime(true) - $gemini_start) * 1000);

if ($resp === false || $curl_err) {
    log_line('error', 'Gemini request failed: ' . $curl_err);
    echo '</div></body></html>';
    exit;
}
if ($http >= 300) {
    log_line('error', "Gemini returned HTTP {$http}.", (string) $resp);
    echo '</div></body></html>';
    exit;
}

$gjson  = json_decode($resp, true);
$raw_result = trim((string) ($gjson['candidates'][0]['content']['parts'][0]['text'] ?? ''));
log_line('ok', "Gemini responded in {$gemini_ms} ms — raw reply: \"{$raw_result}\"");

// Extract Drive file ID
$file_id = null;
if ($raw_result !== '' && strtoupper($raw_result) !== 'NULL') {
    if (preg_match('/\b([A-Za-z0-9_\-]{20,})\b/', $raw_result, $m)) {
        $file_id = $m[1];
    }
}

if ($file_id === null) {
    log_line('warn', 'Gemini returned NULL or no recognisable file ID — no Drive match found.');
    echo '</div></body></html>';
    exit;
}

// Confirm the ID is in the index
$matched_entry = null;
foreach ($gemini_index as $f) {
    if ($f['id'] === $file_id) { $matched_entry = $f; break; }
}
if ($matched_entry) {
    log_line('ok', 'Matched file: "' . $matched_entry['name'] . '"', 'ID: ' . $file_id);
} else {
    log_line('warn', 'File ID returned by Gemini was not in the index — Gemini may have hallucinated.', 'ID: ' . $file_id);
}

// ================================================================
// STEP 7: Download from Drive
// ================================================================
log_line('step', 'STEP 7 — Download file from Drive');

$dl_start = microtime(true);
try {
    $dl_response = $drive_service->files->get($file_id, ['alt' => 'media']);
    $bytes       = (string) $dl_response->getBody();
} catch (Exception $e) {
    log_line('error', 'Drive download failed: ' . $e->getMessage());
    echo '</div></body></html>';
    exit;
}
$dl_ms = round((microtime(true) - $dl_start) * 1000);

if (strlen($bytes) < 100) {
    log_line('error', 'Downloaded file is too small (' . strlen($bytes) . ' bytes) — likely not a valid image.');
    echo '</div></body></html>';
    exit;
}
log_line('ok', sprintf('Downloaded %s bytes in %d ms.', number_format(strlen($bytes)), $dl_ms));

// ================================================================
// STEP 8: Detect image type
// ================================================================
log_line('step', 'STEP 8 — Validate image data');

$src = @imagecreatefromstring($bytes);
if (!$src) {
    // Check if GD is available at all
    if (!function_exists('imagecreatefromstring')) {
        log_line('error', 'PHP GD extension is not loaded on this server — cannot resize. Showing raw download info only.');
    } else {
        log_line('error', 'GD could not decode the file as an image. It may be a PDF, SVG, or unsupported format.');
        log_line('info',  'First 64 bytes (hex):', bin2hex(substr($bytes, 0, 64)));
    }
    echo '</div></body></html>';
    exit;
}
$sw = imagesx($src);
$sh = imagesy($src);
log_line('ok', "Image decoded — original size: {$sw} × {$sh} px.");

// ================================================================
// STEP 9: Resize to 800×800 with white letterbox
// ================================================================
log_line('step', 'STEP 9 — Resize to 800×800 with white letterbox');

$canvas = imagecreatetruecolor(800, 800);
$white  = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $white);

$ratio = min(800 / $sw, 800 / $sh);
$dw    = (int) round($sw * $ratio);
$dh    = (int) round($sh * $ratio);
$dx    = (int) round((800 - $dw) / 2);
$dy    = (int) round((800 - $dh) / 2);

imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
imagedestroy($src);

log_line('info', "Scale ratio: {$ratio} → placed {$dw}×{$dh} at offset ({$dx},{$dy}).");

$out_dir  = BASE_PATH . 'public/tmp/enrichment';
@mkdir($out_dir, 0755, true);
$filename = 'drive_test_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $file_id) . '.jpg';
$out_path = $out_dir . DIRECTORY_SEPARATOR . $filename;
$out_url  = (BASE_URL !== '' ? BASE_URL : '') . '/public/tmp/enrichment/' . $filename;

$ok = imagejpeg($canvas, $out_path, 90);
imagedestroy($canvas);

if (!$ok) {
    log_line('error', 'imagejpeg() failed — check that ' . $out_dir . ' is writable.');
    echo '</div></body></html>';
    exit;
}
log_line('ok', 'Saved to: ' . $out_path);

// ================================================================
// RESULT
// ================================================================
echo '<div class="log-step" style="margin-top:16px;">🏁 RESULT</div>';
echo '<div class="log-ok">Drive match found! File: "' . htmlspecialchars($matched_entry['name'] ?? $file_id) . '"</div>';
echo '<div class="log-info">URL: <a href="' . htmlspecialchars($out_url) . '" target="_blank" style="color:#9cdcfe;">' . htmlspecialchars($out_url) . '</a></div>';
echo '<img src="' . htmlspecialchars($out_url) . '" class="result-img" alt="Drive result">';

?>
</div>
<?php endif; ?>

</body>
</html>
