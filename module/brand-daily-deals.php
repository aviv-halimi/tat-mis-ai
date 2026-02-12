<?php
$footer = '<script language="javascript" type="text/javascript">
$(document).ready(function(e) {
  $(".dailyDeal").on("change", function(e) {
    postAjax("brand-daily-deals", {id: $(this).data("id"), dd: $(this).prop("checked")?1:0}, "status_" + $(this).data("id"));
  });
});
</script>';

include_once('_config.php');

$rs = getRs("SELECT b.brand_id, b.name, b.dailyDeal, COALESCE(b.date_modified, b.date_created) AS date_modified FROM {$_Session->db}.brand b WHERE " . is_enabled('b') . " ORDER BY b.brand_id");

include_once('inc/header.php');

echo '
<div class="panel pagination-inverse m-b-0 clearfix">
<table id="t_brand" class="table table-analytics table-bordered table-hover">
<thead>
    <tr class="inverse">
        <th>ID</th>
        <th>Brand</th>
        <th>Date Modified</th>
        <th>Daily Deal Enabled</th>
    </tr>
</thead>
<tbody>';
foreach($rs as $r) {
    echo '<tr><td>' . $r['brand_id'] . '</td><td>' . $r['name'] . '</td><td data-sort="' . $r['date_modified'] . '">' . getLongDate($r['date_modified']) . '</td><td data-sort="' . $r['dailyDeal'] . '">
    <div class="icheck-primary" style="display:inline-block;">
        <input type="checkbox" class="dailyDeal" name="dailyDeal_' . $r['brand_id'] . '" data-id="' . $r['brand_id'] . '" id="dailyDeal_' . $r['brand_id'] . '" data-on-text="Yes" data-off-text="No" value="1" data-render="switchery" data-theme="primary" data-on-color="success" data-off-color="default"' . iif($r['dailyDeal'], ' checked="checked"') . '/ >
    </div>
    <div style="display:inline-block;" class="status" id="status_' . $r['brand_id'] . '"></div>
    </td></tr>';
}
echo '</tbody>
</table>
</div>';

include_once('inc/footer.php'); 
?>