<?php
/**
 * Cron: process-product-push-queue.php
 *
 * Run as a CLI script from Plesk (not as a web page):
 *   /usr/bin/php /path/to/theartisttree-mis/cron/process-product-push-queue.php
 *
 * For each pending queue row:
 *   1. Check whether the product (identified by SKU) has propagated to every active store's
 *      local DB.  If not yet present in any store, increment attempts and skip.
 *   2. Once found everywhere, apply per-store updates via GET → modify → PUT:
 *      - Add "DiscountEligible" tag to every store
 *      - Set Davis price  (store_id = 12)
 *      - Set Dixon price  (store_id = 13)
 *   3. Mark the row done (or failed after too many attempts).
 *
 * Max attempts: 72  (6 hours @ 5-min intervals)
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

define('SkipAuth', true);
require_once(dirname(__FILE__) . '/../_config.php');
set_time_limit(0);

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
    $queue_id = (int) $q['id'];
    $sku      = (string) $q['blaze_sku'];

    if ($sku === '') {
        setRs("UPDATE product_push_queue SET status = 'failed', last_error = 'Missing SKU' WHERE id = ?", [$queue_id]);
        continue;
    }

    // ---- Step 1: Check propagation via the Blaze API (not local DB, which may lag) ----
    // Search each store's API for the product by SKU. Once all stores return a match
    // we know propagation is complete and we have the per-store Blaze product ObjectId.
    $store_blaze_ids  = []; // store_id => Blaze product ObjectId for that store
    $all_propagated   = true;
    $product_name     = (string) ($q['product_name'] ?? '');
    $store_search_log = []; // debug: what each store returned

    foreach ($store_map as $store_id => $store) {
        // GET /api/v1/partner/products/sku/{sku} — returns a single product by SKU
        $search_json = fetchApi(
            'products/sku/' . rawurlencode($sku),
            $store['api_url'],
            $store['auth_code'],
            $store['partner_key']
        );

        $blaze_id = null;

        if ($search_json) {
            $product_data = json_decode($search_json, true);

            if (!empty($product_data['id'])) {
                $blaze_id = $product_data['id'];
                $store_search_log[$store_id] = "FOUND:{$blaze_id}";
            } else {
                $store_search_log[$store_id] = "NOT_FOUND raw=" . substr($search_json, 0, 200);
            }
        } else {
            $store_search_log[$store_id] = "EMPTY_RESPONSE";
        }

        if (!$blaze_id) {
            $all_propagated = false;
            // Don't break — keep checking remaining stores so we get the full picture
        }

        if ($blaze_id) {
            $store_blaze_ids[$store_id] = $blaze_id;
        }
    }

    if (!$all_propagated) {
        $log_parts = [];
        foreach ($store_search_log as $sid => $msg) {
            $log_parts[] = "store{$sid}: {$msg}";
        }
        setRs("UPDATE product_push_queue SET attempts = attempts + 1 WHERE id = ?", [$queue_id]);
        $results[] = "Queue #{$queue_id} (SKU {$sku}): not yet propagated | " . implode(' || ', $log_parts);
        continue;
    }

    // ---- Step 2: Mark as processing to prevent duplicate runs ----
    setRs("UPDATE product_push_queue SET status = 'processing' WHERE id = ?", [$queue_id]);

    $errors = [];

    // ---- Step 3: Apply updates to each store ----
    foreach ($store_blaze_ids as $store_id => $blaze_product_id) {
        $store = $store_map[$store_id];

        // GET the full product object from Blaze
        $json       = fetchApi('products/' . $blaze_product_id, $store['api_url'], $store['auth_code'], $store['partner_key']);
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
            if (isset($product_obj->priceBreaks) && is_array($product_obj->priceBreaks) && isset($product_obj->priceBreaks[0])) {
                $product_obj->priceBreaks[0]->price = $new_price;
            }
            $product_obj->unitPrice = $new_price;
            $changed = true;
        }

        // -- Dixon price (store_id = 13) --
        if ($store_id == 13 && !empty($q['dixon_price']) && (float)$q['dixon_price'] > 0) {
            $new_price = (float) $q['dixon_price'];
            if (isset($product_obj->priceBreaks) && is_array($product_obj->priceBreaks) && isset($product_obj->priceBreaks[0])) {
                $product_obj->priceBreaks[0]->price = $new_price;
            }
            $product_obj->unitPrice = $new_price;
            $changed = true;
        }

        if (!$changed) continue;

        // PUT the modified product back
        $put_resp        = putApi('products/' . $blaze_product_id, $store['api_url'], $store['auth_code'], $store['partner_key'], $product_obj);
        $put_decoded     = json_decode($put_resp, true);

        if (!$put_decoded || isset($put_decoded['field'])) {
            $errors[] = "Store {$store_id}: PUT failed — " . substr($put_resp ?? '', 0, 200);
        }
    }

    // ---- Step 4: Mark done or failed ----
    if (empty($errors)) {
        setRs(
            "UPDATE product_push_queue SET status = 'done', completed_at = NOW() WHERE id = ?",
            [$queue_id]
        );
        $results[] = "Queue #{$queue_id} (SKU {$sku}): done — updated " . count($store_blaze_ids) . " stores.";
    } else {
        $error_str = implode(' | ', $errors);
        setRs(
            "UPDATE product_push_queue SET status = 'failed', last_error = ? WHERE id = ?",
            [$error_str, $queue_id]
        );
        $results[] = "Queue #{$queue_id} (SKU {$sku}): FAILED — {$error_str}";
    }
}

echo "Processed: " . count($results) . " queue items\n";
foreach ($results as $line) {
    echo $line . "\n";
}
exit(0);
?>
