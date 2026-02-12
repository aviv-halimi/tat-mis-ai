<?php
require_once('../_config.php');
$po_code = getVar('c');
$is_tax  = 1;
$rb = getRs("SELECT name FROM {$_Session->db}.brand WHERE is_enabled = 1 and is_active = 1 ORDER BY name");
$brands = array();
foreach($rb as $b) {
    array_push($brands, $b['name']);
}
$rc = getRs("SELECT name FROM {$_Session->db}.category WHERE is_enabled = 1 and is_active = 1 ORDER BY name");
$categories = array();
foreach($rc as $c) {
    array_push($categories, $c['name']);
}
$rf = $_Session->GetSetting('flower-type');
$_rf = explode(PHP_EOL, $rf);
$flower_types = array();
foreach($_rf as $_f) {
    array_push($flower_types, trim($_f));
}

?>
<script>
$('.po-multiple-brand').on('click', function(e){
    e.preventDefault();
    var product_name;
    var brand_name;
    var _brand_name;
    $('.po-multiple-products tr').each(function() {
		brand_name = $(this).find('.brand-name').val();
		product_name = $(this).find('.product-name').val();
        if (brand_name) _brand_name = brand_name;
        if (product_name && !brand_name) $(this).find('.brand-name').val(_brand_name);
    });
});
$('.po-multiple-category').on('click', function(e){
    e.preventDefault();
    var product_name;
    var category_name;
    var _category_name;
    $('.po-multiple-products tr').each(function() {
        category_name = $(this).find('.category-name').val();
		product_name = $(this).find('.product-name').val();
        if (category_name) _category_name = category_name;
        if (product_name && !category_name) $(this).find('.category-name').val(_category_name);
    });
});
$('.po-multiple-flower').on('click', function(e){
    e.preventDefault();
    var product_name;
    var flower_type_name;
    var _flower_type_name;
    $('.po-multiple-products tr').each(function() {
        flower_type_name = $(this).find('.flower-type-name').val();
		product_name = $(this).find('.product-name').val();
        if (flower_type_name) _flower_type_name = flower_type_name;
        if (product_name && !flower_type_name) $(this).find('.flower-type-name').val(_flower_type_name);
    });
});
$('.po-multiple-qty').on('click', function(e){
    e.preventDefault();
    var product_name;
    var qty;
    var _qty;
    $('.po-multiple-products tr').each(function() {
		qty = $(this).find('.qty').val();
		product_name = $(this).find('.product-name').val();
        if (qty) _qty = qty;
		if (product_name && !qty) $(this).find('.qty').val(_qty);
    });
});
$('.po-multiple-price').on('click', function(e){
    e.preventDefault();
    var product_name;
    var price;
    var _price;
    $('.po-multiple-products tr').each(function() {
		price = $(this).find('.price').val();
		product_name = $(this).find('.product-name').val();
        if (price) _price = price;
		if (product_name && !price) $(this).find('.price').val(_price);
    });
});
$(document).ready(function(e) {
  var categoryList = ["<?php echo implode('","', $categories); ?>"];
  var brandList = ["<?php echo implode('","', $brands); ?>"];
  var flowerTypeList = ["<?php echo implode('","', $flower_types); ?>"];
  $( "#f_po-multiple-products .category-name" ).autocomplete({
    source: categoryList,
    appendTo: "#modal"
  });
  $( "#f_po-multiple-products .brand-name" ).autocomplete({
    source: brandList,
    appendTo: "#modal"
  });
  $( "#f_po-multiple-products .flower-type-name" ).autocomplete({
    source: flowerTypeList,
    appendTo: "#modal"
  });
  $( "#f_po-multiple-products .category-name" ).on('focus', function(e) { $('.suggestions-holder').hide(); });
  $( "#f_po-multiple-products .brand-name" ).on('focus', function(e) { $('.suggestions-holder').hide(); });
  $( "#f_po-multiple-products .flower-type-name" ).on('focus', function(e) { $('.suggestions-holder').hide(); });
  $('.product-name').on('click', function(e) {
        e.stopPropagation();
    });

    var ajaxProductSearch = null;
    $('.product-name').on('keyup', function(e) {
        var $pn = $(this);
        var $td = $(this).parent();
        abortAjax(ajaxProductSearch);
        var data = {};
        data['kw'] = $(this).val();
        data['_r'] = Math.random();
        ajaxProductSearch = $.ajax({
            url: '/ajax/product-kw',
            dataType: 'json',
            type: 'POST',
            data: data,
            success: function(d, textStatus, XMLHttpRequest) {
                var data = d.ret;
                $td.find('.suggestions ul').html(data);
                if (data.length) {
                    $td.find('.suggestions-holder').slideDown(100);
                }
                else {
                    $td.find('.suggestions-holder').hide();
                }
                $td.find('.suggestions ul a.link').on('click', function(e) {
                    e.preventDefault();
                    $pn.val( $(this).text() );
                    $('.suggestions-holder').hide();
                });
                $td.find('.suggestions ul .closer').on('click', function(e) {
                    e.preventDefault();
                    $('.suggestions-holder').hide();
                });
            }
        });
        /*
        ajaxProductSearch = $.post('/ajax/product-kw', {kw: $(this).val()}, function(d) {
            var data = d['ret'];
            $obj.find('.suggestions ul').html(data);
            console.log(data);
            if (data.length) {
                $obj.find('.suggestions-holder').slideDown(100);
            }
            else {
                $obj.find('.suggestions-holder').hide();
            }
            $obj.find('.suggestions ul a').on('click', function() {
                $obj.val() = $(this).text();
            });
        });*/
    });
});
</script>
<?php
$rs = getRs("SELECT po_id, vendor_id FROM po WHERE po_code = ?", array($po_code));
if ($r = getRow($rs)) {
    echo '
    <input type="hidden" name="po_code" value="' . $po_code . '" />
    <table class="table table-bordered po-multiple-products">
    <thead>
    <tr>
    <th>Product SKU</th>
    <th><a href="" class="po-multiple-category">Category</a></th>
    <th><a href="" class="po-multiple-brand">Brand</a></th>
    <th><a href="" class="po-multiple-flower">Flower Type</a></th>
    <th>Tax</th>
    <th><a href="" class="po-multiple-qty">Qty</a></th>
    <th><a href="" class="po-multiple-price">Price</a></th>
    </tr>
    </thead>
    <tbody>
    ';
    for($i = 0; $i<20; $i++) {
        echo '<tr>
        <td>
            <input type="text" size="100" class="form-control product-name" autocomplete="off" id="product_name_' . $i . '" name="product_name_' . $i . '" value="" />
            <div class="suggestions-holder"><div class="suggestions"><ul></ul></div></div>
        </td>
        <td><input type="text" class="form-control category-name" name="category_name_' . $i . '" value="" /></td>
        <td><input type="text" class="form-control brand-name" name="brand_name_' . $i . '" value="" /></td>
        <td><input type="text" class="form-control flower-type-name" name="flower_type_name_' . $i . '" value="" /></td>
        <td>
            <input type="checkbox" value="1" id="is_tax_' . $i . '" name="is_tax_' . $i . '" data-render="switchery" data-theme="primary" checked />
            <label for="is_tax_' . $i . '"><span class="m-l-5 m-r-10"></span></label></div>
        </td>
        <td><input type="text" class="form-control qty" name="qty_' . $i . '" value="" /></td>
        <td><input type="text" class="form-control price" name="price_' . $i . '" value="" /></td>
    </tr>';
    }
    echo '</tbody>
    </table>';
}
else {
    echo '<div class="alert alert-danger">PO not found</div>';
}
?>