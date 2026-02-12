<?php
require_once ('../_config.php');


$rs = getRs("SELECT * FROM transfer_product");
foreach($rs as $r) {
    $success = false;
    $response = null;
    $a_json = json_decode($r['api_response'], true);
    if (isset($a_json['transferNo'])) {
        $success = true;
        $response = 'API call completed successfully';
    }
    else {
        $response = isset($a_json['message'])?$a_json['message']:'API called failed with an unspecified message';
    }

    echo '<li>' . $r['transfer_product_id']. ' > ' . $success . ': ' . $response . '</li>';
    dbUpdate('transfer_product', array('response' => $response, 'api_success' => ($success)?1:0), $r['transfer_product_id']);
}

echo 'Done';
					
?>