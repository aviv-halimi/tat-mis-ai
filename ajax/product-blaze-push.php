<?php
/**
 * Push an enriched product to Blaze using store_id=1 credentials.
 *
 * POST params:
 *   name         string  Product name
 *   description  string  AI-generated / edited description
 *   price        float   Default price
 *   brand_id     int     brand_id in the originating store DB
 *   category_id  int     category_id in the originating store DB
 *   store_db     string  DB name of the originating store (e.g. blaze2)
 */
require_once dirname(__FILE__) . '/../_config.php';

// GD needs headroom when decoding user-uploaded images (phone photos can be 10+ MP,
// which expands to ~40-50 MB of raw pixel data inside GD). Match product-enrich.php
// and product-image-search.php so uploaded-image pushes don't silently OOM.
@ini_set('memory_limit', '512M');

header('Cache-Control: no-cache');
header('Content-type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['success' => false, 'error' => "PHP error ({$errno}): {$errstr} in {$errfile}:{$errline}"]);
    exit;
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) { header('Content-type: application/json'); }
        echo json_encode(['success' => false, 'error' => "Fatal: {$e['message']} in {$e['file']}:{$e['line']}"]);
    }
});

$product_name    = trim((string) ($_POST['name']          ?? ''));
$description     = trim((string) ($_POST['description']   ?? ''));
$price           = isset($_POST['price'])       && is_numeric($_POST['price'])       ? (float) $_POST['price']       : 0;
$davis_price     = isset($_POST['davis_price']) && is_numeric($_POST['davis_price']) ? (float) $_POST['davis_price'] : null;
$dixon_price     = isset($_POST['dixon_price']) && is_numeric($_POST['dixon_price']) ? (float) $_POST['dixon_price'] : null;
$brand_id        = (int) ($_POST['brand_id']     ?? 0);
$category_id     = (int) ($_POST['category_id']  ?? 0);
$store_db        = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) ($_POST['store_db']  ?? '')));
$vendor_id       = (int) ($_POST['vendor_id']    ?? 0);
$po_product_id   = (int) ($_POST['po_product_id'] ?? 0);
$image_url       = trim((string) ($_POST['image_url'] ?? ''));
// Optional crop coordinates (fractions 0..1 of the natural image size) — used
// when the frontend Cropper couldn't export client-side due to CORS/tainted canvas.
$crop_x          = isset($_POST['crop_x']) && is_numeric($_POST['crop_x']) ? (float) $_POST['crop_x'] : null;
$crop_y          = isset($_POST['crop_y']) && is_numeric($_POST['crop_y']) ? (float) $_POST['crop_y'] : null;
$crop_w          = isset($_POST['crop_w']) && is_numeric($_POST['crop_w']) ? (float) $_POST['crop_w'] : null;
$crop_h          = isset($_POST['crop_h']) && is_numeric($_POST['crop_h']) ? (float) $_POST['crop_h'] : null;
$flower_type     = trim((string) ($_POST['flower_type']     ?? ''));
$weight_per_unit = trim((string) ($_POST['weight_per_unit'] ?? 'Each')) ?: 'Each';
$custom_gram_type = trim((string) ($_POST['custom_gram_type'] ?? 'Gram'));
$custom_weight   = isset($_POST['custom_weight']) && is_numeric($_POST['custom_weight']) && (float) $_POST['custom_weight'] > 0
                   ? (float) $_POST['custom_weight'] : null;

if ($product_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing product name.']);
    exit;
}

// ---- Load store_id=1 credentials ----
$store1 = getRow(getRs(
    "SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE store_id = 1 AND " . is_enabled() . " LIMIT 1"
));
if (!$store1) {
    echo json_encode(['success' => false, 'error' => 'Store ID 1 not found or is inactive.']);
    exit;
}

$store1_db   = (string) $store1['db'];
$api_url     = rtrim((string) $store1['api_url'], '/') . '/';
$auth_code   = (string) $store1['auth_code'];
$partner_key = (string) $store1['partner_key'];

// ---- Resolve brand: originating store brand_id → master_brand_id → store1 Blaze id ----
$blaze_brand_id = null;
$debug_brand    = ['input_brand_id' => $brand_id, 'store_db' => $store_db];

if ($brand_id > 0 && $store_db !== '') {
    $brand_row = getRow(getRs(
        "SELECT master_brand_id FROM `{$store_db}`.brand WHERE brand_id = ? LIMIT 1",
        [$brand_id]
    ));
    $debug_brand['master_brand_id'] = $brand_row['master_brand_id'] ?? null;

    if (!empty($brand_row['master_brand_id'])) {
        $master_brand_id = (int) $brand_row['master_brand_id'];
        $store1_brand    = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.brand WHERE master_brand_id = ? AND is_active = 1 LIMIT 1",
            [$master_brand_id]
        ));
        $blaze_brand_id          = $store1_brand['id'] ?? null;
        $debug_brand['store1_id'] = $blaze_brand_id;
    }
}

// ---- Resolve category: master_category_id → store1, fallback to name match ----
$blaze_category_id = null;
$debug_category    = ['input_category_id' => $category_id, 'store_db' => $store_db];

if ($category_id > 0 && $store_db !== '') {
    $cat_row = getRow(getRs(
        "SELECT master_category_id, name FROM `{$store_db}`.category WHERE category_id = ? LIMIT 1",
        [$category_id]
    ));
    $debug_category['master_category_id'] = $cat_row['master_category_id'] ?? null;
    $debug_category['src_name']           = $cat_row['name'] ?? null;

    // Step 1: try master_category_id
    if (!empty($cat_row['master_category_id'])) {
        $store1_cat = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.category WHERE master_category_id = ? LIMIT 1",
            [(int) $cat_row['master_category_id']]
        ));
        $blaze_category_id = $store1_cat['id'] ?? null;
    }

    // Step 2: fallback — match by name in blaze1
    if (!$blaze_category_id && !empty($cat_row['name'])) {
        $store1_cat = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.category WHERE name = ? LIMIT 1",
            [$cat_row['name']]
        ));
        $blaze_category_id = $store1_cat['id'] ?? null;
        if ($blaze_category_id) $debug_category['resolved_via'] = 'name';
    }

    $debug_category['store1_id'] = $blaze_category_id;
}

// ---- Resolve vendor: {store_db}.vendor.name → blaze1.vendor.id ----
$blaze_vendor_id        = null;
$blaze_secondary_vendors = [];
$debug_vendor           = ['input_vendor_id' => $vendor_id, 'store_db' => $store_db];

if ($vendor_id > 0 && $store_db !== '') {
    // Step 1: get vendor name from the originating store
    $src_vendor = getRow(getRs(
        "SELECT name FROM `{$store_db}`.vendor WHERE vendor_id = ? LIMIT 1",
        [$vendor_id]
    ));
    $vendor_name_resolved       = $src_vendor['name'] ?? null;
    $debug_vendor['vendor_name'] = $vendor_name_resolved;

    // Step 2: find that vendor in blaze1 by name
    if ($vendor_name_resolved) {
        $store1_vendor = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.vendor WHERE name = ? AND is_active = 1 LIMIT 1",
            [$vendor_name_resolved]
        ));
        $blaze_vendor_id           = $store1_vendor['id'] ?? null;
        $debug_vendor['store1_id'] = $blaze_vendor_id;
    }
}

// ---- Vendor group: co-op vendors that share inventory on Blaze ----
// When the resolved vendor is a member of this group, Blaze wants ONE canonical
// primary vendor (the first ID below) with the remaining group members attached
// as secondaryVendors so products are cross-visible to all co-op members.
$BLAZE_COOP_VENDOR_GROUP = [
    '68fbaf6e36ff53a9b9d10457',
    '68ed31e7b9121ceb14aa573f',
    '5dcf89fb002f09082a7558ba',
    '68cdcf401c44c0b22a777c91',
];
if ($blaze_vendor_id && in_array($blaze_vendor_id, $BLAZE_COOP_VENDOR_GROUP, true)) {
    $primary_coop_vendor      = $BLAZE_COOP_VENDOR_GROUP[0];
    $debug_vendor['coop_swap'] = [
        'resolved_vendor'  => $blaze_vendor_id,
        'primary'          => $primary_coop_vendor,
        'secondary'        => array_values(array_slice($BLAZE_COOP_VENDOR_GROUP, 1)),
    ];
    $blaze_vendor_id         = $primary_coop_vendor;
    $blaze_secondary_vendors = array_values(array_slice($BLAZE_COOP_VENDOR_GROUP, 1));
}

// ---- Upload image to Blaze as a public asset → get assetKey ----
$blaze_asset_key = null;
$debug_image     = ['image_url' => $image_url];

if ($image_url !== '') {
    $img_bytes = false;
    $img_err   = '';
    $img_mime  = 'image/jpeg';

    if (strpos($image_url, 'data:') === 0) {
        // Uploaded image — decode base64 data URI
        if (preg_match('/^data:(image\/[a-zA-Z+]+);base64,(.+)$/s', $image_url, $m)) {
            $img_mime  = $m[1];
            $img_bytes = base64_decode($m[2]);
            if ($img_bytes === false) {
                $img_err   = 'base64 decode failed';
                $img_bytes = false;
            }
        } else {
            $img_err = 'Invalid data URI format';
        }
    } else {
        // Dropbox/Drive/master enrichment images are cached locally at
        // {BASE_URL}/public/tmp/enrichment/…. Downloading via HTTP round-trips
        // through the auth middleware and can return a login HTML page instead
        // of the image. Detect these and read straight from disk.
        $enrich_tmp_url_prefix = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/public/tmp/enrichment/' : '';
        $local_enrich_path     = null;
        if ($enrich_tmp_url_prefix !== '' && strpos($image_url, $enrich_tmp_url_prefix) === 0) {
            $rel_name = basename(parse_url($image_url, PHP_URL_PATH) ?: '');
            if ($rel_name !== '') {
                $local_enrich_path = rtrim(BASE_PATH, '/\\') . '/public/tmp/enrichment/' . $rel_name;
            }
        }

        if ($local_enrich_path && is_file($local_enrich_path)) {
            $img_bytes = @file_get_contents($local_enrich_path);
            $debug_image['read_from_disk']  = $local_enrich_path;
            $debug_image['local_file_size'] = $img_bytes ? strlen($img_bytes) : 0;
            if ($img_bytes === false) $img_err = 'Failed to read cached image file from disk';
        } else {
            // Remote URL — download via cURL
            $img_ch = curl_init($image_url);
            curl_setopt_array($img_ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; EnrichBot/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $img_bytes = curl_exec($img_ch);
            $img_err   = curl_error($img_ch);
            $img_http  = (int) curl_getinfo($img_ch, CURLINFO_HTTP_CODE);
            $img_ctype = (string) curl_getinfo($img_ch, CURLINFO_CONTENT_TYPE);
            curl_close($img_ch);

            $debug_image['download_http']  = $img_http;
            $debug_image['download_ctype'] = $img_ctype;
            $debug_image['download_size']  = $img_bytes ? strlen($img_bytes) : 0;

            // Guard against auth-middleware redirects returning HTML
            if ($img_bytes && stripos($img_ctype, 'text/html') !== false) {
                $img_err   = 'Downloaded response is HTML (likely an auth redirect), not an image';
                $img_bytes = false;
            }
        }
    }

    if ($img_bytes && strlen($img_bytes) > 0 && !$img_err) {
        // ---- Normalize to a 1000×1000 JPEG (crop then resize) ----
        // If the frontend already sent a 1000×1000 cropped data URI, this is a
        // cheap re-encode. If it sent a raw URL + crop coords, we apply the crop
        // here (canvas-tainted remote images need server-side handling). If no
        // crop coords are given, we center-crop to 1:1 as a safe default.
        $src_img = @imagecreatefromstring($img_bytes);
        if ($src_img !== false) {
            $src_w = imagesx($src_img);
            $src_h = imagesy($src_img);

            // Natural-pixel crop rectangle. The frontend Cropper can deliver
            // a crop box that extends beyond the image (default = square
            // containing the whole image); we letterbox with white below.
            if ($crop_w !== null && $crop_h !== null && $crop_w > 0 && $crop_h > 0) {
                $crop_px = (float) (($crop_x ?? 0) * $src_w);
                $crop_py = (float) (($crop_y ?? 0) * $src_h);
                $crop_pw = (float) ($crop_w * $src_w);
                $crop_ph = (float) ($crop_h * $src_h);
            } else {
                // Default = smallest square that contains the entire image
                $side    = (float) max($src_w, $src_h);
                $crop_px = ($src_w - $side) / 2;
                $crop_py = ($src_h - $side) / 2;
                $crop_pw = $side;
                $crop_ph = $side;
            }

            $dst = imagecreatetruecolor(1000, 1000);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, 1000, 1000, $white);

            // Intersection of crop rect and image rect (source pixels)
            $sx1 = max(0.0, $crop_px);
            $sy1 = max(0.0, $crop_py);
            $sx2 = min((float) $src_w, $crop_px + $crop_pw);
            $sy2 = min((float) $src_h, $crop_py + $crop_ph);

            if ($sx2 > $sx1 && $sy2 > $sy1 && $crop_pw > 0 && $crop_ph > 0) {
                $scale_x = 1000.0 / $crop_pw;
                $scale_y = 1000.0 / $crop_ph;

                $dx = (int) round(($sx1 - $crop_px) * $scale_x);
                $dy = (int) round(($sy1 - $crop_py) * $scale_y);
                $dw = (int) round(($sx2 - $sx1) * $scale_x);
                $dh = (int) round(($sy2 - $sy1) * $scale_y);

                $src_ix = (int) round($sx1);
                $src_iy = (int) round($sy1);
                $src_iw = (int) round($sx2 - $sx1);
                $src_ih = (int) round($sy2 - $sy1);

                imagecopyresampled($dst, $src_img, $dx, $dy, $src_ix, $src_iy, $dw, $dh, $src_iw, $src_ih);
            }

            ob_start();
            imagejpeg($dst, null, 92);
            $resized = ob_get_clean();

            imagedestroy($src_img);
            imagedestroy($dst);
            unset($img_bytes);

            if ($resized !== false && strlen($resized) > 0) {
                $img_bytes = $resized;
                $img_mime  = 'image/jpeg';
                $debug_image['normalized_to']   = '1000x1000 JPEG (letterbox)';
                $debug_image['crop_rect']       = [
                    'x' => $crop_px, 'y' => $crop_py,
                    'w' => $crop_pw, 'h' => $crop_ph,
                ];
                $debug_image['normalized_size'] = strlen($img_bytes);
            } else {
                $debug_image['normalize_err'] = 'GD imagejpeg returned empty';
            }
        } else {
            $debug_image['normalize_err'] = 'imagecreatefromstring failed';
        }

        // 2. Save to a temp file so cURL can send it as multipart
        $tmp_path = tempnam(sys_get_temp_dir(), 'blaze_img_') . '.jpg';
        file_put_contents($tmp_path, $img_bytes);

        // Build a safe upload name/filename. Blaze derives the S3 key from the
        // `name` POST field (NOT the multipart filename) and splits on the
        // LAST dot to determine the extension — so a product name like
        // "Gelonade 3.5" becomes an S3 key ending in ".5" instead of ".jpg",
        // S3 serves no Content-Type, and Blaze can't generate publicURL /
        // thumbURL / mediumURL. Strip dots (and all non [A-Za-z0-9_-] chars)
        // from the base name first, then append a real `.jpg` extension.
        //
        // IMPORTANT: include the `.jpg` extension in the `name` field itself.
        // If we send `name` without any extension, Blaze falls back to the
        // multipart MIME (`image/jpeg`) and writes the S3 key as `.jpeg` —
        // which Blaze's UI / CDN thumbnailer apparently does not recognize
        // (uploaded products show the default placeholder instead of the
        // image, even though the asset uploaded successfully).
        $safe_upload_name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $product_name);
        $safe_upload_name = trim(preg_replace('/-+/', '-', $safe_upload_name), '-');
        if ($safe_upload_name === '') $safe_upload_name = 'product';
        $upload_filename  = $safe_upload_name . '.jpg';
        $upload_name      = $safe_upload_name . '.jpg'; // sent in `name` POST field
        $debug_image['upload_name']     = $upload_name;
        $debug_image['upload_filename'] = $upload_filename;

        // 3. Upload to Blaze
        $upload_url = $api_url . 'asset/upload/public';
        $up_ch = curl_init($upload_url);
        curl_setopt_array($up_ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'      => new CURLFile($tmp_path, $img_mime, $upload_filename),
                'name'      => $upload_name,
                'assetType' => 'Photo',
            ],
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $auth_code,
                'X-API-KEY: '     . $partner_key,
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $upload_resp     = curl_exec($up_ch);
        $upload_err      = curl_error($up_ch);
        $upload_http     = (int) curl_getinfo($up_ch, CURLINFO_HTTP_CODE);
        curl_close($up_ch);
        @unlink($tmp_path);

        $debug_image['upload_http'] = $upload_http;
        $debug_image['upload_err']  = $upload_err ?: null;

        if ($upload_resp && !$upload_err && $upload_http >= 200 && $upload_http < 300) {
            $asset_data = json_decode($upload_resp, true);
            $blaze_asset_key          = $asset_data['key'] ?? null;
            $debug_image['asset_key'] = $blaze_asset_key;
            $debug_image['asset_raw'] = $asset_data;
        } else {
            $debug_image['upload_raw'] = $upload_resp;
        }
    } else {
        $debug_image['download_error'] = $img_err ?: 'Empty response';
    }
}

// ---- Build Blaze ProductAddRequest payload ----
$product_payload = [
    'name'          => $product_name,
    'description'   => $description,
    'unitPrice'     => $price,
    'active'        => true,
    'weightPerUnit' => $weight_per_unit,
];

if ($blaze_brand_id)    $product_payload['brandId']    = $blaze_brand_id;
if ($blaze_category_id) $product_payload['categoryId'] = $blaze_category_id;
if ($blaze_vendor_id)   $product_payload['vendorId']   = $blaze_vendor_id;
if (!empty($blaze_secondary_vendors)) $product_payload['secondaryVendors'] = $blaze_secondary_vendors;
if ($flower_type !== '') $product_payload['flowerType'] = $flower_type;

// customGramType and customWeight are only sent for Custom Weight
if ($weight_per_unit === 'Custom Weight' && $custom_weight !== null) {
    $product_payload['customGramType'] = $custom_gram_type ?: 'Gram';
    $product_payload['customWeight']   = $custom_weight;
}

// ---- Attach the uploaded asset to the new product at create time ----
// Empirical findings from extensive Blaze API testing:
//   - POST without `assets`: master spawns and propagation works, but
//     sourceMap.assets defaults to "PARENT" and the master is unreachable
//     via the Partner API (GET /products/{master_id} → 400), so the UI
//     never sees an asset.
//   - POST with a *minimal* asset stub (`id`/`key`/`type`...): the master
//     spawns and propagation works, but Blaze copies the stub verbatim
//     into the master without dereferencing `id`/`key` to fill in the
//     CDN variant URLs from the asset library — so publicURL/thumbURL/
//     mediumURL etc. all come back null on the product, and the UI shows
//     the placeholder.
//   - POST with the *full* asset object (including publicURL, thumbURL,
//     mediumURL, largeURL, largeX2URL, origURL, name): the master gets a
//     fully-populated asset → image renders everywhere.
// We send every field the upload endpoint returned so Blaze's master
// snapshot has everything the UI needs, regardless of sourceMap routing.
if (!empty($debug_image['asset_raw']['id']) && !empty($debug_image['asset_raw']['key'])) {
    $a = $debug_image['asset_raw'];
    $product_payload['assets'] = [[
        'id'              => $a['id'],
        'key'             => $a['key'],
        'name'            => $a['name']            ?? null,
        'type'            => $a['type']            ?? 'Photo',
        'assetType'       => $a['assetType']       ?? 'Photo',
        'active'          => $a['active']          ?? true,
        'priority'        => $a['priority']        ?? 0,
        'secured'         => $a['secured']         ?? false,
        'publicURL'       => $a['publicURL']       ?? null,
        'thumbURL'        => $a['thumbURL']        ?? null,
        'mediumURL'       => $a['mediumURL']       ?? null,
        'largeURL'        => $a['largeURL']        ?? null,
        'largeX2URL'      => $a['largeX2URL']      ?? null,
        'origURL'         => $a['origURL']         ?? null,
        'platformFileUrl' => $a['platformFileUrl'] ?? null,
    ]];
}

// ---- POST to create the product ----
$blaze_endpoint = $api_url . 'products';
$json_body      = json_encode($product_payload);

$ch = curl_init($blaze_endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json_body,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: '  . $auth_code,
        'X-API-KEY: '      . $partner_key,
        'Content-Length: ' . strlen($json_body),
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response  = curl_exec($ch);
$curlErr   = curl_error($ch);
$httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$blaze_response_decoded = null;
if ($response && isJson($response)) {
    $blaze_response_decoded = json_decode($response, true);
}

$success = !$curlErr && $httpCode >= 200 && $httpCode < 300;

// ---- Surface what Blaze persisted from our create-time fields ----
// The asset and sourceMap.assets="CHILD" override are sent in the POST
// payload above. Capture a quick summary of the resulting product so we
// can verify in the debug log that:
//   - masterId is still populated (i.e. propagation chain intact)
//   - sourceMap.assets is "CHILD" (i.e. the UI now reads our image)
//   - assets[] survived round-trip
$debug_asset_attach = null;
if ($success && is_array($blaze_response_decoded)) {
    $debug_asset_attach = [
        'strategy'        => 'asset_in_post_with_sourcemap_override',
        'product_id'      => $blaze_response_decoded['id']                  ?? null,
        'masterId'        => $blaze_response_decoded['masterId']            ?? null,
        'parentId'        => $blaze_response_decoded['parentId']            ?? null,
        'assets_source'   => $blaze_response_decoded['sourceMap']['assets'] ?? null,
        'returned_assets' => $blaze_response_decoded['assets']              ?? null,
    ];
}

// ---- Insert into propagation queue on successful push ----
if ($success && !empty($blaze_response_decoded['id']) && $po_product_id > 0) {
    $blaze_sku = $blaze_response_decoded['sku'] ?? '';

    // Use po_product.po_product_name (the canonical PO name), not the enrichment modal name
    $po_product_row  = getRow(getRs(
        "SELECT po_product_name FROM theartisttree.po_product WHERE po_product_id = ? LIMIT 1",
        [$po_product_id]
    ));
    $queue_product_name = $po_product_row['po_product_name'] ?? $product_name;

    setRs(
        "INSERT INTO product_push_queue (po_product_id, blaze_product_id, blaze_sku, product_name, store_db, davis_price, dixon_price)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$po_product_id, $blaze_response_decoded['id'], $blaze_sku, $queue_product_name, $store_db,
         ($davis_price > 0 ? $davis_price : null), ($dixon_price > 0 ? $dixon_price : null)]
    );

    // Mark duplicate rows as transferred so they collapse into the single
    // pending sync row. is_created stays 0 until the cron confirms the
    // product has propagated to every active store — at which point the
    // cron flips is_created = 1 and the row leaves the coordination list.
    if ($queue_product_name !== '') {
        setRs(
            "UPDATE theartisttree.po_product
             SET is_transferred = 1
             WHERE po_product_name = ?
               AND is_transferred = 0",
            [$queue_product_name]
        );
    }
}

echo json_encode([
    'success'        => $success,
    'http_code'      => $httpCode,
    'curl_error'     => $curlErr ?: null,
    'blaze_response' => $blaze_response_decoded,
    'blaze_raw'      => $response,
    'payload_sent'   => $product_payload,
    'postman'        => [
        'method'   => 'POST',
        'url'      => $blaze_endpoint,
        'headers'  => [
            'Authorization' => $auth_code,
            'X-API-KEY'     => $partner_key,
            'Content-Type'  => 'application/json',
        ],
        'body_raw' => $json_body,
    ],
    'debug'          => [
        'store1_db'    => $store1_db,
        'brand'        => $debug_brand,
        'category'     => $debug_category,
        'vendor'       => $debug_vendor,
        'image'        => $debug_image,
        'asset_attach' => $debug_asset_attach,
        'endpoint'     => $blaze_endpoint,
    ],
]);
