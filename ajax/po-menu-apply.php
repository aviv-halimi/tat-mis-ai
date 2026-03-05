<?php
/**
 * Step 3 of PO menu sync (new flow):
 * Apply user-confirmed matches and custom items to the PO.
 *
 * POST params:
 *   po_id        - int
 *   confirmed    - JSON array of {product_id, product_name, menu_price, brand_id, category_id}
 *   custom_items - JSON array of {menu_name, menu_price, brand_id, category_id}
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Content-type: application/json');

$po_id       = isset($_POST['po_id'])       ? (int) $_POST['po_id']                                      : 0;
$confirmed   = isset($_POST['confirmed'])   ? json_decode((string) $_POST['confirmed'],   true)           : [];
$custom_items = isset($_POST['custom_items']) ? json_decode((string) $_POST['custom_items'], true)        : [];

if (!$po_id) {
    echo json_encode(['success' => false, 'error' => 'Missing po_id']);
    exit;
}
if (!is_array($confirmed))   { $confirmed   = []; }
if (!is_array($custom_items)) { $custom_items = []; }

$po = getRow(getRs(
    "SELECT po_id, po_code, po_status_id FROM po WHERE " . is_enabled() . " AND po_id = ?",
    [$po_id]
));
if (!$po) {
    echo json_encode(['success' => false, 'error' => 'PO not found']);
    exit;
}
if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(['success' => false, 'error' => 'PO must be in Draft (status 1)']);
    exit;
}
$po_code = $po['po_code'];

$added  = 0;
$errors = [];

// Confirmed existing products (matched to a catalogue product_id)
foreach ($confirmed as $item) {
    $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    if (!$product_id) {
        continue;
    }
    $res = $_PO->SavePOCustomProduct([
        'po_code'             => $po_code,
        'product_id'          => $product_id,
        'po_product_name'     => isset($item['product_name']) ? (string) $item['product_name'] : '',
        'is_existing_product' => 1,
        'price'               => isset($item['menu_price']) && is_numeric($item['menu_price']) ? (float) $item['menu_price'] : 0,
        'qty'                 => 0,
    ]);
    if (!empty($res['success'])) {
        $added++;
    } else {
        $errors[] = ($item['product_name'] ?? 'Product') . ': ' . ($res['response'] ?? 'Failed');
    }
}

// Unmatched/custom items (new products not in the catalogue)
foreach ($custom_items as $item) {
    $name = isset($item['menu_name']) ? trim((string) $item['menu_name']) : '';
    if ($name === '') {
        continue;
    }
    $res = $_PO->SavePOCustomProduct([
        'po_code'             => $po_code,
        'po_product_name'     => $name,
        'is_existing_product' => 0,
        'brand_id'            => isset($item['brand_id'])    && (int) $item['brand_id']    > 0 ? (int) $item['brand_id']    : null,
        'category_id'         => isset($item['category_id']) && (int) $item['category_id'] > 0 ? (int) $item['category_id'] : null,
        'price'               => isset($item['menu_price'])  && is_numeric($item['menu_price'])  ? (float) $item['menu_price'] : 0,
        'qty'                 => 0,
    ]);
    if (!empty($res['success'])) {
        $added++;
    } else {
        $errors[] = $name . ': ' . ($res['response'] ?? 'Failed');
    }
}

$message = $added . ' product(s) added to PO.';
if (!empty($errors)) {
    $message .= ' Errors (' . count($errors) . '): ' . implode('; ', array_slice($errors, 0, 5));
    if (count($errors) > 5) {
        $message .= ' (+ ' . (count($errors) - 5) . ' more)';
    }
}

echo json_encode([
    'success' => true,
    'added'   => $added,
    'errors'  => $errors,
    'message' => $message,
]);
