<?php
$footer = '<script language="javascript" type="text/javascript">
$(document).ready(function(e) {
  $("#date_dd").on("blur", function(e) {
    postAjax("daily-discounts-date", {d: $(this).val()}, "status");
  });
  $(".btn-dd").on("click", function(e) {
    postAjax("daily-discounts-date", {}, "status");
  });
  $(".btn-dd-all").on("click", function(e) {
    postAjax("daily-discounts-date", {d: "all"}, "status");
  });
  $(".daily-discount").on("click", function(e) {
    var id = $(this).data("id");
    postAjax("daily-discount-enable", {id: id, is_enabled: $("#daily_discount_" + id).prop("checked")?1:0}, "status");
  });
  $(".daily-discount-sort").on("click", function(e) {
    e.preventDefault();
    var sort = $(this).data("sort");
    postAjax("daily-discount-sort", {sort: sort}, "status");
  });
  $(".btn-daily-discount-del").on("click", function(e) {
    var $obj = $(this);
    e.preventDefault();
    var id = $(this).data("id");
    
    Swal.fire({
      title: \'Are you sure?\',
      html: \'Daily discount will be permanently removed.\',
      icon: \'question\',
      showCancelButton: true,
      confirmButtonColor: \'#3085d6\',
      cancelButtonColor: \'#d33\',
      confirmButtonText: \'Yes, continue\'
    }).then((result) => {
      if (result.value) {
        postAjax("daily-discount-del", {id: id}, "status", function(data) {
          $obj.closest(\'tr\').slideUp(500);
        }, function(data) {
          Swal.fire({
              icon: \'error\',
              title: \'Not so fast ...\',
              text: data.response
            });
        });
      }
    });
  });
});
</script>';
include_once('inc/header.php');

$rw = getRs("SELECT * FROM weekday WHERE " . is_enabled() . " ORDER BY weekday_id");

$sql_sort = '';

$store_sort = $brand_sort = $category_sort = $product_sort = $discount_sort = $date_sort = false;

$_sort = isset($_SESSION['daily_discount_sort'])?$_SESSION['daily_discount_sort']:null;

if ($_sort == 'category') {
  $sql_sort = ' ORDER BY c.name, b.name, d.rebate_wholesale_discount';
  $category_sort = true;
}
else if ($_sort == 'discount') {
  $sql_sort = ' ORDER BY d.discount_rate, c.name, b.name';
  $discount_sort = true;
}
else if ($_sort == 'store') {
  $sql_sort = ' ORDER BY d.store_ids, c.name, b.name, d.rebate_wholesale_discount';
  $store_sort = true;
}
else if ($_sort == 'product') {
  $sql_sort = ' ORDER BY num_products, c.name, b.name, d.rebate_wholesale_discount';
  $product_sort = true;
}
else if ($_sort == 'dates') {
  $sql_sort = ' ORDER BY d.date_start, num_products, c.name, b.name, d.rebate_wholesale_discount';
  $date_sort = true;
}
else {
  $sql_sort = ' ORDER BY b.name, c.name, d.rebate_wholesale_discount';
  $brand_sort = true;
}

$date_dd = $_Session->GetAdminSettings('date_dd');
if ($date_dd != 'all' and !isDate($date_dd)) $date_dd = null;

if (!$date_dd) {
  $_today = '<button class="btn btn-success"><i class="fa fa-check-circle mr-1"></i> Today</button>';
  $_all = '<button class="btn btn-default btn-dd-all">All dates</button>';
  $_pick_date = '<div class="input-group-text">Pick date</div>';
  $date_dd_sql = date('Y-n-j');
}
else if ($date_dd == 'all') {
  $_today = '<button class="btn btn-default btn-dd">Today</button>';
  $_all = '<button class="btn btn-success"><i class="fa fa-check-circle mr-1"></i> All dates</button>';
  $_pick_date = '<div class="input-group-text">Pick date</div>';
  $date_dd_sql = null;
}
else {
  $_today = '<button class="btn btn-default btn-dd">Today</button>';
  $_all = '<button class="btn btn-default btn-dd-all">All dates</button>';
  $_pick_date = '<div class="input-group-text bg-success text-white"><i class="fa fa-check-circle mr-1"></i> Pick date</div>';
  $date_dd_sql = $date_dd;
}
echo '
<div class="panel panel-default">
  <div class="panel-body">
    <div class="row">
      <div class="col-md-3 text-right input-label mt-2">Show daily discounts for</div>
      <div class="col-md-3">' . $_today . '</div>
      <div class="col-md-3">' . $_all . '</div>
      <div class="col-md-3">  
        <div class="input-group date datepicker p-0" data-date-format="dd/mm/yyyy">
          <div class="input-group-prepend">
            ' . $_pick_date . '
          </div>
          <input type="text" class="form-control" placeholder="dd/mm/yyyy" id="date_dd"  name="date_dd" value="' . toHumanDT($date_dd) . '" />
          <div class="input-group-addon">
            <i class="fa fa-calendar"></i>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<div class="row">';
foreach($rw as $w) {
  $_p = array($w['weekday_id']);
  if ($date_dd_sql) {
    $_p = array($w['weekday_id'], $date_dd_sql, $date_dd_sql);
  }
  $rs = getRs("SELECT d.store_ids, d.daily_discount_id, d.discount_rate, d.rebate_wholesale_discount, d.is_enabled, d.date_start, d.date_end, COALESCE(b.name, 'ALL') AS brand_name, c.category_id, COALESCE(c.name, 'ALL') AS category_name, d.category_ids, COUNT(p.product_id) AS num_products, CASE WHEN is_specialty = 1 THEN 'S' ELSE 'DD' END as type 
  	FROM blaze1.product p 
		RIGHT JOIN (blaze1.category c 
		RIGHT JOIN (blaze1.brand b RIGHT JOIN daily_discount d ON d.brand_id = b.brand_id) ON JSON_CONTAINS(d.category_ids, CAST(c.category_id AS CHAR), '$')) ON (b.brand_id = p.brand_id OR d.brand_id IS NULL) AND (c.category_id = p.category_id OR d.category_ids IS NULL) WHERE " . is_active('d') . " AND JSON_CONTAINS(d.weekday_ids, CAST(? AS CHAR), '$') " . iif($date_dd_sql, "AND ((d.date_start <= ? OR d.date_start IS NULL) AND (d.date_end >= ? OR d.date_end IS NULL))") . " GROUP BY d.store_ids, d.daily_discount_id, d.rebate_wholesale_discount, d.is_enabled, d.date_start, d.date_end, b.name" . $sql_sort, $_p);
  echo '
  <div class="col-md-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="m-0">' . $w['name'] . '</h4>
        </div>
        <div class="panel-body' . iif(sizeof($rs), ' p-0') . '">';
        if (sizeof($rs)) {
          echo '<table class="table m-0">
          <thead><tr>
          <th class="pl-4" style="width:25%"><a href="" class="text-dark daily-discount-sort" data-sort="store">Store</a>' . iif($store_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th class="pl-4" style="width:5%"><a href="" class="text-dark daily-discount-sort" data-sort="dates">Dates</a>' . iif($date_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th class="pl-4" style="width:15%"><a href="" class="text-dark daily-discount-sort" data-sort="brand">Brand</a>' . iif($brand_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th class="pl-4" style="width:5%"><a href="" class="text-dark daily-discount-sort" data-sort="category">Category</a>' . iif($category_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th class="text-right pl-4"><a href="" class="text-dark daily-discount-sort" data-sort="product"># Products</a>' . iif($product_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
		  <th class="text-right pl-4"><a href="" class="text-dark daily-discount-sort" data-sort="product">Type</a>' . iif($product_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th class="text-right pl-4" style="width:10%"><a href="" class="text-dark daily-discount-sort" data-sort="discount">Discount</a>' . iif($discount_sort, ' <i class="fa fa-arrow-down"></i>') . '</th>
          <th></th></tr></thead><tbody>';
          foreach($rs as $r) {
            echo '<tr class="">
            <td class="pl-4" style="width:15%">' . iif($r['store_ids'], (strlen(getDisplayNames('store', $r['store_ids'])) > 20) ? substr(getDisplayNames('store', $r['store_ids']), 0, 20) . '...' : getDisplayNames('store', $r['store_ids']), 'ALL') . '</td>
            <td class="pl-4" style="width:5%">' . getShortDate($r['date_start']) . ' - ' . ($r['date_end']?getShortDate($r['date_end']):'(No End)') . '</td>
            <td class="pl-4" style="width:15%"><a href="" class="btn-table-dialog" data-a="' . $w['weekday_id'] . '" data-url="daily-discount" data-id="' . $r['daily_discount_id'] . '" data-title="Edit Daily Discount">' . $r['brand_name'] . '</a></td>
            <td style="width:5%"><a href="" class="btn-table-dialog" data-a="' . $w['weekday_id'] . '" data-url="daily-discount" data-id="' . $r['daily_discount_id'] . '" data-title="Edit Daily Discount">' . iif($r['category_ids'], (strlen(getDisplayNamesB1('category', $r['category_ids'],'name')) > 50) ? substr(getDisplayNamesB1('category', $r['category_ids'],'name'), 0, 50) . '...' : getDisplayNamesB1('category', $r['category_ids'],'name'), 'ALL') . '</a></td>
            <td style="text-align: center;" >' . number_format($r['num_products']) . '</td>
			<td style="text-align: center;" >' . $r['type'] . '</td>
            <td style="text-align: center; width:10%;">' . number_format($r['discount_rate']) . '%</td>
            <td nowrap class="text-right"><span class="daily-discount" data-id="' . $r['daily_discount_id'] . '" nowrap><input type="checkbox" value="1" id="daily_discount_' . $r['daily_discount_id'] . '" name="daily_discount_' . $r['daily_discount_id'] . '" data-render="switchery" data-theme="primary"' . iif($r['is_enabled'] == '1', ' checked') . ' />
						<label for="daily_discount_' . $r['daily_discount_id'] . '"></label></span> <a href="" data-id="' . $r['daily_discount_id'] . '" class="btn-daily-discount-del"><i class="fa fa-trash-alt text-danger"></i></a></td></tr>';
          }
          echo '</tbody></table>';
        }
        else {
          echo '<div class="alert alert-info m-0">No brand discounts set</div>';
        }

        echo '
        </div>
        <div class="panel-footer">
        <button class="btn btn-primary btn-table-dialog" data-url="daily-discount" data-a="' . $w['weekday_id'] . '" data-title="Add Daily Discount for ' . $w['name'] . '">Add Brand Discount</button>
        </div>
    </div>  
  </div>';
}
echo '</div>';
include_once('inc/footer.php'); 
?>