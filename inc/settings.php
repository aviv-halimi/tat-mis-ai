<?php
define('INC_PATH', BASE_PATH . 'inc/');
define('CLASS_PATH', BASE_PATH . 'class/');
define('PLUGINS_PATH', BASE_PATH . 'plugins/');
define('ASSETS_PATH', BASE_PATH . 'assets/');

require_once(INC_PATH . "db.php");
require_once(INC_PATH . "functions.php");
require_once(CLASS_PATH . "SessionManager.php");
require_once(CLASS_PATH . "FulfillmentManager.php");
require_once(CLASS_PATH . "POManager.php");
require_once(CLASS_PATH . "ProductCardManager.php");
require_once(CLASS_PATH . "Util.php");
require_once(PLUGINS_PATH . "phpmailer/vendor/autoload.php");

$page_name = getScriptName();
$_Session = new SessionManager();
$_Fulfillment = new FulfillmentManager();
$_PO = new POManager();
$_ProductCard = new ProductCardManager();
$_Util = new Util();

//$_Session->InitSession();

require_once(INC_PATH . "security.php");
?>