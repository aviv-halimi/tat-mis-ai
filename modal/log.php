<?php
require_once('../_config.php');
$id = getVarNum('id');
$rs = $_Session->GetActivityID($id);
if ($r = getRow($rs)) {
  $params = json_decode($r['params'], true);
  echo '
  <div class="row form-group">
    <label class="col-sm-2 control-label">Type:</label>
    <div class="col-sm-10">' . $r['activity_type_name'] . '</div>
  </div>
  <div class="row form-group">
    <label class="col-sm-2 control-label">Date:</label>
    <div class="col-sm-10">' . getLongDate($r['date_created']) . '</div>
  </div>
  <div class="row form-group">
    <label class="col-sm-2 control-label">Notes:</label>
    <div class="col-sm-10">' . nl2br($r['notes']) . '</div>
  </div>' . iif($r['admin_id'], '
  <div class="row form-group">
    <label class="col-sm-2 control-label">By Admin:</label>
    <div class="col-sm-10">' . $r['admin_name'] . '</div>
  </div>') . iif($r['user_id'], '
  <div class="row form-group">
    <label class="col-sm-2 control-label">By User:</label>
    <div class="col-sm-10">' . $r['user_name'] . '</div>
  </div>') . '';
  if (sizeof($params)) {
    echo '
    <div class="row form-group">
      <label class="col-sm-12 control-label">' .
      iif($r['activity_type_id'] == 4, '<div><span id="revert_' . $r['activity_code'] . '" class="status"><a href="" class="btn btn-sm btn-warning btn-revert btn-revert-all" data-c="' . $r['activity_code'] . '" data-id="' . $r['activity_code'] . '">Revert All</a></span></div>') . '</label>
      <div class="col-sm-12">
      <table class="table t-log">
      <thead><tr><th>Key</th><th>Previous</th><th>New</th></tr></thead></tbody>';
      foreach($params as $p) {
        $prev = ((strlen($p['prev_name']))?$p['prev_name']:$p['prev']);
        echo iif(!is_numeric($p['key']), '<tr><th>' . nicefy($p['key']) . iif($r['activity_type_id'] == 4, '<div id="revert_' . $r['activity_code'] . '-' . $p['key'] . '" class="status"><a href="" class="btn btn-sm btn-default btn-revert" data-c="' . $r['activity_code'] . '" data-k="' . $p['key'] . '" data-id="' . $r['activity_code'] . '-' . $p['key'] . '">Revert</a></div>') . '</th><td><i>' . $prev . '</i></td><td>' . ((strlen($p['new_name']))?$p['new_name']:$p['new']) . '</td></tr>');
      }/*
      echo '<tr><td>' . print_r($params, true) . '</td></tr>';
      */
      echo '</tbody></table></div>
    </div>';
  }
  echo '
  <div class="row form-group">
    <label class="col-sm-2 control-label">Client Browser:</label>
    <div class="col-sm-10">' . $r['ip_address'] . ' / ' . $r['user_agent'] . '</div>
  </div>';
}
else {?>
<div class="alert alert-warning alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert">&times;</button>
  <div class="alert-icon contrast-alert">
    <i class="icon-exclamation"></i>
  </div>
  <div class="alert-message">
    <span><strong>Not Found!</strong></span>
  </div>
</div>
<?php
}
?>