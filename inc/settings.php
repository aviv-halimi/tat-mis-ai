<?php
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: start');
}
define('INC_PATH', BASE_PATH . 'inc/');
define('CLASS_PATH', BASE_PATH . 'class/');
define('PLUGINS_PATH', BASE_PATH . 'plugins/');
define('ASSETS_PATH', BASE_PATH . 'assets/');

require_once(INC_PATH . "db.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: db.php loaded');
}
require_once(INC_PATH . "functions.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: functions.php loaded');
}
require_once(CLASS_PATH . "SessionManager.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: SessionManager loaded');
}
require_once(CLASS_PATH . "FulfillmentManager.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: FulfillmentManager loaded');
}
require_once(CLASS_PATH . "POManager.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: POManager loaded');
}
require_once(CLASS_PATH . "ProductCardManager.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: ProductCardManager loaded');
}
require_once(CLASS_PATH . "Util.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: Util loaded');
}
require_once(PLUGINS_PATH . "phpmailer/vendor/autoload.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: phpmailer loaded');
}

$page_name = getScriptName();
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: getScriptName done');
}
$_Session = new SessionManager();
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: SessionManager constructed');
}
$_Fulfillment = new FulfillmentManager();
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: FulfillmentManager constructed');
}
$_PO = new POManager();
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: POManager constructed');
}
$_ProductCard = new ProductCardManager();
$_Util = new Util();

//$_Session->InitSession();

require_once(INC_PATH . "security.php");
if (function_exists('qbo_tb_cli_log')) {
    qbo_tb_cli_log('settings.php: done');
}
?>