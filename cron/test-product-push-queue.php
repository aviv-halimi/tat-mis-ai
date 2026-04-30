<?php
/**
 * Debug rerun page for the product push queue.
 *
 * Direct URL: /cron/test-product-push-queue.php?id={queue_id}&run=1
 *
 * Given a product_push_queue.id, replays the same per-store work that
 * cron/process-product-push-queue.php would do for that row — including
 * for stores already in stores_done — and renders the exact JSON body
 * we PUT plus the full Blaze response for each store.
 *
 * IMPORTANT: This page does NOT modify the product_push_queue row. It is
 * purely diagnostic. The Blaze PUTs DO go through to Blaze, however, so
 * each rerun is a real write against the per-store inventories.
 *
 * Lives in cron/ (not module/) because the module/ routing layer interferes
 * with direct hits. Uses inc/product-push-helpers.php so the body
 * construction + PUT encoding are byte-identical to the cron.
 */
include_once(dirname(__FILE__) . '/../_config.php');
require_once(dirname(__FILE__) . '/../inc/product-push-helpers.php');

if (!isset($_Session) || !$_Session->HasModulePermission('cron-sales')) {
    exit('Access denied');
}

set_time_limit(0);
@ini_set('memory_limit', '512M');

$queue_id  = (int) getVar('id');
$run       = ((int) getVar('run')) === 1 && $queue_id > 0;
$queue_row = false;
$store_map = [];
$results   = []; // per-store debug entries

if ($queue_id > 0) {
    // Pull queue row regardless of status — we want to support rerunning
    // rows already marked 'done'.
    $queue_row = getRow(getRs(
        "SELECT * FROM product_push_queue WHERE id = ? LIMIT 1",
        [$queue_id]
    ));
}

if ($run && $queue_row) {
    $sku          = (string) $queue_row['blaze_sku'];
    $product_name = (string) ($queue_row['product_name'] ?? '');

    // Same store list the cron uses: every active store except store_id=2.
    $store_rows = getRs(
        "SELECT store_id, db, api_url, auth_code, partner_key
         FROM store
         WHERE " . is_enabled() . " AND store_id <> 2
         ORDER BY store_id"
    );
    foreach ($store_rows as $s) {
        $store_map[(int)$s['store_id']] = $s;
    }

    foreach ($store_map as $store_id => $store) {
        $row = [
            'store_id'         => $store_id,
            'store_db'         => $store['db'],
            'sku_search_url'   => rtrim($store['api_url'], '/') . '/store/inventory/sku/' . rawurlencode($sku),
            'sku_search_resp'  => null,
            'blaze_product_id' => null,
            'get_url'          => null,
            'get_response'     => null,
            'changed'          => false,
            'request_body'     => null,
            'response_body'    => null,
            'http_code'        => null,
            'curl_error'       => null,
            'request_summary'  => null,
            'response_summary' => null,
            'note'             => null,
        ];

        // ---- Step 1: SKU lookup, mirroring the cron ----
        $search_json = fetchApi(
            'store/inventory/sku/' . rawurlencode($sku),
            $store['api_url'],
            $store['auth_code'],
            $store['partner_key']
        );
        $row['sku_search_resp'] = $search_json;

        $blaze_product_id = null;
        if ($search_json) {
            $sku_decoded = json_decode($search_json, true);
            if (!empty($sku_decoded['id'])) {
                $blaze_product_id = $sku_decoded['id'];
            }
        }

        if (!$blaze_product_id) {
            $row['note']     = 'SKU not found on this store yet (would stay pending in cron).';
            $results[$store_id] = $row;
            continue;
        }
        $row['blaze_product_id'] = $blaze_product_id;
        $row['get_url']          = rtrim($store['api_url'], '/') . '/products/' . $blaze_product_id;

        // ---- Step 2: GET full product (same call shape as cron) ----
        $get_json    = fetchApi('products/' . $blaze_product_id, $store['api_url'], $store['auth_code'], $store['partner_key']);
        $product_obj = json_decode($get_json);
        $row['get_response'] = $get_json;

        if (!$product_obj || empty($product_obj->id)) {
            $row['note']        = 'GET returned no usable product object.';
            $results[$store_id] = $row;
            continue;
        }

        // ---- Step 3: Apply identical mutations the cron does ----
        // Unlike the cron, the test page ALWAYS issues the PUT below — even
        // when no mutations were applied — so we can observe Blaze's response
        // to a no-op-shaped roundtrip and confirm the wire payload is what
        // we expect.
        $changed = applyProductPushMutations($product_obj, $store_id, $queue_row);
        $row['changed'] = $changed;

        if (!$changed) {
            $row['note'] = 'No mutations applied (tag already present and no per-store price set) — PUTting anyway for diagnostic parity.';
        }

        // Snapshot the would-be-sent values for the at-a-glance row above the JSON dumps.
        $row['request_summary'] = [
            'unitPrice'         => isset($product_obj->unitPrice) ? $product_obj->unitPrice : null,
            'priceBreaks_0'     => isset($product_obj->priceBreaks[0]->price) ? $product_obj->priceBreaks[0]->price : null,
            'tags'              => isset($product_obj->tags) ? $product_obj->tags : null,
        ];

        // ---- Step 4: PUT via the shared helper (no JSON_NUMERIC_CHECK) ----
        $put_result = pushQueuePut(
            'products/' . $blaze_product_id,
            $store['api_url'],
            $store['auth_code'],
            $store['partner_key'],
            $product_obj
        );

        $row['request_body']  = $put_result['request_body'];
        $row['response_body'] = $put_result['response_body'];
        $row['http_code']     = $put_result['http_code'];
        $row['curl_error']    = $put_result['curl_error'];

        $put_decoded = $put_result['response_body'] ? json_decode($put_result['response_body'], true) : null;
        if (is_array($put_decoded)) {
            $row['response_summary'] = [
                'unitPrice'     => $put_decoded['unitPrice']             ?? null,
                'priceBreaks_0' => $put_decoded['priceBreaks'][0]['price'] ?? null,
                'tags'          => $put_decoded['tags']                  ?? null,
            ];
        }

        $results[$store_id] = $row;
    }
}

include_once(dirname(__FILE__) . '/../inc/header.php');

/** Pretty-print a JSON string (or pass-through if it isn't JSON). */
function ttp_pretty_json($raw) {
    if ($raw === null || $raw === '' || $raw === false) return '(empty)';
    $decoded = json_decode($raw);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $raw;
}
?>

<style>
    .debug-block { background: #f6f8fa; border: 1px solid #d1d5da; border-radius: 4px; padding: 10px; margin: 6px 0 14px; max-height: 360px; overflow: auto; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 11.5px; white-space: pre; }
    .debug-summary { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; background: #eef; padding: 8px 10px; border-radius: 4px; }
    .debug-store-card { border: 1px solid #cbd5e0; border-radius: 6px; padding: 12px 16px; margin-bottom: 18px; background: #fff; }
    .debug-store-card h4 { margin-top: 0; }
    .badge-http-2 { background: #28a745; }
    .badge-http-4 { background: #ffc107; color: #212529; }
    .badge-http-5 { background: #dc3545; }
    .debug-note { background: #fff3cd; border: 1px solid #ffeeba; padding: 8px 10px; border-radius: 4px; color: #856404; }
</style>

<div class="panel">
    <div class="panel-heading">
        <h4 class="panel-title">Test Product Push Queue Run</h4>
    </div>
    <div class="panel-body">
        <p>
            Re-runs the per-store push for a single <code>product_push_queue.id</code>,
            even if the row is already marked <code>done</code>. Reuses the exact
            tag/price mutation + JSON encoding the cron does (via
            <code>inc/product-push-helpers.php</code>) so we can recreate any
            anomaly here. <strong>This page does NOT modify the queue row, but
            the PUTs are real Blaze writes.</strong>
        </p>

        <form method="GET" action="" class="form-inline">
            <input type="hidden" name="run" value="1">
            <div class="form-group" style="margin-right:8px;">
                <label for="id" style="margin-right:6px;">Queue ID:</label>
                <input type="number" name="id" id="id" class="form-control" value="<?= htmlspecialchars((string)$queue_id) ?>" min="1" required>
            </div>
            <button type="submit" class="btn btn-primary">Re-run</button>
        </form>
    </div>
</div>

<?php if ($queue_id > 0 && !$queue_row): ?>
    <div class="alert alert-danger">No <code>product_push_queue</code> row found for id <?= (int)$queue_id ?>.</div>
<?php endif; ?>

<?php if ($queue_row): ?>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title">Queue Row #<?= (int)$queue_row['id'] ?></h4></div>
        <div class="panel-body">
            <table class="table table-condensed table-bordered" style="width:auto;">
                <tr><th>SKU</th><td><?= htmlspecialchars((string)$queue_row['blaze_sku']) ?></td>
                    <th>Status</th><td><?= htmlspecialchars((string)$queue_row['status']) ?></td></tr>
                <tr><th>Product</th><td colspan="3"><?= htmlspecialchars((string)$queue_row['product_name']) ?></td></tr>
                <tr><th>po_product_id</th><td><?= (int)$queue_row['po_product_id'] ?></td>
                    <th>store_db</th><td><?= htmlspecialchars((string)$queue_row['store_db']) ?></td></tr>
                <tr><th>davis_price</th><td><?= htmlspecialchars((string)($queue_row['davis_price'] ?? '')) ?></td>
                    <th>dixon_price</th><td><?= htmlspecialchars((string)($queue_row['dixon_price'] ?? '')) ?></td></tr>
                <tr><th>attempts</th><td><?= (int)$queue_row['attempts'] ?></td>
                    <th>completed_at</th><td><?= htmlspecialchars((string)($queue_row['completed_at'] ?? '')) ?></td></tr>
                <tr><th>stores_done</th>
                    <td colspan="3"><code><?= htmlspecialchars((string)($queue_row['stores_done'] ?? '')) ?></code></td></tr>
                <tr><th>last_error</th>
                    <td colspan="3"><code><?= htmlspecialchars((string)($queue_row['last_error'] ?? '')) ?></code></td></tr>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($run && $queue_row): ?>
    <?php if (empty($results)): ?>
        <div class="alert alert-warning">No active stores returned (other than store_id=2). Nothing to run.</div>
    <?php endif; ?>

    <?php foreach ($results as $store_id => $r): ?>
        <?php
            $http_class = '';
            if ($r['http_code']) {
                $first = (int) substr((string)$r['http_code'], 0, 1);
                $http_class = $first === 2 ? 'badge-http-2' : ($first === 4 ? 'badge-http-4' : ($first === 5 ? 'badge-http-5' : ''));
            }
        ?>
        <div class="debug-store-card">
            <h4>
                Store <?= (int)$store_id ?> &mdash; <code><?= htmlspecialchars((string)$r['store_db']) ?></code>
                <?php if ($r['http_code']): ?>
                    <span class="label <?= $http_class ?>" style="font-size:12px;">HTTP <?= (int)$r['http_code'] ?></span>
                <?php endif; ?>
                <?php if (!$r['changed'] && $r['request_body'] !== null): ?>
                    <span class="label label-default" style="font-size:11px;">PUT sent with no mutations</span>
                <?php endif; ?>
            </h4>

            <?php if ($r['note']): ?>
                <div class="debug-note"><?= htmlspecialchars((string)$r['note']) ?></div>
            <?php endif; ?>

            <?php if ($r['blaze_product_id']): ?>
                <p>
                    Blaze product id: <code><?= htmlspecialchars((string)$r['blaze_product_id']) ?></code>
                    &nbsp;&middot;&nbsp; GET: <code><?= htmlspecialchars((string)$r['get_url']) ?></code>
                </p>
            <?php endif; ?>

            <?php if ($r['curl_error']): ?>
                <div class="alert alert-danger">cURL error: <?= htmlspecialchars((string)$r['curl_error']) ?></div>
            <?php endif; ?>

            <?php if ($r['request_summary'] || $r['response_summary']): ?>
                <div class="debug-summary">
                    <?php if ($r['request_summary']): ?>
                        <div><strong>Request:</strong>
                            unitPrice=<?= htmlspecialchars(json_encode($r['request_summary']['unitPrice'])) ?>,
                            priceBreaks[0].price=<?= htmlspecialchars(json_encode($r['request_summary']['priceBreaks_0'])) ?>,
                            tags=<?= htmlspecialchars(json_encode($r['request_summary']['tags'])) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($r['response_summary']): ?>
                        <div><strong>Response:</strong>
                            unitPrice=<?= htmlspecialchars(json_encode($r['response_summary']['unitPrice'])) ?>,
                            priceBreaks[0].price=<?= htmlspecialchars(json_encode($r['response_summary']['priceBreaks_0'])) ?>,
                            tags=<?= htmlspecialchars(json_encode($r['response_summary']['tags'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($r['sku_search_resp'] !== null): ?>
                <details>
                    <summary>SKU search response (<code>GET <?= htmlspecialchars((string)$r['sku_search_url']) ?></code>)</summary>
                    <div class="debug-block"><?= htmlspecialchars(ttp_pretty_json($r['sku_search_resp'])) ?></div>
                </details>
            <?php endif; ?>

            <?php if ($r['get_response'] !== null): ?>
                <details>
                    <summary>GET product response (pre-mutation)</summary>
                    <div class="debug-block"><?= htmlspecialchars(ttp_pretty_json($r['get_response'])) ?></div>
                </details>
            <?php endif; ?>

            <?php if ($r['request_body'] !== null): ?>
                <details open>
                    <summary>PUT request body sent to Blaze</summary>
                    <div class="debug-block"><?= htmlspecialchars(ttp_pretty_json($r['request_body'])) ?></div>
                </details>
            <?php endif; ?>

            <?php if ($r['response_body'] !== null): ?>
                <details open>
                    <summary>Blaze PUT response</summary>
                    <div class="debug-block"><?= htmlspecialchars(ttp_pretty_json($r['response_body'])) ?></div>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
require_once(dirname(__FILE__) . '/../inc/footer.php');
?>
