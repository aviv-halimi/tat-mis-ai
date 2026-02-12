<?php
require_once ('../_config.php');
require_once ('../inc/pdf.php');

$po_code = getVar('c');
$po_id = getCodeId('po', $po_code);
$po_filename = getUniqueID() . '.pdf';
generatePO($po_id, MEDIA_PATH . 'po/' . $po_filename);
setRs("UPDATE po SET po_filename = ? WHERE po_id = ?", array($po_filename, $po_id));

redirectTo('/po-download/'. $po_code);
// echo '<a href="/po-download/'. $po_code . '">Download Purchase Order</a>';
// exit();	
?>