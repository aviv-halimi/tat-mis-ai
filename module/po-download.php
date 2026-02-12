<?php
$po_code = getVar('c');
$rs = getRs("SELECT po_id, po_number, po_filename FROM po WHERE " . is_enabled() . " AND po_status_id > 1 AND LENGTH(po_filename) AND po_code = ?", array($po_code));
if ($r = getRow($rs)) {
    $po_filename = $r['po_filename'];
    $_PO->SavePONote($r['po_id'], 'Purchase Order document downloaded');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="PO_' . $r['po_number'] . '.' . getExt($po_filename));
    header('Cache-Control: max-age=0');
    readfile(MEDIA_PATH . 'po/' . $po_filename);
}
else {
    echo 'Not found';
}
?>