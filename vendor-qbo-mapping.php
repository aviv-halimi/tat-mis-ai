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
$qbo_result = array();
$store_db = null;

$debug_log = array(
    'request_store_id_raw' => isset($_REQUEST['store_id']) ? $_REQUEST['store_id'] : '(not set)',
    'store_id_parsed' => $store_id,
    'stores_count' => is_array($stores) ? count($stores) : 0,
    'store_ids_available' => is_array($stores) ? array_map(function ($r) { return (int)$r['store_id']; }, $stores) : array(),
);
// Debug: show keys of first row in case DB returns different column names (e.g. lowercase)
if (is_array($stores) && count($stores) > 0) {
    $debug_log['first_row_keys'] = array_keys($stores[0]);
    $debug_log['first_row_store_id_value'] = isset($stores[0]['store_id']) ? $stores[0]['store_id'] : '(no store_id key)';
}
if ($store_id) {
    // Index stores by store_id so we always find the row when it's in the list
    $stores_by_id = array();
    foreach ($stores as $r) {
        $rid = isset($r['store_id']) ? (int)$r['store_id'] : (isset($r['Store_id']) ? (int)$r['Store_id'] : null);
        if ($rid !== null) {
            $stores_by_id[$rid] = $r;
        }
    }
    $s = isset($stores_by_id[$store_id]) ? $stores_by_id[$store_id] : null;
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
$qbo_needs_auth = !empty($store_id) && !empty($qbo_result['needs_authorization']) && isset($qbo_result['auth_url']) && $qbo_result['auth_url'] !== '';
$qbo_auth_url = $qbo_needs_auth ? $qbo_result['auth_url'] : '';

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
          <p class="text-muted mb-2"><strong>Last save (AJAX)</strong> — updated when you click Save</p>
          <pre id="vendor_qbo_ajax_log" class="mb-3" style="white-space: pre-wrap; word-break: break-all; font-size: 12px; min-height: 2em;">No save attempt yet.</pre>
          <p class="text-muted mb-2"><strong>Page load (store / QBO API)</strong></p>
          <pre class="mb-0" style="white-space: pre-wrap; word-break: break-all; font-size: 12px;"><?php echo htmlspecialchars(json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if ($qbo_needs_auth) { ?>
    <div class="alert alert-info mb-3" id="vendor_qbo_connect_alert">
      <strong>Connect to QuickBooks</strong> — This store is not connected to QuickBooks yet (or the connection expired).
      <button type="button" class="btn btn-primary ml-2" id="vendor_qbo_connect_btn">Connect to QuickBooks</button>
    </div>
    <script>
    (function() {
      var authUrl = <?php echo json_encode($qbo_auth_url); ?>;
      var btn = document.getElementById('vendor_qbo_connect_btn');
      if (btn && authUrl && typeof openQboAuthAndRetry === 'function') {
        btn.addEventListener('click', function() {
          openQboAuthAndRetry(authUrl, function() { location.reload(); });
        });
      }
    })();
    </script>
    <?php } ?>

    <?php if ($store_id && $store_db) { ?>
    <input type="hidden" id="vendor_qbo_store_id_loaded" value="<?php echo (int)$store_id; ?>" />
    <div id="vendor_qbo_status" class="status"></div>
    <p id="vendor_qbo_last_action" class="text-muted small mb-2" style="min-height: 1.5em;">&nbsp;</p>
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
            echo '<td><button type="button" class="btn btn-sm btn-primary btn-vendor-qbo-save" data-vendor-id="' . $vid . '" onclick="return window.vendorQboSaveClick ? window.vendorQboSaveClick(this) : false;">Save</button></td>';
            echo '</tr>';
        }
        ?>
      </tbody>
    </table>
    <?php if (empty($vendors)) { ?>
    <p class="text-muted">No vendors found for this store.</p>
    <?php } ?>
    <?php } elseif ($store_id && !$qbo_needs_auth) { ?>
    <div class="alert alert-warning">Could not load store or QBO not configured. Check store params (qbo_realm_id, qbo_refresh_token) and QBO_CLIENT_ID / QBO_CLIENT_SECRET.</div>
    <?php } ?>
  </div>
</div>
<script>
(function() {
  function showClickAcknowledged() {
    var time = new Date().toLocaleTimeString();
    var msg = 'Click acknowledged at ' + time;
    var logEl = document.getElementById('vendor_qbo_ajax_log');
    var actionEl = document.getElementById('vendor_qbo_last_action');
    var panelEl = document.getElementById('vendor_qbo_debug');
    if (logEl) logEl.textContent = msg + '\n\n(Sending request…)';
    if (actionEl) actionEl.textContent = msg;
    if (panelEl && panelEl.classList) panelEl.classList.add('in');
  }

  window.vendorQboSaveClick = function(btn) {
    showClickAcknowledged();
    var storeId = 0;
    var storeEl = document.getElementById('vendor_qbo_store_id');
    if (storeEl) storeId = parseInt(storeEl.value, 10) || 0;
    if (!storeId && typeof jQuery !== 'undefined') {
      var jqVal = jQuery('#vendor_qbo_store_id').val();
      if (jqVal) storeId = parseInt(jqVal, 10) || 0;
    }
    if (!storeId) {
      var loadedEl = document.getElementById('vendor_qbo_store_id_loaded');
      if (loadedEl && loadedEl.value) storeId = parseInt(loadedEl.value, 10) || 0;
    }
    if (!storeId) {
      alert('Please select a store and load vendors first.');
      return false;
    }
    var vendorId = btn.getAttribute('data-vendor-id');
    var row = btn.closest('tr');
    var select = row ? row.querySelector('.qbo-vendor-select') : null;
    var qboId = select ? select.value : '';
    if (!qboId) {
      alert('Please select a QBO vendor.');
      return false;
    }
    var payload = { store_id: storeId, vendor_id: vendorId, qbo_vendor_id: qboId };
    btn.disabled = true;
    btn.textContent = 'Saving…';

    function done(success, dataOrErr) {
      btn.disabled = false;
      btn.textContent = 'Save';
      var logEl = document.getElementById('vendor_qbo_ajax_log');
      var logObj = {
        request: { url: '/ajax/vendor-qbo-save.php', method: 'POST', payload: payload },
        success: success,
        response: dataOrErr
      };
      if (logEl) logEl.textContent = JSON.stringify(logObj, null, 2);
      var statusEl = document.getElementById('vendor_qbo_status');
      if (statusEl) {
        if (success && dataOrErr && dataOrErr.success) {
          statusEl.innerHTML = '<div class="alert alert-success">' + (dataOrErr.response || 'Mapping saved.') + '</div>';
          if (row) {
            var currentCell = row.querySelector('.qbo-current');
            if (currentCell) currentCell.textContent = qboId;
          }
        } else {
          var errMsg = (dataOrErr && dataOrErr.response) ? dataOrErr.response : (typeof dataOrErr === 'string' ? dataOrErr : 'Error');
          statusEl.innerHTML = '<div class="alert alert-danger">' + errMsg + '</div>';
        }
      }
      var actionEl = document.getElementById('vendor_qbo_last_action');
      if (actionEl) actionEl.textContent = success ? 'Save completed at ' + new Date().toLocaleTimeString() : 'Save failed at ' + new Date().toLocaleTimeString();
    }

    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
      jQuery.ajax({
        url: '/ajax/vendor-qbo-save.php',
        type: 'POST',
        data: payload,
        dataType: 'json'
      }).done(function(data) { done(true, data); }).fail(function(jqXHR) {
        var err = { response: 'Request failed.', status: jqXHR.status };
        if (jqXHR.responseJSON && jqXHR.responseJSON.response) err.response = jqXHR.responseJSON.response;
        else if (jqXHR.responseText && jqXHR.responseText.length < 300) err.response = jqXHR.responseText;
        done(false, err);
      });
    } else {
      var formBody = Object.keys(payload).map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]); }).join('&');
      fetch('/ajax/vendor-qbo-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: formBody
      }).then(function(r) {
        return r.json().then(function(data) {
          done(r.ok && data && data.success, data);
        }, function() {
          done(false, { response: 'Server error ' + r.status + (r.statusText ? ': ' + r.statusText : '') });
        });
      }).catch(function(e) { done(false, { response: 'Network error: ' + (e.message || 'failed') }); });
    }
    return false;
  };
})();
</script>
<?php
include_once('inc/footer.php');
