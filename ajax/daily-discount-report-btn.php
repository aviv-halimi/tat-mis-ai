<?php
require_once ('../_config.php');

$success = false;
$response = '';
$dd = $t = $total = '';

$daily_discount_report_id = getVar('id');

$__rd = getRs("SELECT r.daily_discount_report_id, r.daily_discount_report_code, r.params, r.progress, r.total, r.filename, r.date_generated, COUNT(b.daily_discount_report_brand_id) AS num_brands FROM daily_discount_report_brand b RIGHT JOIN daily_discount_report r ON r.daily_discount_report_id = b.daily_discount_report_id AND b.filename IS NOT NULL WHERE r.daily_discount_report_id = ? GROUP BY r.daily_discount_report_id, r.daily_discount_report_code, r.params, r.progress, r.total, r.filename, r.date_generated", $daily_discount_report_id);
if ($__r = getRow($__rd)) {

    if ($__r['filename']) {
        if ($__r['num_brands'] == 1) {
            $dd .= '<a href="/daily-discount-report-pdf/' . $__r['daily_discount_report_code'] . '" class="btn btn-info btn-xs ml-1" target="_blank"><i class="fa fa-file-pdf"></i> Download</a>';
        }
        else {
            $dd .= '<a href="/daily-discount-report/' . $__r['daily_discount_report_code'] . '" class="btn btn-info btn-xs ml-1">View All Downloads (' . $__r['num_brands'] . ')</a>';
        }
        $t = getLongDate($__r['date_generated']);
        $total = currency_format($__r['total']);
    }
    else {
        if ($__r['progress'] == -1) $dd .= '<button type="button" class="btn btn-danger btn-xs ml-1 btn-daily-discount-report" data-id="' . $daily_discount_report_id . '"><i class="fa fa-exclamation-triangle"></i> No Orders</button>';
        else if ($__r['progress'] == 0) $dd .= '<button type="button" class="btn btn-secondary btn-xs ml-1 btn-daily-discount-report" data-id="' . $daily_discount_report_id . '"><i class="fa fa-clock"></i> Queued ...</button>';
        else {
            $dd .= '<button type="button" class="btn btn-warning btn-xs ml-1 btn-daily-discount-report" data-id="' . $daily_discount_report_id . '"><i class="fa fa-clock"></i> Generating ...' . round($__r['progress']) . '%</button>';
            $t = '<i class="fa fa-circle-notch fa-spin"></i> In progress ...';
        }
    }
    $success = true;
    $response = 'Buttons updated sucessfully';
}
else {
    $response = 'Report not found';
}


header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'btn' => $dd, 'total' => $total, 't' => $t));
exit();
					
?>