<?php
require_once('../_config.php');
$id = getVarNum('id');
$tbl = getVar('a');
$rs = getRs("SELECT CONCAT(d.first_name, ' ' , d.last_name) AS admin_name, t.activity_type_name, a.* FROM admin d RIGHT JOIN (activity a INNER JOIN activity_type t ON a.activity_type_id = t.activity_type_id) ON d.admin_id = a.admin_id WHERE a.re_table = ? AND a.re_id = ? ORDER BY a.activity_id DESC", array($tbl, $id));

foreach ($rs as $r) {
    $params = json_decode($r['params'], true);

    
    if (sizeof($params)) {
    echo '
    <div class="row form-group">
        <div class="col-sm-4">Type: <b>' . $r['activity_type_name'] . '</b></div>
        <div class="col-sm-4">By: <b>' . $r['admin_name'] . '</b></div>
        <div class="col-sm-4">Date: <b>' . getLongDate($r['date_created']) . '</b></div>
        
        <div class="col-sm-12">
        <table class="table t-log">
        <thead><tr><th width="20%">Key</th><th width="40%">Previous</th><th>New</th></tr></thead></tbody>';
        foreach($params as $p) {
        if (!in_array($p['key'], array('sort', 'is_active', $tbl . '_code', $tbl . '_id')))
        echo iif(!is_numeric($p['key']), '<tr><th>' . nicefy($p['key']) . '<div></div></th><td><i>' . ((strlen($p['prev_name']))?$p['prev_name']:$p['prev']) . '</i></td><td>' . ((strlen($p['new_name']))?$p['new_name']:$p['new']) . '</td></tr>');
        }/*
        echo '<tr><td>' . print_r($params, true) . '</td></tr>';
        */
        echo '</tbody></table></div>
    </div>
    <hr />';
    }


}

?>