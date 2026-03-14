<?php
/**
 * Save (and optionally validate) a brand's asset folder URL.
 *
 * POST params:
 *   brand_id      int     PK from blaze1.brand
 *   brand_folder  string  Drive folder URL, raw folder ID, or any asset URL (empty = clear)
 *
 * Returns JSON:
 *   success    bool
 *   status     string   drive_public | drive_private | drive_saved | drive_error |
 *                        other_saved | cleared
 *   folder_id  string|null   Extracted Drive folder ID (if any)
 *   error      string        Only on failure
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Cache-Control: no-cache');
header('Content-type: application/json');

$brand_id     = isset($_POST['brand_id'])     ? (int)    trim($_POST['brand_id'])     : 0;
$brand_folder = isset($_POST['brand_folder']) ? trim((string) $_POST['brand_folder']) : '';

if ($brand_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid brand ID.']);
    exit;
}

// ---- Get blaze1 DB name ----
$store1    = getRow(getRs("SELECT db FROM store WHERE store_id = 1 LIMIT 1"));
$store1_db = $store1['db'] ?? 'blaze1';

// ---- Normalise / extract folder ID ----
$folder_id    = null;
$is_drive     = false;
$is_other_url = false;

if ($brand_folder !== '') {
    if (
        str_contains($brand_folder, 'drive.google.com') ||
        str_contains($brand_folder, 'docs.google.com')
    ) {
        $is_drive = true;
        if (preg_match('#/folders/([A-Za-z0-9_\-]{10,})#', $brand_folder, $m)) {
            $folder_id = $m[1];
        }
    } elseif (preg_match('/^[A-Za-z0-9_\-]{20,}$/', $brand_folder)) {
        // Looks like a raw folder ID
        $is_drive  = true;
        $folder_id = $brand_folder;
    } elseif (filter_var($brand_folder, FILTER_VALIDATE_URL)) {
        $is_other_url = true;
    }
}

// ---- Validate Drive folder accessibility ----
$status = 'saved';

if ($brand_folder === '') {
    $status = 'cleared';
} elseif ($is_drive && $folder_id !== null) {
    // Try public access check with an API key (no auth).
    // We try GOOGLE_DRIVE_API_KEY, then fall back to GOOGLE_SEARCH_API_KEY
    // (same GCP project, both have Drive API enabled in most setups).
    $api_key = '';
    foreach (['GOOGLE_DRIVE_API_KEY', 'GOOGLE_SEARCH_API_KEY'] as $const) {
        $val = getenv($const);
        if ($val === false || $val === '') {
            $val = (defined($const) && constant($const) !== '') ? constant($const) : '';
        }
        if ($val !== '') { $api_key = $val; break; }
    }

    if ($api_key !== '') {
        // Hit the Drive Files API without a bearer token — succeeds only if folder is public.
        $url = 'https://www.googleapis.com/drive/v3/files'
             . '?q='       . rawurlencode("'{$folder_id}' in parents and trashed = false")
             . '&key='     . rawurlencode($api_key)
             . '&pageSize=1'
             . '&fields=files(id)';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 200) {
            $status = 'drive_public';
        } elseif ($http === 403 || $http === 401) {
            $status = 'drive_private';
        } else {
            $status = 'drive_error';
        }
    } else {
        // No API key configured — save the URL but skip the public check.
        $status = 'drive_saved';
    }
} elseif ($is_drive) {
    // Drive URL but couldn't extract a folder ID
    $status = 'drive_error';
} elseif ($is_other_url) {
    $status = 'other_saved';
}

// ---- Persist to DB ----
$save_value = $brand_folder !== '' ? $brand_folder : null;
setRs(
    "UPDATE `{$store1_db}`.brand SET brand_folder = ? WHERE brand_id = ?",
    [$save_value, $brand_id]
);

echo json_encode([
    'success'   => true,
    'status'    => $status,
    'folder_id' => $folder_id,
]);
