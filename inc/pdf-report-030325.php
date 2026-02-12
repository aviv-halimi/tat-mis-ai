<?php
require_once(CLASS_PATH . 'mpdf/vendor/autoload.php');

function reportPdfContent($daily_discount_report_id, $daily_discount_report_brand_id) {

    $content = array();
    $summary = $body = $header = '';
    $g_total = 0;

    if ($daily_discount_report_brand_id) {   
      $rd = getRs("SELECT r.*, b.name AS brand_name, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE rb.daily_discount_report_brand_id = ?", $daily_discount_report_brand_id);
    }
    else {
      //$rd = getRs("SELECT r.*, b.name AS brand_name FROM blaze1.brand b RIGHT JOIN daily_discount_report r ON r.brand_id = b.brand_id WHERE r.daily_discount_report_id = ?", $daily_discount_report_id);
      $rd = getRs("SELECT r.*, b.name AS brand_name, rb.daily_discount_report_brand_id FROM blaze1.brand b INNER JOIN (daily_discount_report_brand rb INNER JOIN daily_discount_report r ON rb.daily_discount_report_id = r.daily_discount_report_id) ON rb.brand_id = b.brand_id WHERE r.daily_discount_report_id = ?", $daily_discount_report_id);
    }
    if ($d = getRow($rd)) {
    $header = '

    <htmlpageheader name="myHeader1" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/media/site/at.png" style="width: 100pt" /></td>
          <td class="text-right" style="vertical-align:bottom"><h1>Daily Deal Discount Report</h1></td>
        </tr>
      </table>
      </div>
    </htmlpageheader>

    <htmlpageheader name="myHeader2" style="display:none">
      <div class="header">
      <table class="full">
        <tr>
          <td><img src="/media/site/at.png" style="width: 60pt" /></td>
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
          <tr class="b-3">
            <th class="text-left">Weekday</th>
            ' . iif(!$d['brand_id'], '<th class="text-left">Brand</th>') . '
            <th class="text-left">Category</th>
            <th class="text-left">Product Name</th>
            <th class="text-left">Rebate Type</th>
            <th class="text-right">Rebate Percentage</th>
            <th class="text-right">Quantity Sold</th>
            <th class="text-right">Cogs / Unit Price</th>
            <th class="text-right">Total Rebate Due</th>
          </tr>
        </thead>
        <tbody class="products">';
            $qty = 0;
            $total = 0;
            foreach($rp as $p) {
                $body .= '
                <tr class="b-1">
                    <td class="pl-1">' . $p['weekday_name'] . '</td>
                    ' . iif(!$d['brand_id'], '<td class="pl-1">' . $p['brand_name'] . '</td>') . '
                    <td class="pl-1">' . $p['category_name'] . '</td>
                    <td class="pl-1">' . $p['product_name'] . '</td>
                    <td class="pl-1">' . $p['daily_discount_type_name'] . '</td>
                    <td class="text-right" nowrap>' . number_format($p['rebate_percent'], 2) . '</td>
                    <td class="text-right" nowrap>' . number_format($p['quantity'], 2) . '</td>
                    <td class="text-right" nowrap>' . currency_format($p['unit_price'], '$') . '</td>
                    <td class="text-right" nowrap>' . currency_format($p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'], '$') . '</td>
                </tr>';
                $qty += $p['quantity'];
                $total += $p['quantity'] * $p['rebate_percent'] / 100 * $p['unit_price'];
            }
            $body .= '
            </tbody>
            <tfoot>
                <tr class="b-2">
                    <td colspan="' . iif(!$d['brand_id'], 6, 5) . '" class="text-right"><b>Total</b></td>
                    <td class="text-right" nowrap><b>' . number_format($qty, 2) . '</b></td>
                    <td></td>
                    <td class="text-right" nowrap><b>' . currency_format($total, '$') . '</b></td>
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
          <th class="text-right">Total Rebate Due</th>
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