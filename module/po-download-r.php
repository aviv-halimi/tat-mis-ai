<?php
$po_code = getVar('c');
$rs = getRs("SELECT po_id, po_number, invoice_filename FROM po WHERE " . is_enabled() . " AND LENGTH(po_filename) AND po_code = ?", array($po_code));
if ($r = getRow($rs)) {
    $_PO->SavePONote($r['po_id'], 'Receiving document downloaded');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="Invoice-' . $r['po_number'] . '.' . getExt($r['invoice_filename']));
    header('Cache-Control: max-age=0');
    readfile(MEDIA_PATH . 'po/' . $r['invoice_filename']);
}
else {

}
?>