<?php
define('SkipAuth', true);
if (isset($_SERVER['SERVER_ADDR'])) $IP = $_SERVER['SERVER_ADDR'];
else {
    if (!isset($IP)) $IP = '0.0.0.0';
}
//if (!in_array($IP, array('100.20.209.199', '172.26.13.147'))) exit('Access Denied: ' . $IP );

require_once ('../_config.php');

$success = false;
$response = '';
$filename = null;
$start = time();
$rs = getRs("SELECT store_id, store_name, db FROM store WHERE " . is_enabled() . " ORDER BY store_name");

$ra = getRs("SELECT daily_discount_report_id FROM daily_discount_report WHERE " . is_enabled() . " AND progress IS NULL ORDER BY daily_discount_report_id");
foreach($ra as $a) {
    $overall_progress = $overall_qty = $overall_total = 0;
    $daily_discount_report_id = $a['daily_discount_report_id'];
    dbUpdate('daily_discount_report', array('progress' => $overall_progress), $daily_discount_report_id);

    $rr = getRs("SELECT b.brand_id, b.name, rb.daily_discount_report_brand_id, r.daily_discount_report_id, r.date_start, r.date_end, r.category_id FROM daily_discount dd INNER JOIN (blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id) ON dd.brand_id = b.brand_id WHERE r.daily_discount_report_id = ? AND " . is_enabled('r') . " GROUP BY b.brand_id, b.name, rb.daily_discount_report_brand_id, r.daily_discount_report_id, r.date_start, r.date_end, r.category_id ORDER BY r.daily_discount_report_id, rb.daily_discount_report_brand_id", $daily_discount_report_id);
	//$response .= 'start first query' . time() - $start . '</br>';
    foreach($rr as $r) {
        $brand_id = $r['brand_id'];
        $daily_discount_report_brand_id = $r['daily_discount_report_brand_id'];
        $date_start = $r['date_start'];
        $date_end = $r['date_end'];
        $brand_id = $r['brand_id'];
        $category_id = $r['category_id'];
        $_a_date_start = explode('-', $date_start);
        $mm = $_a_date_start[1];
        $yy = $_a_date_start[0];
        $progress = $qty = $total = 0;
        $filename = $r['name'] . '-Daily-Deal-Report-' . $mm . '-' . $yy . '.pdf';

        foreach($rs as $s) {
            $progress += (1 / sizeof($rs)) * 100;
            $overall_progress += (1 / sizeof($rs) / sizeof($rr)) * 100;
			//$response .= 'start big query' . time() - $start . '</br>';
			$rp = getRs("SELECT db.name AS brand_name, dc.name AS category_name, p.name AS product_name, wd.weekday_id, wd.name AS weekday_name, dd.rebate_percent, ddt.daily_discount_type_name, SUM(i.quantity) AS quantity, AVG(ROUND(CASE WHEN ddt.daily_discount_type_id = 2 THEN i.fullprice ELSE COALESCE(i.Overwrite_cogs, i.cogs) * ifnull(1-((dd.rebate_wholesale_discount) * i.poBrandDiscount),1) END,2)) AS unit_price FROM weekday wd INNER JOIN (daily_discount_type ddt INNER JOIN (daily_discount dd INNER JOIN (blaze1.brand mb INNER JOIN ({$s['db']}.brand db INNER JOIN ({$s['db']}.category mc INNER JOIN ({$s['db']}.category dc INNER JOIN ({$s['db']}.product p INNER JOIN ({$s['db']}.items i INNER JOIN ({$s['db']}.cart c INNER JOIN ({$s['db']}.transaction t) ON t.transaction_id = c.transaction_id) ON c.cart_id = i.cart_id) ON i.product_id = p.product_id) ON dc.category_id = p.category_id) ON mc.category_id = dc.master_category_id) ON p.brand_id = db.brand_id) ON mb.brand_id = db.master_brand_id) ON (mb.brand_id = dd.brand_id OR dd.brand_id IS NULL) AND (mc.category_id = dd.category_id OR dd.category_id IS NULL) AND (JSON_CONTAINS(dd.store_ids, CAST({$s['store_id']} AS CHAR), '$') OR dd.store_ids IS NULL) AND (dd.date_end IS NULL OR dd.date_end >= FROM_UNIXTIME(t.completedTime/1000)) AND (dd.date_start IS NULL OR dd.date_start <= FROM_UNIXTIME(t.completedTime/1000))) ON ddt.daily_discount_type_id = dd.daily_discount_type_id) ON wd.weekday_id = dd.weekday_id AND wd.weekday_id = weekday(FROM_UNIXTIME(t.completedTime/1000))+1 WHERE " . is_enabled('dd') . " AND (NOT(i.is_clearance) OR dd.is_clearance) AND DATE(from_unixtime(t.completedTime / 1000)) >= ? AND DATE(from_unixtime(t.completedTime / 1000)) <= ? AND dd.brand_id = ? GROUP BY db.name, dc.name, p.name, wd.weekday_id, wd.name, dd.rebate_percent, ddt.daily_discount_type_name ORDER BY wd.weekday_id, db.name, dc.name, p.name"
						, array($date_start, $date_end, $r['brand_id']));

			//$response .= 'end big query' . time() - $start . '</br>';
			/*
            $rp = getRs("
            
            SELECT
                
                db.name AS brand_name,
                dc.name AS category_name,
                p.name AS product_name, 
                wd.weekday_id,
                wd.name AS weekday_name, 
                dd.rebate_percent,
                ddt.daily_discount_type_name,
                SUM(i.quantity) AS quantity,
                AVG(ROUND(CASE WHEN ddt.daily_discount_type_id = 2 THEN i.fullprice ELSE COALESCE(i.Overwrite_cogs, i.cogs) * ifnull(1-((dd.rebate_wholesale_discount) * i.poBrandDiscount),1) END,2)) AS unit_price

            FROM

                weekday wd INNER JOIN (
                    daily_discount_type ddt INNER JOIN (
                        daily_discount dd INNER JOIN (
                            blaze1.brand mb INNER JOIN (
                                {$s['db']}.brand db INNER JOIN (
                                    {$s['db']}.category mc INNER JOIN (
                                        {$s['db']}.category dc INNER JOIN (
                                            {$s['db']}.product p INNER JOIN (
                                                {$s['db']}.items i INNER JOIN (
                                                    {$s['db']}.cart c INNER JOIN (
                                                        {$s['db']}.transaction t
                                                    ) ON t.transaction_id = c.transaction_id
                                                ) ON c.cart_id = i.cart_id
                                            ) ON i.product_id = p.product_id
                                        ) ON dc.category_id = p.category_id
                                    ) ON mc.category_id = dc.master_category_id
                                ) ON p.brand_id = db.brand_id
                            ) ON mb.brand_id = db.master_brand_id
                        ) ON (mb.brand_id = dd.brand_id OR dd.brand_id IS NULL)
                        AND (mc.category_id = dd.category_id OR dd.category_id IS NULL)
                        AND (JSON_CONTAINS(dd.store_ids, CAST({$s['store_id']} AS CHAR), '$') OR dd.store_ids IS NULL)
                        AND (dd.date_end IS NULL OR dd.date_end >= FROM_UNIXTIME(t.completedTime/1000))
                        AND (dd.date_start IS NULL OR dd.date_start <= FROM_UNIXTIME(t.completedTime/1000))
                    ) ON ddt.daily_discount_type_id = dd.daily_discount_type_id
                ) ON wd.weekday_id = dd.weekday_id AND wd.weekday_id = weekday(FROM_UNIXTIME(t.completedTime/1000))+1
            
            WHERE
                " . is_enabled('dd') . "
                AND (NOT(i.is_clearance) OR dd.is_clearance)
                AND DATE(from_unixtime(t.completedTime / 1000)) >= ?
                AND DATE(from_unixtime(t.completedTime / 1000)) <= ?
                AND dd.brand_id = ? 

            GROUP BY
                db.name,
                dc.name,
                p.name, 
                wd.weekday_id,
                wd.name, 
                dd.rebate_percent,
                ddt.daily_discount_type_name

            ORDER BY
                wd.weekday_id, db.name, dc.name, p.name", array($date_start, $date_end, $r['brand_id']));


            
            foreach($rp as $p) {
                $qty += $p['quantity'];
                $total += $p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'];
            }
			*/
            
        
            if (sizeof($rp)) dbPut('daily_discount_report_store', array('daily_discount_report_brand_id' => $daily_discount_report_brand_id, 'daily_discount_report_id' => $daily_discount_report_id, 'store_id' => $s['store_id'], 'params' => json_encode($rp)));

            dbUpdate('daily_discount_report_brand', array('progress' => $progress), $daily_discount_report_brand_id);
            if ($progress >= 100) {
                dbUpdate('daily_discount_report_brand', array('qty' => $qty, 'total' => $total, 'filename' => $filename, 'date_generated' => 'NOW()'), $daily_discount_report_brand_id);
            }
            dbUpdate('daily_discount_report', array('progress' => $overall_progress), $daily_discount_report_id);
        }
        $overall_qty += $qty;
        $overall_total += $total;
    }
    if ($overall_progress >= 100) {
        dbUpdate('daily_discount_report', array('qty' => $overall_qty, 'total' => $overall_total, 'filename' => $filename, 'date_generated' => 'NOW()'), $daily_discount_report_id);
        $success = true;
        $response .= sizeof($rr) . ' brands processed. ';
    }
    else {
        dbUpdate('daily_discount_report', array('progress' => -1), $daily_discount_report_id);
        $response .= 'No pending report generations. ';
    }
}
if (!sizeof($ra)) {
    $response .= 'No pending report generations. ';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response));
exit();
					
?>