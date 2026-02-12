<?php

class ProductCardManager extends SessionManager {
    function NewProductCard($_p) {
      $success = false;
      $response = null;
      $product_card_id = dbPut('product_card', array('store_id' => $this->store_id, 'params' => json_encode($_p)));
      $success = true;
      $_p['product_card_id'] = $product_card_id;
      $this->UpdateProductCard($_p);
      return array('success' => $success, 'response' => $response, 'product_card_id' => $product_card_id);
    }

    function UpdateProductCard($_p) {
      global $_Fulfillment;
      $product_card_id = getVarANum('product_card_id', $_p);
      $cards = array();
      if (isset($_POST['product_ids'])) { 
        $rt = getRs("SELECT * FROM product_card_remove_text WHERE " . is_enabled());
        $rss = getRs("SELECT * FROM product_card_strain WHERE " . is_enabled());   
        $re = getRs("SELECT * FROM product_card_effect WHERE " . is_enabled());
        foreach($_POST['product_ids'] as $product_id) {
          $rp = getRs("SELECT p.id FROM {$this->db}.product p WHERE p.product_id = ?", array($product_id));
          if ($p = getRow($rp)) {          
            $rs = fetchApi('store/inventory/products/' . $p['id'], $this->api_url, $this->auth_code, $this->partner_key);
            $r = json_decode($rs, true);
			$__r = json_decode($rs);

            $batchId = getVarA('batch_' . $product_id, $_p);
            $name_size = getVarANum('name_size_' . $product_id, $_p, 18);
            $strains_size = getVarANum('strains_size_' . $product_id, $_p, 15);
			$show_st = getVarANum('show_st_' . $product_id, $_p, 15);
			$custom_text = getVarA('custom_text_' . $product_id, $_p);
            $_Fulfillment->UpdateInventory($product_id, $p['id']);

            $name = str_replace($r['brand']['name'], '', $r['name']);
            foreach($rt as $t) {
              $name = str_replace($t['product_card_remove_text_name'], '', $name);
            }
            $name = trim($name);
            ///
			$newstrains = $r['complianceInfo']['strain'];
            $strains = array();          
            foreach($r['tags'] as $tag) {
              foreach($rss as $ss) {
                if (strtolower($ss['product_card_strain_name']) == strtolower($tag)) {
                  array_push($strains, $ss['product_card_strain_name']);
                }
              }
            }
            $strains = implode(' x ', $strains); 
            ///
            $effects = array();          
            foreach($r['tags'] as $tag) {
              foreach($re as $e) {
                if (strtolower($e['product_card_effect_name']) == strtolower($tag)) {
                  array_push($effects, $e['product_card_effect_name']);
                }
              }
            }

            ///
            $thc = $cbd = null;
            $terpenses = array();
			$neweffects = array();
			$sqlwhere = NULL;
			$effectCount = 0;
            if ($batchId) {
              $rb = fetchApi('store/batches', $this->api_url, $this->auth_code, $this->partner_key, 'batchId=' . $batchId);
              $b = json_decode($rb, true);
              $b = $b['values'][0];
              $thc = $b['thc'] . iif(str_len($b['thc']), '%');
              $cbd = $b['cbd'] . iif(str_len($b['cbd']), '%');
			  arsort($b['terpenoids']);
              $terpenses = array();
              foreach($b['terpenoids'] as $k => $v) {
                $terpvalue = ($v > .01)?$v:"(N/A)";
				$terpname = (str_len($k)>18)?substr($k,0,16).'...':$k;
				$pushvalue = '<tr><td style="padding:.5pt;font-size:8pt;text-align:left;">' . $terpname . '</td><td style="padding:0pt;font-size:8pt;text-align:right;">' . $terpvalue . '%</td></tr>';
				array_push($terpenses, $pushvalue);
				$sqlwhere =$neweffects?"AND effect NOT IN ('" . implode("','",$neweffects) . "')":NULL;
			  	$tTerp = str_replace('Beta','',$k);
				$tTerp = str_replace('Alpha','',$tTerp);
				$ref= getRs("SELECT DISTINCT effect FROM theartisttree.effects WHERE terpene LIKE '{$tTerp}' {$sqlwhere} AND active = 1");
			  	foreach ($ref as $ref) {
				    if ($effectCount <= 5) {
						array_push($neweffects, $ref['effect']);
						$effectCount = $effectCount + 1;
					}
			  	}
				  
				  
              }
			  /*
			  $terpenestr = "'" . implode("','", $terpenses) . "'";	
			  $reffect = getRs("SELECT DISTINCT effect FROM theartisttree.effects WHERE terpene in ({$terpenestr}) where active = 1 limit 7");
			  foreach ($reffect as $ref) {
				    array_push($neweffects, $ref['effect']);
			  }
			  */
            }
			
            ///
			
			  
			$discountPrice = (isset($__r->priceBreaks[0]->salePrice))?$__r->priceBreaks[0]->salePrice:0;
						  
            $card = array('product_id' => $product_id, 'id' => $r['id'], 'batchId' => $batchId, 'original_name' => $r['name'], 'name' => $name, 'name_size' => $name_size, 'strains' => $strains, 'strains_size' => $strains_size, 'effects' => $neweffects, 'type' => nicefy($r['flowerType']), 'description' => $r['description'], 'brand' => $r['brand']['name'], 'category' => $r['category']['name'], 'price' => $r['price'], 'thc' => $thc, 'cbd' => $cbd, 'terpenses' => $terpenses, 'show_st' => $show_st, 'unitPrice' => $r['unitPrice'], 'discountPrice' => $discountPrice, 'custom_text' => $custom_text);


            if ($r['weightPerUnit'] == 'CUSTOM_GRAMS') {
              $card['weight'] = $r['customWeight'] . ' ' . nicefy($r['customGramType']);
            }
            else {
              $rw = getRs("SELECT product_card_weight_name FROM product_card_weight WHERE blaze_weight = ?", array($r['weightPerUnit']));
              if ($w = getRow($rw)) {
                $card['weight'] = $w['product_card_weight_name'];
              }
              else {
                $card['weight'] = nicefy($r['weightPerUnit']);
              }
            }
            array_push($cards, $card);
          }
        }
      }
      dbUpdate('product_card', array('cards' => json_encode($cards), 'params' => json_encode($_p, JSON_NUMERIC_CHECK)), $product_card_id);
      return $product_card_id;
    }

}

?>