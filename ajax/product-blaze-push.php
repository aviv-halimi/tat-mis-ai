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

$product_name  = trim((string) ($_POST['name']          ?? ''));
$description   = trim((string) ($_POST['description']   ?? ''));
$price         = isset($_POST['price'])       && is_numeric($_POST['price'])       ? (float) $_POST['price']       : 0;
$davis_price   = isset($_POST['davis_price']) && is_numeric($_POST['davis_price']) ? (float) $_POST['davis_price'] : null;
$dixon_price   = isset($_POST['dixon_price']) && is_numeric($_POST['dixon_price']) ? (float) $_POST['dixon_price'] : null;
$brand_id      = (int) ($_POST['brand_id']     ?? 0);
$category_id   = (int) ($_POST['category_id']  ?? 0);
$store_db      = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) ($_POST['store_db']  ?? '')));
$vendor_id     = (int) ($_POST['vendor_id']    ?? 0);
$po_product_id = (int) ($_POST['po_product_id'] ?? 0);
$image_url     = trim((string) ($_POST['image_url'] ?? ''));

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
$blaze_vendor_id = null;
$debug_vendor    = ['input_vendor_id' => $vendor_id, 'store_db' => $store_db];

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

// ---- Upload image to Blaze as a public asset → get assetKey ----
$blaze_asset_key = null;
$debug_image     = ['image_url' => $image_url];

if ($image_url !== '') {
    // 1. Download image bytes
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
    curl_close($img_ch);

    if ($img_bytes && strlen($img_bytes) > 0 && !$img_err) {
        // 2. Save to a temp file so cURL can send it as multipart
        $tmp_path = tempnam(sys_get_temp_dir(), 'blaze_img_') . '.jpg';
        file_put_contents($tmp_path, $img_bytes);

        // 3. Upload to Blaze
        $upload_url = $api_url . 'asset/upload/public';
        $up_ch = curl_init($upload_url);
        curl_setopt_array($up_ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'      => new CURLFile($tmp_path, 'image/jpeg', basename($product_name) . '.jpg'),
                'name'      => $product_name,
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
    'name'        => $product_name,
    'description' => $description,
    'unitPrice'   => $price,
    'active'      => true,
];

if ($blaze_brand_id)    $product_payload['brandId']   = $blaze_brand_id;
if ($blaze_category_id) $product_payload['categoryId'] = $blaze_category_id;
if ($blaze_vendor_id)   $product_payload['vendorId']   = $blaze_vendor_id;

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

// ---- Attach asset via GET-then-PUT (round-trip the server's own object) ----
// Strategy: GET the freshly-created product, inject the asset, PUT back the same
// full structure — avoids NullPointerException from sending a partial payload.
$debug_asset_attach = null;
if ($success && !empty($blaze_response_decoded['id']) && !empty($debug_image['asset_raw']['id'])) {
    $product_id = $blaze_response_decoded['id'];
    $asset_obj  = $debug_image['asset_raw'];
    $get_url    = $api_url . 'products/' . urlencode($product_id);

    // Step 1: GET the product back from Blaze
    $get_ch = curl_init($get_url);
    curl_setopt_array($get_ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $auth_code,
            'X-API-KEY: '     . $partner_key,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $get_resp = curl_exec($get_ch);
    $get_err  = curl_error($get_ch);
    $get_code = (int) curl_getinfo($get_ch, CURLINFO_HTTP_CODE);
    curl_close($get_ch);

    $debug_asset_attach = ['get_http' => $get_code, 'get_err' => $get_err ?: null];

    if (!$get_err && $get_code === 200 && $get_resp && isJson($get_resp)) {
        $full_product = json_decode($get_resp, true);

        // Step 2: inject asset into the assets array
        $full_product['assets'] = [[
            'id'       => $asset_obj['id'],
            'key'      => $asset_obj['key'],
            'type'     => 'Photo',
            'active'   => true,
            'priority' => 0,
            'secured'  => false,
        ]];

        // Step 3: PUT the full object back
        $put_body = json_encode($full_product);
        $put_url  = $get_url;

        $put_ch = curl_init($put_url);
        curl_setopt_array($put_ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $put_body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: '  . $auth_code,
                'X-API-KEY: '      . $partner_key,
                'Content-Length: ' . strlen($put_body),
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $put_resp = curl_exec($put_ch);
        $put_err  = curl_error($put_ch);
        $put_code = (int) curl_getinfo($put_ch, CURLINFO_HTTP_CODE);
        curl_close($put_ch);

        $debug_asset_attach['put_http']     = $put_code;
        $debug_asset_attach['put_err']      = $put_err ?: null;
        $debug_asset_attach['put_response'] = $put_resp ? json_decode($put_resp, true) : null;

        // Use the updated product as the final response if PUT succeeded
        if (!$put_err && $put_code >= 200 && $put_code < 300 && $put_resp) {
            $updated = json_decode($put_resp, true);
            if ($updated) $blaze_response_decoded = $updated;
        }
    } else {
        $debug_asset_attach['get_raw'] = $get_resp;
    }
}

// ---- Insert into propagation queue on successful push ----
if ($success && !empty($blaze_response_decoded['id']) && $po_product_id > 0) {
    $blaze_sku = $blaze_response_decoded['sku'] ?? '';
    setRs(
        "INSERT INTO product_push_queue (po_product_id, blaze_product_id, blaze_sku, product_name, store_db, davis_price, dixon_price)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$po_product_id, $blaze_response_decoded['id'], $blaze_sku, $product_name, $store_db,
         ($davis_price > 0 ? $davis_price : null), ($dixon_price > 0 ? $dixon_price : null)]
    );
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
