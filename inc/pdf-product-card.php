<?php
require_once(PLUGINS_PATH . 'mpdf/vendor/autoload.php');

function pcPdfContent($product_card_id) {
    $styles = array(
        'top:0; left:0;',
        'top:0; right:0;',
        'bottom:0; left:0;',
        'bottom:0; right:0;',
    );
    $ret = '';
    $rs = getRs("SELECT * FROM product_card WHERE product_card_id = ?", array($product_card_id));
    if ($r = getRow($rs)) {
        if (isJson($r['cards'])) $cards = json_decode($r['cards'], true);
        else $cards = array();
        $c = -1;
        foreach($cards as $card) {
            $c++;
			
			//Aviv add 3.1.24
			$show_st = isset($card['show_st'])?$card['show_st']:0;
			$discountPrice = isset($card['discountPrice'])?$card['discountPrice']:0;
			$unitPrice = isset($card['unitPrice'])?$card['unitPrice']:0;
            $_show_st = ($show_st and $discountPrice != $r['unitPrice'] and isset($discountPrice));
			
			if ($show_st and $discountPrice != $unitPrice and $discountPrice != 0) {
				$priceStr = '<td style="font-size:8pt;font-weight:bold;"><s>' . currency_format($unitPrice, '$', ',', ($discountPrice * 100 % 100 != 0)?2:0) . ' + taxes </s></td>';
				$discountStr = '<tr><td></td><td style="font-size:11pt;font-weight:bold;">' . currency_format($discountPrice, '$', ',', ($discountPrice * 100 % 100 != 0)?2:0) . ' + taxes</td></tr>';
			}	
			else {
				$priceStr = '<td style="font-size:11pt;font-weight:bold;">' . currency_format($unitPrice, '$', ',', ($unitPrice * 100 % 100 != 0)?2:0) . ' + taxes </td></tr>';
				$discountStr = '';
			}
			// End Aviv Add 3.1.24
			
			
			if ($c == 4) {
                $c = 0;
                $ret .= '<div style="page-break-before:always;"></diV>';
            }
            if ($c == 0) {
                $ret .= '<div style="position:absolute;top:0;left:0;width:4.8in;height:8.5in;border-right:2px dotted #000;"></div>';
                $ret .= '<div style="position:absolute;top:0;right:0;width:4.8in;height:8.5in;border-left:2px dotted #000;"></div>';
                $ret .= '<div style="position:absolute;top:0;left:0;width:11in;height:3.3in;border-bottom:2px dotted #000;"></div>';
                $ret .= '<div style="position:absolute;bottom:0;left:0;width:11in;height:3.3in;border-top:2px dotted #000;"></div>';
            }
            $ret .= '<div class="card" style="position:absolute;width:4.8in;height:3.3in;' . $styles[$c] . '">
            <div style="height:3.1in;width:4.8in;padding-top:0.15in;padding-left:0.15in;padding-right:0.15in;padding-bottom:0.05in;">
            <table class="table-card" style="margin:0;padding:0;width:4.8in;">
            <tr>
                <td colspan="2">
                    <div style="font-size:' . $card['name_size'] . 'pt;font-weight:bold;">'. $card['name'] . '</div>
                    <div style="font-size:8pt;">' . $card['brand'] . '</div>
                </td>
                <td style="text-align:right;"><img src="http://theartist.wantadigital.com/product-card-logo.png" style="width: 64pt" /></td>
            </tr>';
            /*$ret .= '<tr>
                <td colspan="3" style="font-size:' . $card['strains_size'] . 'pt;border-top:2pt solid #000;border-bottom:2pt solid #000;">' . $card['strains'] . '&nbsp;</td>
            </tr>';
			*/
			 $dHeight = strlen($card['custom_text'])?60:80;
             $ret .= '<tr>
                <td colspan="3" style="font-size:8pt;border-bottom:2pt solid #000;height:' . $dHeight . 'pt;padding-top:5pt;padding-bottom:5pt;border-top:2pt solid #000;border-bottom:2pt solid #000;">' . $card['description'] . '</td>
            </tr>
            <tr>
                <td width = "165px">
                    <table>
                    <tr><td style="padding:0;font-size:8pt;">Category:</td><td style="font-size:11pt;font-weight:bold;">' . $card['category'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Type:</td><td style="font-size:11pt;font-weight:bold;">' . $card['type'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">THC:</td><td style="font-size:11pt;font-weight:bold;">' . $card['thc'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">CBD:</td><td style="font-size:11pt;font-weight:bold;">' . $card['cbd'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Weight:</td><td style="font-size:11pt;font-weight:bold;">' . $card['weight'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Price:</td>' . $priceStr . '</tr>'
					. $discountStr . '
                    </table>
                </td>
                <td width = "175px" style="vertical-align:top;text-align:center;border-left:2px solid #000;"><div style="font-weight:bold;font-size:11pt;">Terpenes</div><div style="font-size:8pt">
                <table width="95%">';
                foreach($card['terpenses'] as $fx) {
                    //$ret .= '<tr ><td style="padding:2pt;font-size:8pt;text-align:left;">' . $fx. '</td></tr>';
					$ret .= $fx;
                }
                $ret .= '
                </table></div></td>
                <td style="vertical-align:top;text-align:center;border-left:2px solid #000;"><div style="font-weight:bold;font-size:11pt;">Effects</div><div style="font-size:8pt;">
                <table>';
                foreach($card['effects'] as $fx) {
                    $ret .= '<tr><td style="padding:2pt;font-size:8pt;">' . $fx. '</td></tr>';

                }
                $ret .= '
                </table></div></td>
            </tr>
			';
			if (strlen($card['custom_text'])) {
				$ret .= '<tr><td colspan = "3" style="height:20pt; font-size:12pt;text-align:center; vertical-align:middle;"><b>' . $card['custom_text'] . '</b></td></tr>';
				}
            $ret .= '
            <tr><td colspan = "3" style="font-size:6.5pt;">* Items and invdividual effects may vary</td></tr>
			</table>
            </div>
            </div>';
        }
    }
  return $ret;
}

function generatePC($product_card_id, $filename) {
  
  $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
  $fontDirs = $defaultConfig['fontDir'];

  $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
  $fontData = $defaultFontConfig['fontdata'];

	$mpdf = new \Mpdf\Mpdf(array(
    'mode' => 'utf-8',
    'format' => [215.9, 279.4],
    'orientation' => 'L',
    'margin-left' => 0,
    'margin-right' => 0,
    'margin-top' => 0,
    'margin-bottom' => 0,
    'fontdata' => $fontData + [
        'gilroy' => [
            'R' => 'Gilroy-Light.ttf',
            'B' => 'Gilroy-ExtraBold.ttf'
        ]
    ],
    'default_font' => 'gilroy'
  ));
  //$mpdf->showImageErrors = true;
	$mpdf->mirrorMargins = 1;	// Use different Odd/Even headers and footers and mirror margins
	
	$stylesheet = file_get_contents(ASSETS_PATH . 'css/product-card.css');
	$mpdf->WriteHTML($stylesheet, 1);	// The parameter 1 tells that this is css/style only and no body/html/text
  
  $mpdf->WriteHTML(pcPdfContent($product_card_id));
	$mpdf->Output($filename, 'F');
	return array('success' => true, 'response' => null);
}

?>