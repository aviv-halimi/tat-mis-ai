<?php
require_once('../_config.php');
$charts = array();
$product_name = null;
$categories = $series = $data = $data_target = array();
$yaxis = array(array('labels' => array('format' => '{value}'), 'title' => array('text' => 'Inventory')));
$product_id = getVar('id');


$_ds = $_Session->GetTableDisplaySettings('inventory');
$_qty = (isset($_ds['qty']))?$_ds['qty']:2;
$_days = (isset($_ds['days']))?$_ds['days']:60;
$_date = (isset($_ds['date']))?$_ds['date']:null;

$params = array($_Session->store_id, $_days, $product_id);
if ($_date) {
    $params = array($_Session->store_id, toMySqlDT($_date), $_days, toMySqlDT($_date), $product_id);
}

$rs = getRs("SELECT p.product_id, p.name, i.qty, i.date_inventory FROM inventory i INNER JOIN {$_Session->db}.product p ON p.product_id = i.product_id WHERE store_id = ? AND date_inventory >= DATE_SUB(" . iif($_date, "?", "CURDATE()") . ", INTERVAL ? DAY)" . iif($_date, " AND date_inventory <= ?") . " AND p.product_id = ?", $params);

foreach ($rs as $r) {
    $product_name = $r['name'];
    if ($r['qty']) {
        array_push($data, array(strtotime($r['date_inventory']) * 1000, $r['qty'] * 1));
    }
}
$series = array(array('name' => $product_name, 'data' => $data, 'marker' => array('enabled' => true)));
array_push($charts, array('id' => 'inventory', 'title' => 'Inventory Levels', 'subtitle' => $product_name, 'legend' => true,  'type' => 'spline', 'xtype' => 'datetime', 'dp' => 0, 'xLabel' => '', 'yaxis' => $yaxis, 'series' => $series));
?>
<div id="inventory" style="height:400px;"></div>
<script>
Highcharts.setOptions({
    lang: {
        thousandsSep: ','
    }
});
<?php
foreach($charts as $chart) {
echo 'var _' . $chart['id'] . ' = Highcharts.chart(\'' . $chart['id'] . '\',' . json_encode($_Util->Chart($chart)) . ');';
}
?>