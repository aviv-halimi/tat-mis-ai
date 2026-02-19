<?php
/**
 * Run Gemini invoice validation for a single PO.
 * POST or GET: po_id
 * Returns JSON: { success, matched, ai_total, payment_terms, message, error? }
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Content-Type: application/json');

$po_id = isset($_REQUEST['po_id']) ? (int) $_REQUEST['po_id'] : 0;
if ($po_id <= 0) {
    echo json_encode(array('success' => false, 'error' => 'Missing or invalid po_id'));
    exit;
}

$po_code = isset($_REQUEST['po_code']) ? trim((string) $_REQUEST['po_code']) : '';
if ($po_code === '' && $po_id > 0) {
    $row = getRow(getRs("SELECT po_code FROM po WHERE po_id = ?", array($po_id)));
    $po_code = $row ? $row['po_code'] : '';
}

require_once dirname(__FILE__) . '/../inc/ai-invoice-gemini.php';

$result = runInvoiceValidationForPO($po_id);

$matched = !empty($result['matched']);
$ai_total = isset($result['ai_total']) ? $result['ai_total'] : null;
$payment_terms = isset($result['payment_terms']) ? $result['payment_terms'] : null;

if (!empty($result['debug_log'])) {
    $note = 'AI Invoice Validation: ' . ($matched ? 'Match' : 'No match') . "\n\n" . implode("\n", $result['debug_log']);
    $_PO->SavePONote($po_id, $note, isset($_Session->admin_id) ? $_Session->admin_id : null);
}

if (!empty($result['add_auto_discount']) && isset($result['discount_amount']) && $result['discount_amount'] != 0 && $po_code !== '') {
    $_PO->SavePOATDiscount(array('po_code' => $po_code, 'po_discount_name' => 'Auto-Discount to Match Invoice', 'discount_type' => 2, 'discount_amount' => $result['discount_amount'], 'is_receiving' => 1));
}

$message = $matched
    ? 'Invoice total matches. Validation saved.'
    : 'Invoice total does not match. AI total saved for reference.';

echo json_encode(array(
    'success' => true,
    'matched' => $matched,
    'ai_total' => $ai_total,
    'payment_terms' => $payment_terms,
    'message' => $message,
));
