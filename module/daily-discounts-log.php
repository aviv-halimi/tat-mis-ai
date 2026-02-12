<?php
$footer = '<script language="javascript" type="text/javascript">
$(document).ready(function(e) {
  bindForm("daily-discounts");
});
</script>';
include_once('inc/header.php');
$daily_discount_log_code = getVar('c');

$rs = getRs("SELECT l.*, w.weekday_name, s.db, s.store_name FROM store s INNER JOIN (daily_discount_log l INNER JOIN weekday w ON w.weekday_id = l.weekday_id) ON s.store_id = l.store_id WHERE " . is_enabled('l') . " AND l.daily_discount_log_code = ?", array($daily_discount_log_code));
foreach($rs as $r) {
  $params = json_decode($r['params'], true);
  echo '<div class="panel panel-default">
  <div class="panel-body">
  <form id="f_daily-discounts" method="post">
  <label><input type="radio" name="store_id" value="' . $r['store_id'] . '" checked /> This store (' . getDisplayName('store', $r['store_id']) . ')</label>
  <label><input type="radio" name="store_id" value="" /> All stores</label>
  <div class="status"></div>
  <div class="form-btns">
  <button type="submit" class="btn btn-lg btn-primary">Set Daily Discounts</button>
  </div>
  </div>
  </div>
  
  <div class="panel panel-default">
  <div class="panel-body">
  <table class="w-100">
    <tr>
      <td><b>Store:</b> ' . $r['store_name'] . '</td>
      <td><b>Weekday:</b> ' . $r['weekday_name'] . '</td>
      <td><b>Start:</b> ' . getLongDate($r['date_start']) . '</td>
      <td><b>End:</b> ' . getLongDate($r['date_end']) . '</td>
      ' . iif($r['date_end'], '<td><b>Runtime:</b> ' . getTimeDiff($r['date_start'], $r['date_end']) . '</td>') . '
      ' . iif($r['admin_id'], '<td><b>By:</b> ' . getAdminName($r['admin_id']) . '</td>') . '
      ' . iif($r['is_auto'], '<td>' . yesNoFormat(1) . ' Autorun</td>') . '
    </tr>
  </table>
  </div>
  </div>';

  if ($r['params']) {
  echo '  
  <div class="panel panel-default">
  <div class="panel-body p-0">
  <table class="table table-bordered table-striped m-0">';

  foreach($params as $b) {
    echo '<thead>
    <tr><th>' . iif($b['brand_id'], 'Brand: ' . $b['brand_name'] . ' ' . getDisplayName($r['db'] . '.brand', $b['brand_id'], 'name', 'brand_id') . ' | ') . ((isset($b['category_id']) and $b['category_id'])?'Category: ' . $b['category_name'] . ' ' . getDisplayName($r['db'] . '.category', $b['category_id'], 'name', 'category_id') . ' | ':'') . (float)$b['discount'] . '%</th><th style="text-align:right">Retail Price</th><th style="text-align:right">Prev. Sale Price</th><th style="text-align:right">New Sale Price</th></tr>
    <tbody>';
    foreach($b['products'] as $p) {
      echo '<tr><td>' . $p['product_name'] . '</td><td align="right">' . $p['price'] . '</td><td align="right">' . $p['prev_salePrice'] . '</td><td align="right">' . $p['new_salePrice'] . '</td></tr>';
    }
    echo '</tbody>';
  }
}
echo '</table>
</div></div>';
}
include_once('inc/footer.php'); 
?>