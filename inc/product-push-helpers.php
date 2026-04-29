<?php
/**
 * Shared helpers for the product push queue.
 *
 * Used by:
 *   - cron/process-product-push-queue.php   (production cron)
 *   - module/test-product-push-queue.php    (debug rerun page)
 *
 * Keeping the PUT helper and the per-store mutation logic here guarantees
 * the test page reproduces the exact behaviour of the cron — same body
 * encoding, same tag/price application — so we can't accidentally diverge.
 */

if (!function_exists('pushQueuePut')) {
    /**
     * Local PUT helper for the product push queue.
     *
     * The shared inc/functions.php putApi() encodes the body with
     * JSON_NUMERIC_CHECK, which silently re-types every numeric-looking string
     * in the (huge) Blaze product object to a JSON number. With nested objects
     * like priceBreaks / taxTables / vendor / brand, that's enough to make
     * Blaze validate-and-discard portions of the document while still applying
     * simpler scalar changes (e.g. a tag append).
     *
     * Encode the body verbatim and return the wire request + response so we
     * can diff what was sent vs what Blaze returned for each store.
     *
     * @return array{request_body:string,response_body:string|false,http_code:int,curl_error:?string}
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
}

if (!function_exists('applyProductPushMutations')) {
    /**
     * Apply the per-store product mutations (tag + price) the cron makes
     * before PUTting back to Blaze.
     *
     * IMPORTANT: $product_obj is mutated in place (PHP passes objects by
     * handle). Returns whether any change was applied.
     *
     * @param object $product_obj  Decoded Blaze product object (json_decode without assoc).
     * @param int    $store_id     Store id we're pushing to.
     * @param array  $q            Queue row (must expose davis_price / dixon_price).
     * @return bool                True if anything was changed.
     */
    function applyProductPushMutations($product_obj, $store_id, array $q) {
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

        return $changed;
    }
}
