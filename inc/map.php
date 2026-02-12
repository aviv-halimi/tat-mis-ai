<?php
include_once('inc/header.php');

$module_option_code = getVar('c');

$_rdsa = $_Session->GetModuleOptions($module_code);
$_rds = $_Session->GetModuleOptions($module_code, $_Session->admin_id, 10);

$__title = $tbl['map'];
$__tbl = $tbl['tbl'];
$__alias = (!isset($tbl['alias']))?'i':$tbl['alias'];
$__color_by = $tbl['color'];
$__sql_select = $tbl['select'];
$__date = $tbl['date'];
$__where = (!isset($tbl['where']))?null:$tbl['where'];
$__fields = $tbl['fields'];

$a__date = explode('.', $__date);
if (sizeof($a__date) == 1) {
  $___date = "{$__alias}.{$__date}";
}
else {
  $___date = $__date;
}

$__sql_from = "{$__tbl} {$__alias}";

foreach(array_reverse($__fields) as $f) {
  $id = $f['name'] . '_id';
  $join = (!isset($f['join']))?'RIGHT':$f['join'];
  $ref = (!isset($f['ref']))?$__alias:$f['ref'];
  $__sql_from = "{$f['name']} {$f['tbl']} {$join} JOIN ({$__sql_from}) ON ";
  if (isset($f['on'])) {
    $__sql_from .= $f['on'];
  }
  else if (isset($f['type']) and $f['type'] == 'json') {
    $__sql_from .= "JSON_CONTAINS({$ref}.{$id}s, CAST({$f['tbl']}.{$id} AS CHAR), '$')";
  }
  else {
    $__sql_from .= "{$ref}.{$id} = {$f['tbl']}.{$id}";
  }
}

// check field ids
$i = 0;
foreach($__fields as $f) {
  $__fields[$i++]['id'] = $f['tbl'] . '_' . $f['name'];
}

$colors = array('#fd9c71', '#a07b1e', '#6e2f0a', '#0522eb', '#218149', '#5b13ab', '#5c0cf6', '#d35837', '#e60122', '#113628', '#2f7b23', '#b9d227', '#af3bb2', '#0f402d', '#824b2c', '#6b5467', '#c00602', '#cf8981', '#bd5f42', '#43b447');

function getColor($r, $color_by) {
  global $__fields, $colors;
  $i = -1;
  foreach($__fields as $f) {
    if ($f['id'] == $color_by) {
      $i = $r[$f['name'] . '_id'];
      break;
    }
  }
  if($i > -1 and sizeof($colors) >= $i) {
    return $colors[$i-1];
  }
  else {
    return '#000';
  }
}

if (!isset($__alias)) {
  $__alias = 'i';
}
$charts = array();
$legend = true;
$yaxis = array('min' => 0, 'title' => array('text' => '# ' . $__title));

$data = $a_data = $data_1 = $data_2 = $date_3 = $series = $categories = array();

$_ds = $_Session->GetTableDisplaySettings($module_code, $module_option_code);
$subtitle = '';

$date_start = (isset($_ds['date_start']))?$_ds['date_start']:null;
$date_end = (isset($_ds['date_end']))?$_ds['date_end']:null;
$limit = (isset($_ds['limit']))?$_ds['limit']:100;
$color_by = (isset($_ds['color_by']))?$_ds['color_by']:$__color_by;
$disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:array();

$sql_where = '';
$sql_params = array();

if ($date_start) {
  $sql_where = " AND {$__alias}.{$__date} >= ?";
  array_push($sql_params, toMySqlDT($date_start));
  $subtitle .= $date_start;
}
else {
  if (strlen($date_end)) $subtitle .= 'start';
}

if ($date_end) {
  $sql_where .= " AND {$__alias}.{$__date} <= ?";
  array_push($sql_params, toMySqlDT($date_end));
  $subtitle .= ' to ' . $date_end;
}
else {
  if (strlen($date_start)) $subtitle .= ' to present';
}

$title = 'All ';
$date_grouping = "";
$date_sorting = "";

$Where = "1=1";
if (isset($__where)) $Where .= " AND {$__where}";
$_title = '';
foreach($__fields as $f) {
  $v = (isset($_ds[$f['name'] . '_ids']) and is_array($_ds[$f['name'] . '_ids']))?$_ds[$f['name'] . '_ids']:array();
  if (sizeof($v)) {
    if (false) { //isset($f['type']) and $f['type'] == 'json') {
      $Where .= iif(strlen($Where), " AND ") . " (";
      $___d = 0;
      foreach($v as $__d) {
        $Where .= iif($___d++, " OR ");
        //$Where .= "JSON_SEARCH({$f['tbl']}.{$f['name']}_ids, 'one', {$__d})";
        $Where .= "JSON_CONTAINS(i.{$f['name']}_ids, '{$__d}', '$')";
      }
      $Where .= ")";
    }
    else {
      $_title .= iif(strlen($_title), ' and ') . getDisplayNames($f['name'], json_encode($v));
      $Where .= iif(strlen($Where), " AND") . " FIND_IN_SET({$f['tbl']}.{$f['name']}_id, '" . implode(',', $v) . "')";
    }
    //$title .= ' ' .getDisplayName('gpd_inmate_type', $gpd_inmate_type_id);
  }
}

$title .= $_title . ' ' . $__title;

$Order = "";
foreach($__fields as $f) {
  if(in_array($f['id'], $disaggregate_ids)) {
    $Order .= iif(strlen($Order), ", ") . "{$f['tbl']}.sort, {$f['tbl']}.{$f['name']}_id, {$f['tbl']}.{$f['name']}_name";
  }
}
if (strlen($Order)) {
  $Order = "ORDER BY " . iif(strlen($date_grouping), " {$date_grouping} ,") . "{$Order}";
}
else {
  if (strlen($date_grouping)) $Order = "ORDER BY {$date_grouping}";
}


$sql = "SELECT {$__sql_select} FROM {$__sql_from} WHERE {$Where} {$sql_where} {$Order} LIMIT {$limit}";

$rr = getRs($sql, $sql_params);

foreach($rr as $r) {
  array_push($markers,
    array(
      'id' => $r['id'],
      'color' => getColor($r, $color_by),
      'name' => $r['name'],
      'gps' => $r['gps'],
      'content' => str_replace("'", "\'", $r['content'])
    )
  );
}

?>
<form role="form" class="ajax-form display-options" id="f_table-display" method="post">
<input type="hidden" name="module_code" value="<?php echo $module_code; ?>" />

<?php
$_hide_options = false;
if (sizeof($_rdsa)) {
  echo '
  <div class="row mb-2">
  <div class="col-sm-12">
  ';
  if (strlen($module_option_code)) {
    $_rmc = getRs("SELECT module_option_name FROM module_option WHERE module_option_code = ?", array($module_option_code));
    if ($_mc = getRow($_rmc)) {
      echo '<h4>' . $_mc['module_option_name'] . '</h4>';
      $_hide_options = true;
    }
  }
  echo '
  </div>
  <div class="col-sm-12 text-right">
  <div class="btn-group btn-group-xs-1">
    <a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
      <i class="ion-ios-settings-outline"></i> Manage Indicators <b class="caret text-muted"></b>
    </a>
    <ul class="dropdown-menu dropdown-menu-right " role="menu">';
      foreach($_rds as $_d) {
        echo '<li><a href="" class="btn-table-display-load" data-c="' . $_d['module_option_code'] . '">' . shorten($_d['module_option_name'], 100) . '</a></li>';
      }
      echo '
      <li class="divider"></li>
      <li><a href="" class="btn-dialog" data-url="table-display" data-c="' . $module_code . '" data-hide-btns="true" title="View All Indicators">View All</a></li>
    </ul>
  </div>
  </div>
  </div>';
}
?>

<div class="panel panel-default display-options">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body">
        <div class="row form-input-flat mb-2">
          <div class="col-sm-2 date">
            <div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
              <input type="text" class="form-control" placeholder="Start date ..." name="date_start" value="<?php echo $date_start; ?>" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-2 date">
            <div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
              <input type="text" class="form-control" placeholder="End date ..." name="date_end" value="<?php echo $date_end; ?>" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                Limit markers to
                </div>
              </div>
              <select name="limit" class="form-control select2">
                <option value="50"<?php echo iif($limit == 50, ' selected'); ?>>50</option>
                <option value="100"<?php echo iif($limit == 100, ' selected'); ?>>100</option>
                <option value="200"<?php echo iif($limit == 200, ' selected'); ?>>200</option>
                <option value="500"<?php echo iif($limit == 500, ' selected'); ?>>500</option>
              </select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">
                  <i class="fa fa-paint-brush mr-1"></i> Color by
                </div>
              </div>
              <select class="form-control select2" id="color_by" name="color_by">
              <?php
              foreach($__fields as $f) {
                if (strlen($f['display'])) echo '<option value="' . $f['id'] . '"' . iif($f['id'] == $color_by, ' selected') . '>' . $f['display'] . '</option>';
              }
              ?>
              </select>
            </div>
          </div>
        </div>
			  <div class="panel-option mt-3 pt-1 pb-1 pl-4">Filters</div>
        <div class="row form-input-flat">
          <?php
          foreach($__fields as $f) {
            if ($f['display']) {
              $v = (isset($_ds[$f['id']]) and is_array($_ds[$f['id']]))?$_ds[$f['id']]:array();
              echo '
              <div class="col-sm-4 mb-3 div_' . $f['id'] . iif(!sizeof($v), ' hide') . '">
                <small>' . $f['display'] . '</small>
                <div>' . displayKeys($f['id'], $v, $f['name'], (isset($f['where']))?$f['where']:null) . '</div>
              </div>';
            }
          }
          ?>
        </div>
        <?php
        $_dd_filters = '';
        $_dd_filters2 = '';
        $_dd = 0;
        foreach($__fields as $f) {
          if ($f['display']) {
            $v = (isset($_ds[$f['id']]) and is_array($_ds[$f['id']]))?$_ds[$f['id']]:array();
            if ($_dd++ % 2 == 0 || sizeof($__fields) < 10) $_dd_filters .= '<li' . iif(sizeof($v), ' class="selected"') . '><a href="" data-f="' . $f['id'] . '">' . $f['display'] . '</a></li>';
            else $_dd_filters2 .= '<li' . iif(sizeof($v), ' class="selected"') . '><a href="" data-f="' . $f['id'] . '">' . $f['display'] . '</a></li>';
          }     
        }
        if (strlen($_dd_filters)) {
        echo '
        <div class="row form-input-flat">
          <div class="col-sm-6">
            <div class="dropdown">
              <a href="javascript:;" class="btn btn-default btn-sm" data-toggle="dropdown"><i class="fa fa-plus-circle"></i> Add Filter <b class="caret"></b></a>
              <div class="dropdown-menu analytics-filters">
                <div class="row">
                <div class="col-md-' . iif(strlen($_dd_filters2), '6', '12') . '">
                <ul>
                ' . $_dd_filters . '
                </ul>
                </div>' . iif(strlen($_dd_filters2), '
                <div class="col-md-6">
                <ul>
                ' . $_dd_filters2 . '
                </ul>
                </div>') . '
                </div>
              </div>
              </div>
          </div>
        </div>';
        }
        ?>
    </div>
    <div class="panel-footer">
      <div class="row">
        <div class="col-md-6">
          <div id="status_table_display" class="status"></div>
        </div>
        <div class="col-md-6 text-right"><button type="submit" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary btn-table-display-restore mt-0" data-c="<?php echo $module_code; ?>">Restore Default</button></div>
      </div>
    </div>
</div>
</form>


<div class="panel panel-default pagination-inverse clearfix">
<div class="panel-heading"><h4 class="panel-title"><?php echo $title; ?></h4></div>
<div class="panel-body"><div class="google-map" id="map"></div>
<div class="panel-footer">
<?php

$r_tbl = null;
$r_where = null;
foreach($__fields as $f) {
  if ($f['id'] == $color_by) {
    $r_tbl = $f['name'];
    if (isset($f['where'])) $r_where = $f['where'];
    break;
  }
}
if ($r_tbl) {
$rs = getRs("SELECT * FROM {$r_tbl} WHERE " . is_active() . iif($r_where, " AND " . $r_where));
foreach($rs as $r) {
  echo '<div style="display:inline-block; padding: 5px;"><span style="display:inline-block; width: 12px; height: 12px; margin-right: 5px; background: ' . ((sizeof($colors) >= $r[$r_tbl . '_id'])?$colors[$r[$r_tbl . '_id'] - 1]:'#000') . ';border:1px solid #000;border-radius: 50%;"></span>' . $r[$r_tbl . '_name'] . '</div>';
}
}
?>
</div>
</div>

<?php include_once('inc/footer.php'); ?>