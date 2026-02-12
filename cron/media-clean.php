<?php

define('SkipAuth', true);
require_once('../_config.php');
require_once('../inc/setup.php');


$size = 0;
$path = MEDIA_PATH . 'po/';
if ($handle = opendir($path)) {

    while (false !== ($file = readdir($handle))) { 
        $filelastmodified = filemtime($path . $file);
        //24 hours in a day * 3600 seconds per hour
        if((time() - $filelastmodified) > 365 * 24 * 3600)
        {
           //unlink($path . $file);
           echo '<li>' . $file;
           $size += filesize($path . $file);
        }

    }

    closedir($handle); 
}

echo '<li>' . fileSizeFormat($size);
?>
