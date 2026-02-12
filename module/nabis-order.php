<?php
$footer = '<script>
$(document).ready(function(e) {
    initProducts();

    bindForm(\'nabis-import\');

    $(\'.btn-unlink\').on(\'click\', function(e) {
        var id = $(this).data(\'n\');
        Swal.fire({
            title: \'Are you sure?\',
            html: \'The product will be immediately and permanently unmapped\',
            icon: \'question\',
            showCancelButton: true,
            confirmButtonColor: \'#3085d6\',
            cancelButtonColor: \'#d33\',
            confirmButtonText: \'Yes, continue\'
          }).then((result) => {
            if (result.value) {
                postAjax(\'nabis-unlink\', {n: id}, \'status\', function(data) {
                    $(\'#product_add_\' + id).removeClass(\'hide\');
                    $(\'#product_custom_\' + id).addClass(\'hide\');
                    $(\'#product_unlink_\' + id).addClass(\'hide\');
                    $(\'#product_display_\' + id).addClass(\'hide\');
                    $(\'#product_link_\' + id).removeClass(\'hide\');
                    $(\'#product_id_\' + id + \'_price\').val(\'\');
                    $(\'#product_id_\' + id + \'_subtotal\').html(\'\');
                    $(\'#product_display_\' + id).html(\'\');
                    $(\'#product_custom_\' + id).html(\'\');
                    $(\'.mapping-status\').html(data.mapping);
                    $(\'.nabis-foot\').html(data.foot);
                });
            }
          });
    });

    $(\'.btn-nabis-po\').on(\'click\', function(e) {
        var btn = $(this);
        var id = $(this).data(\'n\');
        Swal.fire({
            title: \'Ready to generate PO?\',
            html: \'The PO for this order will be generated and moved to sent status. This order will be removed from the Nabis order list.\',
            icon: \'question\',
            showCancelButton: true,
            confirmButtonColor: \'#3085d6\',
            cancelButtonColor: \'#d33\',
            confirmButtonText: \'Yes, continue\'
          }).then((result) => {
            if (result.value) {
                btn.hide();
                postAjax(\'nabis-po\', {id: id}, \'status_nabis-po\', function(data) {
                }, function(data) {
                    btn.show();
                });
            }
          });
    });
});

function nabis(data) {
    closeDialogs();
    var id = data.id;
    $(\'#product_id_\' + id + \'_price\').val(data.price);
    $(\'#product_id_\' + id + \'_subtotal\').html(data.subtotal);
    $(\'#product_display_\' + id).html(\'\');
    $(\'#product_custom_\' + id).html(data.product);
    $(\'#product_id_\' + id).val(0);
    initProducts();

    
    $(\'#product_add_\' + id).addClass(\'hide\');
    $(\'#product_custom_\' + id).removeClass(\'hide\');
    $(\'#product_unlink_\' + id).removeClass(\'hide\');
    $(\'#product_display_\' + id).addClass(\'hide\');
    $(\'#product_link_\' + id).addClass(\'hide\');

    $(\'.mapping-status\').html(data.mapping);
    $(\'.nabis-foot\').html(data.foot);
    initAssets();
}

function initProducts() {
    $(\'.price\').on(\'change\', function(e) {
        var n = $(this).data(\'n\');
        postAjax(\'nabis-product-price\', {n:n, price: $(this).val()}, \'status\', function(data) {
          $(\'#product_id_\' + n + \'_price\').val(data.price);
          $(\'#product_id_\' + n + \'_subtotal\').html(data.subtotal);
          $(\'#product_display_\' + n).html(data.product);
          $(\'.nabis-foot\').html(data.foot);
        });
    });
    
    $(\'.product_id\').each(function(e) {
        var id = $(this).attr(\'id\');
        var n = $(this).data(\'n\');
        if ($(\'#\' + id).hasClass(\'select2-hidden-accessible\')) {
          $(\'#\' + id).select2(\'destroy\');
        }
        $(\'#\' + id).unbind(\'change\');
        $(\'#\' + id).select2({
          ajax: {
            dataType: \'json\', 
            url: \'/ajax/nabis-search\',
            data: function (params) {
              var query = {
                kw: params.term
              }
              // Query parameters will be ?search=[term]&type=public
              return query;
            },
            processResults: function (data) {
              return {
                results: data.results
              };
            },
            cache: true
          },
          placeholder: \'Search for product by name or sku\',
          minimumInputLength: 1,
          templateResult: formatRepo,
          templateSelection: formatRepoSelection
        });
        $(\'#\' + id).bind(\'change\', function(e) {
          postAjax(\'nabis-product-select\', {id: $(this).val(), n:n}, \'status\', function(data) {
            $(\'#\' + id + \'_price\').val(data.price);
            $(\'#\' + id + \'_subtotal\').html(data.subtotal);
            $(\'#product_display_\' + n).html(data.product);
            $(\'#\' + id).val(0);
            initProducts();

            
            $(\'#product_add_\' + n).addClass(\'hide\');
            $(\'#product_custom_\' + n).addClass(\'hide\');
            $(\'#product_unlink_\' + n).removeClass(\'hide\');
            $(\'#product_display_\' + n).removeClass(\'hide\');
            $(\'#product_link_\' + n).addClass(\'hide\');
            $(\'.mapping-status\').html(data.mapping);
            $(\'.nabis-foot\').html(data.foot);

          });
        });
      });
}
</script>';

require_once('inc/header.php');

$nabis_code = getVar('c');
$sheet_id = getVarNum('sheet_id', 0);
$title = getVar('title');
$subtitle = getVar('subtitle_' . $sheet_id);
$date_start = getVar('date_start');
$date_end = getVar('date_end');
$rows = $cols = array();

$_categories = array();
$series = array();

$_category_row = null;

if (isset($_POST['rows'])) {
    $rows = $_POST['rows'];
}
if (isset($_POST['cols'])) {
    $cols = $_POST['cols'];
}

if (!$nabis_code) {
echo '<form action="" id="f_nabis-import" method="post">
<div class="panel">
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-8">' . uploadWidget('nabis', 'filename') . '</div>
            <div class="col-sm-4 text-right">
                <div class="status mb-2" id="status_import" style="display:none;"></div>
                <div class="form-btns"><button type="submit" class="btn btn-large btn-primary"><i class="fa fa-arrow-right"></i> Upload File</button></div>
            </div>
        </div>
    </div>
</div>
</form>';
}
else {

    $rn = getRs("SELECT * FROM nabis WHERE nabis_code = ?", $nabis_code);
    if ($n = getRow($rn)) {
        $nabis_id = $n['nabis_id'];
		$subtotal = $n['subtotal'];
		$discount = $n['discount'];
		$total = $n['total'];

		$po_subtotal = $n['po_subtotal'];
		$po_discount = $n['po_discount'];
		$po_total = $n['po_total'];
        echo '
        
        
        <div class="panel">
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">Description: <b>' . $n['orderName'] . '</b></div>
                    <div class="col-sm-3">Order #: <b>' . $n['orderNumber'] . '</b></div>
                    <div class="col-sm-3">Date: <b>' . $n['orderCreationDate'] . '</b></div>
                </div>
            </div>
        </div>
        ';


    //'brandDoingBusinessAs', 'orderNumber', 'orderName', 'daysTillPaymentDue', 'orderCreationDate', 'deliveryDate', 'gmv', 'orderDiscount', 'creatorEmail', 'status', 'paymentStatus', 'batchCode', 'manufacturerLicenseNumber', 'manufacturerLegalEntityName', 'lineItemSkuCode', 'lineItemSkuName', 'lineItemDiscount', 'unit', 'quantity', 'pricePerUnit', 'standardPricePerUnit', 'sampleType', 'skuBatchId', 'organization', 'overrideQuantityPerUnitOfMeasure', 'lineItemManifestNotes', 'destinationSkuBatchId', 'additionalDiscount', 'promotionsDiscount'
		$recalc = false;
        $rs = getRs("SELECT p.*, p.product_id AS po_product_id, t.product_id, t.sku, t.name, t.unitPrice, t.brand_id as tbrand_id, t.category_id as tcategory_id, t.flowertype as tflower_type FROM {$_Session->db}.product t RIGHT JOIN (nabis_product p INNER JOIN nabis n ON n.nabis_id = p.nabis_id) ON t.nabis_code = p.lineItemSkuCode WHERE n.nabis_id = ? AND " . is_active('n,p') . " ORDER BY CASE WHEN t.product_id OR p.product_name THEN 1 ELSE 0 END, p.nabis_product_id", $nabis_id);
        $tbl = '<table class="table table-striped table-bordered">
        <thead><tr><th>ID</th><th>Brand</th><th>Product Name</th><th>Quantity Ordered</th><th>Unit Price</th><th>Sub Total</th><th colspan="2">Product</th><th>PO Unit Price</th><th>PO Subtotal</th></tr></thead><tbody>';
        $linked = $unlinked = 0;
        foreach($rs as $r) {
            $tbl .= '<tr><td>' . $r['nabis_product_id'] . '</td><td>' . $r['brandDoingBusinessAs'] . '</td><td>' . $r['lineItemSkuName'] . '</td><td>' . $r['quantity'] . '</td><td>' . currency_format($r['pricePerUnit']) . '</td><td>' . currency_format($r['quantity'] * $r['pricePerUnit']) . '</td><td style="width:37%">';
            $tprice = null;
            if ($r['product_id']) {
                $linked++;
				$rpp = getRs("SELECT COALESCE(p.po_cogs,paid, cost, price) as price FROM po_product pp INNER JOIN po ON po.po_id = pp.po_id INNER JOIN {$_Session->db}.product p ON p.product_id = pp.product_id WHERE pp.order_qty > 0 AND po.store_id = {$_Session->store_id} AND " . is_active('pp,po') . " AND " . is_enabled('pp,po') . " AND pp.product_id = {$r['product_id']} ORDER BY po_product_id DESC LIMIT 1");
                if ($trpp = getRow($rpp)) { 
					$tprice = $trpp['price'];
					$r['price'] = $trpp['price'];
					$recalc = true;
				}
				if (!$r['po_product_id']) dbUpdate('nabis_product', array('product_id' => $r['product_id'], 'brand_id' => $r['tbrand_id'], 'category_id' => $r['tcategory_id'], 'flower_type' => $r['tflower_type'], 'product_name' => $r['name'], 'price' => $tprice), $r['nabis_product_id'], );
				//
				//$this->NabisDiscount($n['nabis_id']);
            }
            else if ($r['product_name']) {
                $linked++;
            }
            else {
                $unlinked++;
            }
			
			if ($recalc) {
				$_summary = $_PO->NabisSummary($nabis_id);
				$rnn = getRs("SELECT * FROM nabis WHERE nabis_code = ?", $nabis_code);
				if ($nn= getRow($rnn)) {
					$po_subtotal = $nn['po_subtotal'];
					$po_discount = $nn['po_discount'];
					$po_total = $nn['po_total'];
				}
			}	
            $tbl .= '<div id="product_link_' . $r['nabis_product_id'] . '"' . iif($r['product_id'] || $r['product_name'], ' class="hide"') . '><select data-n="' . $r['nabis_product_id'] . '" id="product_id_' . $r['nabis_product_id'] . '" name="product_id" class="product_id w-100"></select></div>
            <div id="product_display_' . $r['nabis_product_id'] . '"' . iif(!$r['product_id'], ' class="hide"') . '>' . $r['sku'] . ' - ' . $r['name'] . '</div>
            <div id="product_custom_' . $r['nabis_product_id'] . '"' . iif(!$r['product_name'], ' class="hide"') . '>' . $r['product_name'] . ' - ' .  getDisplayName('category', $r['category_id'], 'name', 'category_id', false, $_Session->db . '.') . ', ' .  getDisplayName('brand', $r['brand_id'], 'name', 'brand_id', false, $_Session->db . '.') . ', ' . $r['flower_type'] . ' <a href="" class="btn-dialog" data-url="nabis-custom-product" data-a="' . $r['nabis_product_id'] . '"><i class="fa fa-pen"></i></a></div>';
            
            
            $tbl .= '</td><td style="width:1%">
            <div id="product_unlink_' . $r['nabis_product_id'] . '"' . iif(!($r['product_id'] || $r['product_name']), ' class="hide"') . '><button type="button" data-n="' . $r['nabis_product_id'] . '" class="btn btn-xs btn-warning btn-unlink" data-toggle="tooltip" data-placement="bottom" data-title="Unmap this product"><i class="fa fa-unlink"></i></button></div>
            <div id="product_add_' . $r['nabis_product_id'] . '"' . iif($r['product_id'] || $r['product_name'], ' class="hide"') . '><button type="button" class="btn btn-primary btn-xs btn-dialog" data-url="nabis-custom-product" data-a="' . $r['nabis_product_id'] . '" data-toggle="tooltip" data-placement="bottom" data-title="Add custom product"><i class="fa fa-cog"></i></button></div>
            </td><td style="width:10%"><div class="input-group"><div class="input-group-prepend"><div class="input-group-text">$</div></div><input id="product_id_' . $r['nabis_product_id'] . '_price" type="text" data-n="' . $r['nabis_product_id'] . '" class="form-control price" placeholder="" value="' . (($r['price'])?number_format($r['price'], 2):null) . '" /></div></td><td><span id="product_id_' . $r['nabis_product_id'] . '_subtotal">' . currency_format($r['quantity'] * $r['price']) . '</span></td></tr>';
        }

        $tbl .= '</tbody><tfoot class="nabis-foot">
        <tr><th colspan="5">Subtotal</th><th>' . currency_format($subtotal) . '</th><th colspan="3"></th><th>' . currency_format($po_subtotal) . '</th></tr>
        <tr><th colspan="5">Discount</th><th>' . currency_format($discount) . '</th><th colspan="3"></th><th>' . currency_format($po_discount) . '</th></tr>
        <tr><th colspan="5">Total</th><th>' . currency_format($total) . '</th><th colspan="3"></th><th>' . currency_format($po_total) . '</th></tr>
        </tfoot></table>';

        
        echo '<form id="f_nabis-po" method="post">
        <div class="panel panel-inverse">
        <div class="panel-heading">
        <div class="panel-heading-btn">
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
        </div>
        <h4 class="panel-title mapping-status">' . 
        iif($unlinked, '<i class="fa fa-exclamation-triangle text-danger"></i> ' . $unlinked . ' product' . iif($unlinked != 1, 's') . ' not yet mapped. ') . 
        iif($linked and sizeof($rs) > $linked, '<i class="fa fa-check-circle"></i> ' . $linked . ' product' . iif($linked != 1, 's') . ' already mapped. ') . 
        iif($linked == sizeof($rs), '<i class="fa fa-check-circle text-success"></i> All products mapped. ') . 
        '</h4>
        </div>
        
        <div class="panel-body p-0">' . $tbl . '</div>

        <div class="panel-footer">
            <div class="row">
                <div class="col-md-6"><div class="status" id="status_nabis-po"></div></div>
                <div class="col-md-6 text-right form-btns"><button type="button" class="btn btn-primary btn-nabis-po" data-n="' . $nabis_id . '">Generate PO</button></div>
            </div>
        </div>
        </div>
        </form>';
    }
}
require_once('inc/footer.php');
?>