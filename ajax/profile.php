<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');

$r = $_Session->Profile($_POST);
if ($r['success']) {
  $ItemID = $_Session->admin_id;
  require_once('../trigger/admin.php');
}
echo json_encode($r);
exit();
					
?>