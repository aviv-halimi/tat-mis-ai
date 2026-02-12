<?php
include_once('inc/header.php');
$daily_discount_report_code = getVar('c');

$rs = getRs("SELECT r.* FROM daily_discount_report r WHERE " . is_enabled('r') . " AND r.daily_discount_report_code = ?", $daily_discount_report_code);
if ($r = getRow($rs)) {
    echo $_Session->TableManager('daily-discount-report-brands', $r['daily_discount_report_id']);
}
include_once('inc/footer.php'); 
?>