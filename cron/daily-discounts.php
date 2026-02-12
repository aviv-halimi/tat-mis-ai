<?php
define('SkipAuth', true);
require_once ('../_config.php');
set_time_limit(0);

if ($IP != '52.26.238.52') exit('Access Denied');

$success = false;
$response = $redirect = null;
$a = $brands = $products = array();

$weekday_id = date('N');
$prev_weekday_id = $weekday_id - 1;
if ($prev_weekday_id < 1) $prev_weekday_id = 7;

$rs = getRs("SELECT store_id, db, api_url, auth_code, partner_key FROM store WHERE " . is_enabled() . " AND store_id <> 2 ORDER BY store_id");

foreach($rs as $s) {

    $daily_discount_log_id = dbPut($s['db'] . '.daily_discount_log', array('admin_id' => $_Session->admin_id, 'weekday_id' => $weekday_id, 'date_start' => 'NOW()'));

    $prev_sql = "SELECT d.daily_discount_id, d.discount_rate, d.brand_id, d.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN {$s['db']}.daily_discount d ON (d.brand_id = p.brand_id OR d.brand_id IS NULL) AND (d.category_id = p.category_id OR d.category_id IS NULL) WHERE d.weekday_id = ? AND " . is_active('d') . " ORDER BY d.brand_id, d.category_id, p.name";

    $sql = "SELECT d.daily_discount_id, d.discount_rate, d.brand_id, d.category_id, p.product_id, p.id, p.name AS product_name, p.deleted FROM {$s['db']}.product p INNER JOIN {$s['db']}.daily_discount d ON (d.brand_id = p.brand_id OR d.brand_id IS NULL) AND (d.category_id = p.category_id OR d.category_id IS NULL) WHERE d.weekday_id = ? AND " . is_enabled('d') . " ORDER BY d.brand_id, d.category_id, p.name";

    $brand_id = $category_id = null;
    $num_brands = $num_products = 0;
    $params = array();

    // remove previous sales price
    $rd = getRs($prev_sql, array($prev_weekday_id));
    foreach($rd as $d) {
        if ($d['deleted'] != '1') {
            if ($brand_id != $d['brand_id'] || $category_id != $d['category_id']) {
                $num_brands++;
                $brand_id = $d['brand_id'];
                $category_id = $d['category_id'];
                array_push($params, array('brand_id' => $d['brand_id'], 'brand_name' => '* Previous *', 'category_id' => $d['category_id'], 'category_name' => '* Previous *', 'discount' => $d['discount_rate'], 'products' => array()));
            }

            $json = fetchApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key']);
            $a_json = json_decode($json);

            if (isset($a_json->priceBreaks[0]->price) and isset($a_json->priceBreaks[0]->salePrice)) {

                if (isset($a_json->tags) and is_array($a_json->tags)) {
                    if (!in_array('DiscountEligible', $a_json->tags)) {
                        array_push($a_json->tags, 'DiscountEligible');
                    }
                }
        
                $price = $a_json->priceBreaks[0]->price;
                $salePrice = $a_json->priceBreaks[0]->salePrice;
                $new_salePrice = null;
                $a_json->priceBreaks[0]->salePrice = $new_salePrice;


                if ($salePrice) {
                    $num_products++;
                    $r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
                }
                array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
            }
        }
    }

    $brand_id = $category_id = null;
    // set new sales price
    $rd = getRs($sql, array($weekday_id));
    foreach($rd as $d) {
        $update = true;
        if ($d['deleted'] != '1') {
            if ($brand_id != $d['brand_id'] || $category_id != $d['category_id']) {
                $num_brands++;
                $brand_id = $d['brand_id'];
                $category_id = $d['category_id'];
                array_push($params, array('brand_id' => $d['brand_id'], 'brand_name' => null, 'category_id' => $d['category_id'], 'category_name' => null, 'discount' => $d['discount_rate'], 'products' => array()));
            }

            $json = fetchApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key']);
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
                
                //array_push($a, $a_json);        

                $r_json = putApi('products/' . $d['id'], $s['api_url'], $s['auth_code'], $s['partner_key'], $a_json);
                //array_push($a, json_decode($r_json, true));

                $num_products++;
                array_push($params[sizeof($params) - 1]['products'], array('product_id' => $d['product_id'], 'product_name' => $a_json->name, 'price' => $price, 'prev_salePrice' => $salePrice, 'new_salePrice' => $new_salePrice));
            }
        }
    }

    dbUpdate($s['db'] . '.daily_discount_log', array('date_end' => 'NOW()', 'num_products' => $num_products, 'num_brands' => $num_brands, 'params' => json_encode($params)), $daily_discount_log_id);
    $success = true;
    $response = $num_products . ' product' . iif($num_products != 1, 's') . ' updated';
}

/**/
header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
//echo json_encode($a);
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
?>