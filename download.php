<?php
define('SkipAuth', true);
require_once ('_config.php');

// only allow public access to settings folder
$folder = getVar('t');
if (in_array($folder, array('setting', 'partner', 'site')) || $_Session->admin_id) {
  $subfolder = getVar('s');
  $file = getVar('f');
  $fp = MEDIA_PATH . $folder . iif(strlen($subfolder), '/' . $subfolder) . '/' . $file;
  $sf = iif(in_array($folder, array('admin', 'person')), $folder . '.png', 'image.png');
  if (strpos($file, '-badge.jpg') !== false) {
    $sf = 'badge.png';
  }
  else if (strpos($file, 'ECD-') !== false) {
    $sf = 'ecd.png';
  }
  else if (strpos($file, 'LBS-') !== false) {
    $sf = 'lbs.png';
  }
  if (in_array(getExt($file), array('pdf'))) $sf = 'pdf.png';
  if (in_array(getExt($file), array('xls', 'xlsx'))) $sf = 'xls.png';
  $mp = MEDIA_PATH . 'setting/' . $sf;

  if (strlen($file) and file_exists($fp)) {
    $type = 'application/pdf';
    header('Content-Type:'. $type);
    header('Content-Disposition: inline;filename="' . $file . '"');
    header('Content-Length: ' . filesize($fp));
    readfile($fp);
  }
  else {
    $type = 'image/png';
    header('Content-Type:'. $type);
    header('Content-Length: ' . filesize($mp));
    readfile($mp);
  }
}
else {
	http_response_code(401);
  echo 'Access denied';
}

?>
