<?php
/**
 * Vendor → QuickBooks Online mapping by store.
 * Each store has its own vendor table (blaze1.vendor, etc.); map each to a QBO vendor.
 */
include_once('_config.php');
require_once(BASE_PATH . 'inc/qbo.php');

// getVarInt('store_id') defaults to max=1; use wide range so any store id is accepted
$store_id = getVarInt('store_id', 0, 0, 99999);
$stores = getRs("SELECT store_id, store_name, db FROM store WHERE " . is_enabled() . " ORDER BY store_name");
$vendors = array();
$qbo_vendors = array();
$store_db = null;

$debug_log = array(
    'request_store_id_raw' => isset($_REQUEST['store_id']) ? $_REQUEST['store_id'] : '(not set)',
    'store_id_parsed' => $store_id,
    'stores_count' => is_array($stores) ? count($stores) : 0,
    'store_ids_available' => is_array($stores) ? array_map(function ($r) { return (int)$r['store_id']; }, $stores) : array(),
);
if ($store_id) {
    $s = null;
    foreach ($stores as $r) {
        if ((int)$r['store_id'] === $store_id) {
            $s = $r;
            break;
        }
    }
    $debug_log['store_selected'] = $s ? array('store_id' => $s['store_id'], 'store_name' => $s['store_name'], 'db' => $s['db']) : 'Store not found';
    if ($s) {
        $store_db = $s['db'];
        try {
            $vendors = getRs("SELECT vendor_id, name, QBO_ID FROM {$store_db}.vendor WHERE " . is_enabled() . " ORDER BY name");
            $debug_log['our_vendors'] = array('count' => is_array($vendors) ? count($vendors) : 0, 'error' => null);
        } catch (Throwable $e) {
            $vendors = array();
            $debug_log['our_vendors'] = array('count' => 0, 'error' => $e->getMessage());
        }
        $qbo_result = qbo_list_vendors($store_id);
        $debug_log['qbo_api_response'] = $qbo_result;
        if ($qbo_result['success'] && !empty($qbo_result['vendors'])) {
            $qbo_vendors = $qbo_result['vendors'];
        }
    }
}

include_once('inc/header.php');
?>
<div class="panel">
  <div class="panel-heading">
    <h4 class="panel-title">Vendor → QuickBooks Online Mapping</h4>
  </div>
  <div class="panel-body">
    <p class="text-muted">Select a store to map its vendors to QuickBooks Online vendors. Each location has its own QBO account and vendor list.</p>
    <form method="get" class="form-inline mb-4">
      <label class="mr-2">Store:</label>
      <select name="store_id" id="vendor_qbo_store_id" class="form-control select2" style="min-width:200px;">
        <option value="">— Select store —</option>
        <?php
        foreach ($stores as $r) {
            $sid = (int)$r['store_id'];
            echo '<option value="' . $sid . '"' . ($store_id === $sid ? ' selected' : '') . '>' . htmlspecialchars($r['store_name']) . '</option>';
        }
        ?>
      </select>
      <button type="submit" class="btn btn-primary ml-2">Load vendors</button>
    </form>

    <?php if ($store_id && !empty($debug_log)) { ?>
    <div class="panel panel-default mt-3">
      <div class="panel-heading">
        <a href="javascript:;" data-toggle="collapse" data-target="#vendor_qbo_debug" class="panel-title collapsed">Troubleshooting log (API / query results)</a>
      </div>
      <div id="vendor_qbo_debug" class="panel-collapse collapse">
        <div class="panel-body">
          <pre class="mb-0" style="white-space: pre-wrap; word-break: break-all; font-size: 12px;"><?php echo htmlspecialchars(json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if ($store_id && $store_db) { ?>
    <div id="vendor_qbo_status" class="status"></div>
    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Our vendor</th>
          <th>Current QBO mapping</th>
          <th>Map to QBO vendor</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($vendors as $v) {
            $vid = (int)$v['vendor_id'];
            $qbo_id = isset($v['QBO_ID']) ? trim($v['QBO_ID']) : '';
            $display = $qbo_id !== '' ? $qbo_id : '<span class="text-muted">—</span>';
            echo '<tr data-vendor-id="' . $vid . '">';
            echo '<td>' . htmlspecialchars($v['name']) . '</td>';
            echo '<td class="qbo-current">' . $display . '</td>';
            echo '<td><select class="form-control form-control-sm qbo-vendor-select" data-vendor-id="' . $vid . '">';
            echo '<option value="">— Select —</option>';
            foreach ($qbo_vendors as $q) {
                echo '<option value="' . htmlspecialchars($q['id']) . '"' . ($qbo_id === (string)$q['id'] ? ' selected' : '') . '>' . htmlspecialchars($q['DisplayName']) . '</option>';
            }
            echo '</select></td>';
            echo '<td><button type="button" class="btn btn-sm btn-primary btn-vendor-qbo-save" data-vendor-id="' . $vid . '">Save</button></td>';
            echo '</tr>';
        }
        ?>
      </tbody>
    </table>
    <?php if (empty($vendors)) { ?>
    <p class="text-muted">No vendors found for this store.</p>
    <?php } ?>
    <?php } elseif ($store_id) { ?>
    <div class="alert alert-warning">Could not load store or QBO not configured. Check store params (qbo_realm_id, qbo_refresh_token) and QBO_CLIENT_ID / QBO_CLIENT_SECRET.</div>
    <?php } ?>
  </div>
</div>
<script>
(function() {
  var storeId = <?php echo $store_id ? (int)$store_id : 'null'; ?>;
  if (!storeId) return;
  $('.btn-vendor-qbo-save').on('click', function() {
    var $btn = $(this);
    var vendorId = $btn.data('vendor-id');
    var $row = $btn.closest('tr');
    var qboId = $row.find('.qbo-vendor-select').val();
    if (!qboId) {
      alert('Please select a QBO vendor.');
      return;
    }
    $btn.prop('disabled', true);
    $.post('/ajax/vendor-qbo-save.php', { store_id: storeId, vendor_id: vendorId, qbo_vendor_id: qboId }, function(data) {
      $btn.prop('disabled', false);
      if (data.success) {
        $row.find('.qbo-current').text(qboId);
        $('#vendor_qbo_status').html('<div class="alert alert-success">' + data.response + '</div>').show();
        setTimeout(function() { $('#vendor_qbo_status').fadeOut(); }, 2000);
      } else {
        $('#vendor_qbo_status').html('<div class="alert alert-danger">' + (data.response || 'Error') + '</div>').show();
      }
    }, 'json');
  });
})();
</script>
<?php
include_once('inc/footer.php');
