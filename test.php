<?php

define('SkipAuth', true);

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

define('INC_PATH', BASE_PATH . 'inc/');
define('CLASS_PATH', BASE_PATH . 'class/');
define('PLUGINS_PATH', BASE_PATH . 'plugins/');
define('ASSETS_PATH', BASE_PATH . 'assets/');

require_once(INC_PATH . "db.php");
require_once(INC_PATH . "functions.php");
require_once(CLASS_PATH . "SessionManager.php");
//require_once(CLASS_PATH . "FulfillmentManager.php");
//require_once(CLASS_PATH . "POManager.php");
//require_once(CLASS_PATH . "ProductCardManager.php");
//require_once(CLASS_PATH . "Util.php");


$page_name = getScriptName();
$_Session = new SessionManager();


require_once(INC_PATH . "security.php");

set_time_limit(0);
//if ($IP != '52.26.238.52') exit('Access Denied');
$v = getLocalDisplayNames('category', '[1,2,3]', 'name','category_id');
echo $v;

/**/
header('Cache-Control: no-cache, must-revalidate');
header('Expires: '.date('r', time()+(86400*365)));
header('Content-type: application/json');
//echo json_encode($a);
//echo json_encode(array('success' => $success, 'response' => $response, 'redirect' => $redirect));
exit();
?>