<?php
define('SkipAuth', true);
require_once('_config.php');
$redirect = getVar('r');
$_Session->Logout();

?><!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->

<head>
	<meta charset="utf-8" />
	<title><?php echo $_Session->site_name; ?> - Login</title>
	<link rel="shortcut icon" href="/assets/img/favicon.png" />
	<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
	<meta content="" name="description" />
	<meta content="" name="author" />
	
	<!-- ================== BEGIN BASE CSS STYLE ================== -->
	<link href="https://fonts.googleapis.com/css?family=Roboto+Condensed:400,300,700" rel="stylesheet" id="fontFamilySrc" />
	<link href="/assets/plugins/jquery-ui/jquery-ui.min.css" rel="stylesheet" />
	<link href="/assets/plugins/bootstrap/bootstrap-4.1.1/css/bootstrap.min.css" rel="stylesheet" />
	<link href="/assets/plugins/font-awesome/5.1/css/all.css" rel="stylesheet" />
	<link href="/assets/plugins/switchery/switchery.min.css" rel="stylesheet" />
	<link href="/assets/css/animate.min.css" rel="stylesheet" />
	<link href="/assets/css/style.min.css" rel="stylesheet" />
	<link href="/assets/css/login.css" rel="stylesheet" />
	<!-- ================== END BASE CSS STYLE ================== -->
    
	<!-- ================== BEGIN BASE JS ================== -->
	<script src="/assets/plugins/pace/pace.min.js"></script>
	<!-- ================== END BASE JS ================== -->
	
	<!--[if lt IE 9]>
	    <script src="/assets/crossbrowserjs/excanvas.min.js"></script>
	<![endif]-->
</head>
<body class="pace-top" style="background-repeat:no-repeat;background-size:cover;background-position:center center;background-image:url(<?php echo $_Session->site_image_url; ?>)">
	<!-- begin #page-loader -->
	<div id="page-loader" class="page-loader fade in"><span class="spinner">Loading...</span></div>
	<!-- end #page-loader -->

	<!-- begin #page-container -->
	<div id="page-container" class="fade page-container">
	    <!-- begin login -->
		<div class="login">
		    <!-- begin login-brand -->
            <div class="login-brand bg-inverse text-white">
                <img src="<?php echo $_Session->site_logo_url; ?>" height="48" class="pull-right" alt="" />&nbsp;
            </div>
		    <!-- end login-brand -->
		    <!-- begin login-content -->
            <div class="login-content">
                <form  id="f_login" action="" method="post" class="form-input-flat">
                	<h4 class="text-center m-t-0 m-b-20">Login to your account</h4>
					<input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />
                    <div class="form-group">
                        <input type="text" class="form-control input-lg" name="email" placeholder="Email Address" />
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control input-lg" name="password" placeholder="Password" />
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="remember" value="1" data-render="switchery" data-theme="default" /> Remember me</label>
                    </div>
                    <div class="row m-b-20">
                        <div class="col-lg-12">
							              <div class="status"></div>
                            <span class="form-btns"><button type="submit" class="btn btn-lime btn-lg btn-block">Sign in to your account</button></span>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="" class="text-muted btn-forgot">Forgot your password?</a>
                    </div>
                </form>
                <form  id="f_forgot" action="" method="post" class="form-input-flat" style="display:none;">
                	<h4 class="text-center m-t-0 m-b-20">Forgot your password?</h4>
                    <div class="alert alert-primary">Please provide your email address and we will e-mail instructions on how reset your password.</div>
                    <input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />
                    <div class="form-group">
                        <input type="text" class="form-control input-lg" name="email" placeholder="Email Address" />
                    </div>
                    <div class="row m-b-20">
                        <div class="col-lg-12">
							              <div class="status"></div>
                            <span class="form-btns"><button type="submit" class="btn btn-lime btn-lg btn-block">Submit</button></span>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="" class="text-muted btn-login">Remembered your password?</a>
                    </div>
                </form>
            </div>
		    <!-- end login-content -->
		</div>
		<!-- end login -->
	</div>
	<!-- end page container -->
	
	<!-- ================== BEGIN BASE JS ================== -->
	<script src="/assets/plugins/jquery/jquery-3.3.1.min.js"></script>
	<script src="/assets/plugins/jquery-ui/jquery-ui.min.js"></script>
	<script src="/assets/plugins/bootstrap/bootstrap-4.1.1/js/bootstrap.bundle.min.js"></script>
	<!--[if lt IE 9]>
		<script src="/assets/crossbrowserjs/html5shiv.js"></script>
		<script src="/assets/crossbrowserjs/respond.min.js"></script>
	<![endif]-->
	<script src="/assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
	<script src="/assets/plugins/jquery-cookie/jquery.cookie.js"></script>
	<script src="/assets/plugins/switchery/switchery.min.js"></script>
	<!-- ================== END BASE JS ================== -->
	
	<!-- ================== BEGIN PAGE LEVEL JS ================== -->
    <script src="/assets/js/apps.min.js"></script>
	<!-- ================== END PAGE LEVEL JS ================== -->
    <script src="/assets/js/switcher.js"></script>
  <script src="/assets/js/functions.js"></script>
  <script src="/assets/js/login.js"></script>
	
	<script>
		$(document).ready(function() {
		    App.init();
		});
	</script>
</body>

</html>
