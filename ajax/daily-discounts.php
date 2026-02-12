<?php
require_once ('../_config.php');
set_time_limit(0);

$run_api = true; // This allows for testing without running blaze API calls

$success = false;
$response = $redirect = null;
$a = $brands = $products = array();

$store_id = getVarNum('store_id');

$weekday_id = date('N');
$prev_weekday_id = $weekday_id - 1;
if ($prev_weekday_id < 1) $prev_weekday_id = 7;

$rs = getRs("SELECT store_id, store_name, db, api_url, auth_code, partner_key FROM store WHERE store_id <> 2 AND " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id");

$t_admin_id = isset($_Session->admin_id)?$_Session->admin_id:null;

foreach($rs as $s) {
	// echo'<ul>';
	// echo'<li>' . $s['store_name'];

    $daily_discount_log_id = dbPut('daily_discount_log', array('store_id' => $s['store_id'], 'admin_id' => $t_admin_id, 'weekday_id' => $weekday_id, 'date_start' => 'NOW()'));

  $prev_sql = "SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN ({$s['db']}.brand b RIGHT JOIN ({$s['db']}.category c RIGHT JOIN daily_discount d ON d.category_id = c.master_category_id OR d.category_id IS NULL) ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) WHERE d.weekday_id = ? AND " . is_active('d') . " AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) AND (d.date_end IS NULL OR d.date_end >= subdate(current_date, 1)) AND (d.date_start IS NULL OR d.date_start <= subdate(current_date, 1)) ORDER BY b.brand_id, c.category_id, p.name";

    $sql = "SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN ({$s['db']}.brand b RIGHT JOIN ({$s['db']}.category c RIGHT JOIN daily_discount d ON d.category_id = c.master_category_id OR d.category_id IS NULL) ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) WHERE d.weekday_id = ? AND " . is_enabled('d') . " AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) AND (d.date_end IS NULL OR d.date_end >= NOW()) AND (d.date_start IS NULL OR d.date_start <= NOW()) ORDER BY b.brand_id, c.category_id, p.name";

    $brand_id = $category_id = null;
    $num_brands = $num_products = 0;
    $params = array();
    // remove previous sales price
    $rd = getRs($prev_sql, array($prev_weekday_id, $s['store_id']));
	// echo'<li>' . sizeof($rd) . ' product(s) found for reset';
    foreach($rd as $d) {
		$update = true;
        if ($d['deleted'] != '1') {
            if ($brand_id != $d['brand_id'] || $category_id != $d['category_id']) {
                $num_brands++;
                $brand_id = $d['brand_id'];
                $category_id = $d['category_id'];
                array_push($params, array('brand_id' => $d['brand_id'], 'brand_name' => '* Previous *', 'category_id' => $d['category_id'], 'category_name' => '* Previous *', 'discount' => $d['discount_rate'], 'products' => array()));
            }
			unset($a);
			unset($json);
			unset($a_json);
            
            if ($run_api) {
                $json = fetchApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key']);
            }
            else {
                $json ='{"name":"' . trim($d['product_name']) . '","priceBreaks":[{"price":0,"salePrice":0}]}';
            }
            $a_json = json_decode($json);
			
            if (isset($a_json->priceBreaks[0]->price) and isset($a_json->priceBreaks[0]->salePrice)) {

                if (isset($a_json->tags) and is_array($a_json->tags)) {
                    $_di = array_search('DailyDeal', $a_json->tags);
                	if ($_di !== false) {
						unset($a_json->tags[$_di]);
						$a_json->tags = array_values($a_json->tags);
                	}
					if (!in_array('DiscountEligible', $a_json->tags)) {
                        array_push($a_json->tags, 'DiscountEligible');
                    }
					$_di = array_search('Clearance', $a_json->tags);
					if ($_di !== false) {
						$update = false;
					}
                	$_di = array_search('NoDailyDeal', $a_json->tags);
                	if ($_di !== false) {
                    	$update = false;
                	}
					
                }
        		if ($update) {
					$price = $a_json->priceBreaks[0]->price;
					$salePrice = $a_json->priceBreaks[0]->salePrice;
					$new_salePrice = null;
					$a_json->priceBreaks[0]->salePrice = $new_salePrice;


					if ($salePrice) {
						$num_products++;
                        if ($run_api) {
						    $r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
                        }
					}
					array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
				}
            }
        }
		// echo'<li>' . memory_get_usage();
    }

    $brand_id = $category_id = null;
    // set new sales price
    $rd = getRs($sql, array($weekday_id, $s['store_id']));
	// echo'<li>' . sizeof($rd) . ' product(s) found for discount';
    foreach($rd as $d) {
        $update = true;
        if ($d['deleted'] != '1') {
            if ($brand_id != $d['brand_id'] || $category_id != $d['category_id']) {
                $num_brands++;
                $brand_id = $d['brand_id'];
                $category_id = $d['category_id'];
                array_push($params, array('brand_id' => $d['brand_id'], 'brand_name' => null, 'category_id' => $d['category_id'], 'category_name' => null, 'discount' => $d['discount_rate'], 'products' => array()));
            }
			unset($a);
			unset($json);
			unset($a_json);
            if ($run_api) {
                $json = fetchApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key']);
            }
            else {
                $json ='{"name":"' . trim($d['product_name']) . '","priceBreaks":[{"price":0,"salePrice":0}]}';
            }
            $a_json = json_decode($json);

            if (isset($a_json->tags) and is_array($a_json->tags)) {
                $_di = array_search('DiscountEligible', $a_json->tags);
                if ($_di !== false) {
                    unset($a_json->tags[$_di]);
                    $a_json->tags = array_values($a_json->tags);
                }
                
				$_di = array_search('Clearance', $a_json->tags);
				if ($_di !== false) {
					$update = false;
				}
                
                $_di = array_search('NoDailyDeal', $a_json->tags);
                if ($_di !== false) {
                    $update = false;
                }
				if (!in_array('DailyDeal', $a_json->tags)) {
					array_push($a_json->tags, 'DailyDeal');
				}
            }

            if (strpos(strtolower($d['product_name']), 'display') !== false) {
                $update = false;
            }
            if (strpos(strtolower($d['product_name']), 'promo') !== false) {
                $update = false;
            }
            if (strpos(strtolower($d['product_name']), 'sample') !== false) {
                $update = false;
            }

            if ($update) {
                $price = (isset($a_json->priceBreaks[0]->price))?$a_json->priceBreaks[0]->price:0;
                $salePrice = (isset($a_json->priceBreaks[0]->salePrice))?$a_json->priceBreaks[0]->salePrice:null;
                $new_salePrice = null;
                if ($price > .1) $new_salePrice = 1 * number_format($price * (1 - ($d['discount_rate'] / 100)), 2, '.', '');
                if (isset($a_json->priceBreaks[0]->salePrice)) {
                    $a_json->priceBreaks[0]->salePrice = $new_salePrice;
                }
                else {            
                    $a_json->priceBreaks[0]->{'salePrice'} = $new_salePrice;
                }     

                if ($run_api) {
                    $r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
                }

                $num_products++;
                array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
            }
        }
		// echo'<li>' . memory_get_usage();
    }

	// echo'</ul>';
    dbUpdate('daily_discount_log', array('date_end' => 'NOW()', 'num_products' => $num_products, 'num_brands' => $num_brands, 'params' => json_encode($params)), $daily_discount_log_id);
    $success = true;
    $response = iif($num_products, $num_products) . ' product' . iif($num_products != 1, 's') . ' updated';

    $rl = getRs("SELECT daily_discount_log_code FROM daily_discount_log WHERE daily_discount_log_id = ?", array($daily_discount_log_id));
    if ($l = getRow($rl)) {
        $redirect = '/daily-discounts-log/' . $l['daily_discount_log_code'];
    }
}
if ($success and sizeof($rs) != 1) $redirect = '/daily-discounts-logs';

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();

?>