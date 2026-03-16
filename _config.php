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
//define('dbhost', 'mysql:host=db.theartisttree.com;dbname=theartisttree;');
define('dbuser', 'dbuser');
define('dbpass', 'Vtbk518&');
define('MEDIA_PATH', '/var/www/vhosts/wantadigital.com/media/theartisttree/');
define('QBO_REDIRECT_URI', 'https://mis-ai.theartisttree.com/ajax/qbo-oauth-callback.php');

// QBO: client id and secret set via Apache env (SetEnv) — QBO_CLIENT_ID, QBO_CLIENT_SECRET
// define('QBO_CLIENT_ID','...');
// define('QBO_CLIENT_SECRET','...');
# Gemini API key: set in Plesk env (see doc) so it is not overwritten by git. Fallback constant below if needed.
// define('GEMINI_API_KEY', '');
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
// Optional: full base URL for log links (e.g. invoice validation PDF links). If set, log shows full URL.
// define('SITE_URL', 'https://wantadigital.com/theartisttree-mis-ai');

# PHP CLI for invoice validation (run from UI). Required on Plesk when "php" is php-fpm.
define('INVOICE_VALIDATE_PHP_CLI', '/opt/plesk/php/8.3/bin/php');

# Optional: for invoice validation with Gemini. Set in env as GEMINI_API_KEY or uncomment below.
// define('GEMINI_API_KEY', '');

# Dropbox API access token — needed for brand-folder file listing during enrichment.
# Generate at https://www.dropbox.com/developers/apps → your app → "Generate access token"
# Set as a Plesk environment variable DROPBOX_ACCESS_TOKEN or uncomment the line below.
if (!defined('DROPBOX_ACCESS_TOKEN')) {
    define('DROPBOX_ACCESS_TOKEN', (string) (getenv('DROPBOX_ACCESS_TOKEN') ?: ''));
}
# Optional: use a different model for PO menu sync only. If unset, uses GEMINI_MODEL (e.g. gemini-2.0-flash).
# Some keys/regions only support certain models; if you get 404, leave this commented. See doc/po-menu-sync-504-timeout.md.
// define('GEMINI_PO_MENU_MODEL', 'gemini-1.5-flash');

require_once(BASE_PATH . 'inc/settings.php')
?>
