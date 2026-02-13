<?php

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//++++++++++++++++ MODIFY TO MATCH SERVER ENVIRONTMENT ++++++++++++++++++
//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++


# Database config

/* DEV 
define('dbhost', 'mysql:host=localhost;dbname=theartisttree;');
define('dbuser', 'root');
define('dbpass', '');
define('MEDIA_PATH', 'D:\Media\theartisttree\\');
*/
/* LIVE */
define('dbhost', 'mysql:host=172.26.5.61;dbname=theartisttree;');
define('dbuser', 'dbuser');
define('dbpass', 'Vtbk518&');
define('MEDIA_PATH', '/var/www/vhosts/wantadigital.com/media/theartisttree/');


# Report all PHP errors
//error_reporting(E_ALL);
//error_reporting(-1);
//ini_set('display_errors' , 'On');
//ini_set('error_reporting', E_ALL);

define('SALT', '@Prot3ct#!');

date_default_timezone_set('America/Los_Angeles');

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//++++++++++++++++++++++++++++++ END MODIFY +++++++++++++++++++++++++++++
//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

# Paths
define('BASE_PATH', dirname(__FILE__) . '/');
define('BASE_URL', '');

# PHP CLI for invoice validation (run from UI). Required on Plesk when "php" is php-fpm.
define('INVOICE_VALIDATE_PHP_CLI', '/opt/plesk/php/8.3/bin/php');

require_once(BASE_PATH . 'inc/settings.php')
?>
