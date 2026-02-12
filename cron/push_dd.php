<?php

define('SkipAuth', true);
require_once('../_config.php');

$run_api = true; // This allows for testing without running blaze API calls

$success = false;
$response = $redirect = null;
$a = $brands = $products = array();

$store_id = isset($argv[1])?$argv[1]:null;
$__brand_id = isset($argv[2])?$argv[2]:null;

//echo 'arg: ' . $argv[1];
//echo ' ||| store_id: ' . $store_id;

//$__brand_id = 99999;
//$store_id = 1;


$weekday_id = date('N');
$prev_weekday_id = $weekday_id - 1;
if ($prev_weekday_id < 1) $prev_weekday_id = 7;

$rs = getRs("SELECT store_id, store_name, db, api_url, auth_code, partner_key FROM store WHERE store_id <> 2 AND " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id");

$t_admin_id = isset($_Session->admin_id)?$_Session->admin_id:null;

foreach($rs as $s) {
	echo '<ul>';
	echo '<li>' . $s['store_name'];

    $daily_discount_log_id = dbPut('daily_discount_log', array('store_id' => $s['store_id'], 'admin_id' => $t_admin_id, 'weekday_id' => $weekday_id, 'date_start' => 'NOW()'));

    //$prev_sql = "SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN ({$s['db']}.brand b RIGHT JOIN ({$s['db']}.category c RIGHT JOIN daily_discount d ON d.category_id = c.master_category_id OR d.category_id IS NULL) ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) WHERE d.weekday_id = ? AND " . is_active('d') . " AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) AND (d.date_end IS NULL OR d.date_end >= subdate(current_date, 1)) AND (d.date_start IS NULL OR d.date_start <= subdate(current_date, 1)) AND NOT(p.deleted = '1')" . iif($__brand_id," AND b.master_brand_id = {$__brand_id}") . " ORDER BY b.brand_id, c.category_id, p.name";
	
	/*$prev_sql = "SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN ({$s['db']}.brand b RIGHT JOIN ({$s['db']}.category c RIGHT JOIN 
		(SELECT d1.* 
		FROM daily_discount d1 
		LEFT JOIN daily_discount d2 
			ON d1.brand_id = d2.brand_id 
			AND (d1.category_id = d2.category_id OR (ISNULL(d1.category_id) AND ISNULL(d2.category_id)))
			AND d1.weekday_id = CASE WHEN d2.weekday_id = 1 THEN 7 ELSE d2.weekday_id - 1 END
			AND d1.discount_rate = d2.discount_rate 
			AND (d1.store_ids = d2.store_ids OR (ISNULL(d1.store_ids) AND ISNULL(d2.store_ids)))
			AND NOT(d1.daily_discount_id = d2.daily_discount_id)
			AND (d2.date_end IS NULL OR d2.date_end >= NOW()) 
			AND (d2.date_start IS NULL OR d2.date_start <= NOW())
			AND d2.is_enabled = 1
		WHERE  (d1.date_end IS NULL OR d1.date_end >= subdate(current_date, 1)) 
			AND (d1.date_start IS NULL OR d1.date_start <= subdate(current_date, 1))
			AND ISNULL(d2.daily_discount_id)
			AND d1.is_enabled = 1
		GROUP BY d1.daily_discount_id) as d
	ON d.category_id = c.master_category_id OR d.category_id IS NULL) ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) WHERE d.weekday_id = ? AND " . is_active('d') . " AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) AND NOT(p.deleted = '1') AND p.active = '1' AND p.name NOT LIKE 'promo%' AND p.Name NOT LIKE 'Sample%'  AND p.Name NOT LIKE 'smpl%' AND p.Name NOT LIKE 'display%'" . iif($__brand_id," AND b.master_brand_id = {$__brand_id}") . " GROUP BY p.id ORDER BY b.brand_id, c.category_id, p.name";*/
	
	//$prev_sql = "SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN ({$s['db']}.brand b RIGHT JOIN ({$s['db']}.category c RIGHT JOIN daily_discount d ON 1=1) ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) WHERE d.weekday_id = ? AND " . is_enabled('d') . " AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) AND (d.date_end IS NULL OR d.date_end >= NOW()) AND (d.date_start IS NULL OR d.date_start <= NOW()) AND NOT(p.deleted = '1') AND p.instock = 1 AND c.master_category_id NOT IN (22, 8, 12, 11, 32) AND b.master_brand_id IN (121, 444, 421, 503, 499, 91)" . iif($__brand_id," AND b.master_brand_id = {$__brand_id}") . "GROUP BY p.id ORDER BY b.brand_id, c.category_id, p.name";
	
	$prev_sql = "	
			SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted 
			FROM {$s['db']}.product p 
				INNER JOIN ({$s['db']}.brand b 
				RIGHT JOIN ({$s['db']}.category c 
				RIGHT JOIN daily_discount d 
					ON JSON_CONTAINS(d.category_ids, CAST(c.master_category_id AS CHAR), '$') OR d.category_ids IS NULL) 
					ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) 
					ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) 
			WHERE 
				JSON_CONTAINS(d.weekday_ids, CAST(? AS CHAR), '$') 
				AND (NOT (JSON_CONTAINS(d.weekday_ids, CAST(? AS CHAR), '$')) OR d.date_end = subdate(current_date, 1)) 
				AND " . is_active('d') . " 
				AND " . is_enabled('d') . " 
				AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) 
				AND (d.date_end IS NULL OR d.date_end >= subdate(current_date, 1)) 
				AND (d.date_start IS NULL OR d.date_start <= subdate(current_date, 1)) 
				AND (d.min_weight IS NULL OR d.min_weight <= 
					CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END
					OR (CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END IS NULL)
				)
				AND (d.max_weight IS NULL OR d.max_weight >= 
					CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END
					OR (CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END IS NULL)
				)"
				. iif($__brand_id," AND b.master_brand_id = {$__brand_id}") . " 
			ORDER BY b.brand_id, c.category_id, p.name";
	
	//$prev_weekday_id = 2;
	
    $sql = "
		SELECT d.daily_discount_id, d.discount_rate, d.is_clearance, b.brand_id, c.category_id, p.product_id, p.id, p.name AS product_name, p.deleted 
		FROM {$s['db']}.product p 
			INNER JOIN ({$s['db']}.brand b 
			RIGHT JOIN ({$s['db']}.category c 
			RIGHT JOIN daily_discount d 
				ON JSON_CONTAINS(d.category_ids, CAST(c.master_category_id AS CHAR), '$') OR d.category_ids IS NULL) 
				ON d.brand_id = b.master_brand_id OR d.brand_id IS NULL) 
				ON (b.brand_id = p.brand_id AND c.category_id = p.category_id) 
			WHERE 
				JSON_CONTAINS(d.weekday_ids, CAST(? AS CHAR), '$') 
				AND " . is_active('d') . " 
				AND " .  is_enabled('d') . " 
				AND (JSON_CONTAINS(d.store_ids, CAST(? AS CHAR), '$') OR d.store_ids IS NULL) 
				AND (d.date_end IS NULL OR d.date_end >= current_date) 
				AND (d.date_start IS NULL OR d.date_start <= current_date) 
				AND NOT(p.deleted = '1') 
				AND p.active = '1' 
				AND p.name NOT LIKE 'promo%' 
				AND p.Name NOT LIKE 'Sample%'  
				AND p.Name NOT LIKE 'smpl%' 
				AND p.Name NOT LIKE 'display%'
				AND (d.min_weight IS NULL OR d.min_weight <= 
					CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END
					OR (CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END IS NULL)
				)
				AND (d.max_weight IS NULL OR d.max_weight >= 
					CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END
					OR (CASE 
						WHEN p.weightPerUnit = 'TWO_GRAM' THEN 2
						WHEN p.weightPerUnit = 'FULL_GRAM' THEN 1 
						WHEN p.weightPerUnit = 'HALF_GRAM' THEN .5 
						WHEN p.weightPerUnit = 'EIGHTH' THEN 3.5 
						WHEN p.weightPerUnit = 'CUSTOM_GRAMS' THEN customWeight 
					ELSE NULL END IS NULL)
				)"
				. iif($__brand_id," AND b.master_brand_id = {$__brand_id}") . " 
			GROUP BY p.id  
			ORDER BY b.brand_id, c.category_id, p.name";
	
    $brand_id = $category_id = null;
    $num_brands = $num_products = 0;
    $params = array();
    // remove previous sales price
	$day_before_prev = ($prev_weekday_id == 1)?7:$prev_weekday_id-1;
    $rd = getRs($prev_sql, array($prev_weekday_id, $weekday_id, $s['store_id']));
	echo '<li>' . sizeof($rd) . ' product(s) found for reset';
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
            $p_json = json_decode($json);
			$a_json = json_decode($json);
			
            if (isset($a_json->priceBreaks[0]->price) and isset($a_json->priceBreaks[0]->salePrice)) {

                if (isset($a_json->tags) and is_array($a_json->tags)) {
					$normalized_tags = array_map(function ($tag) {
						return strtolower(trim($tag));
					}, $a_json->tags);
					
                    $_di = array_search('dailydeal', $normalized_tags);
                	if ($_di !== false) {
						unset($a_json->tags[$_di]);
						$a_json->tags = array_values($a_json->tags);
                	}
					
					if (!in_array('discounteligible', $normalized_tags)) {
                        array_push($a_json->tags, 'DiscountEligible');
                    }
					
					$_di = array_search('clearance', $normalized_tags);
					if ($_di !== false) {
						$update = false;
					}
					
					$_di = array_search('clearance ', $normalized_tags);
					if ($_di !== false) {
						$update = false;
					}
					
					$_di = array_search('nodailydeal', $normalized_tags);
                	if ($_di !== false) {
                    	$update = false;
                	}
					
                }
        		if ($update) {
					$price = $a_json->priceBreaks[0]->price;
					$salePrice = $a_json->priceBreaks[0]->salePrice;
					$new_salePrice = null;
					$a_json->priceBreaks[0]->salePrice = $new_salePrice;

					$push = ' (NO CHANGE)';
                
					if ($salePrice) {
						$num_products++;
                        if ($run_api && $a_json != $p_json) {
						    $r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
							$push = null;
                        }
					}
					array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name . $push, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
				}
            }
        }
		echo '<li>' . memory_get_usage();
    }

    $brand_id = $category_id = null;
    // set new sales price
    $rd = getRs($sql, array($weekday_id, $s['store_id']));
	echo '<li>' . sizeof($rd) . ' product(s) found for discount';
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
			$p_json = json_decode($json);
            $a_json = json_decode($json);

            if (isset($a_json->tags) and is_array($a_json->tags)) {
				
				$normalized_tags = array_map(function ($tag) {
						return strtolower(trim($tag));
					}, $a_json->tags);
				
				$_di = array_search('discounteligible', $normalized_tags);
                if ($_di !== false) {
                    unset($a_json->tags[$_di]);
                    $a_json->tags = array_values($a_json->tags);
                }
				
				$_di = array_search('discounteligible ', $normalized_tags);
                if ($_di !== false) {
                    unset($a_json->tags[$_di]);
                    $a_json->tags = array_values($a_json->tags);
                }
				
				$_di = array_search('discount eligible', $normalized_tags);
                if ($_di !== false) {
                    unset($a_json->tags[$_di]);
                    $a_json->tags = array_values($a_json->tags);
                }
				
				
				$_di = array_search('discount eligible ', $normalized_tags);
                if ($_di !== false) {
                    unset($a_json->tags[$_di]);
                    $a_json->tags = array_values($a_json->tags);
                }

				$_di = array_search('clearance', $normalized_tags);
				if ($_di !== false) {
					$update = false;
				}

				$_di = array_search('nodailydeal', $normalized_tags);
				if ($_di !== false) {
					$update = false;
				}
				
				if (!in_array('dailydeal', $normalized_tags)) {
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
				$push = ' (NO CHANGE)';
                if ($run_api && $a_json != $p_json) {
					$r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
					$push = null;
                }

                $num_products++;
                array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name . $push, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
            }
        }
		echo '<li>' . memory_get_usage();
    }

	echo '</ul>';
    dbUpdate('daily_discount_log', array('date_end' => 'NOW()', 'num_products' => $num_products, 'num_brands' => $num_brands, 'params' => json_encode($params)), $daily_discount_log_id);
    $success = true;
    $response = $num_products . ' product' . iif($num_products != 1, 's') . ' updated';
}

echo '<hr />';
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();

?>