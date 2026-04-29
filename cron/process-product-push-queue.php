<?php
/**
 * Cron: process-product-push-queue.php
 *
 * Run as a CLI script from Plesk (not as a web page):
 *   /usr/bin/php /path/to/theartisttree-mis/cron/process-product-push-queue.php
 *
 * For each pending queue row:
 *   1. Check which stores have NOT yet been processed (stores_done column tracks completed ones).
 *   2. For each remaining store, look up the product via SKU:
 *      GET /api/v1/partner/store/inventory/sku/{sku}
 *   3. For stores where the product is found, apply updates (tags + per-store price) via
 *      GET products/{id} → modify → PUT products/{id}
 *   4. Save the per-store Blaze product ID to theartisttree.po_product.blaze_id.
 *   5. Update stores_done in the queue row so subsequent runs only retry missing stores.
 *   6. Mark the row 'done' only when every active store is accounted for.
 *
 * Max attempts: 72  (6 hours @ 5-min intervals)
 *
 * Required DB columns (see sql/product_push_queue.sql for ALTER statements):
 *   product_push_queue.stores_done  TEXT NULL  — JSON {store_id: blaze_product_id}
 *   po_product.blaze_id             VARCHAR(64) NULL
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

define('SkipAuth', true);
require_once(dirname(__FILE__) . '/../_config.php');
set_time_limit(0);

/**
 * Local PUT helper for this cron only.
 *
 * The shared inc/functions.php putApi() encodes the body with JSON_NUMERIC_CHECK,
 * which silently re-types every numeric-looking string in the (huge) Blaze
 * product object to a JSON number. With nested objects like priceBreaks /
 * taxTables / vendor / brand, that's enough to make Blaze validate-and-discard
 * portions of the document while still applying simpler scalar changes (e.g.
 * a tag append) — exactly the symptom we hit on blaze13.
 *
 * Encode the body verbatim and capture the wire request + response so we can
 * diff what was sent vs what Blaze returned for each store.
 */
function pushQueuePut($endpoint, $api_url, $auth_code, $partner_key, $data) {
    $url       = rtrim($api_url, '/') . '/' . ltrim($endpoint, '/');
    $json_data = json_encode($data);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $json_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: '  . $auth_code,
            'X-API-KEY: '      . $partner_key,
            'Content-Length: ' . strlen($json_data),
        ],
    ]);
    $resp      = curl_exec($ch);
    $http_code = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    return [
        'request_body'  => $json_data,
        'response_body' => $resp,
        'http_code'     => $http_code,
        'curl_error'    => $curl_err ?: null,
    ];
}

// ---- Load all active stores (skip store_id=2 like daily-discounts does) ----
$store_rows = getRs(
    "SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . " AND store_id <> 2 ORDER BY store_id"
);
$store_map = [];
foreach ($store_rows as $s) {
    $store_map[(int)$s['store_id']] = $s;
}

if (empty($store_map)) exit('No active stores found.');

// ---- Get pending queue items (not yet exceeded max attempts) ----
$queue = getRs(
    "SELECT * FROM product_push_queue WHERE status = 'pending' AND attempts < 72 ORDER BY pushed_at"
);

$results = [];

foreach ($queue as $q) {
    $queue_id     = (int)    $q['id'];
    $sku          = (string) $q['blaze_sku'];
    $po_product_id = (int)   $q['po_product_id'];
    $product_name = (string) ($q['product_name'] ?? '');

    if ($sku === '') {
        setRs("UPDATE product_push_queue SET status = 'failed', last_error = 'Missing SKU' WHERE id = ?", [$queue_id]);
        continue;
    }

    // ---- Decode which stores have already been successfully updated ----
    $stores_done = [];
    if (!empty($q['stores_done'])) {
        $decoded = json_decode($q['stores_done'], true);
        if (is_array($decoded)) $stores_done = $decoded;
    }

    // Stores still needing to be processed this run
    $stores_pending = [];
    foreach ($store_map as $store_id => $store) {
        if (!isset($stores_done[(string)$store_id])) {
            $stores_pending[$store_id] = $store;
        }
    }

    if (empty($stores_pending)) {
        // All stores already done — mark complete and clear duplicates
        setRs(
            "UPDATE product_push_queue SET status = 'done', completed_at = NOW() WHERE id = ?",
            [$queue_id]
        );
        if ($product_name !== '') {
            setRs(
                "UPDATE theartisttree.po_product
                 SET is_created = 1, is_transferred = 1
                 WHERE po_product_name = ?
                   AND (is_created = 0 OR is_transferred = 0)",
                [$product_name]
            );
        }
        $results[] = "Queue #{$queue_id} (SKU {$sku}): already complete (all stores in stores_done).";
        continue;
    }

    // ---- Step 1: Check propagation for pending stores via the Blaze API ----
    $newly_found  = []; // store_id => blaze_product_id (found in this run)
    $still_missing = [];
    $search_log    = [];

    foreach ($stores_pending as $store_id => $store) {
        // GET /api/v1/partner/store/inventory/sku/{sku}
        $search_json = fetchApi(
            'store/inventory/sku/' . rawurlencode($sku),
            $store['api_url'],
            $store['auth_code'],
            $store['partner_key']
        );

        $blaze_id = null;
        if ($search_json) {
            $product_data = json_decode($search_json, true);
            if (!empty($product_data['id'])) {
                $blaze_id = $product_data['id'];
                $search_log[$store_id] = "FOUND:{$blaze_id}";
            } else {
                $search_log[$store_id] = "NOT_FOUND raw=" . substr($search_json, 0, 120);
            }
        } else {
            $search_log[$store_id] = "EMPTY_RESPONSE";
        }

        if ($blaze_id) {
            $newly_found[$store_id] = $blaze_id;
        } else {
            $still_missing[] = $store_id;
        }
    }

    if (empty($newly_found)) {
        // Nothing new — increment attempts and move on
        $log_parts = [];
        foreach ($search_log as $sid => $msg) $log_parts[] = "store{$sid}: {$msg}";
        setRs("UPDATE product_push_queue SET attempts = attempts + 1 WHERE id = ?", [$queue_id]);
        $results[] = "Queue #{$queue_id} (SKU {$sku}): not yet propagated | " . implode(' || ', $log_parts);
        continue;
    }

    // ---- Step 2: Mark as processing to prevent duplicate concurrent runs ----
    setRs("UPDATE product_push_queue SET status = 'processing' WHERE id = ?", [$queue_id]);

    $errors    = [];
    $put_debug = []; // per-store snapshot of the PUT request/response wire payloads

    // ---- Step 3: Apply updates to each newly-found store ----
    foreach ($newly_found as $store_id => $blaze_product_id) {
        $store = $store_map[$store_id];

        // GET the full product object from Blaze
        $json        = fetchApi('products/' . $blaze_product_id, $store['api_url'], $store['auth_code'], $store['partner_key']);
        $product_obj = json_decode($json);

        if (!$product_obj || empty($product_obj->id)) {
            $errors[] = "Store {$store_id}: GET failed for product {$blaze_product_id}";
            continue;
        }

        $changed = false;

        // -- Add DiscountEligible tag (all stores) --
        if (!isset($product_obj->tags) || !is_array($product_obj->tags)) {
            $product_obj->tags = [];
        }
        if (!in_array('DiscountEligible', $product_obj->tags)) {
            $product_obj->tags[] = 'DiscountEligible';
            $changed = true;
        }

        // -- Davis price (store_id = 12) --
        if ($store_id == 12 && !empty($q['davis_price']) && (float)$q['davis_price'] > 0) {
            $new_price = (float) $q['davis_price'];
            if (isset($product_obj->priceBreaks[0])) {
                $product_obj->priceBreaks[0]->price = $new_price;
            }
            $product_obj->unitPrice = $new_price;
            $changed = true;
        }

        // -- Dixon price (store_id = 13) --
        if ($store_id == 13 && !empty($q['dixon_price']) && (float)$q['dixon_price'] > 0) {
            $new_price = (float) $q['dixon_price'];
            if (isset($product_obj->priceBreaks[0])) {
                $product_obj->priceBreaks[0]->price = $new_price;
            }
            $product_obj->unitPrice = $new_price;
            $changed = true;
        }

        if ($changed) {
            // PUT the modified product back. Everything outside of the tag /
            // price tweaks above (including vendorId) is sent back exactly as
            // Blaze returned it from GET — we don't touch any other fields.
            // Use the local helper (no JSON_NUMERIC_CHECK) and capture the
            // exact wire payload + Blaze response for diagnostics.
            $put_result  = pushQueuePut(
                'products/' . $blaze_product_id,
                $store['api_url'],
                $store['auth_code'],
                $store['partner_key'],
                $product_obj
            );
            $put_resp    = $put_result['response_body'];
            $put_decoded = $put_resp ? json_decode($put_resp, true) : null;

            $put_debug[(string)$store_id] = [
                'blaze_product_id'   => $blaze_product_id,
                'http_code'          => $put_result['http_code'],
                'curl_error'         => $put_result['curl_error'],
                'request_unitPrice'  => isset($product_obj->unitPrice) ? $product_obj->unitPrice : null,
                'request_pb0_price'  => isset($product_obj->priceBreaks[0]->price) ? $product_obj->priceBreaks[0]->price : null,
                'response_unitPrice' => is_array($put_decoded) ? ($put_decoded['unitPrice'] ?? null) : null,
                'response_pb0_price' => is_array($put_decoded) ? ($put_decoded['priceBreaks'][0]['price'] ?? null) : null,
                'response_tags'      => is_array($put_decoded) ? ($put_decoded['tags'] ?? null) : null,
                'request_body'       => $put_result['request_body'],
                'response_body'      => $put_resp,
            ];

            if (!$put_decoded || isset($put_decoded['field'])) {
                $errors[] = "Store {$store_id}: PUT failed (http {$put_result['http_code']}) — " . substr($put_resp ?? '', 0, 200);
                continue;
            }
        }

        // ---- Step 4: Record this store as done ----
        $stores_done[(string)$store_id] = $blaze_product_id;

        // ---- Step 5: Write blaze_id back to po_product for this store ----
        // Match on po_product_id (precise) with safety guards: correct store via po join,
        // blaze_id not yet set, product created within the last 30 days.
        setRs(
            "UPDATE theartisttree.po_product p
                INNER JOIN theartisttree.po po ON po.po_id = p.po_id
             SET p.blaze_id = ?
             WHERE p.po_product_id = ?
               AND po.store_id     = ?
               AND p.blaze_id      IS NULL
               AND p.is_enabled    = 1
               AND p.is_active     = 1
               AND p.date_created  >= NOW() - INTERVAL 30 DAY",
            [$blaze_product_id, $po_product_id, $store_id]
        );
    }

    // ---- Step 6: Persist updated stores_done and decide final status ----
    $stores_done_json = json_encode($stores_done);
    $all_done         = empty($still_missing) && empty($errors);

    if ($all_done) {
        setRs(
            "UPDATE product_push_queue
             SET status = 'done', completed_at = NOW(), stores_done = ?, attempts = attempts + 1
             WHERE id = ?",
            [$stores_done_json, $queue_id]
        );

        // Propagation complete — now it's safe to flag every duplicate
        // po_product row as created so they leave the coordination list.
        if ($product_name !== '') {
            setRs(
                "UPDATE theartisttree.po_product
                 SET is_created = 1, is_transferred = 1
                 WHERE po_product_name = ?
                   AND (is_created = 0 OR is_transferred = 0)",
                [$product_name]
            );
        }

        $results[] = "Queue #{$queue_id} (SKU {$sku}): done — all " . count($stores_done) . " stores complete.";
    } else {
        // Some stores succeeded this run, others still missing or had errors — stay pending
        $error_summary = !empty($errors) ? implode(' | ', $errors) : null;

        setRs(
            "UPDATE product_push_queue
             SET status = 'pending', stores_done = ?, last_error = ?, attempts = attempts + 1
             WHERE id = ?",
            [$stores_done_json, $error_summary, $queue_id]
        );

        $done_list    = implode(',', array_keys($stores_done));
        $missing_list = implode(',', $still_missing);
        $results[]    = "Queue #{$queue_id} (SKU {$sku}): partial — done stores [{$done_list}], still pending [{$missing_list}]"
                        . ($error_summary ? " | errors: {$error_summary}" : '');
    }

    // ---- Persist per-store PUT debug snapshot for post-mortem diffing ----
    // Stored in a separate column so a missing column (schema not yet ALTERed)
    // can't break the queue update above. See sql/product_push_queue.sql.
    if (!empty($put_debug)) {
        try {
            setRs(
                "UPDATE product_push_queue SET last_put_debug = ? WHERE id = ?",
                [json_encode($put_debug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $queue_id]
            );
        } catch (Throwable $e) {
            // Column probably doesn't exist yet — fall back to noting it once.
            $results[] = "Queue #{$queue_id}: could not persist last_put_debug ({$e->getMessage()}). "
                       . "Run the ALTER in sql/product_push_queue.sql to enable PUT debug capture.";
        }
    }
}

echo "Processed: " . count($results) . " queue items\n";
foreach ($results as $line) {
    echo $line . "\n";
}
exit(0);
?>
