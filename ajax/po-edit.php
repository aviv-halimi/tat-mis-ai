<?php
require_once ('../_config.php');
require_once ('../inc/pdf.php');

$success = false;
$response = $swal = $redirect = $dialog = null;
$po_id = getVarNum('po_id');
$po_edit_status_id = getVarNum('po_edit_status_id');
$is_pending = getVarInt('is_pending');
$is_cancel = getVarInt('is_cancel');


$rt = $_PO->GetPO($po_id);
$re = getRs("SELECT * FROM po_edit WHERE " . is_enabled() . " AND po_edit_status_id = 1 AND po_id = ? ORDER BY po_edit_id DESC", $po_id);
$po_edit = array();
$_disaggregate_ids = array(1,2);

if ($t = getRow($rt)) {
    $po_id = $t['po_id'];
    $po_code = $t['po_code'];
    if ($e = getRow($re)) {
        if ($po_edit_status_id == 2) {
            $po_edit = (isset($e['params']))?json_decode($e['params'], true):array();

            $rs = $_PO->GetSavedPOProducts($po_id, null, null, null, $_disaggregate_ids);
            
            foreach($rs as $r) {

                if (isset($po_edit['po_product_qty_' . $r['po_product_id']]) and $po_edit['po_product_qty_' . $r['po_product_id']] != $r['order_qty']) {
                    $po_edit_qty = numFormat($po_edit['po_product_qty_' . $r['po_product_id']]);
                }
                else {
                    $po_edit_qty = null;
                }
                if (is_numeric($po_edit_qty) and $po_edit_qty != $r['order_qty']) {
                    dbUpdate('po_product', array('order_qty' => $po_edit_qty, 'original_order_qty' => $r['order_qty'], 'is_edited' => 1, 'is_editable' => 1), $r['po_product_id']);
                }
                if (isset($po_edit['po_custom_product_name_' . $r['po_product_id']])) {
                    $i = 0;
                    foreach($po_edit['po_custom_product_name_' . $r['po_product_id']] as $pe) {
                        $q = $po_edit['po_custom_product_qty_' . $r['po_product_id']][$i++];
                        dbPut('po_product', array('po_id' => $po_id, 'parent_po_product_id' => $r['po_product_id'], 'po_product_name' => $pe . ' (SUB)', 'order_qty' => $q, 'original_order_qty' => null, 'weight_per_unit' => $r['weight_per_unit'], 'cannabis_type' => $r['cannabis_type'], 'flower_type' => $r['flower_type'], 'category_id' => $r['category_id'], 'brand_id' => $r['brand_id'], 'cost' => $r['cost'], 'price' => $r['price'], 'is_non_conforming' => $r['is_non_conforming'], 'is_tax' => $r['is_tax'], 'is_created' => $r['is_created'], 'is_transferred' => $r['is_transferred'], 'is_included' => $r['is_included'], 'is_edited' => 1, 'is_editable' => 1));
                    }
                }
            }
            dbUpdate('po_edit', array('po_edit_status_id' => $po_edit_status_id, 'admin_id' => $_Session->admin_id, 'date_approved' => 'NOW()'), $e['po_edit_id']);
            $success = true;
            $response = 'PO modifications (' . $e['po_edit_id'] . ') approved.';
            $_PO->SavePONote($po_id, $response);


            if ($is_pending) {
                dbUpdate('po', array('po_status_id' => 1), $po_id);
                $_PO->SavePONote($po_id, 'PO moved to Pending');
                $redirect = '{refresh}';
            }
            else {            
                $po_filename = getUniqueID() . '.pdf';
                generatePO($po_id, MEDIA_PATH . 'po/' . $po_filename);
                $dialog = array('url' => 'notification', 'title' => 'Notify vendor', 'a' => 6, 'c' => $po_code);

                ////////
                
                setRs("UPDATE po SET po_filename = ? WHERE po_id = ?", array($po_filename, $po_id));
                $_PO->SavePONote($po_id, 'PO document updated: <a href="/po-download/' . $po_code . '" target="_blank"><i class="fa fa-file-pdf mr-1"></i> Download</a>', $_Session->admin_id);
            }
        }
        else if ($po_edit_status_id == 3) {

            
            $po_edit = (isset($e['params']))?json_decode($e['params'], true):array();

            $rs = $_PO->GetSavedPOProducts($po_id, null, null, null, $_disaggregate_ids);
            
            foreach($rs as $r) {

                if (isset($po_edit['po_product_qty_' . $r['po_product_id']]) and $po_edit['po_product_qty_' . $r['po_product_id']] != $r['order_qty']) {
                    $po_edit_qty = numFormat($po_edit['po_product_qty_' . $r['po_product_id']]);
                }
                else {
                    $po_edit_qty = null;
                }
                if (is_numeric($po_edit_qty) and $po_edit_qty != $r['order_qty']) {
                    dbUpdate('po_product', array('order_qty' => $po_edit_qty, 'original_order_qty' => $r['order_qty'], 'is_edited' => 1, 'is_editable' => 1), $r['po_product_id']);
                }
            }


            dbUpdate('po_edit', array('po_edit_status_id' => $po_edit_status_id, 'admin_id' => $_Session->admin_id), $e['po_edit_id']);

            $success = true;
            $response = 'PO modifications (' . $e['po_edit_id'] . ') declined.';
            $redirect = '{refresh}';
            $_PO->SavePONote($po_id, $response);

            if ($is_pending) {
                dbUpdate('po', array('po_status_id' => 1), $po_id);
                $_PO->SavePONote($po_id, 'PO moved to Pending');
            }
            else if ($is_cancel) {
                /*
                $_a = $_PO->NewPO(array('parent_po_id' => $po_id, 'po_name' => $t['po_name'], 'vendor_id' => $t['vendor_id'], 'email' => $t['email'], 'po_type_id' => $t['po_type_id'], 'po_reorder_type_id' => $t['po_reorder_type_id']));
                $new_po_id = $_a['po_id'];
                $redirect = '/po/' . getIdCode('po', $new_po_id);
                $_PO->SavePONote($new_po_id, 'PO reissued from archived PO:' . $po_id);
                */

                dbUpdate('po', array('is_active' => 0), $po_id);
                $_PO->SavePONote($po_id, 'PO archived');
                $redirect = '/pos';
            }
        }
        else {
            $response = 'PO modification status invalid. Select Approved or Declined.';
        }
    }
    else {
        $response = 'No updates to apply';
    }
}
else {
    $response = 'PO not found';
}

if ($success) {
    $_PO->POProgress($po_id);
}


header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(array('success' => $success, 'response' => $response, 'swal' => $swal, 'redirect' => $redirect, 'dialog' => $dialog));
exit();
					
?>