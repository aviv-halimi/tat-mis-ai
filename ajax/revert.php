<?php
require_once ('../_config.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
$a = $_Session->Revert($_POST);
echo json_encode($a);

$TableName = $a['tbl'];
$ItemID = $a['id'];

if (file_exists(BASE_PATH . 'trigger/' . $TableName . '.php')) {
  require_once(BASE_PATH . 'trigger/' . $TableName . '.php');
}

exit();
					
?>