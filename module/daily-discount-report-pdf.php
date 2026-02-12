<?php
require_once ('_config.php');
require_once ('inc/pdf-report.php');

$success = false;
$response = '';

$daily_discount_report_code = getVar('c');

$rs = getRs("SELECT daily_discount_report_id, daily_discount_report_code, filename FROM daily_discount_report WHERE daily_discount_report_code = ?", array($daily_discount_report_code));
if ($r = getRow($rs)) {
    $fp = MEDIA_PATH . 'daily_discount_report/' . $r['filename'];
    generateReport($r['daily_discount_report_id'], null, $fp);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="' . $r['filename']);
    header('Cache-Control: max-age=0');
    readfile($fp);
}
else {
    exit('Not Found');
}					
?>