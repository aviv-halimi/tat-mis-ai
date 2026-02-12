<?php
require_once('../_config.php');
$help_id = getVarNum('id');
$rs = getRs("SELECT * FROM help WHERE " . is_enabled() . " AND help_id = ?", array($help_id));
if ($r = getRow($rs)) {
  echo '<div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">' . $r['help_name'] . '</h3></div><div class="panel-body p-0"><div class="video-container"><iframe src="https://www.youtube.com/embed/' . $r['video'] . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>
    <div class="panel-footer">' . nl2br($r['description']) . '</div>
    </div>';
}
?>