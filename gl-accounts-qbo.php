<?php
/**
 * GL Accounts from QuickBooks Online by store.
 * Lists Chart of Accounts (QBO Id and name). Optional search filter by account name.
 */
include_once('_config.php');
require_once(BASE_PATH . 'inc/qbo.php');

$store_id = getVarInt('store_id', 0, 0, 99999);
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$stores = getRs("SELECT store_id, store_name, db FROM store WHERE " . is_enabled() . " ORDER BY store_name");
$accounts = array();
$qbo_result = array();

if ($store_id) {
    $qbo_result = qbo_list_accounts($store_id, $search);
    if (!empty($qbo_result['success']) && !empty($qbo_result['accounts'])) {
        $accounts = $qbo_result['accounts'];
    }
}
$qbo_show_connect = !empty($store_id) && isset($qbo_result['success']) && !$qbo_result['success']
    && (isset($qbo_result['needs_authorization']) || isset($qbo_result['auth_url']) || (isset($qbo_result['error']) && strpos((string)$qbo_result['error'], 'params missing') !== false));
$qbo_auth_url = isset($qbo_result['auth_url']) ? $qbo_result['auth_url'] : '';
$qbo_has_auth_url = $qbo_auth_url !== '';

include_once('inc/header.php');
?>
<div class="panel">
  <div class="panel-heading">
    <h4 class="panel-title">QBO GL Accounts (Chart of Accounts)</h4>
  </div>
  <div class="panel-body">
    <p class="text-muted">Select a store to list its QuickBooks Online GL accounts. Use the search box to filter by account name, or leave blank to see all accounts.</p>
    <form method="get" class="form-inline mb-4">
      <label class="mr-2">Store:</label>
      <select name="store_id" id="gl_store_id" class="form-control select2 mr-3" style="min-width:200px;">
        <option value="">— Select store —</option>
        <?php
        foreach ($stores as $r) {
            $sid = (int)$r['store_id'];
            echo '<option value="' . $sid . '"' . ($store_id === $sid ? ' selected' : '') . '>' . htmlspecialchars($r['store_name']) . '</option>';
        }
        ?>
      </select>
      <label class="mr-2">Search name:</label>
      <input type="text" name="search" class="form-control mr-3" style="min-width:180px;" placeholder="e.g. 14-100 or Rebates" value="<?php echo htmlspecialchars($search); ?>" />
      <button type="submit" class="btn btn-primary">Load accounts</button>
    </form>

    <?php if ($qbo_show_connect && $qbo_has_auth_url) { ?>
    <div class="alert alert-info mb-3">
      <strong>Connect to QuickBooks</strong> — This store is not connected to QuickBooks yet (or the connection expired).
      <span class="d-inline-block mt-2">Click the button to open the authorization page in a new window, then return here.</span>
      <button type="button" class="btn btn-primary ml-2 mt-2" id="gl_qbo_connect_btn">Connect to QuickBooks</button>
    </div>
    <script>
    (function() {
      var authUrl = <?php echo json_encode($qbo_auth_url); ?>;
      var btn = document.getElementById('gl_qbo_connect_btn');
      if (btn && authUrl) {
        btn.addEventListener('click', function() {
          if (typeof openQboAuthAndRetry === 'function') {
            openQboAuthAndRetry(authUrl, function() { location.reload(); });
          } else {
            window.open(authUrl, 'qbo_oauth', 'width=600,height=700,scrollbars=yes');
          }
        });
      }
    })();
    </script>
    <?php } ?>

    <?php if ($store_id && !$qbo_show_connect) { ?>
    <?php if (isset($qbo_result['error']) && !$qbo_result['success']) { ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($qbo_result['error']); ?></div>
    <?php } ?>

    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>QBO Id</th>
            <th>Account name</th>
            <th>Type</th>
            <th>SubType</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($accounts as $a) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($a['id']) . '</td>';
            echo '<td>' . htmlspecialchars($a['Name']) . '</td>';
            echo '<td>' . htmlspecialchars($a['AccountType']) . '</td>';
            echo '<td>' . htmlspecialchars($a['AccountSubType']) . '</td>';
            echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
    <?php if (empty($accounts)) { ?>
    <p class="text-muted">No accounts found.<?php if ($search !== '') { ?> Try a different search or clear the search to list all accounts.<?php } ?></p>
    <?php } else { ?>
    <p class="text-muted small"><?php echo count($accounts); ?> account(s) listed.</p>
    <?php } ?>
    <?php } elseif ($store_id) { ?>
    <div class="alert alert-warning">Store selected but could not load QBO accounts. Connect to QuickBooks for this store (see above) or check the error message.</div>
    <?php } ?>
  </div>
</div>
<?php include_once('inc/footer.php'); ?>
