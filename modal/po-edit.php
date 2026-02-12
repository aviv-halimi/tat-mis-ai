<?php
require_once('../_config.php');
$po_id = getVarNum('id');

$re = getRs("SELECT * FROM po_edit WHERE " . is_enabled() . " AND po_edit_status_id = 1 AND po_id = ? ORDER BY po_edit_id DESC", $po_id);
if ($e = getRow($re)) {
    echo poPdfContent($po_id, $e);
    echo '<input type="hidden" id="edit_po_id" name="edit_po_id" value="' . $po_id . '" />
    <div class="row m-t-10 m-b-10">
    <div class="col-sm-2 col-form-label text-right"></div>
    <div class="col-sm-10"><div id="status_po-edit" class="status"></div></div>
    </div>
    <div class="row m-t-10 m-b-10 text-center">
      <div class="col-md-3"><button type="button" class="btn btn-lg btn-primary btn-accept">ACCEPT & RESEND</button></div>
      <div class="col-md-3"><button type="button" class="btn btn-lg btn-secondary btn-accept-pending">ACCEPT & EDIT</button></div>
      <div class="col-md-3"><button type="button" class="btn btn-lg btn-info btn-reject-pending">REJECT & EDIT</button></div>
      <div class="col-md-3"><button type="button" class="btn btn-lg btn-warning btn-cancel">CANCEL PO</button></div>
    </div>
    <input type="hidden" id="is_pending" name="is_pending" value="0" />
    <input type="hidden" id="is_cancel" name="is_cancel" value="0" />
    <input type="hidden" id="po_edit_status_id" name="po_edit_status_id" value="' . $e['po_edit_status_id'] . '" />';
}
else {
    echo alertBox('No pending modifications for this PO');
}

?>

<script>
$(document).ready(function(e) {
  $('.btn-accept').on('click', function(e) {
    $('#po_edit_status_id').val(2);
    $('#is_pending').val(0);
    $('#is_cancel').val(0);
    confirmPOEdits('Accept and Resend to Vendor', 'Are you sure you want to Accept these PO edits as is and resend to vendor?');
  });
  $('.btn-accept-pending').on('click', function(e) {
    $('#po_edit_status_id').val(2);
    $('#is_pending').val(1);
    $('#is_cancel').val(0);
    confirmPOEdits('Accept and Edit PO', 'Are you sure you want to Accept these PO edit? PO will revert back to Pending for more edits');
  });
  $('.btn-reject-pending').on('click', function(e) {
    $('#po_edit_status_id').val(3);
    $('#is_pending').val(1);
    $('#is_cancel').val(0);
    confirmPOEdits('Accept and Edit PO', 'Are you sure you want to Reject these PO edits? PO will revert back to Pending for more edits');
  });
  $('.btn-cancel').on('click', function(e) {
    $('#po_edit_status_id').val(3);
    $('#is_pending').val(0);
    $('#is_cancel').val(1);
    confirmPOEdits('Cancel and Reissue new PO', 'Are you sure you want to Cancel this PO and reissue a new one for this vendor?');
  });
});

function confirmPOEdits(a, b) {
    Swal.fire({
      title: a,
      html: b,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, continue'
    }).then((result) => {
      if (result.value) {
        $('.form-btns').hide();
        postAjax('po-edit', {po_id: $('#edit_po_id').val(), po_edit_status_id: $('#po_edit_status_id').val(), is_pending: $('#is_pending').val(), is_cancel: $('#is_cancel').val()}, 'status_po-edit', function(data) {}, function(data) {
          $('.form-btns').show();
        });
      }
    });
}
</script>
<?php

function poPdfContent($po_id, $e = array()) {
    global $_PO;
    global $_Session;
    $link = '';
    $ret = '';
    $_disaggregate_ids = array(1,2);
    $rt = $_PO->GetPO($po_id);
    if ($t = getRow($rt)) {
      $po_id = $t['po_id'];
      $po_edit = (isset($e['params']))?json_decode($e['params'], true):array();
      $vendor_name = $vendor_address = $delivery_address = $vendor__id = null;
      $rv = getRs("SELECT * FROM {$_Session->db}.vendor WHERE vendor_id = ?", array($t['vendor_id']));
      if ($v = getRow($rv)) {
        $link = 'https://scheduling.theartisttree.com/po/' . $v['id'] . '/'  . $t['po_code'];
        $vendor_name = $v['name'];
        $vendor_address = 'ATTN: ' . $v['firstName'] . ' ' . $v['lastName'] . '<br />' . $v['address'] . '<br />' . $v['city'] . ', ' . $v['state'] . ' ' . $v['zipCode'] . iif(str_len($v['country']), '<br />' . $v['country']) . iif(str_len($v['email']), '<br />' . $v['email']) . '<br /><br />' . $v['licenseNumber'];
      }
      $ra = getRs("SELECT delivery_address FROM store WHERE store_id = ?", array($_Session->store_id));
      if ($a = getRow($ra)) {
        $delivery_address = $a['delivery_address'];
      }
      $rd_pt = getRs("SELECT po_discount_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));
      $rd = getRs("SELECT po_discount_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 1 ORDER BY po_discount_id", array($po_id));
      $rs = $_PO->GetSavedPOProducts($po_id, null, null, null, $_disaggregate_ids);
      $progress = $_PO->POProgress($po_id);
  
      $_nf = new NumberFormatter("en", NumberFormatter::SPELLOUT);
  
        $ret = '
        <div class="pdf">
        <div class="title">
        <table class="full">
          <tr>
            <td>' . nl2br($delivery_address ?? '') . '</td>
            <td class="text-right">
            <table class="bordered">
              <tr>
                <td class="text-right">PO Number:</td>
                <td class="pl-1"><b>' . $t['po_number'] . '</b></td>
              </tr>
              <tr>
                <td class="text-right">PO Status:</td>
                <td class="pl-1">' . getDisplayName('po_status', $t['po_status_id']) . '</td>
              </tr>
              <tr>
                <td class="text-right">Order Date:</td>
                <td class="pl-1">' . getShortDate($t['date_ordered']) . '</td>
              </tr>
              <tr>
                <td class="text-right">Requested Delivery:</td>
                <td class="pl-1">' . getShortDate($t['date_requested_ship']) . '</td>
              </tr>
            </table>
            </td>
          </tr>
        </table>
        </div>
        <div class="vendor">
          <table class="sm">
          <tr>
          <td class="pr-2">Vendor:</td>
          <td><b>' . $vendor_name . '</b><div>' . $vendor_address . '</div></td>
          </tr>
          </table>
        </div>
        <div>
        <table class="full">
          <thead>
            <tr class="b-3">
              <th class="text-left">Item</th>
              <th class="text-right">Order Qty</th>
              <th class="text-right"></th>
              <th class="text-right">Price</th>
              <th class="text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody class="products">';
          $first_run = true;
          $brand_id = $category_id = null;
          $subtotal = 0;
          foreach($rs as $r) {
            if ($category_id != $r['category_id'] && in_array(1, $_disaggregate_ids)) {
              $category_id = $r['category_id'];
              $ret.= '
              <tr class="b-2">
                <th class="pl-1" colspan="5">' . $r['category_name'] . '</th>
              </tr>';
              $first_run = false;
            }
            if ($brand_id != $r['brand_id'] && in_array(2, $_disaggregate_ids)) {
              $brand_id = $r['brand_id'];
              $ret.= '
              <tr class="b-2">
                <th class="pl-2" colspan="5">' . $r['brand_name'] . '</th>
              </tr>';
              $first_run = false;
            }
            if (isset($po_edit['po_product_qty_' . $r['po_product_id']]) and $po_edit['po_product_qty_' . $r['po_product_id']] != $r['order_qty']) {
                $po_edit_qty = numFormat($po_edit['po_product_qty_' . $r['po_product_id']]);
            }
            else {
                $po_edit_qty = null;
            }
            $ret .= '
            <tr class="b-1">
            <td class="pl-3">' . $r['product_name'] . ' (' . $r['sku'] . ')</td>
            <td class="text-right" nowrap>' . number_format($r['order_qty'], 2) . '</td>
            <td nowrap>' . ((is_numeric($po_edit_qty) and $po_edit_qty != $r['order_qty'])?'<span class="badge badge-warning"><i class="icon fa fa-exclamation-triangle"></i></span> ' . number_format($po_edit_qty, 2):'') . '</td>
            <td class="text-right" nowrap>' . currency_format($r['price']?:$r['cost'], '$ ') . '</td>
            <td class="text-right" nowrap>' . currency_format(((is_numeric($po_edit_qty) and $po_edit_qty != $r['order_qty'])?$po_edit_qty:$r['order_qty']) * ($r['price']?:$r['cost']), '$ ') . '</td>
            </tr>';
			      $subtotal += ((is_numeric($po_edit_qty) and $po_edit_qty != $r['order_qty'])?$po_edit_qty:$r['order_qty']) * ($r['price']?:$r['cost']);
            if (isset($po_edit['po_custom_product_name_' . $r['po_product_id']])) {
                $i = 0;
                foreach($po_edit['po_custom_product_name_' . $r['po_product_id']] as $pe) {
                    $q = $po_edit['po_custom_product_qty_' . $r['po_product_id']][$i++];
                    $ret .= '
                    <tr class="po-custom-product b-1">
                    <td class="pl-3" colspan="2"><i class="fa fa-share"></i> ' . $pe . ' (SUB)</td>
                    <td nowrap><span class="badge badge-warning"><i class="icon fa fa-exclamation-triangle"></i></span> ' . number_format($q, 2) . '</td>
                    <td class="text-right" nowrap>' . currency_format($r['price']?:$r['cost'], '$ ') . '</td>
                    <td class="text-right" nowrap>' . currency_format($q * ($r['price']?:$r['cost']), '$ ') . '</td>
                    </tr>';
					          $subtotal += ($q * ($r['price']?:$r['cost']));
                }
            }
          }
          $ret .= '<tr class="b-4"><td colspan="5">&nbsp;</td></tr></tbody>
          <tfoot>
          <tr><th class="text-left" colspan="4">Subtotal</th><th class="text-right" nowrap>' . currency_format($subtotal, '$ ') . '</th></tr>
          ' . iif($t['discount'], '<tr><th class="text-left" colspan="3">' . iif($t['discount'], iif(str_len($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($t['discount'], '$ ') . '</th></tr>');
  
          foreach($rd_pt as $d) {
            $ret .= '
            <tr><th class="text-left" colspan="4">' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($d['discount']) . '</th></tr>';
          }
          
          $ret .= iif($t['tax'], '<tr class="b-2"><th class="text-left" colspan="4">Tax' . iif(!$t['tax_amount'], ' (' . (float)$t['tax_rate'] . '%)') . '</th><th class="text-right" nowrap>' . currency_format($t['tax'], '$ ') . '</th></tr>');
  
		      $total = $subtotal - $t['discount'] + $t['tax'];
          foreach($rd as $d) {
            $ret .= '
            <tr><th class="text-left" colspan="4">' . iif($d['discount'], iif(str_len($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($d['discount']) . '</th></tr>';
			      $total -= $d['discount'];
          }
  
          $ret .= '<tr class="b-5"><th class="text-left" colspan="4">GRAND TOTAL</th><th class="text-right" nowrap>' . currency_format($total, '$ '). '</th></tr>
          </tfoot>
        </table>
  
        </div>
        ';
  
        if (str_len($t['description'])) {
          $ret .= '<div class="comments"><h3>Comments / Special Instructions:</h3><p>' . nl2br($t['description'] ?? '') . '</p></div>';
        }
  
        $ret .= '
        </div>
        ';
    }
    return $ret;
  }

?>