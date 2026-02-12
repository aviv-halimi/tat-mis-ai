<?php
require_once('../_config.php');
http_response_code(404);
$meta_title = '404 - Page Not Found';
$_content = '<div class="alert alert-danger alert-bordered text-lg">
	<strong>404 - Page Not Found</strong>
	<p>Sorry ' . $_Session->first_name . ', the page you are attempting to load cannot be found. Please contact system administrator for more information.</p>
</div>';

require_once('header.php');
echo $_content;
require_once('footer.php');
?>