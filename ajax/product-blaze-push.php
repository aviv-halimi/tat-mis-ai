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

$product_name = trim((string) ($_POST['name']        ?? ''));
$description  = trim((string) ($_POST['description'] ?? ''));
$price        = isset($_POST['price']) && is_numeric($_POST['price']) ? (float) $_POST['price'] : 0;
$brand_id     = (int) ($_POST['brand_id']    ?? 0);
$category_id  = (int) ($_POST['category_id'] ?? 0);
$store_db     = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) ($_POST['store_db']    ?? '')));
$vendor_id    = (int) ($_POST['vendor_id'] ?? 0);

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

// ---- Resolve category: originating store category_id → master_category_id → store1 Blaze id ----
$blaze_category_id = null;
$debug_category    = ['input_category_id' => $category_id, 'store_db' => $store_db];

if ($category_id > 0 && $store_db !== '') {
    $cat_row = getRow(getRs(
        "SELECT master_category_id FROM `{$store_db}`.category WHERE category_id = ? LIMIT 1",
        [$category_id]
    ));
    $debug_category['master_category_id'] = $cat_row['master_category_id'] ?? null;

    if (!empty($cat_row['master_category_id'])) {
        $master_cat_id = (int) $cat_row['master_category_id'];
        $store1_cat    = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.category WHERE master_category_id = ? AND is_active = 1 LIMIT 1",
            [$master_cat_id]
        ));
        $blaze_category_id             = $store1_cat['id'] ?? null;
        $debug_category['store1_id']   = $blaze_category_id;
    }
}

// ---- Resolve vendor: {store_db}.vendor.vendor_id → master_vendor_id → blaze1.vendor.id ----
$blaze_vendor_id = null;
$debug_vendor    = ['input_vendor_id' => $vendor_id, 'store_db' => $store_db];

if ($vendor_id > 0 && $store_db !== '') {
    $vendor_row = getRow(getRs(
        "SELECT master_vendor_id FROM `{$store_db}`.vendor WHERE vendor_id = ? LIMIT 1",
        [$vendor_id]
    ));
    $debug_vendor['master_vendor_id'] = $vendor_row['master_vendor_id'] ?? null;

    if (!empty($vendor_row['master_vendor_id'])) {
        $master_vendor_id = (int) $vendor_row['master_vendor_id'];
        $store1_vendor    = getRow(getRs(
            "SELECT id FROM `{$store1_db}`.vendor WHERE master_vendor_id = ? AND is_active = 1 LIMIT 1",
            [$master_vendor_id]
        ));
        $blaze_vendor_id              = $store1_vendor['id'] ?? null;
        $debug_vendor['store1_id']    = $blaze_vendor_id;
    }
}

// ---- Build Blaze ProductAddRequest payload ----
$product_payload = [
    'name'        => $product_name,
    'description' => $description,
    'price'       => $price,
    'active'      => true,
];

if ($blaze_brand_id) {
    $product_payload['brand'] = ['id' => $blaze_brand_id];
}
if ($blaze_category_id) {
    $product_payload['category'] = ['id' => $blaze_category_id];
}
if ($blaze_vendor_id) {
    $product_payload['vendor'] = ['id' => $blaze_vendor_id];
}

// ---- POST to Blaze API ----
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

echo json_encode([
    'success'        => $success,
    'http_code'      => $httpCode,
    'curl_error'     => $curlErr ?: null,
    'blaze_response' => $blaze_response_decoded,
    'blaze_raw'      => $response,
    'payload_sent'   => $product_payload,
    'debug'          => [
        'store1_db'    => $store1_db,
        'brand'        => $debug_brand,
        'category'     => $debug_category,
        'vendor'       => $debug_vendor,
        'endpoint'     => $blaze_endpoint,
    ],
]);
