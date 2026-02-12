<?php
require_once('../_config.php');
$po_code = getVar('c');
$date_start = getVar('b');

function getH($d) {
    return (date('G', $d) * 60) + date('i', $d);
}


if ($po_code) {
    
    $rs = getRs("SELECT p.*, s.po_status_name, v.vendor_name FROM po_status s RIGHT JOIN (po p LEFT JOIN vendor v ON v.vendor_id = p.vendor_id) ON s.po_status_id = p.po_status_id WHERE " . is_enabled('p') . " AND p.po_code = ?", array($po_code));
    if ($r = getRow($rs)) {
        echo '<div class="mb-2"><b>PO:</b> ' . $r['po_number'] . ', ' . $r['vendor_name'] . ' (' . currency_format($r['total']) . '), ' . $r['po_status_name'] . ' - ID: ' . $r['po_id'] . '</div>
        <input type="hidden" name="po_code" value="' . $po_code . '" />';
    }
    else {
        echo '<div class="alert alert-danger">PO not found</div>';
    }
}
else {
    echo '
    <b>Select PO:</b> <i>(Search by po number or vendor)</i>
    <div class="mb-2">
    <select class="form-control" id="_po_code" name="po_code" placeholder="Search for po" style="width: 100%" data-width="100%"><option></option></select>
    </div>';

}

if ($date_start) {
    echo '<input type="hidden" name="date_start" value="' . $date_start . '" />';
}
else {
    echo '
    <b>Select Delivery Date / Time:</b>
    <div class="mb-2">
    <select class="form-control" id="date_start" name="date_start"><option value="">- Select -</option>';






    
        $store_id = $_Session->store_id;
        $scheduling_window = 24;
        $r = array('store_id' => $_Session->store_id);
        $include_day = $include_days = $exclude_times = $exclude_daily = $exclude_weekly = $exclude_monthly = $exclude_yearly = array();

        $max_daily_deliveries = 20;
        $_duration = 60;

        $rss = getRs("SELECT value FROM setting WHERE setting_code = 'po-event-duration'");
        if ($ss = getRow($rss)) {
            if (is_numeric($ss['value'])) $_duration = $ss['value'];
        }

        $rss = getRs("SELECT params FROM store WHERE store_id = ?", array($store_id));
        if ($ss = getRow($rss)) {
            $__p = json_decode($ss['params'], true);
            if (isset($__p['max_daily_deliveries'])) $max_daily_deliveries = $__p['max_daily_deliveries'];                            
            if (isset($__p['appointment_duration']) and is_numeric($__p['appointment_duration'])) $_duration = $__p['appointment_duration'];
        }
        

        $rps = getRs("SELECT * FROM po_event_setting WHERE store_id = ? AND " . is_enabled(), array($r['store_id']));
        
        foreach($rps as $s) {
            if ($s['po_event_setting_type_id'] == 1 and !$s['po_event_setting_frequency_id']) {
                array_push($include_day, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 1 and $s['po_event_setting_frequency_id'] == 2) {
                array_push($include_days, array(date('w', strtotime($s['date_start'])), strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 2 and !$s['po_event_setting_frequency_id']) {
                array_push($exclude_daily, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 2 and $s['po_event_setting_frequency_id'] == 1) {
                array_push($exclude_times, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 2 and $s['po_event_setting_frequency_id'] == 2) {
                array_push($exclude_weekly, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 2 and $s['po_event_setting_frequency_id'] == 3) {
                array_push($exclude_monthly, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
            if ($s['po_event_setting_type_id'] == 2 and $s['po_event_setting_frequency_id'] == 4) {
                array_push($exclude_yearly, array(strtotime($s['date_start']), strtotime($s['date_end'])));
            }
        }

        echo '<div class="row">';

        $now = $current = time() + (24 * 60 * 60);
        $loopvar = 0;
        $_h_start = 0;
        $_h_end = 1440;

        $show_calendar = true;
        $days_shown = 0;
        while ($show_calendar) {
            $show_day = false;
            foreach($include_days as $dd) {
                if (date('w', $current) == $dd[0]) {
                    $show_day = true;
                    $_h_start = getH($dd[1]);
                    $_h_end = getH($dd[2]);
                }
            }
            foreach($include_day as $dd) {
                if (date('Y-m-d', $current) == date('Y-m-d', $dd[0])) {
                    $show_day = true;
                    $_h_start = getH($dd[0]);
                    $_h_end = getH($dd[1]);
                }
            }
            $date_start = date('Y-m-d', $current);

            $rp = getRs("SELECT *, DATE_FORMAT(date_start, '%Y-%m-%d') AS cleandate FROM po_event WHERE FIND_IN_SET(po_event_status_id, '1,2,3') AND store_id = ? AND " . is_enabled() . " AND DATE_FORMAT(date_start, '%Y-%m-%d') = ?", array($r['store_id'], $date_start));

            if (sizeof($rp) >= $max_daily_deliveries) {
                $show_day = false;
            }
            if ($show_day) {


                $num_slots = 0;
                $slots = '';
                $t = ' am';
                $_h = $_h_start;
                while($_h <= $_h_end) {
                    $loopvar += 1;
                    $h = floor($_h / 60);
                    $m = $_h % 60;
                    $mm = str_pad($m, '2', '0', STR_PAD_LEFT);
                    $he = $h + 1;
                    $hh = $h;
                    if ($hh >= 12) {
                        $t = ' pm';
                    }
                    if ($hh > 12) {
                        $hh -=12;
                    }
                    $open = true;
                    foreach($rp as $p) {
                        if (date('H', strtotime($p['date_start'])) == $h) {
                            $open = false;
                            $_h += $_duration;
                        }
                    }

                    foreach($exclude_times as $tt) {
                        if (
                            ($_h + $_duration) > getH($tt[0])
                            and
                            ($_h + $_duration) <= getH($tt[1])
                            )
                        {
                            $open = false;
                            if (getH($tt[1]) > $_h) $_h = getH($tt[1]);
                        }
                    }
                    foreach($exclude_daily as $tt) {
                        if (
                            date('z/Y', $current) >= date('z/Y', $tt[0])
                            and
                            date('z/Y', $current) <= date('z/Y', $tt[1])
                            and
                            ($_h + $_duration) > getH($tt[0])
                            and
                            ($_h + $_duration) <= getH($tt[1])
                            )
                        {
                            $open = false;
                            if (getH($tt[1]) > $_h) $_h = getH($tt[1]);
                        }
                    }
                    foreach($exclude_weekly as $tt) {
                        if (
                            date('N', $current) >= date('N', $tt[0])
                            and
                            date('N', $current) <= date('N', $tt[1])
                            and
                            ($_h + $_duration) > getH($tt[0])
                            and
                            ($_h + $_duration) <= getH($tt[1])
                            )
                        {
                            $open = false;
                            if (getH($tt[1]) > $_h) $_h = getH($tt[1]);
                        }
                    }
                    foreach($exclude_monthly as $tt) {
                        if (
                            date('d', $current) >= date('d', $tt[0])
                            and
                            date('d', $current) <= date('d', $tt[1])
                            and
                            ($_h + $_duration) > getH($tt[0])
                            and
                            ($_h + $_duration) <= getH($tt[1])
                            )
                        {
                            $open = false;
                            if (getH($tt[1]) > $_h) $_h = getH($tt[1]);
                        }
                    }
                    foreach($exclude_yearly as $tt) {
                        if (
                            date('d/n', $current) >= date('d/n', $tt[0])
                            and
                            date('d/Y', $current) <= date('z/Y', $tt[1])
                            and
                            ($_h + $_duration) > getH($tt[0])
                            and
                            ($_h + $_duration) <= getH($tt[1])
                            )
                        {
                            $open = false;
                            if (getH($tt[1]) > $_h) $_h = getH($tt[1]);
                        }
                    }
                    if ($open) {
                        $slots .= '<option value="' . $date_start . ' ' . $h . ':' . $mm . '">' . date('D, M jS', $current) . ' ' . $hh . ':' . $mm . ' ' . $t . '</option>';
                        $num_slots++;
                        $_h += $_duration;
                    }
                    if ($loopvar > 100000) {
                        break;
                    }
                }

                if ($num_slots) {                
                    echo '<optgroup label="' . date('D, M jS', $current) . ' (' . $num_slots  . ' slot' . iif($num_slots != 1, 's') . ')">' . $slots . '</optgroup>';
                    if ($days_shown++ >= ($scheduling_window - 1)) $show_calendar = false;
                }

            }
            else {
                //echo $date_start;
            }
            $current += (24 * 60 * 60);

        }
    
    
    echo '</select>
    </div>';

}

echo '
<b>Comments:</b>
<div class="mb-2">
<textarea class="form-control" name="description" placeholder="Add any delivery instructions / comments here ..."></textarea>
</div>';

?>

<script>

$(document).ready(function() {
    $('#_po_code').select2({
        ajax: {
            dataType: 'json', 
            url: '/ajax/po-search',
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
        dropdownParent: $('#modal'),
        placeholder: 'Search by po number or vendor',
        minimumInputLength: 1,
        templateResult: formatRepo,
        templateSelection: formatRepoSelection
    });
});

function formatRepo (repo) {
  if (repo.loading) {
    return repo.text;
  }

  var $container = $(
    "<div class='clearfix'>" +
      "" + repo.name + "" +
    "</div>"
  );

  return $container;
}

function formatRepoSelection (repo) {
  return repo.name;
}
</script>