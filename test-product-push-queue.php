<?php
/**
 * Diagnostic page for the product push queue.
 *
 * Open with: /test-product-push-queue.php?queue_id=7
 *
 * For the given queue row, it walks through the exact flow the cron runs:
 *   1) GET /store/inventory/sku/{sku}  (per store)
 *   2) GET /products/{id}              (per store)
 *   3) Inspect the product JSON — vendorId, vendor object, tags
 *   4) Attempt a minimal PUT (DiscountEligible tag only) and show raw response
 *
 * It makes NO database writes — purely read-only / API-round-trip.
 * Safe to run repeatedly.
 */
define('SkipAuth', true);
require_once(dirname(__FILE__) . '/_config.php');
set_time_limit(300);

header('Content-Type: text/html; charset=utf-8');

$queue_id = isset($_GET['queue_id']) ? (int) $_GET['queue_id'] : 7;
$do_put   = isset($_GET['put']) && $_GET['put'] === '1';
$only_sid = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function jsonPretty($v) { return h(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); }

// ---- Load queue row ----
$q = getRow(getRs("SELECT * FROM product_push_queue WHERE id = ? LIMIT 1", [$queue_id]));
if (!$q) { echo "<h1>Queue #{$queue_id} not found</h1>"; exit; }

$sku            = (string) $q['blaze_sku'];
$po_product_id  = (int)    $q['po_product_id'];
$source_store_db = (string)$q['store_db'];

// ---- Source vendor info ----
$po_row = getRow(getRs(
    "SELECT po.po_id, po.po_code, po.vendor_id AS src_vendor_id, po.vendor_name AS src_vendor_name
     FROM theartisttree.po_product pp
     INNER JOIN theartisttree.po po ON po.po_id = pp.po_id
     WHERE pp.po_product_id = ? LIMIT 1",
    [$po_product_id]
));
$src_vendor_name = $po_row['src_vendor_name'] ?? null;
$src_vendor_in_src_store = null;
if (!empty($po_row['src_vendor_id']) && $source_store_db !== '') {
    $src_vendor_in_src_store = getRow(getRs(
        "SELECT vendor_id, id, name, is_active, is_enabled FROM `{$source_store_db}`.vendor WHERE vendor_id = ? LIMIT 1",
        [(int)$po_row['src_vendor_id']]
    ));
}

// ---- Active stores ----
$store_rows = getRs(
    "SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . " AND store_id <> 2 ORDER BY store_id"
);

?>
<!doctype html>
<html><head><meta charset="utf-8">
<title>Product Push Queue — Diagnostic</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; padding: 20px; max-width: 1400px; }
  h1 { margin-bottom: 5px; }
  h2 { background: #eef; padding: 6px 10px; margin-top: 30px; border-left: 4px solid #36c; }
  h3 { margin: 14px 0 6px; }
  table.kv { border-collapse: collapse; margin-bottom: 12px; }
  table.kv td { border: 1px solid #ccc; padding: 4px 10px; font-size: 13px; vertical-align: top; }
  table.kv td:first-child { background: #f7f7f7; font-weight: 600; white-space: nowrap; }
  pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; font-size: 11px; max-height: 400px; overflow: auto; }
  .ok  { color: #080; }
  .bad { color: #c00; }
  .warn { color: #a60; }
  .store { border: 2px solid #999; padding: 14px; margin-bottom: 16px; border-radius: 6px; }
  .store.err { border-color: #c00; background: #fff5f5; }
  .store.ok  { border-color: #080; background: #f5fff5; }
  .btn { display:inline-block; padding:6px 12px; background:#36c; color:#fff; text-decoration:none; border-radius:4px; font-size:13px; margin-right:6px; }
  .btn.warn { background:#c60; }
  .btn.bad  { background:#c00; }
  .small { font-size: 11px; color: #666; }
</style>
</head><body>

<h1>Product Push Queue — Diagnostic</h1>
<div class="small">Queue #<?=h($queue_id)?> | SKU <?=h($sku)?> | Source: <?=h($source_store_db)?> | PO Product #<?=h($po_product_id)?></div>

<div style="margin:12px 0;">
  <a class="btn" href="?queue_id=<?=h($queue_id)?>">Re-run (GET-only)</a>
  <a class="btn warn" href="?queue_id=<?=h($queue_id)?>&amp;put=1"
     onclick="return confirm('This will send PUT requests to Blaze for each store. Continue?')">
     Re-run WITH minimal PUT test
  </a>
  <?php if ($only_sid): ?>
    <a class="btn" href="?queue_id=<?=h($queue_id)?><?=$do_put?'&put=1':''?>">Show all stores</a>
  <?php endif; ?>
</div>

<h2>Queue row</h2>
<table class="kv">
  <?php foreach ($q as $k => $v): if ($k === 'last_error') continue; ?>
    <tr><td><?=h($k)?></td><td><?=h($v)?></td></tr>
  <?php endforeach; ?>
</table>
<?php if (!empty($q['last_error'])): ?>
  <h3>Last error</h3>
  <pre><?=h($q['last_error'])?></pre>
<?php endif; ?>

<h2>Source vendor resolution (store <?=h($source_store_db)?>)</h2>
<table class="kv">
  <tr><td>po.vendor_id</td><td><?=h($po_row['src_vendor_id'] ?? '')?></td></tr>
  <tr><td>po.vendor_name</td><td><?=h($src_vendor_name ?? '(missing)')?></td></tr>
  <tr><td><?=h($source_store_db)?>.vendor (by vendor_id)</td>
      <td><?= $src_vendor_in_src_store ? '<pre>'.jsonPretty($src_vendor_in_src_store).'</pre>' : '<span class="bad">not found</span>' ?></td></tr>
</table>

<?php
foreach ($store_rows as $s) {
    $sid = (int) $s['store_id'];
    if ($only_sid && $sid !== $only_sid) continue;

    echo '<div class="store" id="store' . $sid . '">';
    echo '<h2 style="margin-top:0;">Store ' . $sid . ' (' . h($s['db']) . ')</h2>';

    // ---- 1) SKU lookup ----
    $search_url = rtrim($s['api_url'], '/') . '/store/inventory/sku/' . rawurlencode($sku);
    echo '<h3>Step 1: GET ' . h($search_url) . '</h3>';

    $t0 = microtime(true);
    $search_json = fetchApi('store/inventory/sku/' . rawurlencode($sku), $s['api_url'], $s['auth_code'], $s['partner_key']);
    $t1 = microtime(true);
    $search_decoded = $search_json ? json_decode($search_json, true) : null;
    $blaze_id = $search_decoded['id'] ?? null;

    echo '<div class="small">Elapsed: ' . round(($t1 - $t0) * 1000) . ' ms | Response size: ' . strlen((string)$search_json) . ' bytes</div>';
    if (!$blaze_id) {
        echo '<p class="bad">No product id returned — skipping this store.</p>';
        echo '<pre>' . h(substr((string)$search_json, 0, 2000)) . '</pre>';
        echo '</div>';
        continue;
    }
    echo '<p class="ok">Blaze product id: <b>' . h($blaze_id) . '</b></p>';

    // ---- 2) GET full product ----
    $get_url = rtrim($s['api_url'], '/') . '/products/' . $blaze_id;
    echo '<h3>Step 2: GET ' . h($get_url) . '</h3>';

    $t0 = microtime(true);
    $json = fetchApi('products/' . $blaze_id, $s['api_url'], $s['auth_code'], $s['partner_key']);
    $t1 = microtime(true);

    $decoded = $json ? json_decode($json, true) : null;
    if (!$decoded) {
        echo '<p class="bad">GET returned no/invalid JSON.</p>';
        echo '<pre>' . h(substr((string)$json, 0, 2000)) . '</pre>';
        echo '</div>';
        continue;
    }

    $vendorId  = $decoded['vendorId']   ?? null;
    $vendorObj = $decoded['vendor']     ?? null; // may exist as nested object
    $brandId   = $decoded['brandId']    ?? null;
    $categoryId= $decoded['categoryId'] ?? null;
    $tags      = $decoded['tags']       ?? [];
    $active    = $decoded['active']     ?? null;

    echo '<div class="small">Elapsed: ' . round(($t1 - $t0) * 1000) . ' ms | Response size: ' . strlen((string)$json) . ' bytes</div>';

    echo '<table class="kv">';
    echo '<tr><td>vendorId</td><td>' . h($vendorId ?? '(missing)') . '</td></tr>';
    echo '<tr><td>vendor (nested)</td><td>' . (is_array($vendorObj) ? '<pre>'.jsonPretty($vendorObj).'</pre>' : h($vendorObj ?? '(none)')) . '</td></tr>';
    echo '<tr><td>brandId</td><td>' . h($brandId ?? '(none)') . '</td></tr>';
    echo '<tr><td>categoryId</td><td>' . h($categoryId ?? '(none)') . '</td></tr>';
    echo '<tr><td>active</td><td>' . h(var_export($active, true)) . '</td></tr>';
    echo '<tr><td>tags</td><td>' . h(json_encode($tags)) . '</td></tr>';
    echo '</table>';

    // ---- Cross-check vendorId against local store DB ----
    echo '<h3>Cross-check: does that vendorId exist in ' . h($s['db']) . '.vendor?</h3>';
    if ($vendorId) {
        $local_v = getRow(getRs(
            "SELECT vendor_id, id, name, is_active, is_enabled FROM `{$s['db']}`.vendor WHERE id = ? LIMIT 1",
            [$vendorId]
        ));
        if ($local_v) {
            echo '<pre class="ok">' . jsonPretty($local_v) . '</pre>';
        } else {
            echo '<p class="bad">NOT FOUND in local ' . h($s['db']) . '.vendor by id = ' . h($vendorId) . '</p>';
        }
    } else {
        echo '<p class="bad">vendorId is empty in the product JSON.</p>';
    }

    if ($src_vendor_name) {
        $by_name = getRow(getRs(
            "SELECT vendor_id, id, name, is_active, is_enabled FROM `{$s['db']}`.vendor WHERE name = ? LIMIT 1",
            [$src_vendor_name]
        ));
        echo '<div class="small">Looked up source vendor name (<b>' . h($src_vendor_name) . '</b>) in ' . h($s['db']) . '.vendor:</div>';
        if ($by_name) {
            echo '<pre>' . jsonPretty($by_name) . '</pre>';
        } else {
            echo '<p class="warn">No vendor with that exact name in this store.</p>';
        }
    }

    // Full JSON (collapsed)
    echo '<details><summary>Full product JSON from GET (' . strlen((string)$json) . ' bytes)</summary>';
    echo '<pre>' . jsonPretty($decoded) . '</pre>';
    echo '</details>';

    // ---- 3) Minimal PUT test (only if ?put=1) ----
    if ($do_put) {
        echo '<h3>Step 3: PUT ' . h($get_url) . ' (minimal change — add DiscountEligible tag)</h3>';

        // Decode WITHOUT assoc so empty JSON objects stay as stdClass and re-encode as {} (see product-blaze-push.php comment)
        $put_obj = json_decode($json);
        if (!isset($put_obj->tags) || !is_array($put_obj->tags)) $put_obj->tags = [];
        if (!in_array('DiscountEligible', $put_obj->tags)) $put_obj->tags[] = 'DiscountEligible';

        $t0 = microtime(true);
        $put_resp = putApi('products/' . $blaze_id, $s['api_url'], $s['auth_code'], $s['partner_key'], $put_obj);
        $t1 = microtime(true);

        $put_decoded = $put_resp ? json_decode($put_resp, true) : null;
        $looks_ok = $put_decoded && !isset($put_decoded['field']) && !isset($put_decoded['errorType']);

        echo '<div class="small">Elapsed: ' . round(($t1 - $t0) * 1000) . ' ms | Response size: ' . strlen((string)$put_resp) . ' bytes</div>';
        if ($looks_ok) {
            echo '<p class="ok"><b>PUT succeeded.</b></p>';
        } else {
            echo '<p class="bad"><b>PUT failed:</b></p>';
        }
        echo '<pre>' . h(substr((string)$put_resp, 0, 4000)) . '</pre>';
    } else {
        echo '<p class="small"><i>(Add <code>&amp;put=1</code> to the URL to attempt a PUT for diagnostic purposes.)</i></p>';
    }

    echo '</div>'; // .store
}
?>

</body></html>
