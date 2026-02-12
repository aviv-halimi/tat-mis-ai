<?php
require_once ('../_config.php');

$tbl = getVar('tbl', 'file');
$id = getVar('id');
$document_code = getVar('doc_code');
$document_folder_code = getVar('folder_code');
$folder = MEDIA_PATH . $tbl . '/';
$params = array('tbl' => $tbl, 'document_code' => $document_code, 'document_folder_code' => $document_folder_code, 'admin_id' => $_Session->admin_id);

require_once('../class/UploadHandler.php');
$upload_handler = new UploadHandler($folder, $id, $params);

?>