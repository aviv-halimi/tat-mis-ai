<?php
$ds = $de = null;
$_rs = getRs("SELECT daily_discount_report_id, daily_discount_report_type_id, brand_id FROM daily_discount_report WHERE daily_discount_report_id = ?", $ItemID);
if ($_r = getRow($_rs)) {
    if (in_array($_r['daily_discount_report_type_id'], array(1,2))) {
        $ds = date('Y-m') . '-1';
        if ($_r['daily_discount_report_type_id'] == 1) $ds = date('Y-m-d', strtotime('-1 months', strtotime($ds)));
        $de = date('Y-m-d', strtotime('+1 months', strtotime($ds)));
        $de = date('Y-m-d', strtotime('-1 days', strtotime($de)));
        dbUpdate('daily_discount_report', array('date_start' => $ds, 'date_end' => $de), $_r['daily_discount_report_id']);
    }
    else if (in_array($_r['daily_discount_report_type_id'], array(3))) {
        $ds = toMySqlDT(getVar('date_start'));
        $de = toMySqlDT(getVar('date_end'));
        dbUpdate('daily_discount_report', array('date_start' => $ds, 'date_end' => $de), $_r['daily_discount_report_id']);
    }
    if (!$_r['brand_id']) {

        //$rb = getRs("SELECT brand_id, name FROM blaze1.brand WHERE is_enabled = 1 ORDER BY name");
        //$rb = getRs("SELECT b.brand_id, b.name FROM daily_discount dd INNER JOIN blaze1.brand b ON dd.brand_id = b.brand_id WHERE " . is_enabled('dd,b') . " AND (dd.date_start IS NULL OR dd.date_start <= ?) AND (dd.date_end IS NULL OR dd.date_end >= ?) GROUP BY b.brand_id, b.name ORDER BY b.name", array($ds, $de));
		$rb = getRs("SELECT coalesce(dd.linked_brand_id,dd.brand_id) AS brand_id, group_concat(distinct b.name ORDER BY b.name ASC SEPARATOR ' | ') AS name FROM daily_discount dd INNER JOIN blaze1.brand b ON dd.brand_id = b.brand_id WHERE " . is_enabled('dd,b') . " AND (dd.date_start IS NULL OR dd.date_start <= ?) AND (dd.date_end IS NULL OR dd.date_end >= ?) GROUP BY coalesce(dd.linked_brand_id,dd.brand_id) ORDER BY group_concat(distinct b.name ORDER BY b.name ASC SEPARATOR ' | ')", array($de, $ds));
        foreach($rb as $r) {
            dbPut('daily_discount_report_brand', array('daily_discount_report_id' => $_r['daily_discount_report_id'], 'daily_discount_report_brand_name' => $r['name'], 'date_start' => $ds, 'date_end' => $de, 'brand_id' => $r['brand_id'], 'admin_id' => $_Session->admin_id));
        }

    }
    else {
        dbPut('daily_discount_report_brand', array('daily_discount_report_id' => $_r['daily_discount_report_id'], 'date_start' => $ds, 'date_end' => $de, 'brand_id' => $_r['brand_id'], 'admin_id' => $_Session->admin_id));

    }
}
if (isset($NewRecord) and $NewRecord == $ItemID) {
    dbUpdate('daily_discount_report', array('admin_id' => $_Session->admin_id), $ItemID);
}
?>