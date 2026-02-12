<?php
require_once ('_config.php');
require_once ('inc/pdf-report.php');

$success = false;
$response = '';

$daily_discount_report_brand_code = getVar('c');

$rs = getRs("SELECT daily_discount_report_brand_id, daily_discount_report_brand_code, filename FROM daily_discount_report_brand WHERE daily_discount_report_brand_code = ?", array($daily_discount_report_brand_code));
if ($r = getRow($rs)) {
    $fp = MEDIA_PATH . 'daily_discount_report_brand/' . $r['filename'];
    generateReport(null, $r['daily_discount_report_brand_id'], $fp);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="' . $r['filename']);
    header('Cache-Control: max-age=0');
    readfile($fp);
}
else {
    exit('Not Found');
}					
?>