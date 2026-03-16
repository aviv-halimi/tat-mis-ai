<?php
/**
 * Get next PO for same store with status = 5 (Ready for Invoicing).
 * POST: po_code (current PO code)
 * Returns: { success, next_po_code, redirect_url, needs_ai_validation } or { success, redirect_url: "/pos" } if no next.
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$po_code = isset($_REQUEST['po_code']) ? trim((string) $_REQUEST['po_code']) : '';
if ($po_code === '') {
    echo json_encode(array('success' => false, 'error' => 'Missing po_code'));
    exit;
}

$current = getRow(getRs(
    "SELECT po_id, store_id FROM po WHERE " . is_enabled('po') . " AND po_code = ?",
    array($po_code)
));
if (!$current) {
    echo json_encode(array('success' => false, 'error' => 'PO not found'));
    exit;
}

$next = getRow(getRs(
    "SELECT po_id, po_code, ai_invoice_number, ai_total FROM po " .
    "WHERE " . is_enabled('po') . " AND store_id = ? AND po_status_id = 5 AND po_type_id = 1 AND po_id > ? ORDER BY po_id ASC LIMIT 1",
    array($current['store_id'], $current['po_id'])
));

if (!$next) {
    echo json_encode(array('success' => true, 'redirect_url' => '/pos'));
    exit;
}

$has_ai = (isset($next['ai_invoice_number']) && trim((string)$next['ai_invoice_number']) !== '')
    || (isset($next['ai_total']) && $next['ai_total'] !== null && (string)$next['ai_total'] !== '');
$needs_ai_validation = !$has_ai;

echo json_encode(array(
    'success' => true,
    'next_po_code' => $next['po_code'],
    'next_po_id' => (int) $next['po_id'],
    'redirect_url' => '/po/' . $next['po_code'],
    'needs_ai_validation' => $needs_ai_validation,
));
