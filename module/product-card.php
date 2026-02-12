<?php
include_once('_config.php');
include_once(INC_PATH . 'pdf-product-card.php');

if ($module_code == 'product-card-new') {
    $_Session->admin_settings['_ds-product-card'] = array();
    setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($_Session->admin_settings), $_Session->admin_id));
    redirectTo('/product-card');
    exit();
}

$product_card_file = 'product-card.pdf';
$_product_card_id = $_product_card_code = null;
$_hide_options = false;
$_ds = $_Session->GetTableDisplaySettings($module_code);
$_product_card_id = (isset($_ds['product_card_id']))?$_ds['product_card_id']:null;
$_product_ids = array();
$cards = array();
if ($_product_card_id) {
    $rs = getRs("SELECT * FROM product_card WHERE product_card_id = ?", array($_product_card_id));
    if ($r = getRow($rs)) {
        $product_card_file = $_product_card_id . '-' . $r['product_card_code'] . '.pdf';
        $_product_card_code = $r['product_card_code'];
        if (isJson($r['cards'])) $cards = json_decode($r['cards'], true);
        $_ds = json_decode($r['params'], true);
        $_product_ids = (isset($_ds['product_ids']))?$_ds['product_ids']:array();
    }
}
include_once('inc/header.php');
?>


<form role="form" class="ajax-form display-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="product-card" />
<input type="hidden" name="product_card_id" id="product_card_id" value="<?php echo $_product_card_id; ?>" />

<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body pb-0"<?php echo iif($_hide_options, ' style="display:none;"'); ?>>
		<div class="panel-option pt-1 pb-1 pl-4">Select Products</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12">
          <select name="product_ids[]" class="form-control multiple-select transfer-product" multiple="multiple" data-placeholder="Select Products ...">
            <?php
            //$rp = getRs("SELECT product_id, name, sku FROM {$_Session->db}.product WHERE " . is_active() . " AND active = '1' AND deleted = '' ORDER BY TRIM(name), TRIM(sku)");
			      $rp = getRs("SELECT p.product_id, p.name, p.sku FROM {$_Session->db}.product p INNER JOIN {$_Session->db}.category c ON c.category_id = p.category_id WHERE " . is_active('p') . " AND p.active = '1' AND p.deleted = '' AND c.name LIKE '%flower%' ORDER BY TRIM(p.name), TRIM(p.sku)");

            foreach($rp as $p) {
                echo '<option value="' . $p['product_id'] . '"' . iif(in_array($p['product_id'], $_product_ids), ' selected') . '>' . $p['name'] . ' (' . $p['sku'] . ')</option>';
            }
            ?>
            </select>
          </div>
        </div>

        <?php
        if (sizeof($cards)) {
        echo '
		<div class="panel-option mt-3 pt-1 pb-1 pl-4">Select Batches / Display Options</div>
        <div class="row form-input-flat mb-2">';
        foreach($cards as $card) {
            //$_Fulfillment->UpdateInventory($product_id, $p['id']);
            $rb = getRs("SELECT l.product_batch_location_name, b.qty, b.batchId, b.batchPurchaseDate FROM {$_Session->db}.product_batch_location l INNER JOIN {$_Session->db}.product_batch b ON b.product_batch_location_id = l.product_batch_location_id WHERE " . is_enabled('l') . " AND b.qty <> 0 AND b.product_id = ?", array($card['product_id']));
        echo '
          <div class="col-sm-6 mb-2" style="border:2px solid #000;padding:10px;">
            <div><b>' . $card['original_name'] . '</b></div><select name="batch_' . $card['product_id'] . '" class="from-control select2" style="width:100%;">';
            $showDL = 0;
            foreach($rb as $b) {
                echo '<option value="' . $b['batchId'] . '"' . iif($card['batchId'] == $b['batchId'], ' selected') . '>' . $b['product_batch_location_name'] . ': ' . number_format($b['qty']) . ' ' . iif($b['batchPurchaseDate'], ' (' . date('j/n/Y g:i a', $b['batchPurchaseDate'] / 1000) . ')') . '</option>';
				if (strlen($card['batchId'])) {
					$showDL = 1;	
				}
              }
            echo '</select>
            <div class="row mt-2">
                <div class="col-sm-4">            
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">Name Font Size</div>
                            <select name="name_size_' . $card['product_id'] . '" class="form-control select2">';
                            for($f = 10; $f<=22; $f++) {
                                echo '<option value="' . $f . '"' . iif($card['name_size'] == $f, ' selected') . '>' . $f . '</option>';
                            }
                            echo '</select>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">            
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">Strains Font Size</div>
                            <select name="strains_size_' . $card['product_id'] . '" class="form-control select2">';
                            for($f = 10; $f<=22; $f++) {
                                echo '<option value="' . $f . '"' . iif($card['strains_size'] == $f, ' selected') . '>' . $f . '</option>';
                            }
                            echo '</select>
                        </div>
                    </div>
                </div>
				<div class="col-sm-4">            
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">Show Strikethrough?</div>
                            <select name="show_st_' . $card['product_id'] . '" class="form-control select2">';
                            
                                echo '<option value="0"'  . iif($card['show_st'] == 0, ' selected') . '> No </option>';
								echo '<option value="1"'  . iif($card['show_st'] == 1, ' selected') . '> Yes </option>';
                            
                            echo '</select>
                        </div>
                    </div>
                </div>
            </div>
			<div class="row mt-2">
				<div class="col-md">Custom Text<input name="custom_text_' . $card['product_id'] . '" value = "' . $card['custom_text'] . '" class="form-control"></div>
			</div>
          </div>';
        }
             
        echo '</div>';
        }
        ?>



        <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
          <hr class="m-0" />
          <div class="p-10">
            <div class="row">
              <div class="col-sm-6">
                <span id="status_table_display" class="status"></span>
              </div>
              <div class="col-sm-6 text-right">
                <?php if ($_product_card_id) {
                  echo '<button type="submit" class="btn btn-warning mt-0">STEP 2:  Select Batch/Options</button>';
                }
                else {                  
                  echo '<button type="submit" class="btn btn-primary mt-0">STEP 1:  Select Products</button>';
                }
                ?>
              </div>
            </div>
          </div>
        </div>


        </div>

      </div>
</form>


<?php
if ($_product_card_id) {
    if ($r = getRow($rs)) {
        //print_r($r['cards']);
        generatePC($_product_card_id, MEDIA_PATH . '/product_card/' . $product_card_file);
		if ($showDL) {
			echo '<a href="/product-card-download/' . $_product_card_code . '" target="_blank" class="btn btn-lg btn-primary">STEP 3:  Download File</a>';
		}
    }
// echo '<iframe width="100%" height="900" src="/media/po/5fd7a3c197f5e.pdf" type="application/pdf"></iframe>';
}
include_once('inc/footer.php'); 
?>