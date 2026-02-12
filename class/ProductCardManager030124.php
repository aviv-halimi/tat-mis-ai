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

            $batchId = getVarA('batch_' . $product_id, $_p);
            $name_size = getVarANum('name_size_' . $product_id, $_p, 18);
            $strains_size = getVarANum('strains_size_' . $product_id, $_p, 15);
            $_Fulfillment->UpdateInventory($product_id, $p['id']);

            $name = str_replace($r['brand']['name'], '', $r['name']);
            foreach($rt as $t) {
              $name = str_replace($t['product_card_remove_text_name'], '', $name);
            }
            $name = trim($name);
            ///
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
            if ($batchId) {
              $rb = fetchApi('store/batches', $this->api_url, $this->auth_code, $this->partner_key, 'batchId=' . $batchId);
              $b = json_decode($rb, true);
              $b = $b['values'][0];
              $thc = $b['thc'] . iif(strlen($b['thc']), '%');
              $cbd = $b['cbd'] . iif(strlen($b['cbd']), '%');
              $terpenses = array();
              foreach($b['terpenoids'] as $k => $v) {
                array_push($terpenses, $k);
              }
            }

            ///
			
				
            $card = array('product_id' => $product_id, 'id' => $r['id'], 'batchId' => $batchId, 'original_name' => $r['name'], 'name' => $name, 'name_size' => $name_size, 'strains' => $strains, 'strains_size' => $strains_size, 'effects' => $effects, 'type' => nicefy($r['flowerType']), 'description' => $r['description'], 'brand' => $r['brand']['name'], 'category' => $r['category']['name'], 'price' => currency_format($r['unitPrice'], '$', ',', ($r['unitPrice'] * 100 % 100 != 0)?2:0) . ' + Taxes','discountPrice' => currency_format($discountPrice, '$', ',', ($discountPrice * 100 % 100 != 0)?2:0) . ' + Taxes', 'thc' => $thc, 'cbd' => $cbd, 'terpenses' => $terpenses);


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