<?php
include_once('_config.php');
$_ajax = getVarInt('_ajax');
$_chart_type = getVar('_chart_type');
$_chart_stacking = getVarInt('_chart_stacking');
$_chart_3d = getVarInt('_chart_3d');
if ($_chart_type) {
  $_Session->SaveChartSetting($module_code, $_chart_type, $_chart_stacking, $_chart_3d);
}

if (!$_ajax) {
  include_once('inc/header.php');
}

$module_option_code = getVar('c');

$_rdsa = $_Session->GetModuleOptions($module_code);
$_rds = $_Session->GetModuleOptions($module_code, $_Session->admin_id, 10);

$__title = $tbl['analytics'];
$__tbl = $tbl['tbl'];
$__alias = (!isset($tbl['alias']))?'i':$tbl['alias'];
$__date = $tbl['date'];
$__where = (!isset($tbl['where']))?null:$tbl['where'];
$__count = (!isset($tbl['count']))?null:$tbl['count'];
$__counts = (!isset($tbl['counts']))?null:$tbl['counts'];
$__fields = $tbl['fields'];
$__show_total = (!isset($tbl['show_total']))?true:$tbl['show_total'];
$__show_percent = (!isset($tbl['show_percent']))?true:$tbl['show_percent'];
$__unit = (!isset($tbl['unit']))?'# ' . $__title:$tbl['unit'];

$a__date = explode('.', $__date);
if (sizeof($a__date) == 1) {
  $___date = "{$__alias}.{$__date}";
}
else {
  $___date = $__date;
}

if (!$__count) {
  $__count = "COUNT(DISTINCT({$__alias}.{$__tbl}_id))";
}

if (!$__counts) {
  $__counts = array(
    array($__unit, $__count)
  );
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
  $__sql_from .= " AND " . is_enabled($f['tbl']);
}

// check field ids
$i = 0;
foreach($__fields as $f) {
  if (!isset($f['type'])) {
    $__fields[$i]['type'] = 'char';
  }
  $__fields[$i]['id'] = $f['tbl'] . '_' . $f['name'];
  $i++;
}

if (!isset($__alias)) {
  $__alias = 'i';
}

// start: get settings //
$_ds = $_Session->GetTableDisplaySettings($module_code, $module_option_code);
$subtitle = '';

$count_index = (isset($_ds['count_index']))?$_ds['count_index']:0;
$date_start = (isset($_ds['date_start']))?$_ds['date_start']:null;
$chart_type = (isset($_ds['chart_type']))?$_ds['chart_type']:'column';
$chart_stacking = (isset($_ds['chart_stacking']))?$_ds['chart_stacking']:null;
$chart_3d = (isset($_ds['chart_3d']))?$_ds['chart_3d']:null;
$date_end = (isset($_ds['date_end']))?$_ds['date_end']:null;
$timespan = (isset($_ds['timespan']))?$_ds['timespan']:null; // year
$disaggregate_ids = (isset($_ds['disaggregate_ids']))?$_ds['disaggregate_ids']:array();
// end: get settings //

if ($count_index < sizeof($__counts)) {
  $_counts = $__counts[$count_index];
  $__unit = $_counts[0];
  $__count = $_counts[1];
  $__title = $__unit;
}

$charts = array();
$legend = true;
$yaxis = array('min' => 0, 'title' => array('text' => $__unit));
$__subtotal = (!isset($tbl['subtotal']))?$__unit:$tbl['subtotal'];


$sql_where = " AND " . is_enabled($__alias);
$sql_params = array();

if ($date_start) {
  $sql_where .= " AND {$___date} >= ?";
  array_push($sql_params, toMySqlDT($date_start));
  $subtitle .= $date_start;
}
else {
  if (strlen($date_end)) $subtitle .= 'start';
}

if ($date_end) {
  $sql_where .= " AND {$___date} <= ?";
  array_push($sql_params, toMySqlDT($date_end));
  $subtitle .= ' to ' . $date_end;
}
else {
  if (strlen($date_start)) $subtitle .= ' to present';
}

$title = '';
$date_grouping = "";
$date_sorting = "";
$xtype = 'category';
$type = 'column';

if ($timespan == 'day') {
  $title = 'Daily ';
  $date_grouping = "DATE({$___date})";
  $date_sorting = "DATE({$___date}) DESC";
  $xtype = 'datetime';
  $type = 'spline';
}
else if ($timespan == 'week') {
  $title = 'Weekly ';
  $date_grouping = "CONCAT(YEAR({$___date}), '-', LPAD(WEEK({$___date}), 2, '0'))";
  $date_sorting = "YEAR({$___date}) DESC, WEEK({$___date}) DESC";
  $xtype = 'category';
  $type = 'column';
}
else if ($timespan == 'month') {
  $title = 'Monthly ';
  $date_grouping = "CONCAT(YEAR({$___date}), '-', LPAD(MONTH({$___date}), 2, '0'))";
  $date_sorting = "YEAR({$___date}) DESC, MONTH({$___date}) DESC";
  $xtype = 'category';
  $type = 'column';
}
else if ($timespan == 'quarter') {
  $title = 'Quarterly ';
  $date_grouping = "CONCAT(YEAR({$___date}), '-', QUARTER({$___date}))";
  $date_sorting = "YEAR({$___date}) DESC, QUARTER({$___date}) DESC";
  $xtype = 'category';
  $type = 'column';
}
else if ($timespan == 'year') {
  $days = 365;
  $title = 'Yearly ';
  $date_grouping = "YEAR({$___date})";
  $date_sorting = "YEAR({$___date})";
  $xtype = 'category';
  $type = 'column';
}
else {
  $timespan = 'Period';
}

$Where = "1=1";
if (isset($__where)) $Where .= " AND {$__where}";
$_title = '';
foreach($__fields as $f) {
  //$v = (isset($_ds[$f['name'] . '_ids']) and is_array($_ds[$f['name'] . '_ids']))?$_ds[$f['name'] . '_ids']:array();
  $v = (isset($_ds[$f['id']]) and is_array($_ds[$f['id']]))?$_ds[$f['id']]:array();
  if (sizeof($v)) {
    if (false) { //isset($f['type']) and $f['type'] == 'json') {
      $Where .= iif(strlen($Where), " AND ") . " (";
      $___d = 0;
      foreach($v as $__d) {
        $Where .= iif($___d++, " OR ");
        $Where .= "JSON_SEARCH({$f['tbl']}.{$f['name']}_ids, 'one', {$__d})";
      }
      $Where .= ")";
    }
    else {
      $_title .= iif(strlen($_title), ' and ') . getDisplayNames($f['name'], json_encode($v)) . iif($f['type'] == 'bool', ' ' . $f['display']);
      $Where .= iif(strlen($Where), " AND") . " FIND_IN_SET({$f['tbl']}.{$f['name']}_id, '" . implode(',', $v) . "')";
    }
    //$title .= ' ' .getDisplayName('gpd_inmate_type', $gpd_inmate_type_id);
  }
}

$title .= $_title . ' ' . $__title;

$Order = "";
foreach($__fields as $f) {
  if(in_array($f['id'], $disaggregate_ids)) {
    $Order .= iif(strlen($Order), ", ") . "{$f['tbl']}.sort, {$f['tbl']}.{$f['name']}_name";
  }
}
if (strlen($Order)) {
  $Order = "ORDER BY " . iif(strlen($date_grouping), " {$date_grouping} ,") . "{$Order}";
}
else {
  if (strlen($date_grouping)) $Order = "ORDER BY {$date_grouping}";
}

$sql = "SELECT {$__count} AS qty,
" . iif(strlen($date_grouping), "{$date_grouping}", "'ALL'") . " AS curr_date";
foreach($__fields as $f) {
  if(in_array($f['id'], $disaggregate_ids)) {
    $sql .= ", {$f['tbl']}.{$f['name']}_id, {$f['tbl']}.{$f['name']}_name AS {$f['id']}";
  }
}
$sql .= "
FROM
{$__sql_from}
WHERE {$Where} {$sql_where}
GROUP BY 'A'
" . iif(strlen($date_grouping), ", {$date_grouping}");
foreach($__fields as $f) {
  if(in_array($f['id'], $disaggregate_ids)) {
    $sql .= ", {$f['tbl']}.{$f['name']}_id, {$f['tbl']}.{$f['name']}_name";
  }
}
$sql .= "
{$Order}
";

$rr = getRs($sql, $sql_params);

$data = $a_data = $data_1 = $data_2 = $data_3 = $series = $categories = array();
$cat_display = '';

if (!sizeof($disaggregate_ids)) {

  foreach($rr as $r) {
    array_push($categories, $r['curr_date']);
    array_push($data, array('y' => 1 * $r['qty']));
  }  
  array_push($series, array('name' => $__title, 'data' => $data, 'visible' => true));

}
else {
  $cat_field = 'curr_date';
  
  if (!strlen($date_grouping) and sizeof($disaggregate_ids) > 1) {
    foreach($rr as $r) {
      foreach($__fields as $f) {
        if(in_array($f['id'], $disaggregate_ids)) {
          $cat_field = $f['id'];
          $cat_display = $f['display'];
          break;
        }
      }
    }
  }

  $ref = $ref_display = null;
  foreach($rr as $r) {
    $_curr_date = 'd_' . toLink($r[$cat_field]);
    if (!isset($data[$_curr_date])) {
      array_push($categories, $r[$cat_field]);
      $data[$_curr_date] = array();
    }
  }

  foreach($rr as $r) {
    foreach($__fields as $f) {
      if(in_array($f['id'], $disaggregate_ids) and $cat_field != $f['id']) {
        $ref = $f['name'];
        if ($f['type'] == 'bool') $ref_display = ' ' . $f['display'];
        break;
      }
    }
    /*
    if (!in_array($r[$ref . '_id'], $data_2)) {
      array_push($data_2, $r[$ref . '_id']);
      array_push($data_3, array('id' => $r[$ref . '_id'], 'name' => $r[$ref . '_name']));
    }*/
  }
  if ($ref) {
    $_rr = getRs("SELECT {$ref}_id, {$ref}_name FROM {$ref} WHERE " . is_enabled() . " ORDER BY sort, {$ref}_name");
    foreach($_rr as $_r) {
      array_push($data_3, array('id' => $_r[$ref . '_id'], 'name' => $_r[$ref . '_name'] . $ref_display));
    }
    
    for($d = sizeof($data_3)-1; $d>=0; $d--) {
      $found = false;
      foreach($rr as $r) {
        if ($r[$ref . '_id'] == $data_3[$d]['id']) {
          $found = true;
          break;
        }
      }
      if (!$found) unset($data_3[$d]);    
    }

    foreach($data_3 as $g) {
      $data_1 = array();
      foreach($categories as $c) {
        array_push($data_1, array('y' => 0));
        foreach($rr as $r) {
          if ($r[$cat_field] == $c && $g['id'] == $r[$ref . '_id']) {
            $data_1[sizeof($data_1) - 1]['y'] = $r['qty'] * 1;
          }
        }
      }
      array_push($series, array('name' => $g['name'], 'data' => $data_1, 'visible' => true));
    }
  }
}

if ($chart_type == 'pie') {
  $_series = $series;
  $series = array();
  $i = 0;
  if (sizeof($categories) > 1) {
    foreach($categories as $c) {
      array_push($series, array('name' => $c, 'y' => $_series[0]['data'][$i]['y']));
      $i++;
    }
  }
  else {
    foreach($_series as $_s) {
      array_push($series, array('name' => $_s['name'], 'y' => $_s['data'][0]['y']));
      $i++;
    }
  }
  $series = array(array('name' => $title, 'showInLegend' => true, 'data' => $series));
  $_categories = array();
}
else {
  $_categories = $categories;
}
if (sizeof($disaggregate_ids) < 2 || (!strlen($date_grouping) and sizeof($disaggregate_ids) < 3)) {
  array_push($charts, array('id' => 'chart', 'title' => $title, 'legend' => true, 'subtitle' => $subtitle, 'type' => $chart_type, 'stacking' => $chart_stacking, 'chart_3d' => $chart_3d, 'categories' => $_categories, 'xLabel' => $cat_display, 'yaxis' => $yaxis, 'series' => $series));
}
$table = '<table class="table table-analytics table-bordered table-hover table-striped">';
$table .= '
<thead>
    <tr class="inverse1">
      <th></th>
      <th>' . ucwords($timespan) . '</th>';
      foreach($__fields as $f) {
        if(in_array($f['id'], $disaggregate_ids)) {
          $table .= '<th>' . $f['display'] . '</th>';
        }
      }
      $table .= '
      <th>' . $__subtotal . '</th>
      ' . iif($__show_percent, '<th>%</th>') . '
    </tr>
</thead>
<tbody>';
$ret = '';
$i = 0;
$total = 0;
foreach($rr as $r) {
  $total += $r['qty'];
}
foreach($rr as $r) {
  $ret .= '<tr><td>' . ++$i . '</td>
  <td>' . $r['curr_date'] . '</td>';  
  foreach($__fields as $f) {
    if(in_array($f['id'], $disaggregate_ids)) {
      $ret .= '<td>' . $r[$f['id']] . '</td>';
    }
  }
  $ret .= '
  <td>' . number_format($r['qty']) . '</td>
  ' . iif($__show_percent, '<td>' . (($total)?number_format($r['qty'] / $total * 100, 2) . '%':'') . '</td>') . '
  </tr>';
}
$table .= $ret . '
</tbody>' . iif($__show_total, '
<thead>
<tr>
  <th></th>
  <th colspan="' . (sizeof($disaggregate_ids) + 1) . '">TOTAL</th>
  <th>' . number_format($total) . '</th><th>100%</th>
</tr>
</thead>');
$table .= '</table>';

if (!$_ajax) {
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
        echo '<li><a href="" class="btn-table-display-load" data-c="' . $_d['module_option_code'] . '">' . $_d['module_option_id'] . '. ' . shorten($_d['module_option_name'], 100) . '</a></li>';
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

<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Display Options</h4>
    </div>
    <div class="panel-body pb-0"<?php echo iif($_hide_options, ' style="display:none;"'); ?>>
        <?php
        if (sizeof($__counts) > 1) {
        echo '
			  <div class="panel-option pt-1 pb-1 pl-4">Key Indicator</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-4 date">
            <select name="count_index" class="form-control select2">';
            $i = 0;
            foreach($__counts as $_c) {
              echo '<option value="' . $i . '"' . iif($count_index == $i, ' selected') . '>' . $_c[0] . '</option>';
              $i++;
            }
            echo '</select>
          </div>
        </div>';
        }
        ?>
			  <div class="panel-option<?php echo iif(sizeof($__counts) > 1, ' mt-3'); ?> pt-1 pb-1 pl-4">Date Selection</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-4 date">
            <div class="input-group date datepicker" data-date-format="dd/mm/yyyy">
              <input type="text" class="form-control" placeholder="Start date ..." name="date_start" value="<?php echo $date_start; ?>" />
              <div class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-4 date">
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
                  <i class="fa fa-clock mr-1"></i> Timespan
                </div>
              </div>
              <select class="form-control select2" name="timespan">
              <option value=""> - All -</option>
              <option value="day"<?php echo iif($timespan == 'day', ' selected'); ?>>Daily</option>
              <option value="week"<?php echo iif($timespan == 'week', ' selected'); ?>>Weekly</option>
              <option value="month"<?php echo iif($timespan == 'month', ' selected'); ?>>Monthly</option>
              <option value="quarter"<?php echo iif($timespan == 'quarter', ' selected'); ?>>Quarterly</option>
              <option value="year"<?php echo iif($timespan == 'year', ' selected'); ?>>Yearly</option>
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
			  <div class="panel-option mt-3 pt-1 pb-1 pl-4">Disaggregate by</div>
        <div class="row form-input-flat mb-2">
          <div class="col-sm-12">
            <div>
            <select class="form-control multiple-select" multiple="multiple" id="disaggregate_ids" name="disaggregate_ids[]" width="100%">
            <?php
            foreach($__fields as $f) {
            if ($f['display']) echo '<option value="' . $f['id'] . '"' . iif(in_array($f['id'], $disaggregate_ids), ' selected') . '>' . $f['display'] . '</option>';
            }
            ?>
            </select>
            </div>
          </div>
        </div>



        <div class="panel-option bg-none mt-4 p-0 mb-0" style="background:none;">
          <hr class="m-0" />
          <div class="p-10">
            <div class="row">
              <div class="col-sm-6">
                <span id="status_table_display" class="status"></span>
              </div>
              <div class="col-sm-6 text-right">
                <button type="submit" class="btn btn-warning mt-0">Update</button>
                <button type="button" class="btn btn-secondary btn-table-display-restore mt-0" data-c="<?php echo $module_code; ?>">Restore Default</button>
              </div>
            </div>
          </div>
        </div>



      </div>
  </div>
</form>


<div class="panel">
  <ul class="nav nav-tabs">
    <?php if(sizeof($charts)) { ?>
    <li class="nav-item">
        <a href="javascript:void();" data-target="#charts" data-toggle="tab" class="nav-link active"><i class="fa fa-chart-bar"></i> CHART</a>
    </li>
    <?php } ?>
    <li class="nav-item">
        <a href="javascript:void();" data-target="#table" data-toggle="tab" class="nav-link<?php echo iif(!sizeof($charts), ' active'); ?>"><i class="fa fa-table"></i> TABLE</a>
    </li>
  </ul>
  <div class="tab-content p-0">
    <div class="tab-pane<?php echo iif(!sizeof($charts), ' active'); ?>" id="table">

<div class="panel panel-default-1 m-0 pagination-inverse clearfix">
<div class="panel-heading pb-0 pl-3"><h4 class="panel-title m-0 text-center"><?php echo $title; ?></h4></div>
<div class="panel-body px-0">
<?php echo $table; ?>
</div>
</div>
</div>
<?php if(sizeof($charts)) { ?>
    <div class="tab-pane active" id="charts">
    <div class="ml-4 mt-2">
      <div class="row">
        <div class="col-md-8">
          <div class="btn-group">
          <?php
          $cts = array('column', 'bar', 'line', 'spline', 'area', 'scatter');
          if (!sizeof($disaggregate_ids) || (!strlen($date_grouping) and sizeof($disaggregate_ids) < 2)) {
            $cts = array('column', 'bar', 'pie', 'line', 'spline', 'area', 'scatter');
          }
          foreach($cts as $ct) {
            echo '<button type="button" class="btn btn-default btn-chart-type' . iif($chart_type == $ct, ' btn-success') . '" data-chart="chart" data-type="' . $ct . '">' . ucwords($ct) . '</button>';
          }
          ?>
          </div>
          <span class="nowrap chart-options-stacking-div<?php echo iif(!in_array($chart_type, array('column', 'bar')), ' hide'); ?>"><input type="checkbox" class="chart-options-stacking chart-options" value="stacking" id="chart_type_options_stacking" name="chart_type_options[]" data-render="switchery" data-theme="info"<?php echo iif($chart_stacking, ' checked'); ?> />
          <label for="chart_type_options_stacking"><span class="m-l-5 m-r-10">Stack</span></label></span>
          <span class="nowrap chart-options-3d-div<?php echo iif(!in_array($chart_type, array('column', 'pie', 'bar')), ' hide'); ?>"><input type="checkbox" class="chart-options-3d chart-options" value="3d" id="chart_type_options_3d" name="chart_type_options[]" data-render="switchery" data-theme="info"<?php echo iif($chart_3d, ' checked'); ?> />
          <label for="chart_type_options_3d"><span class="m-l-5 m-r-10">3D</span></label></span>
        </div>
        <div class="col-md-4 text-right">
          <div class="mx-4">
          <?php
          echo (isset($_ds['id'])?'<a href="javascript:void(0)" class="toggle-tooltipmx-2" data-toggle="tooltip" title="Use this shortcode to include this chart on any page on the dissemination website"><i class="ion-help-circled"></i></a>
          [chart id="' . $_ds['id'] . '"]':'');
          ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    foreach($charts as $c) {
      echo '<div class="panel"><div class="panel-body" style="position:relative;"><div style="margin-top:30px;" class="chart" id="' . $c['id'] . '"></div>      
      <div class="text-center" style="z-index:1000;padding:20px;position:absolute;right:0;top:0;left:0;width:100%;"><span id="status_chart_type" class="mr-5"></span></div>
      </div></div>';
    }
    ?>
    </div>
  <?php } ?>
</div>
<?php include_once('inc/footer.php'); 
}
else {
  header('Cache-Control: no-cache, must-revalidate');
  header('Expires: '.date('r', time()+(86400*365)));
  header('Content-type: application/json');

  echo json_encode(array('success' => true, 'response' => 'Completed', 'chart' => $charts[0], 'table' => $table));
}
?>