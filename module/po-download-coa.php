<?php
$po_code = getVar('c');
$rs = getRs("SELECT po_number, coa_filename FROM po WHERE " . is_enabled() . " AND po_status_id > 3 AND LENGTH(po_filename) AND po_code = ?", array($po_code));
if ($r = getRow($rs)) {
    $_PO->SavePONote($r['po_id'], 'Certificate of Analysis downloaded');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline;filename="COA-' . $r['po_number'] . '.' . getExt($r['coa_filename']));
    header('Cache-Control: max-age=0');
    readfile(MEDIA_PATH . 'po/' . $r['coa_filename']);
}
else {

}
?>