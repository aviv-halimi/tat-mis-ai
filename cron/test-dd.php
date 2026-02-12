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
$store_id = 3;


$weekday_id = date('N');
$prev_weekday_id = $weekday_id - 1;
if ($prev_weekday_id < 1) $prev_weekday_id = 7;

$rs = getRs("SELECT store_id, store_name, db, api_url, auth_code, partner_key FROM store WHERE store_id <> 2 AND " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id");

$t_admin_id = isset($_Session->admin_id)?$_Session->admin_id:null;

foreach($rs as $s) {

	echo $s['store_name'];
	echo '<br><br>';
    $brand_id = $category_id = null;
    $num_brands = $num_products = 0;
    $params = array();
    // remove previous sales price
	$day_before_prev = ($prev_weekday_id == 1)?7:$prev_weekday_id-1;
    //$rd = getRs($prev_sql, array($prev_weekday_id, $weekday_id, $s['store_id']));
		$update = true;

	
            
            $json = fetchApi('products/6904e8007dfb633262e7a3eb', $s['api_url'], $s['auth_code'], $s['partner_key']);
    
            $p_json = json_decode($json);
			$a_json = json_decode($json);
			echo 'test';
			echo $a_json;
			echo '<br><br>';
            if (isset($a_json->priceBreaks[0]->price) and isset($a_json->priceBreaks[0]->salePrice)) {
				echo 'Step 1....<br>';
                if (isset($a_json->tags) and is_array($a_json->tags)) {
                    $_di = array_search('dailydeal', array_map('strtolower', $a_json->tags));
                	if ($_di !== false) {
						unset($a_json->tags[$_di]);
						$a_json->tags = array_values($a_json->tags);
                	}
					
					if (!in_array('discounteligible', array_map('strtolower', $a_json->tags))) {
                        array_push($a_json->tags, 'DiscountEligible');
                    }
					
					$_di = array_search('clearance', array_map('strtolower', $a_json->tags));
					if ($_di !== false) {
						echo 'Clerance Found!!<br>';
						$update = false;
					}
					
					$_di = array_search('nodailydeal', array_map('strtolower', $a_json->tags));
                	if ($_di !== false) {
                    	$update = false;
                	}
					
                }
            }
        
		echo '<li>' . memory_get_usage();
    

    $brand_id = $category_id = null;
    // set new sales price
	echo '</ul>';
    $success = true;
    $response = $num_products . ' product' . iif($num_products != 1, 's') . ' updated';
}

echo '<hr />';
echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();

?>