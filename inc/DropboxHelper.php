<?php
/**
 * Dropbox helper for the Enrichment workflow.
 * Mirrors the public API surface of GoogleDriveHelper.php.
 * Uses raw cURL against Dropbox API v2 — no SDK required.
 *
 * Public API:
 *   dbx_is_dropbox_url(string $url): bool
 *   dbx_to_direct_url(string $url): string           — ?dl=0 / no param → ?dl=1
 *   dbx_test_accessibility(string $url): string      — 'dropbox_ok' | 'dropbox_no_access' | 'dropbox_error'
 *   dbx_get_file_list(string $shared_link, string $token): array  — [{name, path, download_url}]
 *   dbx_gemini_match_multi(string $product, string $brand, array $files, string $key, int $max): array
 */

// ============================================================
// 1. URL UTILITIES
// ============================================================

/**
 * Returns true when the URL points to Dropbox.
 */
function dbx_is_dropbox_url(string $url): bool
{
    return str_contains($url, 'dropbox.com');
}

/**
 * Converts any Dropbox link to a direct-download link (?dl=1).
 * Replaces or appends the dl= query parameter.
 */
function dbx_to_direct_url(string $url): string
{
    // Remove any existing dl= param
    $url = preg_replace('/([?&])dl=\d+/', '$1', $url);
    $url = rtrim($url, '?&');

    // Append dl=1
    return $url . (str_contains($url, '?') ? '&' : '?') . 'dl=1';
}

/**
 * Returns the shared-link URL stripped of query string (used as the
 * base for constructing per-file download URLs).
 */
function dbx_link_base(string $url): string
{
    return rtrim((string) preg_replace('/[?#].*$/', '', $url), '/');
}

// ============================================================
// 2. ACCESSIBILITY TEST  (no API token required)
// ============================================================

/**
 * Tests whether a Dropbox shared link is publicly accessible
 * by issuing a HEAD request.
 *
 * Returns:
 *   'dropbox_ok'         — reachable (2xx / 3xx)
 *   'dropbox_no_access'  — 401 / 403
 *   'dropbox_error'      — other HTTP error or cURL failure
 */
function dbx_test_accessibility(string $url): string
{
    $test_url = dbx_to_direct_url($url);

    $ch = curl_init($test_url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; AssetBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)                           return 'dropbox_error';
    if ($http >= 200 && $http < 400)    return 'dropbox_ok';
    if ($http === 401 || $http === 403) return 'dropbox_no_access';
    return 'dropbox_error';
}

// ============================================================
// 3. FILE LISTING  (requires Dropbox access token)
// ============================================================

/**
 * Strips the dl= parameter from a Dropbox URL while preserving all other
 * query parameters (e.g. rlkey, e=2). Used internally for API calls.
 */
function dbx_strip_dl_param(string $url): string
{
    $url = preg_replace('/([?&])dl=\d+(&?)/', '$1$2', $url);
    return rtrim($url, '?&');
}

/**
 * Internal: page through one folder path of a shared Dropbox link.
 * Returns ['files' => [...], 'folders' => [...]] for the entries found.
 */
function _dbx_list_one_folder(string $api_link, string $path, string $token): array
{
    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];
    $files      = [];
    $folders    = [];
    $cursor     = null;
    $has_more   = true;

    while ($has_more) {
        if ($cursor === null) {
            $endpoint = 'https://api.dropboxapi.com/2/files/list_folder';
            $body     = json_encode([
                'path'                           => $path,
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
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$resp || $http >= 300) break;

        $data = json_decode($resp, true);
        if (!is_array($data)) break;

            foreach ((array) ($data['entries'] ?? []) as $entry) {
                $tag  = (string) ($entry['.tag']  ?? '');
                $name = (string) ($entry['name']  ?? '');
                if ($name === '') continue;

                // Dropbox /scl/fo/ shared-link listings often omit path_lower for
                // root-level entries. Fall back through path_display, then construct
                // from the folder/file name relative to $path.
                $path_lower = (string) ($entry['path_lower']   ?? '');
                $path_disp  = (string) ($entry['path_display'] ?? '');

                if ($path_lower !== '') {
                    $epath = $path_lower;
                } elseif ($path_disp !== '') {
                    $epath = strtolower($path_disp);
                } else {
                    // Construct relative path: parent path + / + name (lowercased)
                    $epath = rtrim($path, '/') . '/' . strtolower($name);
                }

                if ($tag === 'folder') {
                    $folders[] = ['name' => $name, 'path' => $epath];
                } elseif ($tag === 'file' && $epath !== '') {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, $image_exts, true)) {
                        $files[] = ['name' => $name, 'path' => $epath];
                    }
                }
            }

        $has_more = (bool) ($data['has_more'] ?? false);
        $cursor   = $has_more ? (string) ($data['cursor'] ?? '') : null;
    }

    return ['files' => $files, 'folders' => $folders];
}

/**
 * Internal: ask Gemini to pick the ONE subfolder most likely to contain
 * images for $hint from the provided list. Returns the matched folder or null.
 */
function _dbx_gemini_pick_folder(array $subfolders, string $hint, string $gemini_key): ?array
{
    if (empty($subfolders) || $gemini_key === '' || $hint === '') return null;

    $folder_list = implode("\n", array_map(fn($f) => $f['path'] . ' | ' . $f['name'], $subfolders));
    $model  = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $prompt =
        "A Dropbox brand-asset folder for a cannabis company is organised into subfolders.\n" .
        "We are looking for product images matching: \"{$hint}\"\n" .
        "Available subfolders:\n{$folder_list}\n\n" .
        "Return the path (the part before ' | ') of the ONE subfolder most likely to lead to " .
        "images for this product. Consider both the product/strain name AND generic image-type " .
        "folders (e.g. 'Product Images', 'Product (by Strain)'). " .
        "If nothing is even remotely relevant, return NULL.";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' .
           urlencode($model) . ':generateContent?key=' . urlencode($gemini_key);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => (string) json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 128],
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $gresp  = (string) curl_exec($ch);
    curl_close($ch);
    $gjson  = json_decode($gresp, true);
    $result = trim((string) ($gjson['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    if ($result === '' || strtoupper(trim($result)) === 'NULL') return null;

    $result_lower = strtolower($result);
    foreach ($subfolders as $sf) {
        if (str_contains($result_lower, strtolower($sf['path'])) ||
            str_contains($result_lower, strtolower($sf['name']))) {
            return $sf;
        }
    }
    return null;
}

/**
 * Recursively lists all image files in a Dropbox shared folder.
 * At each level that contains subfolders, Gemini picks the most relevant one
 * to explore first (deep-first guided search), then falls through to the others.
 * This lets it drill through arbitrary nesting (e.g. root → 3_Product Images →
 * Product (by Strain) → Banana Belt) without hard-coding depth limits.
 *
 * @param  string  $api_link         dl=-stripped shared link (with rlkey)
 * @param  string  $folder_path      Current folder path relative to shared-folder root
 * @param  string  $token            Dropbox API access token
 * @param  string|null $gemini_key   Enables Gemini-guided folder selection when set
 * @param  string  $gemini_hint      Product/brand search string for Gemini
 * @param  int     &$folders_visited Running count of API calls (pass by reference)
 * @param  int     $max_folders      Hard cap on total API folder-listing calls
 * @return array<array{name:string,path:string}>
 */
function _dbx_traverse(
    string  $api_link,
    string  $folder_path,
    string  $token,
    ?string $gemini_key,
    string  $gemini_hint,
    int    &$folders_visited,
    int     $max_folders
): array {
    if ($folders_visited >= $max_folders) return [];

    $result = _dbx_list_one_folder($api_link, $folder_path, $token);
    $folders_visited++;

    $files      = $result['files'];
    $subfolders = $result['folders'];

    if (empty($subfolders)) return $files;

    // Ask Gemini to pick the best subfolder to explore first
    $best      = null;
    $gemini_ok = $gemini_key !== null && $gemini_key !== '' && $gemini_hint !== '';
    if ($gemini_ok) {
        $best = _dbx_gemini_pick_folder($subfolders, $gemini_hint, $gemini_key);
    }

    // Order: best first, then the rest
    $others  = array_values(array_filter($subfolders, fn($sf) => $sf !== $best));
    $ordered = $best ? array_merge([$best], $others) : $subfolders;

    foreach ($ordered as $sf) {
        if ($folders_visited >= $max_folders) break;
        $sub_files = _dbx_traverse(
            $api_link, $sf['path'], $token,
            $gemini_key, $gemini_hint,
            $folders_visited, $max_folders
        );
        $files = array_merge($files, $sub_files);
    }

    return $files;
}

/**
 * Public entry point: lists all image files in a Dropbox shared folder.
 * Uses Gemini to guide folder selection at each nesting level, so it finds
 * deeply-nested strain images without having to blindly crawl every subfolder.
 *
 * @param  string      $shared_link  Full shared-folder URL (any dl= value)
 * @param  string      $token        Dropbox API access token
 * @param  string|null $gemini_key   Enables guided traversal when provided
 * @param  string      $gemini_hint  Product/brand name for guidance
 * @param  int         $max_folders  Safety cap on total API calls (default 30)
 * @return array<array{name:string,path:string}>
 */
function dbx_get_file_list(
    string  $shared_link,
    string  $token,
    ?string $gemini_key  = null,
    string  $gemini_hint = '',
    int     $max_folders = 30
): array {
    if ($token === '') return [];

    $api_link        = dbx_strip_dl_param($shared_link);
    $folders_visited = 0;

    return _dbx_traverse($api_link, '', $token, $gemini_key, $gemini_hint, $folders_visited, $max_folders);
}

// ============================================================
// 4. FILE DOWNLOAD  (server-side, via sharing/get_shared_link_file)
// ============================================================

/**
 * Downloads a single file from a Dropbox shared folder using the Dropbox API.
 *
 * Uses `sharing/get_shared_link_file` which:
 *   - Works with both legacy /sh/ and newer /scl/fo/ link formats
 *   - Preserves rlkey and other params automatically
 *   - Does NOT require constructing a per-file download URL
 *
 * @param  string $shared_link  Full shared folder URL (rlkey etc. preserved)
 * @param  string $file_path    Path relative to folder root, e.g. '/product.jpg'
 * @param  string $token        Dropbox API access token
 * @return string|false         Raw file bytes, or false on failure
 */
function dbx_download_file(string $shared_link, string $file_path, string $token): string|false
{
    if ($token === '') return false;

    $api_link = dbx_strip_dl_param($shared_link);
    $arg      = json_encode(['url' => $api_link, 'path' => $file_path]);

    $ch = curl_init('https://content.dropboxapi.com/2/sharing/get_shared_link_file');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',   // empty body; params go in the header
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Dropbox-API-Arg: ' . $arg,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err || $http >= 300 || !$resp || strlen($resp) < 64) return false;
    return $resp;
}

/**
 * Downloads a Dropbox file and saves a resized 800×800 JPEG locally.
 * Mirrors gd_download_and_resize() from GoogleDriveHelper.php.
 *
 * @return string|null  Public URL to the saved image, or null on failure
 */
function dbx_download_and_resize(
    string $shared_link,
    string $file_path,
    string $file_name,
    string $token,
    string $tmp_dir,
    string $tmp_url
): ?string {
    $bytes = dbx_download_file($shared_link, $file_path, $token);
    if ($bytes === false) return null;

    $src = @imagecreatefromstring($bytes);
    if (!$src) return null;

    $sw = imagesx($src);
    $sh = imagesy($src);

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

    @mkdir($tmp_dir, 0755, true);

    $base     = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
    $safe     = 'dbx_' . $base . '_' . substr(md5($file_path), 0, 8) . '.jpg';
    $out_path = rtrim($tmp_dir, '/\\') . DIRECTORY_SEPARATOR . $safe;
    $out_url  = rtrim($tmp_url, '/') . '/' . $safe;

    $ok = imagejpeg($canvas, $out_path, 90);
    imagedestroy($canvas);

    return $ok ? $out_url : null;
}

// ============================================================
// 5. GEMINI FUZZY MATCH
// ============================================================

/**
 * Sends the Dropbox file list to Gemini and returns up to $max matched files,
 * ranked best-to-worst, as [ ['path' => '...', 'name' => '...'], ... ].
 *
 * Callers then pass each result to dbx_download_and_resize() to get a local URL.
 * This mirrors the Drive pattern (gd_gemini_match_multi → gd_download_and_resize).
 *
 * @param  string $product_name
 * @param  string $brand_name
 * @param  array  $file_list   From dbx_get_file_list()  [{name, path}]
 * @param  string $gemini_key
 * @param  int    $max
 * @return array<array{path:string,name:string}>
 */
function dbx_gemini_match_multi(
    string $product_name,
    string $brand_name,
    array  $file_list,
    string $gemini_key,
    int    $max = 5
): array {
    if (empty($file_list) || $gemini_key === '') return [];

    // Pre-filter: keep files where at least one 3+ char keyword appears in the name
    $search_words = array_filter(
        preg_split('/\s+/', strtolower(trim($brand_name . ' ' . $product_name))),
        fn(string $w) => strlen($w) >= 3
    );

    $index = $file_list;
    if (!empty($search_words)) {
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
        if (!empty($filtered)) $index = $filtered;
    }

    if (count($index) > 500) $index = array_slice($index, 0, 500);

    // Gemini gets: path | name  (path is the unique identifier it returns)
    $filename_list = implode(
        "\n",
        array_map(fn(array $f) => $f['path'] . ' | ' . $f['name'], $index)
    );

    $return_instruction = $max === 1
        ? "Return ONLY the single best-matching file path (the part before the ' | '). If no reasonable match exists, return the single word NULL."
        : "Return up to {$max} file paths, one per line, ranked best-to-worst match. Only include paths with a genuine match. If no reasonable match exists, return the single word NULL.";

    $prompt =
        "You are an expert inventory librarian for a cannabis company.\n" .
        "Goal: Match the product \"{$brand_name} {$product_name}\" to the best possible image file(s) from the following list.\n" .
        "Filename List:\n{$filename_list}\n\n" .
        "Rules:\n" .
        "- Prioritize files that match both brand and strain name.\n" .
        "- Ignore file extensions when matching.\n" .
        "- If multiple versions exist, prefer the one that looks most like a final asset.\n" .
        "- {$return_instruction}";

    $model  = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $tokens = $max === 1 ? 128 : ($max * 80);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' .
           urlencode($model) . ':generateContent?key=' . urlencode($gemini_key);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => (string) json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => $tokens],
        ]),
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = (string) curl_exec($ch);
    curl_close($ch);

    $json   = json_decode($resp, true);
    $result = trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    if ($result === '' || strtoupper(trim($result)) === 'NULL') return [];

    // Build path → file-info lookup for fast resolution
    $path_map = [];
    foreach ($index as $f) {
        $path_map[$f['path']] = $f;
    }

    // Extract paths from Gemini's response (each starts with /)
    $matched = [];
    foreach (preg_split('/\r?\n/', $result) as $line) {
        $line = trim($line);
        if (preg_match('#(/[^\s|,]+)#', $line, $m)) {
            $path = strtolower(rtrim(trim($m[1]), '.,;'));
            if (isset($path_map[$path])) {
                $entry = $path_map[$path];
                if (!in_array($entry, $matched, true)) {
                    $matched[] = $entry;
                }
            }
        }
        if (count($matched) >= $max) break;
    }

    return $matched;   // [ ['path' => '...', 'name' => '...'], ... ]
}
