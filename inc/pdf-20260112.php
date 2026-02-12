<?php
require_once('../plugins/mpdf/vendor/autoload.php');

function poPdfContent($po_id) {
  global $_PO;
  global $_Session;
  $link = '';
  $ret = '';
  $_disaggregate_ids = array(1,2);
  $rt = $_PO->GetPO($po_id);
  if ($t = getRow($rt)) {
    $po_id = $t['po_id'];
    $vendor_name = $vendor_address = $delivery_address = $vendor__id = null;
    $rv = getRs("SELECT * FROM {$_Session->db}.vendor WHERE vendor_id = ?", array($t['vendor_id']));
    if ($v = getRow($rv)) {
      //$link = 'https://scheduling.theartisttree.com/po/' . $v['id'] . '/'  . $t['po_code'];
	  //$link = 'https://scheduling.theartisttree.com/po/' . $t['po_code'];
	  $link = 'https://scheduling.theartisttree.com/';
      $vendor_name = $v['name'];
      $vendor_address = 'ATTN: ' . $v['firstName'] . ' ' . $v['lastName'] . '<br />' . $v['address'] . '<br />' . $v['city'] . ', ' . $v['state'] . ' ' . $v['zipCode'] . iif(strlen($v['country']), '<br />' . $v['country']) . iif(strlen($v['email']), '<br />' . $v['email']) . '<br /><br />' . $v['licenseNumber'];
    }
    $ra = getRs("SELECT delivery_address FROM store WHERE store_id = ?", array($_Session->store_id));
    if ($a = getRow($ra)) {
      $delivery_address = $a['delivery_address'];
    }
	/*if ($t['vendor_id'] = 500 && $_Session->store_id = 12) {
      $delivery_address = '<b>CLUB420, Davis </b>
			**Ship to Dixon Location**
			240 Dorset Ct
			Dixon, CA 95620

			<i>* Please email anthony@club420.com for delivery related issues</i>';
    }*/
    $rd_pt = getRs("SELECT po_discount_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 0 ORDER BY po_discount_id", array($po_id));
    $rd = getRs("SELECT po_discount_id, po_discount_code, po_discount_name, discount_rate, discount_amount, (CASE WHEN discount_rate THEN (discount_rate / 100 * " . ($t['subtotal'] - $t['discount'] + $t['tax']) . ") ELSE discount_amount END) AS discount FROM po_discount WHERE " . is_enabled() . " AND is_receiving = 0 AND po_id = ? AND is_after_tax = 1 ORDER BY po_discount_id", array($po_id));
    $rs = $_PO->GetSavedPOProducts($po_id, null, null, null, $_disaggregate_ids);
    $progress = $_PO->POProgress($po_id);

    $_nf = new NumberFormatter("en", NumberFormatter::SPELLOUT);

      $ret = '
 


      <htmlpageheader name="myHeader1" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/media/site/at.png" style="width: 100pt" /></td>
          <td class="text-right" style="vertical-align:bottom"><h1>' . getDisplayName('po_type', $t['po_type_id']) . '</h1></td>
        </tr>
      </table>
      </div>
      </htmlpageheader>
      
      <htmlpageheader name="myHeader2" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/media/site/at.png" style="width: 60pt" /></td>
          <td class="text-right" style="vertical-align:bottom"><h2>' . getDisplayName('po_type', $t['po_type_id']) . '</h2></td>
        </tr>
      </table>      
      </div>
      </htmlpageheader>
      
      <htmlpagefooter name="myFooter1" style="display:none">
      <div class="footer"><div class="text-right">Page {PAGENO} of {nb}</div></div>
      </htmlpagefooter>
      
      <htmlpagefooter name="myFooter2" style="display:none">
      <div class="footer"><div>Page {PAGENO} of {nb}</div></div>
      </htmlpagefooter>















      <div class="content">
      <div class="title">
      <table class="full">
        <tr>
          <td>' . nl2br($delivery_address) . '</td>
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
          
        </thead>
        <tbody class="products">';
	  
	  /*<tr class="b-3">
            <th class="text-left">Item</th>
            <th class="text-right">Order Qty</th>
            <th class="text-right">Price</th>
            <th class="text-right">Subtotal</th>
          </tr>*/
	  
        $first_run = true;
        $brand_id = $category_id = null;
        foreach($rs as $r) {
          if ($brand_id != $r['brand_id'] && in_array(2, $_disaggregate_ids)) {
            $brand_id = $r['brand_id'];
            $ret.= '
            <tr class="b-3">
              <th class="pl-1">' . $r['brand_name'] . '</th>
			  <th class="text-right">Order Qty</th>
              <th class="text-right">Price</th>
              <th class="text-right">Subtotal</th>
            </tr>';
            $first_run = false;
          }
		  if ($category_id != $r['category_id'] && in_array(1, $_disaggregate_ids)) {
            $category_id = $r['category_id'];
            $ret.= '
            <tr class="b-2">
              <th class="pl-2" colspan="4">' . $r['category_name'] . '</th>
            </tr>';
            $first_run = false;
          }
          $ret .= '
          <tr class="b-1">
          <td class="pl-3">' . $r['product_name'] . ' (' . $r['sku'] . ')</td>
          <td class="text-right" nowrap>' . number_format($r['order_qty'], 2) . '</td>
          <td class="text-right" nowrap>' . currency_format($r['price']?:$r['cost'], '$ ') . '</td>
          <td class="text-right" nowrap>' . currency_format($r['order_qty'] * ($r['price']?:$r['cost']), '$ ') . '</td>
          </tr>';
        }
        $ret .= '<tr class="b-4"><td colspan="4">&nbsp;</td></tr></tbody>
        <tfoot>
        <tr><th class="text-left" colspan="3">Subtotal</th><th class="text-right" nowrap>' . currency_format($t['subtotal'], '$ ') . '</th></tr>
        ' . iif($t['discount'], '<tr><th class="text-left" colspan="3">' . iif($t['discount'], iif(strlen($t['discount_name']), $t['discount_name'], 'Discount') . iif($t['discount_rate'], ' (-' . (float)$t['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($t['discount'], '$ ') . '</th></tr>');

        foreach($rd_pt as $d) {
          $ret .= '
          <tr><th class="text-left" colspan="3">' . iif($d['discount'], iif(strlen($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($d['discount']) . '</th></tr>';
        }
        
        $ret .= iif($t['tax'], '<tr class="b-2"><th class="text-left" colspan="3">Tax' . iif(!$t['tax_amount'], ' (' . (float)$t['tax_rate'] . '%)') . '</th><th class="text-right" nowrap>' . currency_format($t['tax'], '$ ') . '</th></tr>');

        foreach($rd as $d) {
          $ret .= '
          <tr><th class="text-left" colspan="3">' . iif($d['discount'], iif(strlen($d['po_discount_name']), $d['po_discount_name'], 'Discount') . iif($d['discount_rate'], ' (-' . (float)$d['discount_rate'] . '%)'), 'Discount') . '</th><th class="text-right" nowrap>-' . currency_format($d['discount']) . '</th></tr>';
        }

        $ret .= '<tr class="b-5"><th class="text-left" colspan="3">GRAND TOTAL</th><th class="text-right" nowrap>' . currency_format($t['total'], '$ '). '</th></tr>
        </tfoot>
      </table>

      <p>Please click on the link below to schedule a delivery:</p><p><a href="' . $link . '" target="_blank">' . $link . '</a></p>
      </div>
      ';

      if (strlen($t['description'])) {
        $ret .= '<div class="comments"><h3>Comments / Special Instructions:</h3><p>' . nl2br($t['description']) . '</p></div>';
      }

      $ret .= '
      </div>
      ';
  }
  return $ret;
}

function generatePO($po_id, $filename) {
  
  $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
  $fontDirs = $defaultConfig['fontDir'];

  $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
  $fontData = $defaultFontConfig['fontdata'];

	$mpdf = new \Mpdf\Mpdf(array(
    'mode' => 'utf-8', 
    'margin-left' => 0,
    'margin-right' => 0,
    'margin-top' => 0,
    'margin-bottom' => 0,
    'fontdata' => $fontData + [
        'roboto' => [
            'R' => 'RobotoCondensed-Regular.ttf',
            'I' => 'RobotoCondensed-Italic.ttf',
            'B' => 'RobotoCondensed-Bold.ttf',
            'BI' => 'RobotoCondensed-BoldItalic.ttf',
        ]
    ],
    'default_font' => 'roboto'
  ));
	
	$mpdf->mirrorMargins = 1;	// Use different Odd/Even headers and footers and mirror margins
	
	$stylesheet = file_get_contents('../assets/css/pdf.css');
	$mpdf->WriteHTML($stylesheet, 1);	// The parameter 1 tells that this is css/style only and no body/html/text
  
  $mpdf->WriteHTML(poPdfContent($po_id));
	$mpdf->Output($filename, 'F');
	return array('success' => true, 'response' => null);
}

?>