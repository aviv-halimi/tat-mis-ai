<?php
ini_set('max_execution_time', 300);
ini_set('memory_limit', '1G');
require_once(CLASS_PATH . 'mpdf/vendor/autoload.php');

function reportPdfContent($daily_discount_report_id, $daily_discount_report_brand_id) {

    $content = array();
	$showGrossSales = null;
	$byDate = (new DateTime() > new DateTime('2025-10-01'));
    $summary = $body = $header = '';
    $g_total = 0;

    if ($daily_discount_report_brand_id) {   
      $rd = getRs("SELECT r.*, b.brand_id, b.name AS brand_name, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE rb.daily_discount_report_brand_id = ?", $daily_discount_report_brand_id);
    }
    else {
      //$rd = getRs("SELECT r.*, b.name AS brand_name FROM blaze1.brand b RIGHT JOIN daily_discount_report r ON r.brand_id = b.brand_id WHERE r.daily_discount_report_id = ?", $daily_discount_report_id);
      $rd = getRs("SELECT r.*, b.brand_id, b.name AS brand_name, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE r.daily_discount_report_id = ?", $daily_discount_report_id);
    }
    if ($d = getRow($rd)) {
    $showGrossSales = ($d['brand_id'] == 91);
	$header = '

    <htmlpageheader name="myHeader1" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/var/www/vhosts/wantadigital.com/media/theartisttree/site/at.png" style="width: 100pt" /></td>
          <td class="text-right" style="vertical-align:bottom"><h1>Daily Deal Discount Report</h1></td>
        </tr>
      </table>
      </div>
    </htmlpageheader>

    <htmlpageheader name="myHeader2" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/var/www/vhosts/wantadigital.com/media/theartisttree/site/at.png" style="width: 60pt" /></td>
          <td class="text-right" style="vertical-align:bottom"><h4>' . $d['brand_name'] . iif($d['brand_name'], ': ') . date('F jS, Y', strtotime($d['date_start'])) . ' - ' . date('F jS, Y', strtotime($d['date_end'])) . '</h4></td>
        </tr>
      </table>      
      </div>
    </htmlpageheader>
      
    <htmlpagefooter name="myFooter1" style="display:none">
      <div class="footer"><div class="text-right">Page {PAGENO} of {nb}</div></div>
    </htmlpagefooter>
      
    <htmlpagefooter name="myFooter2" style="display:none">
      <div class="footer"><div>Page {PAGENO} of {nb}</div></div>
    </htmlpagefooter>';

    $rs = getRs("SELECT s.store_name, d.* FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s')  . " ORDER BY d.daily_discount_report_store_id", array($d['daily_discount_report_brand_id']));
    foreach($rs as $r) {
        $rp = json_decode($r['params'], true);
        if (sizeof($rp)) {
        $body .= '
      <div class="page-break"></div><div class="content">
        <h3>' . $r['store_name'] . '</h3>
        <table class="full bordered">
        <thead>
          <tr class="b-3">'
             . iif($byDate,'<th class="text-center" style="font-size: 10pt;">Date</th>') .
			'<th class="text-center" style="font-size: 10pt;">Weekday</th>
            <th class="text-center" style="font-size: 10pt;">Category</th>
            <th class="text-center" style="font-size: 10pt;">Product Name</th>'
			 . iif($showGrossSales,'<th class="text-center" style="font-size: 10pt;">Gross Sales</th>') . 
            '<th class="text-center" style="font-size: 10pt;">Rebate Type</th>
            <th class="text-center" style="font-size: 10pt;">Rebate %</th>
            <th class="text-center" style="font-size: 10pt;">Cogs / Unit Price</th>
			<th class="text-center" style="font-size: 10pt;">Qty Sold</th>  
            <th class="text-center" style="font-size: 10pt;">Total Rebate Due</th>
          </tr>
        </thead>
        <tbody class="products">';
            $qty = 0;
            $total = 0;
			$w_qty = 0;
           $w_total =0;
			$pWeekday = NULL;
            foreach($rp as $p) {
                /*if ($pWeekday && $pWeekday != $p['weekday_name']) {
					$body .= '
						<tr style="background-color: lightgrey;" class="b-2" >
							<td colspan="6" class="text-left" style="font-size: 11pt;" ><b> '. $pWeekday . ' Total</b></td>
							<td  class="text-center" style="font-size: 11pt;" nowrap><b>' . number_format($w_qty, 2) . '</b></td>
							<td  class="text-center" style="font-size: 11pt;" nowrap><b>' . currency_format($w_total, '$') . '</b></td>
						</tr>';
					$w_qty = 0;
                	$w_total =0;
				}*/
				$body .= '
                <tr class="b-1">'
					 . iif($byDate,'<td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . iif($p['TransactionDate'],date('m/d/y', strtotime($p['TransactionDate']))) . '</td>') .                  
					'<td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . $p['weekday_name'] . '</td>
                    <td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . $p['category_name'] . '</td>
                    <td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . $p['product_name'] . '</td>'
					 . iif($showGrossSales,'<td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . currency_format($p['GrossSales']) . '</td>') . 
                    '<td class="text-center" style="font-size: 8pt; vertical-align: middle;">' . $p['daily_discount_type_name'] . '</td>
                    <td  class="text-center" style="font-size: 8pt; vertical-align: middle;" nowrap>' . number_format($p['rebate_percent'], 2) . '</td>
                    <td  class="text-center" style="font-size: 8pt; vertical-align: middle;" nowrap>' . currency_format($p['unit_price'], '$') . '</td>
					<td  class="text-center" style="font-size: 8pt; vertical-align: middle;" nowrap>' . number_format($p['quantity'], 2) . '</td>          
                    <td  class="text-center" style="font-size: 8pt; vertical-align: middle;" nowrap><b>' . currency_format($p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'], '$') . '</b></td>
                </tr>';
                $qty += $p['quantity'];
                $total += $p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'];
				$w_qty += $p['quantity'];
                $w_total += $p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'];
				$pWeekday = $p['weekday_name'];
            }
            /*$body .= '
				<tr style="background-color: lightgrey;" class="b-2" >
					<td colspan="6" class="text-left" style="font-size: 11pt;" ><b> '. $pWeekday . ' Total</b></td>
					<td  class="text-center" style="font-size: 11pt;" nowrap><b>' . number_format($w_qty, 2) . '</b></td>
					<td  class="text-center" style="font-size: 11pt;" nowrap><b>' . currency_format($w_total, '$') . '</b></td>
				</tr>'*/
            $body .= '
			</tbody>
            <tfoot>
                <tr style="background-color: grey;" class="b-2">
                    <td colspan="' . (($showGrossSales ? 7 : 6) + ($byDate ? 1 : 0)) . '" class="text-left" style="font-size: 11pt; color: white;" ><b>Grand Total</b></td>
                    <td  class="text-center" style="font-size: 11pt; color: white;" nowrap><b>' . number_format($qty, 2) . '</b></td>
                    <td  class="text-center" style="font-size: 11pt; color: white;" nowrap><b>' . currency_format($total, '$') . '</b></td>
                </tr>
            </tfoot>';
            $summary .= '<tr><td>' . $r['store_name'] . '</td><td class="text-right">' . currency_format($total, '$') . '</td></tr>';
            $g_total += $total;
       
        $body .= '
      </table></div>';

      array_push($content, $body);
      $body = '';
     }
    }

    $summary = '<div class="content">
      <div style="padding-top:20px;">
      <h2>' . $d['brand_name'] . iif($d['brand_name'], ': ') . date('F, Y', strtotime($d['date_start'])) . '</h2>
      <h4>' . date('F jS, Y', strtotime($d['date_start'])) . ' - ' . date('F jS, Y', strtotime($d['date_end'])) . '</h4>
      <table class="full bordered">
      <thead>
        <tr class="b-3">
          <th class="text-left">Store Location</th>
          <th class="text-right">Daily Deal Reimbursement</th>
        </tr>
      </thead><tbody>' . $summary . '</tbody><tfoot><tr class="b-3"><th class="text-left">TOTAL</th><th class="text-right">' . currency_format($g_total, '$') . '</tfoot></table></div>
      </div>';

  }


  //return $header . '<div class="content">' . $summary . $body . '</div>';
  array_unshift($content, $summary);
  array_unshift($content, $header);
  return $content;
}

function generateReport($daily_discount_report_id, $daily_discount_report_brand_id, $filename) {
  
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
	
	$stylesheet = file_get_contents(ASSETS_PATH . 'css/report.css');
	$mpdf->WriteHTML($stylesheet, 1);	// The parameter 1 tells that this is css/style only and no body/html/text
  $rs = reportPdfContent($daily_discount_report_id, $daily_discount_report_brand_id);
  foreach($rs as $r) {
    //$mpdf->AddPage();
    $mpdf->WriteHTML($r);
  }
  //$mpdf->WriteHTML();
	$mpdf->Output($filename, 'F');
	return array('success' => true, 'response' => null);
}

?>