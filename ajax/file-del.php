<?php
require_once ('../_config.php');

$success = true;
$response = '';

$file_code = getVar('c');

$rs = getRs("SELECT file_id FROM file WHERE " . is_active() . " AND file_code = ?", array($file_code));

if ($r = getRow($rs)) {
  dbUpdate('file', array('is_enabled' => 0, 'is_active' => 0), $r['file_id']);
  $sucess = true;
  $response = 'Deleted successfully.';
}
else {
  $response = 'Not found.';
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
echo json_encode(array('success' => $success, 'response' => $response));
exit();
					
?>