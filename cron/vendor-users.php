<?php

//define('SkipAuth', true);
require_once('../_config.php');

$num_vendors = $num_skipped_vendors = 0;
$rs = getRs("SELECT v.id, v.name, v.email, v.firstName, v.lastName, v.phone, v.vendor_id FROM blaze1.vendor v ORDER BY v.vendor_id");
echo '<li>Found vendors: ' . sizeof($rs);
echo '<hr />';
foreach($rs as $r) {

    //$rv = getRs("SELECT vendor_id FROM vendor WHERE JSON_CONTAINS(blaze_ids, ?, '\"$\"')", $r['id']);
    //$rv = getRs("SELECT vendor_id FROM vendor WHERE JSON_CONTAINS(blaze_ids, CAST(? AS VARCHAR), '$')", array($r['id']));
    $rv = getRs("SELECT vendor_id FROM vendor WHERE blaze_ids LIKE ?", array('%"' . $r['id'] . '"%'));
    if (!sizeof($rv)) {
        //dbPut('vendor', array('blaze_ids' => '["' . $r['id'] . '"]', 'blaze_id' => $r['id'], 'vendor_name' => $r['name'], 'email' => $r['email'], 'first_name' => $r['firstName'], 'last_name' => $r['lastName'], 'phone' => $r['phone'], 'po_vendor_id' => $r['vendor_id']));
        $num_vendors++;
        echo '<li style="color:red;">Missing: ' . $r['name'];
    }
    else {
        $num_skipped_vendors++;
        echo '<li style="color:green;">Present: ' . $r['name'];
    }
}
echo '<hr />';
echo '<li style="color:red;font-weight:bold;">Total Missing: ' . $num_vendors;
echo '<li style="color:green;font-weight:bold;">Total Present: ' . $num_skipped_vendors;

?>