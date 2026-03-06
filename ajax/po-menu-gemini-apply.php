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

// If the JS-passed po_id isn't in the po table, derive it from the product IDs.
// This handles cases where display settings drift and the button sends a po_product_id by mistake.
$po = getRow(getRs(
    "SELECT po_id, po_code, po_status_id, is_active, is_enabled FROM po WHERE po_id = ? LIMIT 1",
    [$po_id]
));
if (!$po && !empty($disable_ids)) {
    $sample_product_id = (int) reset($disable_ids);
    $db = $_Session->db;
    $derived = getRow(getRs(
        "SELECT po_id FROM {$db}.po_product WHERE po_product_id = ? LIMIT 1",
        [$sample_product_id]
    ));
    if ($derived && (int) $derived['po_id'] > 0) {
        $derived_po_id = (int) $derived['po_id'];
        $po = getRow(getRs(
            "SELECT po_id, po_code, po_status_id, is_active, is_enabled FROM po WHERE po_id = ? LIMIT 1",
            [$derived_po_id]
        ));
        if ($po) {
            $po_id = $derived_po_id; // use the correct PO ID going forward
        }
    }
}
if (!$po) {
    echo json_encode(['success' => false, 'error' => "PO not found (po_id={$po_id})"]);
    exit;
}
if (!(int)$po['is_active'] || !(int)$po['is_enabled']) {
    echo json_encode(['success' => false, 'error' => "PO {$po_id} is inactive (is_active={$po['is_active']}, is_enabled={$po['is_enabled']})"]);
    exit;
}
if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(['success' => false, 'error' => "PO must be in Draft status (1), current status={$po['po_status_id']}"]);
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
// Bypass SavePOCustomProduct to avoid per-row overhead (GetCodeId scan + POProgress recalc).
// Mirror exactly what SavePOCustomProduct does at line 652-656 of POManager.php.
if (!empty($add_products)) {
    $po_row = getRow(getRs("SELECT po_id FROM po WHERE po_code = ?", [$po_code]));
    $direct_po_id = $po_row ? (int) $po_row['po_id'] : 0;

    if ($direct_po_id) {
        foreach ($add_products as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') { continue; }

            $brand_id    = isset($item['brand_id'])    && (int) $item['brand_id']    > 0 ? (int) $item['brand_id']    : null;
            $category_id = isset($item['category_id']) && (int) $item['category_id'] > 0 ? (int) $item['category_id'] : null;
            $price       = isset($item['price'])       && is_numeric($item['price'])      ? (float) $item['price']     : 0;

            $new_id = dbPut('po_product', [
                'po_id'           => $direct_po_id,
                'po_product_name' => $name,
                'is_editable'     => 1,
                'is_tax'          => 0,
                'category_id'     => $category_id,
                'brand_id'        => $brand_id,
                'is_created'      => 0,
                'is_transferred'  => 0,
            ]);
            if ($new_id) {
                if ($price > 0) {
                    dbUpdate('po_product', ['price' => $price], $new_id);
                }
                $added_count++;
            } else {
                $errors[] = $name . ': insert failed';
            }
        }
    } else {
        $errors[] = 'Could not resolve po_id from po_code';
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
