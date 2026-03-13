<?php
/**
 * Google Drive Service-Account helper for the Enrichment workflow.
 *
 * Provides:
 *   - JWT / OAuth2 token acquisition (no library required — uses OpenSSL)
 *   - Recursive folder crawl with shortcut resolution
 *   - 4-hour JSON file-cache for the Drive index
 *   - Gemini fuzzy-match to pick the best filename from the index
 *   - GD-based image download + 800×800 letterbox resize
 */

// ============================================================
// 1. JWT / ACCESS TOKEN
// ============================================================

function gd_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Returns a cached (or freshly minted) Google OAuth2 access token.
 * Token is cached for 55 minutes in /public/tmp/enrichment/.token_cache.json.
 */
function gd_get_access_token(array $creds): ?string
{
    $cache_file = BASE_PATH . 'public/tmp/enrichment/.token_cache.json';

    if (file_exists($cache_file)) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (
            is_array($cached) &&
            !empty($cached['token']) &&
            !empty($cached['expires']) &&
            (int) $cached['expires'] > time() + 60
        ) {
            return (string) $cached['token'];
        }
    }

    $now     = time();
    $header  = gd_base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = gd_base64url((string) json_encode([
        'iss'   => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));

    $signing_input = $header . '.' . $payload;
    $pkey = openssl_pkey_get_private($creds['private_key']);
    if ($pkey === false) return null;

    if (!openssl_sign($signing_input, $raw_signature, $pkey, 'SHA256')) return null;

    $jwt = $signing_input . '.' . gd_base64url($raw_signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 30,
    ]);
    $resp = (string) curl_exec($ch);
    curl_close($ch);

    $data  = json_decode($resp, true);
    $token = $data['access_token'] ?? null;

    if ($token) {
        @mkdir(dirname($cache_file), 0755, true);
        file_put_contents(
            $cache_file,
            (string) json_encode([
                'token'   => $token,
                'expires' => $now + ((int) ($data['expires_in'] ?? 3600)),
            ])
        );
    }

    return $token ? (string) $token : null;
}

// ============================================================
// 2. DRIVE INDEX (crawl + 4-hour cache)
// ============================================================

/**
 * Returns the cached or freshly built flat file index for the root folder.
 * Each entry: [ 'id' => '<fileId>', 'name' => '<filename>' ]
 */
function gd_get_index(array $creds, string $root_folder_id): array
{
    $cache_file = BASE_PATH . 'public/tmp/enrichment/.drive_index_cache.json';
    $cache_ttl  = 4 * 3600;

    if (file_exists($cache_file)) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (
            is_array($cached) &&
            isset($cached['built_at'], $cached['folder'], $cached['files']) &&
            $cached['folder'] === $root_folder_id &&
            (time() - (int) $cached['built_at']) < $cache_ttl
        ) {
            return (array) $cached['files'];
        }
    }

    $token = gd_get_access_token($creds);
    if (!$token) return [];

    $visited = [];
    $files   = gd_crawl_folder($token, $root_folder_id, $visited);

    @mkdir(dirname($cache_file), 0755, true);
    file_put_contents(
        $cache_file,
        (string) json_encode([
            'built_at' => time(),
            'folder'   => $root_folder_id,
            'files'    => $files,
        ])
    );

    return $files;
}

/**
 * Recursively list all (non-folder) files inside $folder_id.
 * Resolves shortcuts: if target is a folder, recurses into it;
 * if target is a file, indexes it under the shortcut's filename.
 *
 * @param string[] $visited  Folder IDs already crawled (prevents loops).
 * @return array<array{id:string,name:string}>
 */
function gd_crawl_folder(string $token, string $folder_id, array &$visited): array
{
    if (isset($visited[$folder_id])) return [];
    $visited[$folder_id] = true;

    $files      = [];
    $page_token = null;

    do {
        $params = [
            'q'        => "'{$folder_id}' in parents and trashed = false",
            'fields'   => 'nextPageToken,files(id,name,mimeType,shortcutDetails)',
            'pageSize' => 1000,
        ];
        if ($page_token) $params['pageToken'] = $page_token;

        $ch = curl_init('https://www.googleapis.com/drive/v3/files?' . http_build_query($params));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 45,
        ]);
        $resp = json_decode((string) curl_exec($ch), true);
        curl_close($ch);

        foreach ((array) ($resp['files'] ?? []) as $file) {
            $mime = (string) ($file['mimeType'] ?? '');
            $id   = (string) ($file['id']       ?? '');
            $name = (string) ($file['name']     ?? '');

            if ($mime === 'application/vnd.google-apps.folder') {
                $sub   = gd_crawl_folder($token, $id, $visited);
                $files = array_merge($files, $sub);

            } elseif ($mime === 'application/vnd.google-apps.shortcut') {
                $target_id   = (string) ($file['shortcutDetails']['targetId']       ?? '');
                $target_mime = (string) ($file['shortcutDetails']['targetMimeType'] ?? '');

                if ($target_id === '') continue;

                if ($target_mime === 'application/vnd.google-apps.folder') {
                    $sub   = gd_crawl_folder($token, $target_id, $visited);
                    $files = array_merge($files, $sub);
                } else {
                    // Shortcut to a regular file — index under the shortcut's display name
                    $files[] = ['id' => $target_id, 'name' => $name ?: $target_id];
                }
            } else {
                // Regular file
                $files[] = ['id' => $id, 'name' => $name];
            }
        }

        $page_token = $resp['nextPageToken'] ?? null;

    } while ($page_token);

    return $files;
}

// ============================================================
// 3. GEMINI FUZZY MATCH
// ============================================================

/**
 * Ask Gemini to pick the best file ID from $file_index for the given product.
 *
 * Returns the winning file ID string, or NULL if no match.
 */
function gd_gemini_match(
    string $product_name,
    string $brand_name,
    array  $file_index,
    string $gemini_key
): ?string {
    if (empty($file_index)) return null;

    // Pre-filter: keep entries whose filename shares at least one meaningful word
    // with the product name or brand (reduces prompt size dramatically).
    $search_words = array_filter(
        preg_split('/\s+/', strtolower($brand_name . ' ' . $product_name)),
        fn(string $w) => strlen($w) >= 3
    );

    if (!empty($search_words)) {
        $filtered = array_filter($file_index, function (array $f) use ($search_words): bool {
            $lower = strtolower($f['name']);
            foreach ($search_words as $w) {
                if (str_contains($lower, $w)) return true;
            }
            return false;
        });
        // Fall back to full index if pre-filter is too aggressive
        if (count($filtered) >= 1) {
            $file_index = array_values($filtered);
        }
    }

    // Cap at 500 entries to stay within Gemini's context window
    if (count($file_index) > 500) {
        $file_index = array_slice($file_index, 0, 500);
    }

    $filename_list = implode(
        "\n",
        array_map(fn(array $f) => $f['id'] . ' | ' . $f['name'], $file_index)
    );

    $prompt = "You are an expert inventory librarian for a cannabis company.\n" .
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

    $payload = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 64],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => (string) json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = (string) curl_exec($ch);
    curl_close($ch);

    $json   = json_decode($resp, true);
    $result = trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    if ($result === '' || strtoupper($result) === 'NULL') return null;

    // Gemini sometimes wraps the ID in extra text — extract first token that looks like a Drive ID
    if (preg_match('/\b([A-Za-z0-9_\-]{20,})\b/', $result, $m)) {
        return $m[1];
    }

    return null;
}

// ============================================================
// 4. IMAGE DOWNLOAD + GD LETTERBOX RESIZE
// ============================================================

/**
 * Downloads a Drive file and saves a 800×800 white-letterboxed JPEG.
 *
 * Returns the web-accessible URL (relative to site root) on success, or null.
 */
function gd_download_and_resize(
    string $token,
    string $file_id,
    string $out_dir,
    string $out_url_prefix
): ?string {
    if (!function_exists('imagecreatefromstring')) return null; // GD not available

    // Download raw bytes from Drive
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($file_id) . '?alt=media';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $bytes    = curl_exec($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($bytes === false || $curl_err || $http >= 300 || strlen((string) $bytes) < 100) {
        return null;
    }

    $src = @imagecreatefromstring((string) $bytes);
    if (!$src) return null;

    $sw  = imagesx($src);
    $sh  = imagesy($src);

    // 800×800 white canvas
    $canvas = imagecreatetruecolor(800, 800);
    $white  = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    // Scale to fit, maintaining aspect ratio
    $ratio = min(800 / $sw, 800 / $sh);
    $dw    = (int) round($sw * $ratio);
    $dh    = (int) round($sh * $ratio);
    $dx    = (int) round((800 - $dw) / 2);
    $dy    = (int) round((800 - $dh) / 2);

    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    @mkdir($out_dir, 0755, true);
    $filename = 'drive_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $file_id) . '.jpg';
    $out_path = rtrim($out_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $ok = imagejpeg($canvas, $out_path, 90);
    imagedestroy($canvas);

    if (!$ok) return null;

    return rtrim($out_url_prefix, '/') . '/' . $filename;
}
