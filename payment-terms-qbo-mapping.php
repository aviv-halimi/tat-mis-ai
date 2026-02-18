<?php
/**
 * Payment terms → QuickBooks Online mapping by store.
 * Map day ranges (min_days–max_days) to QBO Term for bills.
 * Run doc/payment_terms-table.sql in each store DB first.
 */
include_once('_config.php');
require_once(BASE_PATH . 'inc/qbo.php');

$store_id = getVarInt('store_id', 0, 0, 99999);
$stores = getRs("SELECT store_id, store_name, db FROM store WHERE " . is_enabled() . " ORDER BY store_name");
$terms = array();
$qbo_terms = array();
$store_db = null;

if ($store_id) {
    $stores_by_id = array();
    foreach ($stores as $r) {
        $rid = isset($r['store_id']) ? (int)$r['store_id'] : null;
        if ($rid !== null) $stores_by_id[$rid] = $r;
    }
    $s = isset($stores_by_id[$store_id]) ? $stores_by_id[$store_id] : null;
    if ($s) {
        $store_db = $s['db'];
        try {
            $terms = getRs("SELECT id, min_days, max_days, qbo_term_id, qbo_term_name FROM {$store_db}.payment_terms WHERE " . is_enabled() . " ORDER BY min_days");
        } catch (Throwable $e) {
            $terms = array();
        }
        $qbo_result = qbo_list_terms($store_id);
        if ($qbo_result['success'] && !empty($qbo_result['terms'])) {
            $qbo_terms = $qbo_result['terms'];
        }
    }
}

include_once('inc/header.php');
?>
<div class="panel">
  <div class="panel-heading">
    <h4 class="panel-title">Payment Terms → QuickBooks Online Mapping</h4>
  </div>
  <div class="panel-body">
    <p class="text-muted">Select a store to map day ranges (e.g. 1–30, 31–60) to QBO payment terms. When pushing a bill, <code>po.payment_terms</code> (days) is matched to a range to get the QBO Term.</p>
    <form method="get" class="form-inline mb-4">
      <label class="mr-2">Store:</label>
      <select name="store_id" id="pt_store_id" class="form-control select2" style="min-width:200px;">
        <option value="">— Select store —</option>
        <?php foreach ($stores as $r) {
            $sid = (int)$r['store_id'];
            echo '<option value="' . $sid . '"' . ($store_id === $sid ? ' selected' : '') . '>' . htmlspecialchars($r['store_name']) . '</option>';
        } ?>
      </select>
      <button type="submit" class="btn btn-primary ml-2">Load</button>
    </form>

    <?php if ($store_id && $store_db) { ?>
    <input type="hidden" id="pt_store_id_loaded" value="<?php echo (int)$store_id; ?>" />
    <div id="pt_status" class="status mb-2"></div>

    <div class="panel panel-default">
      <div class="panel-heading">Add range</div>
      <div class="panel-body">
        <form id="f_pt_add" class="form-inline">
          <input type="hidden" name="store_id" value="<?php echo (int)$store_id; ?>" />
          <label class="mr-2">Min days:</label>
          <input type="number" name="min_days" class="form-control mr-3" min="0" max="9999" placeholder="0" required />
          <label class="mr-2">Max days:</label>
          <input type="number" name="max_days" class="form-control mr-3" min="0" max="9999" placeholder="30" required />
          <label class="mr-2">QBO term:</label>
          <select name="qbo_term_id" id="pt_qbo_term_add" class="form-control select2 mr-3" style="min-width:180px;">
            <option value="">— Select —</option>
            <?php foreach ($qbo_terms as $t) {
                echo '<option value="' . htmlspecialchars($t['id']) . '" data-name="' . htmlspecialchars($t['Name']) . '">' . htmlspecialchars($t['Name']) . '</option>';
            } ?>
          </select>
          <button type="submit" class="btn btn-primary">Add</button>
        </form>
      </div>
    </div>

    <table class="table table-bordered table-striped mt-3">
      <thead>
        <tr>
          <th>Min days</th>
          <th>Max days</th>
          <th>QBO term</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($terms as $row) {
            $row_id = (int)$row['id'];
            $qbo_id = isset($row['qbo_term_id']) ? trim($row['qbo_term_id']) : '';
            echo '<tr data-id="' . $row_id . '">';
            echo '<td><input type="number" class="form-control form-control-sm pt-min" value="' . (int)$row['min_days'] . '" min="0" max="9999" /></td>';
            echo '<td><input type="number" class="form-control form-control-sm pt-max" value="' . (int)$row['max_days'] . '" min="0" max="9999" /></td>';
            echo '<td><select class="form-control form-control-sm pt-qbo-term">';
            echo '<option value="">— Select —</option>';
            foreach ($qbo_terms as $t) {
                $sel = ($qbo_id === (string)$t['id']) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($t['id']) . '" data-name="' . htmlspecialchars($t['Name']) . '"' . $sel . '>' . htmlspecialchars($t['Name']) . '</option>';
            }
            echo '</select></td>';
            echo '<td><button type="button" class="btn btn-sm btn-primary btn-pt-save" data-id="' . $row_id . '">Save</button></td>';
            echo '</tr>';
        }
        ?>
      </tbody>
    </table>
    <?php if (empty($terms)) { ?>
    <p class="text-muted">No payment term ranges yet. Add one above.</p>
    <?php } ?>
    <?php } elseif ($store_id) { ?>
    <div class="alert alert-warning">Could not load store or QBO. Ensure the <code>payment_terms</code> table exists in the store DB (run doc/payment_terms-table.sql) and store QBO params are set.</div>
    <?php } ?>
  </div>
</div>
<script>
(function() {
  var storeId = document.getElementById('pt_store_id_loaded');
  storeId = storeId ? parseInt(storeId.value, 10) : 0;
  if (!storeId) return;

  $('#f_pt_add').on('submit', function(e) {
    e.preventDefault();
    var $f = $(this);
    var min = parseInt($f.find('[name="min_days"]').val(), 10) || 0;
    var max = parseInt($f.find('[name="max_days"]').val(), 10) || 0;
    var qboId = $f.find('[name="qbo_term_id"]').val();
    var qboName = $f.find('[name="qbo_term_id"] option:selected').data('name') || '';
    if (!qboId) { $('#pt_status').html('<div class="alert alert-danger">Select a QBO term.</div>'); return; }
    if (min > max) { $('#pt_status').html('<div class="alert alert-danger">Min days must be ≤ max days.</div>'); return; }
    $('#pt_status').html('<span class="text-muted">Adding…</span>');
    $.post('/ajax/payment-terms-qbo-save.php', {
      store_id: storeId,
      min_days: min,
      max_days: max,
      qbo_term_id: qboId,
      qbo_term_name: qboName
    }, 'json').done(function(data) {
      if (data.success) { location.reload(); } else { $('#pt_status').html('<div class="alert alert-danger">' + (data.response || 'Error') + '</div>'); }
    }).fail(function() { $('#pt_status').html('<div class="alert alert-danger">Request failed.</div>'); });
  });

  $('.btn-pt-save').on('click', function() {
    var btn = $(this);
    var row = btn.closest('tr');
    var id = row.data('id');
    var min = parseInt(row.find('.pt-min').val(), 10) || 0;
    var max = parseInt(row.find('.pt-max').val(), 10) || 0;
    var sel = row.find('.pt-qbo-term');
    var qboId = sel.val();
    var qboName = sel.find('option:selected').data('name') || '';
    if (!qboId) { $('#pt_status').html('<div class="alert alert-danger">Select a QBO term.</div>'); return; }
    if (min > max) { $('#pt_status').html('<div class="alert alert-danger">Min days must be ≤ max days.</div>'); return; }
    btn.prop('disabled', true).text('Saving…');
    $('#pt_status').html('');
    $.post('/ajax/payment-terms-qbo-save.php', {
      store_id: storeId,
      id: id,
      min_days: min,
      max_days: max,
      qbo_term_id: qboId,
      qbo_term_name: qboName
    }, 'json').done(function(data) {
      if (data.success) { $('#pt_status').html('<div class="alert alert-success">Updated.</div>'); } else { $('#pt_status').html('<div class="alert alert-danger">' + (data.response || 'Error') + '</div>'); }
      btn.prop('disabled', false).text('Save');
    }).fail(function() { $('#pt_status').html('<div class="alert alert-danger">Request failed.</div>'); btn.prop('disabled', false).text('Save'); });
  });
})();
</script>
