<?php
include_once('inc/header.php');
$daily_discount_report_code = getVar('c');

echo '<div id="dd-report-qbo-log-box" class="mb-3"><div class="card"><div class="card-header py-2"><strong>QBO Push log</strong></div><div id="dd-report-qbo-log" class="card-body py-2 small" style="max-height:180px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;">Waiting for activity…</div></div></div>';

$rs = getRs("SELECT r.* FROM daily_discount_report r WHERE " . is_enabled('r') . " AND r.daily_discount_report_code = ?", $daily_discount_report_code);
if ($r = getRow($rs)) {
    echo $_Session->TableManager('daily-discount-report-brands', $r['daily_discount_report_id']);
}
include_once('inc/footer.php'); 
?>