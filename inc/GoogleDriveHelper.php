<?php
/**
 * Google Drive Service-Account helper for the Enrichment workflow.
 *
 * Uses the official google/apiclient library (v2.x).
 *
 * Public API (called from ajax/product-enrich.php):
 *   gd_get_index($creds, $rootFolderId)           → flat array of ['id'=>..,'name'=>..]
 *   gd_gemini_match($product, $brand, $index, $key) → Drive file ID string or null
 *   gd_download_and_resize($service, $fileId, $outDir, $outUrlPrefix) → URL or null
 *   gd_make_drive_service($creds)                  → Google_Service_Drive
 */

if (!class_exists('Google_Client')) {
    require_once BASE_PATH . 'vendor/autoload.php';
}

// ============================================================
// 1. BUILD A Google_Service_Drive FROM SERVICE-ACCOUNT CREDS
// ============================================================

/**
 * Creates an authenticated Google_Service_Drive using the service account.
 */
function gd_make_drive_service(array $creds): Google_Service_Drive
{
    $client = new Google_Client();
    $client->setAuthConfig($creds);
    $client->setScopes([Google_Service_Drive::DRIVE_READONLY]);
    return new Google_Service_Drive($client);
}

// ============================================================
// 2. RECURSIVE FILE INDEX  (mirrors getFlatFileIndex from spec)
// ============================================================

/**
 * Recursively fetches all filenames and IDs from $folderId,
 * resolving shortcuts so brand sub-folders are never skipped.
 *
 * Handles Drive API pagination (pageSize=1000) automatically.
 *
 * @param  Google_Service_Drive $service
 * @param  string               $folderId
 * @param  array                $index     Accumulator — pass [] on first call
 * @param  array                $visited   Loop-guard — pass [] on first call
 * @return array<array{id:string,name:string}>
 */
function gd_get_flat_file_index(
    Google_Service_Drive $service,
    string $folderId,
    array &$index   = [],
    array &$visited = []
): array {
    // Loop guard
    if (isset($visited[$folderId])) return $index;
    $visited[$folderId] = true;

    $pageToken = null;

    do {
        $optParams = [
            'q'         => "'{$folderId}' in parents and trashed = false",
            'fields'    => 'nextPageToken,files(id,name,mimeType,shortcutDetails)',
            'pageSize'  => 1000,
            'supportsAllDrives'      => true,
            'includeItemsFromAllDrives' => true,
        ];
        if ($pageToken) $optParams['pageToken'] = $pageToken;

        try {
            $results = $service->files->listFiles($optParams);
        } catch (Exception $e) {
            break; // skip folder on API error and continue
        }

        foreach ($results->getFiles() as $file) {
            $mime = $file->getMimeType();

            // 1. Shortcut — resolve and recurse if it points to a folder
            if ($mime === 'application/vnd.google-apps.shortcut') {
                $details = $file->getShortcutDetails();
                if (
                    $details !== null &&
                    $details->getTargetMimeType() === 'application/vnd.google-apps.folder'
                ) {
                    gd_get_flat_file_index($service, $details->getTargetId(), $index, $visited);
                }
                // shortcut to a file: index it under the shortcut's display name
                elseif ($details !== null && $details->getTargetId() !== null) {
                    $index[] = [
                        'id'   => $details->getTargetId(),
                        'name' => $file->getName(),
                    ];
                }

            // 2. Regular subfolder — recurse
            } elseif ($mime === 'application/vnd.google-apps.folder') {
                gd_get_flat_file_index($service, $file->getId(), $index, $visited);

            // 3. Any other file (image, PDF, etc.) — add to index
            } else {
                $index[] = [
                    'id'   => $file->getId(),
                    'name' => $file->getName(),
                ];
            }
        }

        $pageToken = $results->getNextPageToken();

    } while ($pageToken);

    return $index;
}

// ============================================================
// 3. CACHED INDEX (4-hour JSON file cache)
// ============================================================

/**
 * Returns the flat file index for $rootFolderId, served from a
 * 4-hour JSON cache at public/tmp/enrichment/.drive_index_cache.json
 * to avoid hitting Drive API rate limits on every request.
 */
function gd_get_index(array $creds, string $rootFolderId): array
{
    // Per-folder cache file — allows brand folder and master folder to coexist
    $safe_id    = preg_replace('/[^A-Za-z0-9_\-]/', '', $rootFolderId);
    $cache_file = BASE_PATH . 'public/tmp/enrichment/.drive_index_' . $safe_id . '.json';
    $cache_ttl  = 4 * 3600;

    // Return cached index if still fresh and for the same root folder
    if (file_exists($cache_file)) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (
            is_array($cached) &&
            isset($cached['built_at'], $cached['files']) &&
            (time() - (int) $cached['built_at']) < $cache_ttl
        ) {
            return (array) $cached['files'];
        }
    }

    // Build fresh index
    $service = gd_make_drive_service($creds);
    $index   = [];
    $visited = [];
    gd_get_flat_file_index($service, $rootFolderId, $index, $visited);

    // Write cache
    @mkdir(dirname($cache_file), 0755, true);
    file_put_contents(
        $cache_file,
        (string) json_encode([
            'built_at' => time(),
            'files'    => $index,
        ])
    );

    return $index;
}

// ============================================================
// 4. GEMINI FUZZY MATCH
// ============================================================

/**
 * Sends the file index to Gemini and asks it to return the single
 * best-matching Drive file ID for "[brand] [product]".
 *
 * Pre-filters index to entries sharing at least one meaningful word
 * with the product/brand before sending to Gemini (keeps prompt small).
 *
 * Returns the winning file ID, or null if no match.
 */
function gd_gemini_match(
    string $product_name,
    string $brand_name,
    array  $file_index,
    string $gemini_key
): ?string {
    if (empty($file_index)) return null;

    // Pre-filter by keyword overlap (at least one 3+ char word)
    $search_words = array_filter(
        preg_split('/\s+/', strtolower(trim($brand_name . ' ' . $product_name))),
        fn(string $w) => strlen($w) >= 3
    );

    if (!empty($search_words)) {
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
        if (count($filtered) >= 1) $file_index = $filtered;
    }

    // Safety cap — Gemini context limit
    if (count($file_index) > 500) $file_index = array_slice($file_index, 0, 500);

    $filename_list = implode(
        "\n",
        array_map(fn(array $f) => $f['id'] . ' | ' . $f['name'], $file_index)
    );

    $prompt =
        "You are an expert inventory librarian for a cannabis company.\n" .
        "Goal: Match the product \"{$brand_name} {$product_name}\" to the best possible image file from the following list.\n" .
        "Filename List:\n{$filename_list}\n\n" .
        "Rules:\n" .
        "- Prioritize files that match both brand and strain name.\n" .
        "- Ignore file extensions when matching.\n" .
        "- If multiple versions exist (e.g., 'Product_Final' vs 'Product_V1'), pick the one that looks most like a final asset.\n" .
        "- Return ONLY the Google Drive File ID (the part before the ' | '). " .
        "If no reasonable match exists, return the single word NULL.";

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' .
             urlencode($model) . ':generateContent?key=' . urlencode($gemini_key);

    $ch = curl_init($url);
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
    $resp = (string) curl_exec($ch);
    curl_close($ch);

    $json   = json_decode($resp, true);
    $result = trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    if ($result === '' || strtoupper($result) === 'NULL') return null;

    // Extract the first token that looks like a Drive file ID (20+ alphanumeric chars)
    if (preg_match('/\b([A-Za-z0-9_\-]{20,})\b/', $result, $m)) {
        return $m[1];
    }

    return null;
}

// ============================================================
// 5. DOWNLOAD + GD LETTERBOX RESIZE
// ============================================================

/**
 * Downloads a Drive file via the API and saves a 800×800
 * white-letterboxed JPEG to $outDir.
 *
 * Returns the web-accessible relative URL, or null on failure.
 *
 * @param Google_Service_Drive $service  Authenticated Drive service
 * @param string               $fileId
 * @param string               $outDir   Filesystem path to write JPEG
 * @param string               $outUrlPrefix  URL prefix (e.g. '/public/tmp/enrichment')
 */
function gd_download_and_resize(
    Google_Service_Drive $service,
    string $fileId,
    string $outDir,
    string $outUrlPrefix
): ?string {
    if (!function_exists('imagecreatefromstring')) return null; // GD not available

    // Download raw bytes via Drive API (alt=media)
    try {
        $response = $service->files->get($fileId, ['alt' => 'media']);
        $bytes    = (string) $response->getBody();
    } catch (Exception $e) {
        return null;
    }

    if (strlen($bytes) < 100) return null;

    $src = @imagecreatefromstring($bytes);
    if (!$src) return null;

    $sw = imagesx($src);
    $sh = imagesy($src);

    // 800×800 white canvas
    $canvas = imagecreatetruecolor(800, 800);
    $white  = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    // Scale proportionally to fit inside 800×800
    $ratio = min(800 / $sw, 800 / $sh);
    $dw    = (int) round($sw * $ratio);
    $dh    = (int) round($sh * $ratio);
    $dx    = (int) round((800 - $dw) / 2);
    $dy    = (int) round((800 - $dh) / 2);

    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    @mkdir($outDir, 0755, true);
    $filename = 'drive_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $fileId) . '.jpg';
    $outPath  = rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $ok = imagejpeg($canvas, $outPath, 90);
    imagedestroy($canvas);

    return $ok ? rtrim($outUrlPrefix, '/') . '/' . $filename : null;
}
