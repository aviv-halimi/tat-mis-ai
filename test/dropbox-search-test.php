<?php
/**
 * Dropbox Search Diagnostic
 * --------------------------
 * Tests the full Dropbox enrichment pipeline:
 *   1. Accessibility check (HEAD request — no token needed)
 *   2. File listing via Dropbox API v2 (requires DROPBOX_ACCESS_TOKEN)
 *   3. Keyword pre-filter
 *   4. Gemini fuzzy match
 *   5. Render matching images directly from ?dl=1 URLs
 *
 * Usage: /test/dropbox-search-test.php
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/DropboxHelper.php';

$submitted      = isset($_POST['run']);
$input_link     = trim((string) ($_POST['dropbox_link'] ?? ''));
$input_query    = trim((string) ($_POST['search_query'] ?? ''));
$force_recrawl  = !empty($_POST['force_recrawl']);

define('DBX_TEST_TMP_DIR', BASE_PATH . 'public/tmp/enrichment');
define('DBX_TEST_TMP_URL', BASE_URL  . '/public/tmp/enrichment');

// ---- helper: flush a styled log line immediately ----
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dropbox Search Diagnostic</title>
<style>
  body  { font-family: monospace; font-size: 13px; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 20px; }
  h1    { color: #9cdcfe; margin-bottom: 4px; font-size: 18px; }
  p.sub { color: #888; margin-top: 0; font-size: 11px; }
  form  { background: #252526; border: 1px solid #3c3c3c; border-radius: 6px; padding: 16px; margin-bottom: 20px; max-width: 820px; }
  label { display: block; color: #9cdcfe; margin-bottom: 4px; font-size: 12px; }
  input[type=text] {
    width: 100%; box-sizing: border-box; background: #3c3c3c; border: 1px solid #555;
    color: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace;
    font-size: 13px; margin-bottom: 10px;
  }
  .row  { display: flex; gap: 12px; }
  .row > div { flex: 1; }
  .check-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #ccc; font-size: 12px; }
  button { background: #0e639c; color: #fff; border: none; border-radius: 4px; padding: 8px 18px; font-size: 13px; cursor: pointer; }
  button:hover { background: #1177bb; }
  #log  { background: #1e1e1e; border: 1px solid #3c3c3c; border-radius: 6px; padding: 14px; max-width: 980px; line-height: 1.9; }
  .log-step  { color: #569cd6; font-weight: bold; margin-top: 10px; }
  .log-info  { color: #d4d4d4; }
  .log-ok    { color: #4ec9b0; }
  .log-warn  { color: #ce9178; }
  .log-error { color: #f44747; }
  .log-skip  { color: #888; font-style: italic; }
  pre.detail {
    background: #252526; border: 1px solid #3c3c3c; border-radius: 4px;
    padding: 8px; margin: 4px 0 0 22px; font-size: 11px; white-space: pre-wrap;
    word-break: break-all; color: #b5cea8; max-height: 220px; overflow: auto;
  }
  .results-grid {
    display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px;
  }
  .result-card {
    border: 2px solid #4ec9b0; border-radius: 6px; overflow: hidden;
    max-width: 200px; background: #252526;
  }
  .result-card img  { display: block; width: 200px; height: 200px; object-fit: contain; background: #fff; }
  .result-card .caption {
    font-size: 10px; color: #9cdcfe; padding: 4px 6px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .result-card .caption a { color: #9cdcfe; text-decoration: none; }
  .result-card .caption a:hover { text-decoration: underline; }
</style>
</head>
<body>

<h1>📦 Dropbox Search Diagnostic</h1>
<p class="sub">
  Paste a Dropbox shared-folder link and a search term.
  The tool lists all image files, runs keyword pre-filtering, then sends the candidates to Gemini for fuzzy matching.
  No database writes.
</p>

<form method="post">
  <label>Dropbox Shared Folder URL</label>
  <input type="text" name="dropbox_link"
         value="<?= htmlspecialchars($input_link) ?>"
         placeholder="https://www.dropbox.com/sh/xxxxxxxxxx/…"
         autofocus>

  <div class="row">
    <div>
      <label>Search / Product Name</label>
      <input type="text" name="search_query"
             value="<?= htmlspecialchars($input_query) ?>"
             placeholder="e.g. Lemon Cherry Runtz or just a strain word like graype">
    </div>
  </div>

  <div class="check-row">
    <input type="checkbox" name="force_recrawl" id="force_recrawl" <?= $force_recrawl ? 'checked' : '' ?>>
    <label for="force_recrawl" style="margin:0;">Force re-list Dropbox folder (ignore 4-hour cache)</label>
  </div>
  <button type="submit" name="run" value="1">▶ Run Test</button>
</form>

<?php if (!$submitted): ?>
</body></html>
<?php exit; endif; ?>

<div id="log">
<?php
// ================================================================
// STEP 1: Validate inputs
// ================================================================
log_line('step', 'STEP 1 — Input validation');

if ($input_link === '') {
    log_line('error', 'Dropbox link is required.');
    echo '</div></body></html>'; exit;
}
if ($input_query === '') {
    log_line('error', 'Search query is required.');
    echo '</div></body></html>'; exit;
}
if (!dbx_is_dropbox_url($input_link)) {
    log_line('error', 'URL does not look like a Dropbox link: "' . $input_link . '"');
    echo '</div></body></html>'; exit;
}

log_line('ok',   'Dropbox link: "' . $input_link . '"');
log_line('ok',   'Search query: "' . $input_query . '"');

// Normalise: strip dl= but keep rlkey and other params
$api_link = dbx_strip_dl_param($input_link);
log_line('info', 'API link (dl= stripped, rlkey preserved): "' . $api_link . '"');
log_line('info', 'Files will be downloaded server-side via sharing/get_shared_link_file — no direct URL construction needed.');

// ================================================================
// STEP 2: Accessibility check (no token required)
// ================================================================
log_line('step', 'STEP 2 — Accessibility check (HEAD request)');

$access_start = microtime(true);
$access_status = dbx_test_accessibility($input_link);
$access_ms = round((microtime(true) - $access_start) * 1000);

if ($access_status === 'dropbox_ok') {
    log_line('ok',   "Folder is publicly accessible ({$access_ms} ms).");
} elseif ($access_status === 'dropbox_no_access') {
    log_line('warn', "Folder returned 401/403 — it may not be publicly shared ({$access_ms} ms).");
    log_line('info', 'Set the Dropbox sharing to "Anyone with the link" to allow access.');
    log_line('info', 'Continuing anyway — the API token may still allow access…');
} else {
    log_line('error', "HEAD request failed or returned an unexpected status ({$access_ms} ms).");
    log_line('info',  'Continuing anyway — the folder listing may still succeed with a token…');
}

// ================================================================
// STEP 3: Dropbox access token
// ================================================================
log_line('step', 'STEP 3 — Dropbox API access token');

$dbx_token = defined('DROPBOX_ACCESS_TOKEN') ? DROPBOX_ACCESS_TOKEN : '';
if ($dbx_token === '') {
    $dbx_token = (string) (getenv('DROPBOX_ACCESS_TOKEN') ?: '');
}

if ($dbx_token === '') {
    log_line('error', 'DROPBOX_ACCESS_TOKEN is not set.');
    log_line('info',  'Set the environment variable in Plesk or add define(\'DROPBOX_ACCESS_TOKEN\', \'...\') to _config.php.');
    log_line('info',  'Generate a token at https://www.dropbox.com/developers/apps → your app → "Generate access token".');
    echo '</div></body></html>'; exit;
}
log_line('ok', 'Token found (' . strlen($dbx_token) . ' chars, prefix: ' . substr($dbx_token, 0, 8) . '…).');

// ================================================================
// STEP 4: List files in the Dropbox folder
// ================================================================
log_line('step', 'STEP 4 — List files in Dropbox folder (API v2 files/list_folder)');

// Compute a stable API link (dl= stripped but rlkey kept) — matches what DropboxHelper uses
$api_link   = dbx_strip_dl_param($input_link);

// Check per-folder cache
$cache_file = DBX_TEST_TMP_DIR . '/.dbx_index_' . md5($api_link) . '.json';
$cache_ttl  = 4 * 3600;
$file_list  = null;

if ($force_recrawl && file_exists($cache_file)) {
    unlink($cache_file);
    log_line('warn', 'Cache cleared (force re-list requested).');
}

if (!$force_recrawl && file_exists($cache_file)) {
    $cached    = json_decode((string) file_get_contents($cache_file), true);
    $cache_age = is_array($cached) ? (time() - (int) ($cached['built_at'] ?? 0)) : null;
    if (is_array($cached) && isset($cached['files']) && $cache_age !== null && $cache_age < $cache_ttl) {
        $file_list = (array) $cached['files'];
        log_line('ok', sprintf('Loaded from cache — %d files, %s old.',
            count($file_list), gmdate('H\hi\ms\s', $cache_age)));
    } else {
        log_line('warn', 'Cache stale or invalid — re-listing.');
    }
}

if ($file_list === null) {
    log_line('info', 'Calling Dropbox API: POST https://api.dropboxapi.com/2/files/list_folder');
    log_line('info', 'Shared link (rlkey preserved): "' . $api_link . '"');
    log_line('info', 'Note: recursive=true is not supported for shared links — traversing subfolders manually.');

    $image_exts      = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];
    $file_list       = [];
    $all_entries     = 0;
    $folder_queue    = [''];   // '' = root of the shared folder
    $folders_done    = 0;
    $max_folders     = 50;
    $list_start      = microtime(true);
    $request_num     = 0;

    while (!empty($folder_queue) && $folders_done < $max_folders) {
        $current_path = array_shift($folder_queue);
        $folders_done++;
        $label = $current_path === '' ? '(root)' : $current_path;
        log_line('info', "Listing folder {$folders_done}: {$label}");

        $cursor   = null;
        $has_more = true;

        while ($has_more) {
            $request_num++;
            if ($cursor === null) {
                $endpoint = 'https://api.dropboxapi.com/2/files/list_folder';
                $body     = json_encode([
                    'path'                           => $current_path,
                    'shared_link'                    => ['url' => $api_link],
                    'recursive'                      => false,
                    'include_non_downloadable_files' => false,
                ]);
            } else {
                $endpoint = 'https://api.dropboxapi.com/2/files/list_folder/continue';
                $body     = json_encode(['cursor' => $cursor]);
            }

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $dbx_token,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp     = curl_exec($ch);
            $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($curl_err) {
                log_line('error', "Request #{$request_num} cURL error: {$curl_err}");
                $has_more = false; break;
            }
            if ($http >= 300) {
                $err_body   = json_decode((string) $resp, true);
                $err_detail = $err_body['error_summary'] ?? (string) $resp;
                log_line('error', "Request #{$request_num} HTTP {$http} for folder \"{$label}\".", $err_detail);
                $has_more = false; break;
            }

            $data = json_decode($resp, true);
            if (!is_array($data)) {
                log_line('error', "Request #{$request_num}: could not parse JSON response.", (string) $resp);
                $has_more = false; break;
            }

            $page_entries = (array) ($data['entries'] ?? []);
            $all_entries += count($page_entries);
            $img_this_page = 0;

            foreach ($page_entries as $entry) {
                $tag  = (string) ($entry['.tag']      ?? '');
                $name = (string) ($entry['name']       ?? '');
                $path = (string) ($entry['path_lower'] ?? '');

                if ($tag === 'folder' && $path !== '') {
                    $folder_queue[] = $path;
                    log_line('info', "  Found subfolder: {$path} (queued for traversal)");
                } elseif ($tag === 'file' && $name !== '' && $path !== '') {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, $image_exts, true)) {
                        $file_list[] = ['name' => $name, 'path' => $path];
                        $img_this_page++;
                    }
                }
            }

            if ($img_this_page > 0) {
                log_line('ok', "  {$img_this_page} image(s) found in \"{$label}\".");
            }

            $has_more = (bool) ($data['has_more'] ?? false);
            $cursor   = $has_more ? (string) ($data['cursor'] ?? '') : null;
        }
    }

    if ($folders_done >= $max_folders && !empty($folder_queue)) {
        log_line('warn', "Stopped after {$max_folders} folders (safety cap). " . count($folder_queue) . ' folder(s) not traversed.');
    }

    $list_ms = round((microtime(true) - $list_start) * 1000);

    if (empty($file_list)) {
        log_line('warn', "Traversal complete in {$list_ms} ms — 0 image files found.");
        log_line('info', "Total API entries: {$all_entries} across {$folders_done} folder(s).");
        if ($access_status !== 'dropbox_ok') {
            log_line('info', 'Note: accessibility check failed earlier — the token may also lack access to this specific folder.');
        }
        echo '</div></body></html>'; exit;
    }

    log_line('ok', sprintf('Traversal complete in %d ms — %d image file(s) found across %d folder(s), %d total entries.',
        $list_ms, count($file_list), $folders_done, $all_entries));

    // Cache for next 4 hours
    @mkdir(DBX_TEST_TMP_DIR, 0755, true);
    file_put_contents($cache_file, (string) json_encode(['built_at' => time(), 'files' => $file_list]));
    log_line('info', 'File list cached for 4 hours → ' . basename($cache_file));
}

// Show a sample of the index
$sample = array_slice($file_list, 0, 10);
$sample_text = implode("\n", array_map(fn($f) => $f['path'] . '  |  ' . $f['name'], $sample));
if (count($file_list) > 10) $sample_text .= "\n… and " . (count($file_list) - 10) . ' more';
log_line('info', 'File index sample:', $sample_text);

// ================================================================
// STEP 5: Keyword pre-filter
// ================================================================
log_line('step', 'STEP 5 — Keyword pre-filter');

$search_words = array_filter(
    preg_split('/\s+/', strtolower($input_query)),
    fn(string $w) => strlen($w) >= 3
);
log_line('info', 'Keywords (3+ chars): ' . implode(', ', array_map(fn($w) => '"' . $w . '"', $search_words)));

$filtered = array_values(array_filter(
    $file_list,
    function (array $f) use ($search_words): bool {
        $lower = strtolower($f['name']);
        foreach ($search_words as $w) {
            if (str_contains($lower, $w)) return true;
        }
        return false;
    }
));

if (empty($filtered)) {
    log_line('warn', 'Pre-filter matched 0 files. Sending full index to Gemini (' . count($file_list) . ' files).');
    $gemini_index = count($file_list) <= 500 ? $file_list : array_slice($file_list, 0, 500);
    if (count($file_list) > 500) log_line('warn', 'Index truncated to 500 for Gemini prompt.');
} else {
    log_line('ok', count($filtered) . ' file(s) matched keyword filter.');
    $filt_text = implode("\n", array_map(fn($f) => $f['path'] . '  |  ' . $f['name'], $filtered));
    log_line('info', 'Filtered candidates:', $filt_text);
    $gemini_index = $filtered;
}

// ================================================================
// STEP 6: Gemini API key
// ================================================================
log_line('step', 'STEP 6 — Gemini API key');

$gemini_key = getenv('GEMINI_API_KEY');
if ($gemini_key === false || $gemini_key === '') {
    $gemini_key = (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') ? GEMINI_API_KEY : '';
}
if ($gemini_key === '') {
    log_line('error', 'GEMINI_API_KEY is not set — cannot run fuzzy match.');
    echo '</div></body></html>'; exit;
}
$model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
log_line('ok', 'Gemini key loaded. Model: ' . $model);

// ================================================================
// STEP 7: Gemini fuzzy match
// ================================================================
log_line('step', 'STEP 7 — Gemini fuzzy match (up to 5 results)');

$filename_list = implode(
    "\n",
    array_map(fn(array $f) => $f['path'] . ' | ' . $f['name'], $gemini_index)
);

$max    = 5;
$prompt =
    "You are an expert inventory librarian for a cannabis company.\n" .
    "Goal: Match the product \"{$input_query}\" to the best possible image file(s) from the following list.\n" .
    "Filename List:\n{$filename_list}\n\n" .
    "Rules:\n" .
    "- Prioritize files that match the product/strain name.\n" .
    "- Ignore file extensions when matching.\n" .
    "- If multiple versions exist, prefer the one that looks most like a final asset.\n" .
    "- Return up to {$max} file paths (the part before the ' | '), one per line, ranked best-to-worst.\n" .
    "  Only include paths with a genuine match. If no reasonable match exists, return the single word NULL.";

log_line('info', 'Gemini prompt:', $prompt);

$gurl = 'https://generativelanguage.googleapis.com/v1beta/models/' .
        urlencode($model) . ':generateContent?key=' . urlencode($gemini_key);

$ch = curl_init($gurl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => (string) json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => $max * 80],
    ]),
    CURLOPT_TIMEOUT => 60,
]);
$t0       = microtime(true);
$gresp    = curl_exec($ch);
$gcurl_err = curl_error($ch);
$ghttp    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$gms = round((microtime(true) - $t0) * 1000);

if ($gcurl_err) {
    log_line('error', "Gemini cURL error: {$gcurl_err}");
    echo '</div></body></html>'; exit;
}
if ($ghttp >= 300) {
    log_line('error', "Gemini HTTP {$ghttp}.", (string) $gresp);
    echo '</div></body></html>'; exit;
}

$gjson      = json_decode($gresp, true);
$raw_result = trim((string) ($gjson['candidates'][0]['content']['parts'][0]['text'] ?? ''));
log_line('ok', "Gemini replied in {$gms} ms.");
log_line('info', 'Raw Gemini response:', $raw_result);

if ($raw_result === '' || strtoupper(trim($raw_result)) === 'NULL') {
    log_line('warn', 'Gemini returned NULL — no matching files found in this folder.');
    echo '</div></body></html>'; exit;
}

// ================================================================
// STEP 8: Map Gemini paths → file entries
// ================================================================
log_line('step', 'STEP 8 — Map Gemini paths → file entries');

$path_map = [];
foreach ($gemini_index as $f) {
    $path_map[$f['path']] = $f;
}

$matched = [];
foreach (preg_split('/\r?\n/', $raw_result) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    if (preg_match('#(/[^\s|,]+)#', $line, $m)) {
        $path = strtolower(rtrim(trim($m[1]), '.,;'));
        if (isset($path_map[$path])) {
            $entry = $path_map[$path];
            if (!isset($matched[$path])) {
                $matched[$path] = $entry;
                log_line('ok', 'Mapped: "' . $path . '" → "' . $entry['name'] . '"');
            }
        } else {
            log_line('warn', 'Path not found in index (possible Gemini hallucination): "' . $path . '"');
        }
    }
    if (count($matched) >= $max) break;
}

if (empty($matched)) {
    log_line('error', 'Could not map any Gemini-returned paths to indexed files.');
    echo '</div></body></html>'; exit;
}

log_line('ok', count($matched) . ' match(es) identified. Downloading via sharing/get_shared_link_file…');

// ================================================================
// STEP 9: Download + resize each matched file (server-side)
// ================================================================
log_line('step', 'STEP 9 — Download & resize matched files (Dropbox API → local 800×800 JPEG)');
log_line('info', 'Using endpoint: POST https://content.dropboxapi.com/2/sharing/get_shared_link_file');

@mkdir(DBX_TEST_TMP_DIR, 0755, true);

$result_images = [];
foreach ($matched as $path => $entry) {
    $dl_start = microtime(true);
    log_line('info', 'Downloading "' . $entry['name'] . '" (path: ' . $path . ')…');

    $local_url = dbx_download_and_resize(
        $input_link,
        $entry['path'],
        $entry['name'],
        $dbx_token,
        DBX_TEST_TMP_DIR,
        DBX_TEST_TMP_URL
    );

    $dl_ms = round((microtime(true) - $dl_start) * 1000);

    if ($local_url === null) {
        log_line('error', 'Download/resize failed for "' . $entry['name'] . '" in ' . $dl_ms . ' ms.');
        log_line('info', 'Possible causes: token lacks access, file is not an image GD can decode (PDF/SVG), or tmp dir is not writable.');
    } else {
        log_line('ok', 'Saved in ' . $dl_ms . ' ms → ' . $local_url);
        $result_images[] = ['url' => $local_url, 'name' => $entry['name']];
    }
}

if (empty($result_images)) {
    log_line('error', 'All downloads failed. Check token permissions and tmp directory writeability.');
    echo '</div></body></html>'; exit;
}

// ================================================================
// RESULT — render images
// ================================================================
echo '<div class="log-step" style="margin-top:16px;">🏁 RESULT — ' . count($result_images) . ' image(s) from Brand Dropbox Folder</div>';
echo '<div class="results-grid">';
foreach ($result_images as $img) {
    $img_url  = htmlspecialchars($img['url']);
    $img_name = htmlspecialchars($img['name']);
    echo '
    <div class="result-card">
      <img src="' . $img_url . '" alt="' . $img_name . '" loading="lazy">
      <div class="caption">
        <a href="' . $img_url . '" target="_blank">' . $img_name . '</a>
      </div>
    </div>';
}
echo '</div>';

?>
</div>
</body>
</html>
