<?php
$footer = '<script src="/assets/js/scheduling.js"></script>';
include_once('inc/header.php');
?>



<div class="tab-content pt-3">
    <div class="tab-pane active" id="list" role="tabpanel" aria-labelledby="list-tab">
        <div class="alert alert-info"><i class="fa fa-bulb"></i> List of upcoming appointment days. Click on the day to view and select the time slot for the appointment.</div>
        <?php

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
                        $slots .= '<button class="btn btn-block btn-primary btn-dialog" data-url="po-event" data-b="' . $date_start . ' ' . $h . ':' . $mm . '" data-title="Add Appointment: ' . date('D, M jS', $current) . ' ' . $hh . ':' . $mm . ' ' . $t . '" data-save-text="Confirm">' . $hh . ':' . $mm . ' ' . $t . '</button>';
                        $num_slots++;
                        $_h += $_duration;
                    }
                    if ($loopvar > 100000) {
                        break;
                    }
                }

                if ($num_slots) {                
                echo '<div class="col-sm-3">
                
                <div class="card card-box mt-1 mb-4">
                    <div class="card-indicator bg-first"></div>
                        <div class="card-body px-4 py-3">
                            <div class="pb-3 d-flex justify-content-between">
                                <a href="#" class="day">
                                    ' . date('D, M jS', $current) . '
                                </a>
                                <div class="badge badge-first px-3">' . $num_slots  . ' slot' . iif($num_slots != 1, 's') . '</div>
                            </div><div class="slots" style="display:none;">' . $slots . '</div>                                           
                        </div>
                    </div>                            
                </div>';
                if ($days_shown++ >= ($scheduling_window - 1)) $show_calendar = false;
                }

            }
            else {
                //echo $date_start;
            }
            $current += (24 * 60 * 60);

        }
        echo '</div>';


        ?>
        </div>
    </div>
</div>

<?php
function getH($d) {
    return (date('G', $d) * 60) + date('i', $d);
}

include_once('inc/footer.php');
?>