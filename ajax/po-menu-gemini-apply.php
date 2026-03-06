<?php
/**
 * Apply Gemini reconciliation results to a PO.
 *
 * POST params:
 *   po_id        int
 *   disable_ids  JSON array of po_product_id ints  → set is_enabled = 0
 *   add_products JSON array of {name, price, brand_id, category_id} → add as custom products
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Cache-Control: no-cache');
header('Content-type: application/json');

$po_id       = isset($_POST['po_id'])       ? (int) $_POST['po_id']                                : 0;
$disable_ids = isset($_POST['disable_ids']) ? json_decode((string) $_POST['disable_ids'], true)    : [];
$add_products = isset($_POST['add_products']) ? json_decode((string) $_POST['add_products'], true) : [];

if (!$po_id) {
    echo json_encode(['success' => false, 'error' => 'Missing po_id']);
    exit;
}
if (!is_array($disable_ids))  { $disable_ids  = []; }
if (!is_array($add_products)) { $add_products = []; }

$po = getRow(getRs(
    "SELECT po_id, po_code, po_status_id FROM po WHERE " . is_enabled() . " AND po_id = ?",
    [$po_id]
));
if (!$po) {
    echo json_encode(['success' => false, 'error' => 'PO not found']);
    exit;
}
if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(['success' => false, 'error' => 'PO must be in Draft status (1)']);
    exit;
}
$po_code = $po['po_code'];

$disabled_count = 0;
$added_count    = 0;
$errors         = [];

// ---- 1. Disable products not on the menu (single query) ----
if (!empty($disable_ids)) {
    $safe_ids = array_values(array_filter(array_map('intval', $disable_ids)));
    if (!empty($safe_ids)) {
        $placeholders = implode(',', $safe_ids); // already cast to int — safe to inline
        setRs(
            "UPDATE po_product SET is_enabled = 0 WHERE po_id = ? AND po_product_id IN ({$placeholders})",
            [$po_id]
        );
        $disabled_count = count($safe_ids);
    }
}

// ---- 2. Add new products from the menu (not already on PO) ----
$_p = [];
foreach ($add_products as $item) {
    $name = trim((string) ($item['name'] ?? ''));
    if ($name === '') { continue; }
    $_p[] = [
        'po_code'             => $po_code,
        'po_product_name'     => $name,
        'is_existing_product' => 0,
        'brand_id'            => isset($item['brand_id'])    && (int) $item['brand_id']    > 0 ? (int) $item['brand_id']    : null,
        'category_id'         => isset($item['category_id']) && (int) $item['category_id'] > 0 ? (int) $item['category_id'] : null,
        'price'               => isset($item['price'])       && is_numeric($item['price'])      ? (float) $item['price']     : 0,
        'qty'                 => 0,
    ];
}
foreach ($_p as $_r) {
    $res = $_PO->SavePOCustomProduct($_r);
    if (!empty($res['success'])) {
        $added_count++;
    } else {
        $errors[] = ($_r['po_product_name']) . ': ' . ($res['response'] ?? 'Failed');
    }
}

$message = "Disabled {$disabled_count} product(s) not on menu. Added {$added_count} new product(s) from menu.";
if (!empty($errors)) {
    $message .= ' Errors (' . count($errors) . '): ' . implode('; ', array_slice($errors, 0, 5));
    if (count($errors) > 5) { $message .= ' (+ ' . (count($errors) - 5) . ' more)'; }
}

echo json_encode([
    'success'        => true,
    'disabled_count' => $disabled_count,
    'added_count'    => $added_count,
    'errors'         => $errors,
    'message'        => $message,
]);
