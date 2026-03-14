<?php
/**
 * Save (and validate) a brand's asset folder URL.
 *
 * Validation uses the service account (not an API key) because
 * Google's unauthenticated API only returns 200 for truly-public
 * ("Anyone on the internet") folders — it rejects "Anyone with the
 * link" folders with 403.  The service account correctly accesses
 * link-shared folders, which is also what the enrichment workflow uses.
 *
 * POST params:
 *   brand_id      int     PK from blaze1.brand
 *   brand_folder  string  Drive folder URL, raw folder ID, or any asset URL (empty = clear)
 *
 * Returns JSON:
 *   success    bool
 *   status     string   drive_ok | drive_no_access | drive_no_creds | drive_error |
 *                        other_saved | cleared
 *   folder_id  string|null
 *   error      string   Only on failure
 */
require_once dirname(__FILE__) . '/../_config.php';
require_once BASE_PATH . 'inc/GoogleDriveHelper.php';

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
        $is_drive  = true;
        $folder_id = $brand_folder;
    } elseif (filter_var($brand_folder, FILTER_VALIDATE_URL)) {
        $is_other_url = true;
    }
}

// ---- Validate Drive folder using the service account ----
$status = 'saved';

if ($brand_folder === '') {
    $status = 'cleared';

} elseif ($is_drive && $folder_id !== null) {

    $creds_path = BASE_PATH . 'credentials/service-account.json';

    if (!file_exists($creds_path)) {
        // Credentials file not on server — save without validation
        $status = 'drive_no_creds';
    } else {
        $creds = json_decode((string) file_get_contents($creds_path), true);

        if (!is_array($creds) || empty($creds['private_key'])) {
            $status = 'drive_no_creds';
        } else {
            try {
                $service = gd_make_drive_service($creds);

                // Try to list one file inside the folder.
                // Returns 200 for any folder the service account can read,
                // including "anyone with the link" folders.
                $result = $service->files->listFiles([
                    'q'                         => "'{$folder_id}' in parents and trashed = false",
                    'pageSize'                  => 1,
                    'fields'                    => 'files(id)',
                    'supportsAllDrives'         => true,
                    'includeItemsFromAllDrives' => true,
                ]);
                $status = 'drive_ok';   // service account can access it

            } catch (\Google_Service_Exception $e) {
                $status = ($e->getCode() === 403 || $e->getCode() === 404)
                        ? 'drive_no_access'
                        : 'drive_error';
            } catch (\Exception $e) {
                $status = 'drive_error';
            }
        }
    }

} elseif ($is_drive) {
    // Drive URL but couldn't extract folder ID
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
