<?php
/*
if (getVar('blaze_id')) {
    $_rv = getRs("SELECT name FROM {$_Session->db}.vendor WHERE id = ?", getVar('blaze_id'));
    if ($_v = getRow($_rv)) dbUpdate('vendor', array('vendor_name' => $_v['name']), $ItemID);
}
else {
    $leadtime = getVarNum('leadtime');
    $target_days_on_hand = getVarNum('target_days_on_hand');
    $scheduling_window = getVarNum('scheduling_window');
    $is_suspended = getVarInt('is_suspended');
    $_rv = getRs("SELECT id FROM {$_Session->db}.vendor WHERE vendor_id = ?", array($ItemID));
    if ($_v = getRow($_rv)) {
        $_id = $_v['id'];
        $_rs = getRs("SELECT * FROM store WHERE store_id <> ? AND " . is_enabled() . " ORDER BY store_id", array($_Session->store_id));
        foreach($_rs as $_r) {
            setRs("UPDATE {$_r['db']}.vendor SET leadtime = ?, target_days_on_hand = ?, scheduling_window = ?, is_suspended = ? WHERE id = ?", array($leadtime, $target_days_on_hand, $scheduling_window, $is_suspended, $_id));
        }
    }
}
    */
?>