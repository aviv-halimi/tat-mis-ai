<?php
require_once ('../_config.php');

function GetCalendarTasks($_p, $module_code = 'visits-calendar') {
    global $_Session;
    $date_start = getVarA('start', $_p);
    $date_end = getVarA('end', $_p);
    $_date_start = strtotime($date_start);
    $_date_end = strtotime($date_end);
    /*
    while($_date_start < $_date_end) {
        $__date_start = date('Y-m-d', $_date_start);
        $rs = getRs("SELECT * FROM po_event WHERE " . is_enabled() . " AND date_start = ?", array($__date_start));
        $days['d_' . $_date_start * 1000] = array('color' => '#fff', 'description' => sizeof($rs) . ' slots available');
        $_date_start += (24 * 60 * 60);
    }
    */
    $_where = ' AND e.store_id = ?';
    $_params = array($_Session->store_id);
    if ($date_start) {
        $_where .= " AND e.date_start >= ?";
        array_push($_params, $date_start);
    }
    if ($date_end) {
        $_where .= " AND e.date_start <= ?";
        array_push($_params, $date_end);
    }
    $rs = getRs("SELECT po.po_number, e.po_event_id, e.po_event_code, e.po_event_status_id, e.date_start, e.date_end, e.date_created, e.vendor_name, '' AS contact_name FROM po INNER JOIN po_event e ON e.po_id = po.po_id WHERE " . is_enabled('e') . " AND FIND_IN_SET(e.po_event_status_id, '2,3')" . $_where . " ORDER BY e.date_start", $_params);
    $events = array();
    foreach($rs as $r) {
		$event = array('id' => $r['po_event_id'], 'code' => $r['po_event_code'], 'title' => $r['vendor_name'] . ' (' . $r['po_number'] . ')', 'start' => $r['date_start'], 'end' => $r['date_end'], 'status' => ((rand(0,1) == 1)?'Complete':'Scheduled'), 'textColor' => 'white', 'backgroundColor' => '#ff5f00', 'borderColor' => '#ff5f00', 'editable' => ($r['date_start'])?false:true, 'icon' => 'comment');
        if ($r['po_event_status_id'] == 2) { // scheduled
            $event['icon'] = 'clock';
            $event['backgroundColor'] = '#076a2b';
            $event['borderColor'] = '#076a2b';
            $event['textColor'] = 'white';
        }
        else if ($r['po_event_status_id'] == 3) { // completed
            $event['icon'] = 'check';
            $event['backgroundColor'] = '#ed184d';
            $event['borderColor'] = '#ed184d';
            $event['textColor'] = 'white';
            $event['display'] = 'background';
        }
        else  { // delayed
            $event['icon'] = 'exclamation-triangle';
            $event['backgroundColor'] = '#9d9d9d';
            $event['borderColor'] = '#9d9d9d';
            $event['textColor'] = 'black';
            $event['display'] = 'background';
        }
        array_push($events, $event);
    }    
    return array('success' => true, 'response' => 'Events', 'events' => $events);
}


header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

echo json_encode(GetCalendarTasks($_POST));
exit();
					
?>