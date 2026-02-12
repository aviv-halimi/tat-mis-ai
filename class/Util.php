<?php
 
class Util extends SessionManager {
  
  function ImageUpload($t, $f, $image) {  
    return '
    <input type="hidden" name="' . $f . '" id="' . $f . '" value="' . $image . '" />
    <div class="row">
        <div class="col-sm-12">
            <span class="btn btn-primary fileinput-button">
                <i class="icon icon-upload"></i> Select image ...
                <input id="' . $f . '_fileupload" data-table="' . $t . '" type="file" class="fileupload" name="files[]" />
            </span>
            <div class="fileupload-progress"></div>
            <div class="upload-preview">' . iif(strlen($image), '<img src="/media/' . $t . '/' . $image . '" alt="' . $image . '" />') . '</div>
            <a href="javascript:void(0)" id="image_remove" class="btn-remove-img btn btn-danger"' . iif(strlen($image) == 0, ' style="display:none;"') . '>Remove</a>
        </div>
    </div>';
  }  

  function Chart($chart) {
    $categories = false;
    $series = $yaxis = $drilldown = array();
    $dp = 0;
    $prefix = $suffix = null;
    $type = 'column';
    $xtype = 'category';
    $series = $chart['series'];
    $yaxis = $chart['yaxis'];
    $subtitle = null;
    $yLabels = false;
    $legend = false;
    $xLabel = null;
    $stacking = null;
    $chart_3d = false;
    if (isset($chart['subtitle'])) $subtitle = $chart['subtitle'];
    if (isset($chart['yLabels'])) $yLabels = $chart['yLabels'];
    if (isset($chart['type'])) $type = $chart['type'];
    if (isset($chart['categories'])) $categories = $chart['categories'];
    if (isset($chart['drilldown'])) $drilldown = $chart['drilldown'];
    if (isset($chart['xtype'])) $xtype = $chart['xtype'];
    if (isset($chart['legend'])) $legend = $chart['legend'];
    if (isset($chart['yLabel'])) $yaxis['title']['text'] = $chart['yLabel'];
    if (isset($chart['xLabel'])) $xLabel = $chart['xLabel'];
    if (isset($chart['stacking'])) $stacking = $chart['stacking'];
    if (isset($chart['chart_3d'])) $chart_3d = $chart['chart_3d'];
    if (isset($chart['dp'])) $dp = $chart['dp'];
    if (isset($chart['suffix'])) $suffix = ' ' . $chart['suffix'];
    $chart = array(
      'chart' => array(
        'type' => $type,
        'plotBackgroundColor' => null,
        'plotBackgroundImage' => null,
        'plotBorderWidth' => 0,
        'plotShadow' => false,
        //'polar' => true,
        'options3d' => array (
          'enabled' => $chart_3d,
          'alpha' => ($type != 'pie')?10:45,
          'beta' => ($type != 'pie')?5:0,
          'depth' => ($type != 'pie')?100:50,
          'viewDistance' => 25
        )
      ),
      'title' => array(
        'text' => $chart['title']   
      ),
      'subtitle' => array(
        'text' => $subtitle
      ),
      'xAxis' => array(
        'categories' => $categories,
        'crosshair' => true,
        'type' => $xtype,
        'title' => array(
          'text' => $xLabel
        )
      ),
      'exporting' => array(
        'buttons' => array(
          'contextButton' => array(
            'menuItems' => array("downloadPNG", "downloadJPEG", "downloadPDF", "downloadSVG")
          )
        )
      ),
      'pane' => array(
        'startAngle' => -150,
        'endAngle' => 150,
        'background' => array(
            array(
            'backgroundColor' => array(
                'linearGradient' =>  array('x1' => 0, 'y1' => 0, 'x2' => 0, 'y2' => 1),
                'stops' => [
                    [0, '#FFF'],
                    [1, '#333']
                ]
            ),
            'borderWidth' => 0,
            'outerRadius' => '109%'
          ), array(
            'backgroundColor' => array(
                'linearGradient' => array('x1' => 0, 'y1' => 0, 'x2' => 0, 'y2' => 1),
                'stops' => [
                    [0, '#333'],
                    [1, '#FFF']
                ]
            ),
            'borderWidth' => 1,
            'outerRadius' => '107%'
          ), array(
            // default background
          ), array(
            'backgroundColor' => '#DDD',
            'borderWidth' => 0,
            'outerRadius' => '105%',
            'innerRadius' => '103%'
        ))
    ),

    'yAxis' => array(
      'crosshair' => array(
          'enabled' => true,
          'color' => '#333'
      ),
      'lineWidth' => 0,
      'tickInterval' => 25,
      'reversedStacks' => false,
      'endOnTick' => true,
      'showLastLabel' => true
    ),

      'yAxis' => $yaxis,
      'legend' => array(
        'enabled' => $legend
      ),
      'plotOptions' => array(
        'columns' => array(
          'stacking' => $stacking,
          'pointPadding' => 0.2,
          'borderWidth' => 1,
          'groupPadding' => 0.15
        ),
        'series' => array(
          'stacking' => $stacking,
          'borderWidth' => 0,
          'marker' => array(
            'enabled' => false
          ),
          'label' => array(
            'enabled' => false
          ),
          'dataLabels' => array(
              'enabled' => false,
              'format' => (isset($chart['symbol'])?$chart['symbol']:'') . '{point.y:,.0f}'
          )
        ),
        'pie' => array(
            'allowPointSelect' => true,
            'depth' => 50,
            'cursor' => 'pointer',
            'dataLabels' => array(
                'enabled' => true,
                'format' => '<b>{point.name}</b>: {point.percentage:.1f} %'
            )
        )
    ),
    'tooltip' => array(
      'valueDecimals' => $dp,
      'valuePrefix' => $prefix,
      'valueSuffix' => $suffix
    ),
    /*
    'tooltip' => array(
      'split' => true,
        'headerFormat' => '<span style="font-size:11px">{series.name}</span><br>',
        'pointFormat' => '<span style="color:{point.color}">{point.name}</span>: <b>' . (isset($chart['symbol'])?$chart['symbol']:'') . '{point.y:,.0\f}</b><br/>'
    ),
      plotOptions: {
          column: {
              pointPadding: 0.2,
              borderWidth: 0
          }
      },
              plotOptions: {
                  area: {
                      fillColor: {
                          linearGradient: {
                              x1: 0,
                              y1: 0,
                              x2: 0,
                              y2: 1
                          },
                          stops: [
                              [0, Highcharts.getOptions().colors[0]],
                              [1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
                          ]
                      },
                      marker: {
                          radius: 2
                      },
                      lineWidth: 1,
                      states: {
                          hover: {
                              lineWidth: 1
                          }
                      },
                      threshold: null
                  }
              },
         
      'tooltip' => array(
        'pointFormat' => '{series.name}: <b>{point.percentage:.1f}%</b>'
      ),     
      */
      /*,
      plotOptions: {
          pie: {
              allowPointSelect: true,
              cursor: 'pointer',
              dataLabels: {
                  enabled: true,
                  format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                  style: {
                      color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                  }
              }
          }
      },
    'plotOptions' => array(
        'column' => array(
            //'stacking' => 'normal',
            'dataLabels' => array(
                'enabled' => true
            )
        )
    )
    ,*/
      'series' => $series,
      'drilldown' => $drilldown,
      'credits' => array(
        'enabled' => false
      )
    );
    return $chart;
  }















  function GetChart($module_option_id) {
    $charts = array();
    $html = '';
    $tbl = array();

    $_rm = getRs("SELECT * FROM module_option WHERE " . is_enabled() . " AND module_option_id = ?", array($module_option_id));
    if ($_m = getRow($_rm)) {

      $_ds = json_decode($_m['params'], true);

      $rm = getRs("SELECT * FROM module WHERE module_id = ?", array($_m['module_id']));
      if ($m = getRow($rm)) {
        $tbl = json_decode($m['params'], true);
      }





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

      
      $__sql_from = "{$__tbl} {$__alias}";

      foreach(array_reverse($__fields) as $f) {
        $__include = true;
        if (isset($f['type']) && $f['type'] == 'json') {
          if (!in_array($f['id'], $disaggregate_ids)) $__include = false;
        }
        if ($__include) {
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
      }
      
      if ($count_index < sizeof($__counts)) {
        $_counts = $__counts[$count_index];
        $__title = $__unit = $_counts[0];
        $__count = $_counts[1];
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
      
      $title = ''; //'All ';
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
        array_push($charts, array('id' => 'chart_' . $module_option_id, 'title' => $title, 'legend' => true, 'subtitle' => $subtitle, 'type' => $chart_type, 'stacking' => $chart_stacking, 'chart_3d' => $chart_3d, 'categories' => $_categories, 'xLabel' => $cat_display, 'yaxis' => $yaxis, 'series' => $series));
      }
      





    }

    foreach($charts as $c) {
      $html = '<div id="' . $c['id'] . '"></div>';
    }
    return array('charts' => $charts, 'html' => $html);

  }









}

/*

    'pane' => array(
        'startAngle' => -150,
        'endAngle' => 150,
        'background' => array(
            'backgroundColor' => {
                'linearGradient' => { x1: 0, y1: 0, x2: 0, y2: 1 },
                'stops' => [
                    [0, '#FFF'],
                    [1, '#333']
                ]
            ),
            'borderWidth' => 0,
            'outerRadius' => '109%'
          ), array(
            'backgroundColor' => array(
                'linearGradient' => array('x1' => 0, 'y1' => 0, 'x2' => 0, 'y2' => 1),
                'stops' => [
                    [0, '#333'],
                    [1, '#FFF']
                ]
            ),
            'borderWidth' => 1,
            'outerRadius' => '107%'
          ), array(
            // default background
          ), array(
            'backgroundColor' => '#DDD',
            'borderWidth' => 0,
            'outerRadius' => '105%',
            'innerRadius' => '103%'
        )
    ),

    // the value axis
    yAxis => array(
      'min' => 0,
      'max' => 200,
      'minorTickInterval' => 'auto',
        'minorTickWidth' => 1,
        'minorTickLength' => 10,
        'minorTickPosition' => 'inside',
        'minorTickColor' => '#666',

        'tickPixelInterval' => 30,
        'tickWidth' => 2,
        'tickPosition' => 'inside',
        'tickLength' => 10,
        'tickColor' => '#666',
        'labels' => array(
            'step' => 2,
            'rotation' => 'auto'
        ),
        'title' => array(
            'text' => 'km/h'
        ),
        'plotBands' => [{
            'from' => 0,
            'to' => 120,
            'color' => '#55BF3B' // green
        ), array(
            'from' => 120,
            'to' => 160,
            'color' => '#DDDF0D' // yellow
        ), array(
            'from' => 160,
            'to' => 200,
            'color' => '#DF5353' // red
        )
      ),

    series' => [{
        name' => 'Speed',
        data' => [80],
        tooltip' => {
            valueSuffix' => ' km/h'
        }
    }]

*/
?>