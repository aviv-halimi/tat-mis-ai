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
 * Lists all image files inside a Dropbox shared folder, recursively.
 * Uses the Dropbox API v2 `files/list_folder` with a shared_link parameter.
 *
 * Returned file entries are normalized to:
 *   [ 'name' => 'product.jpg', 'path' => '/product.jpg', 'download_url' => 'https://…?dl=1' ]
 *
 * The download_url is constructed as:
 *   {shared_link_base}{path}?dl=1
 * which Dropbox resolves correctly for public shared-folder links.
 *
 * @param  string $shared_link  Any form of the Dropbox shared-folder URL
 * @param  string $token        Dropbox API access token (from app console)
 * @return array<array{name:string,path:string,download_url:string}>
 */
function dbx_get_file_list(string $shared_link, string $token): array
{
    if ($token === '') return [];

    // Normalize: strip dl= param for API call (API wants the bare shared link)
    $api_link  = preg_replace('/([?&])dl=\d+[&]?/', '$1', $shared_link);
    $api_link  = rtrim($api_link, '?&');

    $link_base = dbx_link_base($shared_link);  // base for constructing per-file URLs

    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];

    $files    = [];
    $cursor   = null;
    $has_more = true;

    while ($has_more) {
        if ($cursor === null) {
            $endpoint = 'https://api.dropboxapi.com/2/files/list_folder';
            $body     = json_encode([
                'path'                           => '',
                'shared_link'                    => ['url' => $api_link],
                'recursive'                      => true,
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
            if (($entry['.tag'] ?? '') !== 'file') continue;

            $name = (string) ($entry['name']       ?? '');
            $path = (string) ($entry['path_lower'] ?? '');
            if ($name === '' || $path === '') continue;

            // Only index image files
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $image_exts, true)) continue;

            // path_lower is relative to the shared folder root (starts with /)
            $download_url = $link_base . $path . '?dl=1';

            $files[] = [
                'name'         => $name,
                'path'         => $path,
                'download_url' => $download_url,
            ];
        }

        $has_more = (bool) ($data['has_more'] ?? false);
        $cursor   = $has_more ? (string) ($data['cursor'] ?? '') : null;
    }

    return $files;
}

// ============================================================
// 4. GEMINI FUZZY MATCH
// ============================================================

/**
 * Sends the Dropbox file list to Gemini and returns up to $max
 * direct-download URLs ranked best-to-worst for the given product.
 *
 * Uses the same pre-filter + prompt strategy as gd_gemini_match_multi()
 * in GoogleDriveHelper.php, but with file paths as identifiers instead
 * of Drive file IDs.
 *
 * @param  string $product_name
 * @param  string $brand_name
 * @param  array  $file_list   From dbx_get_file_list()
 * @param  string $gemini_key
 * @param  int    $max         Maximum number of URLs to return
 * @return array<string>       Direct download URLs
 */
function dbx_gemini_match_multi(
    string $product_name,
    string $brand_name,
    array  $file_list,
    string $gemini_key,
    int    $max = 5
): array {
    if (empty($file_list) || $gemini_key === '') return [];

    // Pre-filter by keyword overlap (at least one 3+ char word matches filename)
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

    // Build list: path | name  (path is the unique identifier Gemini returns)
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

    // Build path → download_url lookup
    $path_to_url = [];
    foreach ($index as $f) {
        $path_to_url[$f['path']] = $f['download_url'];
    }

    // Extract file paths from Gemini's response (lines starting with /)
    $matched_urls = [];
    foreach (preg_split('/\r?\n/', $result) as $line) {
        $line = trim($line);
        // Gemini may include surrounding text; extract the path-like token
        if (preg_match('#(/[^\s|,]+)#', $line, $m)) {
            $path = strtolower(rtrim(trim($m[1]), '.,;'));
            if (isset($path_to_url[$path])) {
                $url = $path_to_url[$path];
                if (!in_array($url, $matched_urls, true)) {
                    $matched_urls[] = $url;
                }
            }
        }
        if (count($matched_urls) >= $max) break;
    }

    return $matched_urls;
}
