<?php
/**
 * Google Drive Search Diagnostic
 * --------------------------------
 * Tests the full enrichment Drive pipeline:
 *   1. Brand-specific Drive folder (from blaze1.brand.brand_folder)
 *   2. Global master Drive folder (always tried as fallback)
 *
 * Usage: /test/drive-search-test.php
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';

define('GD_TEST_ROOT_FOLDER', '1YQSjGTVXYiQP5jBSku2HdRtpbCUvklQx');
define('GD_TEST_TMP_DIR',     BASE_PATH . 'public/tmp/enrichment');
define('GD_TEST_TMP_URL',     BASE_URL  . '/public/tmp/enrichment');

// ----------------------------------------------------------------
// Load brands from blaze1 for the pulldown
// ----------------------------------------------------------------
$all_brands = getRs(
    "SELECT brand_id, name, brand_folder FROM `blaze1`.brand
      WHERE is_active = 1 ORDER BY name",
    []
);

$submitted     = isset($_POST['run']);
$input_name    = trim((string) ($_POST['product_name'] ?? ''));
$sel_brand_id  = (int) ($_POST['brand_id'] ?? 0);
$force_recrawl = !empty($_POST['force_recrawl']);

// Find selected brand row
$sel_brand = null;
foreach ($all_brands as $b) {
    if ((int) $b['brand_id'] === $sel_brand_id) { $sel_brand = $b; break; }
}

// ---- helper: flush a log line to the browser immediately ----
function log_line(string $level, string $msg, string $detail = ''): void
{
    $icons = ['info' => '🔵', 'ok' => '✅', 'warn' => '⚠️', 'error' => '❌', 'step' => '🔷', 'skip' => '⏭️'];
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

// ---- helper: get or build drive file index with per-folder cache ----
function test_get_index(
    $drive_service, array $creds, string $folder_id,
    bool $force_recrawl, string $label
): array {
    $safe_id    = preg_replace('/[^A-Za-z0-9_\-]/', '', $folder_id);
    $cache_file = GD_TEST_TMP_DIR . '/.drive_index_' . $safe_id . '.json';
    $cache_ttl  = 4 * 3600;

    if ($force_recrawl && file_exists($cache_file)) {
        unlink($cache_file);
        log_line('warn', "[{$label}] Cache cleared (force re-crawl requested).");
    }

    if (!$force_recrawl && file_exists($cache_file)) {
        $cached    = json_decode((string) file_get_contents($cache_file), true);
        $cache_age = is_array($cached) ? (time() - (int) ($cached['built_at'] ?? 0)) : null;
        if (is_array($cached) && isset($cached['files']) && $cache_age !== null && $cache_age < $cache_ttl) {
            $file_index = (array) $cached['files'];
            log_line('ok', sprintf(
                '[%s] Loaded from cache — %d files, %s old.',
                $label, count($file_index), gmdate('H\hi\ms\s', $cache_age)
            ));
            return $file_index;
        }
        log_line('warn', "[{$label}] Cache stale — re-crawling.");
    }

    log_line('info', "[{$label}] Crawling folder ID: {$folder_id} (may take 10–60 s for large folders)…");
    $start = microtime(true);
    try {
        $index   = [];
        $visited = [];
        gd_get_flat_file_index($drive_service, $folder_id, $index, $visited);
    } catch (Exception $e) {
        log_line('error', "[{$label}] Crawl failed: " . $e->getMessage());
        return [];
    }
    $ms = round((microtime(true) - $start) * 1000);
    log_line('ok', sprintf('[%s] Crawl complete — %d files in %d ms (%d folders visited).',
        $label, count($index), $ms, count($visited)));

    @mkdir(GD_TEST_TMP_DIR, 0755, true);
    file_put_contents($cache_file, (string) json_encode([
        'built_at' => time(),
        'files'    => $index,
    ]));
    log_line('info', "[{$label}] Index cached for next 4 hours.");

    return $index;
}

// ---- helper: keyword filter + Gemini match for one folder ----
function test_drive_search(
    array $file_index, string $clean_name, string $brand_name,
    string $gemini_key, string $model, string $label
): ?string /* file_id */ {
    if (empty($file_index)) {
        log_line('warn', "[{$label}] Index is empty — skipping Gemini match.");
        return null;
    }

    // Show a sample
    $sample = array_slice($file_index, 0, 8);
    $sample_text = implode("\n", array_map(fn($f) => $f['id'] . '  |  ' . $f['name'], $sample));
    if (count($file_index) > 8) $sample_text .= "\n… and " . (count($file_index) - 8) . ' more';
    log_line('info', "[{$label}] Index sample:", $sample_text);

    // Keyword pre-filter
    $search_words = array_filter(
        preg_split('/\s+/', strtolower(trim($brand_name . ' ' . $clean_name))),
        fn(string $w) => strlen($w) >= 3
    );
    log_line('info', "[{$label}] Keywords: " . implode(', ', array_map(fn($w) => '"' . $w . '"', $search_words)));

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
        log_line('warn', "[{$label}] Pre-filter matched 0 files — sending full index to Gemini (" . count($file_index) . " files).");
        $gemini_index = count($file_index) <= 500 ? $file_index : array_slice($file_index, 0, 500);
        if (count($file_index) > 500) log_line('warn', "[{$label}] Truncated to 500 for Gemini.");
    } else {
        log_line('ok', "[{$label}] " . count($filtered) . " file(s) matched keyword filter.");
        $filtered_text = implode("\n", array_map(fn($f) => $f['id'] . '  |  ' . $f['name'], $filtered));
        log_line('info', "[{$label}] Filtered candidates:", $filtered_text);
        $gemini_index = $filtered;
    }

    // Build prompt
    $filename_list = implode("\n", array_map(fn(array $f) => $f['id'] . ' | ' . $f['name'], $gemini_index));
    $prompt =
        "You are an expert inventory librarian for a cannabis company.\n" .
        "Goal: Match the product \"{$brand_name} {$clean_name}\" to the best possible image file from the following list.\n" .
        "Filename List:\n{$filename_list}\n\n" .
        "Rules:\n" .
        "- Prioritize files that match both brand and strain name.\n" .
        "- Ignore file extensions when matching.\n" .
        "- If multiple versions exist (e.g., 'Product_Final' vs 'Product_V1'), pick the one that looks most like a final asset.\n" .
        "- Return ONLY the Google Drive File ID (the part before the ' | '). " .
        "If no reasonable match exists, return the single word NULL.";
    log_line('info', "[{$label}] Prompt sent to Gemini:", $prompt);

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
    $t0       = microtime(true);
    $resp     = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = round((microtime(true) - $t0) * 1000);

    if ($resp === false || $curl_err) {
        log_line('error', "[{$label}] Gemini request failed: " . $curl_err);
        return null;
    }
    if ($http >= 300) {
        log_line('error', "[{$label}] Gemini HTTP {$http}.", (string) $resp);
        return null;
    }

    $gjson      = json_decode($resp, true);
    $raw_result = trim((string) ($gjson['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    log_line('ok', "[{$label}] Gemini replied in {$ms} ms — raw: \"{$raw_result}\"");

    if ($raw_result === '' || strtoupper($raw_result) === 'NULL') {
        log_line('warn', "[{$label}] Gemini returned NULL — no match in this folder.");
        return null;
    }

    $file_id = null;
    if (preg_match('/\b([A-Za-z0-9_\-]{20,})\b/', $raw_result, $m)) {
        $file_id = $m[1];
    }
    if ($file_id === null) {
        log_line('warn', "[{$label}] Could not extract a file ID from Gemini response.");
        return null;
    }

    // Verify it exists in the index
    $matched = null;
    foreach ($gemini_index as $f) {
        if ($f['id'] === $file_id) { $matched = $f; break; }
    }
    if ($matched) {
        log_line('ok', "[{$label}] Matched: \"" . $matched['name'] . "\"", 'ID: ' . $file_id);
    } else {
        log_line('warn', "[{$label}] Gemini returned an ID not in the index (possible hallucination).", 'ID: ' . $file_id);
    }

    return $file_id;
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
  form { background: #252526; border: 1px solid #3c3c3c; border-radius: 6px; padding: 16px; margin-bottom: 20px; max-width: 720px; }
  label { display: block; color: #9cdcfe; margin-bottom: 4px; font-size: 12px; }
  input[type=text], select {
    width: 100%; box-sizing: border-box; background: #3c3c3c; border: 1px solid #555;
    color: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace;
    font-size: 13px; margin-bottom: 10px;
  }
  select option { background: #3c3c3c; }
  .row { display: flex; gap: 12px; }
  .row > div { flex: 1; }
  .brand-folder-note { font-size: 11px; color: #888; margin-top: -8px; margin-bottom: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .brand-folder-note a { color: #9cdcfe; }
  .check-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #ccc; font-size: 12px; }
  button { background: #0e639c; color: #fff; border: none; border-radius: 4px; padding: 8px 18px; font-size: 13px; cursor: pointer; }
  button:hover { background: #1177bb; }
  #log  { background: #1e1e1e; border: 1px solid #3c3c3c; border-radius: 6px; padding: 14px; max-width: 960px; line-height: 1.9; }
  .log-step  { color: #569cd6; font-weight: bold; margin-top: 10px; }
  .log-info  { color: #d4d4d4; }
  .log-ok    { color: #4ec9b0; }
  .log-warn  { color: #ce9178; }
  .log-error { color: #f44747; }
  .log-skip  { color: #888; font-style: italic; }
  pre.detail { background: #252526; border: 1px solid #3c3c3c; border-radius: 4px; padding: 8px; margin: 4px 0 0 22px; font-size: 11px; white-space: pre-wrap; word-break: break-all; color: #b5cea8; max-height: 200px; overflow: auto; }
  .result-img { margin-top: 16px; border: 2px solid #4ec9b0; border-radius: 6px; max-width: 320px; display: block; }
  .result-label { display: inline-block; margin-top: 8px; padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: bold; }
  .label-brand  { background: #1a3a2a; color: #4ec9b0; }
  .label-master { background: #1a2a3a; color: #9cdcfe; }
</style>
<script>
  // Show brand_folder URL under the dropdown when selection changes
  document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('brand_id');
    var note = document.getElementById('brand_folder_note');
    var folders = <?= json_encode(array_column($all_brands, 'brand_folder', 'brand_id')) ?>;

    function update() {
      var id = sel.value;
      var url = (folders[id] && folders[id] !== '') ? folders[id] : null;
      note.innerHTML = url
        ? '📁 Brand folder: <a href="' + url + '" target="_blank">' + url + '</a>'
        : '<span style="color:#555;">No brand folder set for this brand.</span>';
    }
    sel.addEventListener('change', update);
    update();
  });
</script>
</head>
<body>

<h1>🔍 Google Drive Search Diagnostic</h1>
<p class="sub">Tests brand-specific folder first, then global master folder as fallback. No database writes.</p>

<form method="post">
  <div class="row">
    <div>
      <label>Product Name</label>
      <input type="text" name="product_name" value="<?= htmlspecialchars($input_name) ?>" placeholder="e.g. Lemon Cherry Runtz Flower 3.5g" autofocus>
    </div>
    <div>
      <label>Brand (blaze1.brand)</label>
      <select name="brand_id" id="brand_id">
        <option value="0">(no brand — master folder only)</option>
        <?php foreach ($all_brands as $b): ?>
          <option value="<?= (int) $b['brand_id'] ?>" <?= (int) $b['brand_id'] === $sel_brand_id ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) $b['name']) ?>
            <?= ($b['brand_folder'] ? ' 📁' : '') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="brand-folder-note" id="brand_folder_note"></div>
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
log_line('ok', 'Product name: "' . $input_name . '"');

$clean_name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $input_name);
$clean_name = trim(preg_replace('/\s+/', ' ', $clean_name));
if ($clean_name !== $input_name) {
    log_line('info', 'Clean name (strain codes stripped): "' . $clean_name . '"');
} else {
    log_line('info', 'Clean name: "' . $clean_name . '" (unchanged)');
}

$brand_name = $sel_brand ? (string) $sel_brand['name'] : '';
if ($brand_name !== '') {
    log_line('ok',   'Brand selected: "' . $brand_name . '" (brand_id=' . $sel_brand_id . ')');
} else {
    log_line('info', 'No brand selected — will search master folder only.');
}

// ================================================================
// STEP 2: Brand folder lookup
// ================================================================
log_line('step', 'STEP 2 — Brand folder lookup');

$brand_folder_url      = $sel_brand ? ((string) ($sel_brand['brand_folder'] ?? '')) : '';
$brand_drive_folder_id = null;

if ($brand_folder_url === '') {
    log_line('skip', 'No brand folder configured for this brand — brand folder step will be skipped.');
} else {
    log_line('info', 'Brand folder value: "' . $brand_folder_url . '"');

    // Extract Drive folder ID
    if (preg_match('#/folders/([A-Za-z0-9_\-]{10,})#', $brand_folder_url, $m)) {
        $brand_drive_folder_id = $m[1];
        log_line('ok', 'Extracted Drive folder ID from URL: ' . $brand_drive_folder_id);
    } elseif (preg_match('/^[A-Za-z0-9_\-]{20,}$/', $brand_folder_url)) {
        $brand_drive_folder_id = $brand_folder_url;
        log_line('ok', 'Brand folder value is a raw Drive folder ID: ' . $brand_drive_folder_id);
    } else {
        log_line('warn', 'Brand folder is not a Google Drive URL — cannot search it as a Drive folder.');
        log_line('info', 'Value: "' . $brand_folder_url . '"');
    }
}

// ================================================================
// STEP 3: Credentials
// ================================================================
log_line('step', 'STEP 3 — Service account credentials');

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
log_line('ok', 'Service account: ' . ($creds['client_email'] ?? '?'));

// ================================================================
// STEP 4: Authenticate with Drive API
// ================================================================
log_line('step', 'STEP 4 — Authenticate with Google Drive API');

try {
    $drive_service = gd_make_drive_service($creds);
    log_line('ok', 'Google_Service_Drive created successfully.');
} catch (Exception $e) {
    log_line('error', 'Failed to create Drive service: ' . $e->getMessage());
    echo '</div></body></html>';
    exit;
}

// ================================================================
// STEP 5: Gemini key
// ================================================================
log_line('step', 'STEP 5 — Gemini API key');

$gemini_key = getenv('GEMINI_API_KEY');
if ($gemini_key === false || $gemini_key === '') {
    $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
}
if ($gemini_key === '') {
    log_line('error', 'GEMINI_API_KEY is not set — cannot run fuzzy match.');
    echo '</div></body></html>';
    exit;
}
$model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
log_line('ok', 'Gemini key loaded. Model: ' . $model);

// ================================================================
// STEP 6a: Search brand-specific Drive folder
// ================================================================
$found_file_id  = null;
$found_source   = null;

if ($brand_drive_folder_id !== null) {
    log_line('step', 'STEP 6a — Search BRAND FOLDER (' . $brand_drive_folder_id . ')');

    $brand_index = test_get_index($drive_service, $creds, $brand_drive_folder_id, $force_recrawl, 'Brand Folder');

    if (!empty($brand_index)) {
        $found_file_id = test_drive_search($brand_index, $clean_name, $brand_name, $gemini_key, $model, 'Brand Folder');
        if ($found_file_id !== null) {
            $found_source = 'Brand Drive Folder';
            log_line('ok', '✨ Match found in BRAND FOLDER — skipping master folder search.');
        } else {
            log_line('warn', 'No match in brand folder — will try master folder next.');
        }
    } else {
        log_line('warn', 'Brand folder index is empty — service account may lack access.');
        log_line('info', 'Share folder with: ' . ($creds['client_email'] ?? '?'));
    }
} else {
    log_line('skip', 'STEP 6a — Brand folder search skipped (no Drive folder configured).');
}

// ================================================================
// STEP 6b: Search global master Drive folder (fallback)
// ================================================================
if ($found_file_id === null) {
    log_line('step', 'STEP 6b — Search MASTER FOLDER (' . GD_TEST_ROOT_FOLDER . ')');

    $master_index = test_get_index($drive_service, $creds, GD_TEST_ROOT_FOLDER, $force_recrawl, 'Master Folder');

    if (!empty($master_index)) {
        $found_file_id = test_drive_search($master_index, $clean_name, $brand_name, $gemini_key, $model, 'Master Folder');
        if ($found_file_id !== null) {
            $found_source = 'Google Drive (Master)';
        }
    } else {
        log_line('error', 'Master folder index is empty — check service account access to root folder.');
    }
}

// ================================================================
// STEP 7: Download from Drive
// ================================================================
if ($found_file_id === null) {
    log_line('step', 'STEP 7 — Download');
    log_line('warn', 'No Drive match found in either folder. Done.');
    echo '</div></body></html>';
    exit;
}

log_line('step', 'STEP 7 — Download matched file from Drive');

$dl_start = microtime(true);
try {
    $dl_response = $drive_service->files->get($found_file_id, ['alt' => 'media']);
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
// STEP 8: Validate image
// ================================================================
log_line('step', 'STEP 8 — Validate image data');

$src = @imagecreatefromstring($bytes);
if (!$src) {
    if (!function_exists('imagecreatefromstring')) {
        log_line('error', 'PHP GD extension is not loaded — cannot resize.');
    } else {
        log_line('error', 'GD could not decode this file as an image (may be PDF/SVG/unsupported).');
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

@mkdir(GD_TEST_TMP_DIR, 0755, true);
$filename = 'drive_test_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $found_file_id) . '.jpg';
$out_path = GD_TEST_TMP_DIR . DIRECTORY_SEPARATOR . $filename;
$out_url  = GD_TEST_TMP_URL . '/' . $filename;

$ok = imagejpeg($canvas, $out_path, 90);
imagedestroy($canvas);

if (!$ok) {
    log_line('error', 'imagejpeg() failed — check that ' . GD_TEST_TMP_DIR . ' is writable.');
    echo '</div></body></html>';
    exit;
}
log_line('ok', 'Saved to: ' . $out_path);

// ================================================================
// RESULT
// ================================================================
$label_class = ($found_source === 'Brand Drive Folder') ? 'label-brand' : 'label-master';
$label_text  = $found_source ?? 'Google Drive';

echo '<div class="log-step" style="margin-top:16px;">🏁 RESULT</div>';
echo '<div class="log-ok">Drive match found! Source: <span class="result-label ' . $label_class . '">' . htmlspecialchars($label_text) . '</span></div>';
echo '<div class="log-info">URL: <a href="' . htmlspecialchars($out_url) . '" target="_blank" style="color:#9cdcfe;">' . htmlspecialchars($out_url) . '</a></div>';
echo '<img src="' . htmlspecialchars($out_url) . '" class="result-img" alt="Drive result">';

?>
</div>
<?php endif; ?>

</body>
</html>
