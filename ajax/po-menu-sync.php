<?php
/**
 * Sync PO with brand menu (status 2): run Gemini on uploaded menu PDFs and
 * (1) set is_enabled = 0 for PO lines not on the menu,
 * (2) add custom products for menu items not already on the PO.
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . date('r', time() + 86400 * 365));
header('Content-type: application/json');

$po_code = isset($_POST['po_code']) ? trim((string) $_POST['po_code']) : '';
$po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;

if (!$po_code && !$po_id) {
    echo json_encode(array('success' => false, 'error' => 'Missing po_code or po_id'));
    exit;
}

$rs = getRs(
    "SELECT po_id, po_code, po_status_id, menu_filenames FROM po WHERE " . is_enabled() . " AND (po_code = ? OR po_id = ?) LIMIT 1",
    array($po_code ?: null, $po_id ?: $po_id)
);
$po = getRow($rs);
if (!$po) {
    echo json_encode(array('success' => false, 'error' => 'PO not found'));
    exit;
}

$po_id = (int) $po['po_id'];
$po_code = $po['po_code'];

if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(array('success' => false, 'error' => 'Sync with menu is only available when PO is in status 1 (Draft).'));
    exit;
}

$menu_filenames = $po['menu_filenames'];
if (!$menu_filenames) {
    echo json_encode(array('success' => false, 'error' => 'Upload brand menu PDFs first, then run Sync.'));
    exit;
}

$files = json_decode($menu_filenames, true);
if (!is_array($files) || empty($files)) {
    echo json_encode(array('success' => false, 'error' => 'No menu PDF files found.'));
    exit;
}

$base_path = defined('MEDIA_PATH') ? rtrim(MEDIA_PATH, '/\\') . '/po/' : '';
$pdf_paths = array();
foreach ($files as $f) {
    $name = isset($f['name']) ? $f['name'] : '';
    if ($name === '') {
        continue;
    }
    $full = $base_path . $name;
    if (file_exists($full)) {
        $pdf_paths[] = $full;
    }
}
if (empty($pdf_paths)) {
    echo json_encode(array('success' => false, 'error' => 'Menu PDF files not found on disk.'));
    exit;
}

// PO products: all lines currently on the PO (enabled); AI will return which to disable and which to add.
$products_rs = getRs(
    "SELECT t.po_product_id, COALESCE(s.name, t.po_product_name) AS product_name " .
    "FROM po_product t " .
    "LEFT JOIN " . $_Session->db . ".product s ON s.product_id = t.product_id " .
    "WHERE t.po_id = ? AND t.is_active = 1 AND t.is_enabled = 1",
    array($po_id)
);
$po_products = array();
foreach ($products_rs as $row) {
    $po_products[] = array(
        'po_product_id' => (int) $row['po_product_id'],
        'product_name' => (string) $row['product_name'],
    );
}

require_once dirname(__FILE__) . '/../inc/ai-po-menu-gemini.php';

$debug_log = array();
$result = matchPoToMenuGemini($pdf_paths, $po_products, $debug_log);

if ($result === null) {
    echo json_encode(array(
        'success' => false,
        'error' => 'AI could not process the menu PDFs.',
        'debug_log' => $debug_log,
    ));
    exit;
}

$disabled = 0;
foreach ($result['disable_po_product_ids'] as $pid) {
    $pid = (int) $pid;
    if ($pid <= 0) {
        continue;
    }
    setRs("UPDATE po_product SET is_enabled = 0 WHERE po_id = ? AND po_product_id = ?", array($po_id, $pid));
    $disabled++;
}

$added = 0;
$add_errors = array();
foreach ($result['add_products'] as $item) {
    $name = isset($item['name']) ? trim((string) $item['name']) : '';
    $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0;
    if ($name === '') {
        continue;
    }
    $res = $_PO->SavePOCustomProduct(array(
        'po_code' => $po_code,
        'po_product_name' => $name,
        'price' => $price,
        'qty' => 0,
        'is_existing_product' => 0,
    ));
    if (!empty($res['success'])) {
        $added++;
    } else {
        $add_errors[] = $name . ': ' . (isset($res['response']) ? $res['response'] : 'Failed');
    }
}

$message = 'Sync complete: ' . $disabled . ' line(s) disabled (not on menu), ' . $added . ' product(s) added from menu.';
if (!empty($add_errors)) {
    $message .= ' Add issues: ' . implode('; ', array_slice($add_errors, 0, 5));
    if (count($add_errors) > 5) {
        $message .= ' (+' . (count($add_errors) - 5) . ' more)';
    }
}

echo json_encode(array(
    'success' => true,
    'message' => $message,
    'disabled_count' => $disabled,
    'added_count' => $added,
    'add_errors' => $add_errors,
));
exit;
