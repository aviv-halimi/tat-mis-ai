<?php
require_once('../_config.php');
/*
$rs = getRs("SELECT * FROM store WHERE store_id = ?", array(getVarNum('id', 1)));
if ($r = getRow($rs)) {
    $params = json_decode($r['params']);
    $footer = '<b>' . $r['description'] . '</b><br />' . $r['address'] . '<br /> ' . $r['city'] . ', ' . $r['state'] . ' ' . $r['zip'] . '<br />Ph: ' . $r['phone'];
    print_r(sendEmail('The Artist Tree', 'Scheduling.Weho@TheArtistTree.com', 'Al', 'info@wantadigital.com', 'Test Message', 'This PO is ready to go', $footer));
}
*/

print_r(sendEmail('The Artist Tree', 'Scheduling.Weho@TheArtistTree.com', 'Al', 'alieu@forte-innovations.com', 'Test Message', 'This PO is ready to go', 'footer'));
?>