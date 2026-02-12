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
            <div style="height:3.0in;width:4.8in;padding:0.15in;">
            <table class="table-card" style="margin:0;padding:0;width:4.8in;">
            <tr>
                <td colspan="2">
                    <div style="font-size:' . $card['name_size'] . 'pt;font-weight:bold;">'. $card['name'] . '</div>
                    <div style="font-size:8pt;">' . $card['brand'] . '</div>
                </td>
                <td style="text-align:right;"><img src="http://theartist.wantadigital.com/product-card-logo.png" style="width: 64pt" /></td>
            </tr>
            <tr>
                <td colspan="3" style="font-size:' . $card['strains_size'] . 'pt;border-top:2pt solid #000;border-bottom:2pt solid #000;">' . $card['strains'] . '&nbsp;</td>
            </tr>
            <tr>
                <td colspan="3" style="font-size:6.5pt;border-bottom:2pt solid #000;height:50pt;padding-top:5pt;padding-bottom:5pt;">' . $card['description'] . '</td>
            </tr>
            <tr>
                <td>
                    <table>
                    <tr><td style="padding:0;font-size:8pt;">Category:</td><td style="font-size:11pt;font-weight:bold;">' . $card['category'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Type:</td><td style="font-size:11pt;font-weight:bold;">' . $card['type'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">THC:</td><td style="font-size:11pt;font-weight:bold;">' . $card['thc'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">CBD:</td><td style="font-size:11pt;font-weight:bold;">' . $card['cbd'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Weight:</td><td style="font-size:11pt;font-weight:bold;">' . $card['weight'] . '</td></tr>
                    <tr><td style="padding:0;font-size:8pt;">Price:</td><td style="font-size:11pt;font-weight:bold;">' . $card['price'] . '</td></tr>
                    </table>
                </td>
                <td style="vertical-align:top;text-align:center;border-left:2px solid #000;"><div style="font-weight:bold;font-size:11pt;">Terpenes</div><div style="font-size:8pt;">
                <table>';
                foreach($card['terpenses'] as $fx) {
                    $ret .= '<tr><td style="padding:2pt;font-size:8pt;">' . $fx. '</td></tr>';

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
            </table>
            </div>
            <div style="font-size:8pt;padding-left:0.15in;">* Items and invdividual effects may vary</div>
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