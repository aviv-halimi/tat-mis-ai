<?php
require_once('../_config.php');
$module_code = getVar('c');
$rs = $_Session->GetModuleOptions($module_code, null, null);
$ra = getRs("SELECT admin_id, admin_name FROM admin WHERE " . is_enabled() . " AND site_id = ? ORDER BY last_name", array($_Session->site_id));
echo '
<div class="row form-group">
  <div class="col-sm-12">
  <table class="table-modal table">
  <thead><tr><th>ID</th><th>Date</th><th>Saved by</th><th width="50%">Description</th><th></th></tr></thead></tbody>';
  foreach ($rs as $r) {
  echo '<tr><td>' . $r['module_option_id'] . '</td><td data-sort="' . $r['date_created'] . '">' . getLongDate($r['date_created']) . '</td><td>' . getDisplayName('admin', $r['admin_id']) . '</td><td width="50%">
  <div class="module-option-' . $r['module_option_id'] . '-view">' . $r['module_option_name'] . '</div>
  <div class="module-option-' . $r['module_option_id'] . '-edit hide">
  <div class="input-group">
  <input type="text" name="" class="form-control" value="' . $r['module_option_name'] . '" />
  <div class="input-group-append">
  <button type="button" class="btn btn-primary btn-sm btn-module-option-save" data-id="' . $r['module_option_id'] . '"><i class="fa fa-check"></i></button>
  </div>
  </div>
  </div>
  <div class="module-option-' . $r['module_option_id'] . '-share hide">
  <small class="text-info">Select user(s)</small>
  <div>
  <select class="form-control multiple-select" multiple="multiple" id="admin_ids_' . $r['module_option_id'] . '" name="admin_ids_' . $r['module_option_id'] . '[]" style="width: 100%">';
  foreach($ra as $a) {
  echo '<option value="' . $a['admin_id'] . '">' . $a['admin_name'] . '</option>';
  }
  echo '
  </select>
  <div class="pt-1"><button type="button" class="btn btn-default btn-table-display-share-send" data-id="' . $r['module_option_id'] . '" data-c="' . $r['module_option_code'] . '"><i class="ion-android-send"></i> Send Link</button></div>
  </div>
  </td><td class="nowrap"><a href="" class="btn btn-default btn-table-display-load" data-c="' . $r['module_option_code'] . '">Load</a>
  <button type="button" class="btn btn-default btn-table-display-share" data-id="' . $r['module_option_id'] . '" data-c="' . $r['module_option_code'] . '"><i class="ion-android-share-alt"></i> Share</button>
  ' . iif($r['admin_id'] == $_Session->admin_id, '<button type="button" class="btn btn-default btn-module-option-edit" data-id="' . $r['module_option_id'] . '"><i class="ion-edit"></i></button>') . '</td></tr>';
  }
  echo '</tbody></table></div>
</div>';
?>