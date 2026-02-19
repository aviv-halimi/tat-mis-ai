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

require_once dirname(__FILE__) . '/../inc/ai-invoice-gemini.php';

$result = runInvoiceValidationForPO($po_id);

$matched = !empty($result['matched']);
$ai_total = isset($result['ai_total']) ? $result['ai_total'] : null;
$payment_terms = isset($result['payment_terms']) ? $result['payment_terms'] : null;

if (!empty($result['debug_log'])) {
    $note = 'AI Invoice Validation: ' . ($matched ? 'Match' : 'No match') . "\n\n" . implode("\n", $result['debug_log']);
    $_PO->SavePONote($po_id, $note, isset($_Session->admin_id) ? $_Session->admin_id : null);
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
