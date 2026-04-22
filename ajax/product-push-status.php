<?php
/**
 * Returns current propagation status for a list of po_product_ids.
 *
 * The product-coordination-2 page polls this endpoint to keep the "Syncing"
 * badges up to date as cron/process-product-push-queue.php propagates pushed
 * products to the remaining stores.
 *
 * POST/GET param:
 *   ids  — comma-separated list of po_product_id values
 *
 * Response:
 *   {
 *     "success": true,
 *     "active_store_count": 4,
 *     "statuses": {
 *       "<po_product_id>": {
 *         "status": "pending|processing|done|failed|null",
 *         "stores_done_count": 2,
 *         "is_created": 0|1
 *       },
 *       ...
 *     }
 *   }
 */
require_once dirname(__FILE__) . '/../_config.php';

session_write_close();

header('Cache-Control: no-cache, no-store');
header('Content-Type: application/json');

$raw_ids = trim((string) ($_REQUEST['ids'] ?? ''));
$ids = [];
if ($raw_ids !== '') {
    foreach (explode(',', $raw_ids) as $v) {
        $n = (int) trim($v);
        if ($n > 0) $ids[$n] = $n;
    }
}

if (empty($ids)) {
    echo json_encode(['success' => true, 'active_store_count' => 0, 'statuses' => (object)[]]);
    exit;
}

$active_store_count = (int) getRow(getRs(
    "SELECT COUNT(*) AS cnt FROM store WHERE " . is_enabled() . " AND store_id <> 2"
))['cnt'];

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params       = array_values($ids);

$rs = getRs(
    "SELECT p.po_product_id,
            p.is_created,
            (SELECT ppq.status      FROM product_push_queue ppq WHERE ppq.po_product_id = p.po_product_id ORDER BY ppq.pushed_at DESC LIMIT 1) AS push_status,
            (SELECT ppq.stores_done FROM product_push_queue ppq WHERE ppq.po_product_id = p.po_product_id ORDER BY ppq.pushed_at DESC LIMIT 1) AS push_stores_done
     FROM   po_product p
     WHERE  p.po_product_id IN ({$placeholders})",
    $params
);

$statuses = [];
foreach ($rs as $r) {
    $done_count = 0;
    if (!empty($r['push_stores_done'])) {
        $decoded = json_decode($r['push_stores_done'], true);
        if (is_array($decoded)) $done_count = count($decoded);
    }
    $statuses[(int)$r['po_product_id']] = [
        'status'            => $r['push_status'] ?: null,
        'stores_done_count' => $done_count,
        'is_created'        => (int) $r['is_created'],
    ];
}

echo json_encode([
    'success'            => true,
    'active_store_count' => $active_store_count,
    'statuses'           => $statuses,
]);
