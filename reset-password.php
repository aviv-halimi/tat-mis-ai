<?php
define('SkipAuth', true);
require_once('_config.php');

$admin_id = $is_expired = null;
$password_token = getVar('t');
$_Session->Logout();

$rs = getRs("SELECT admin_id, admin_name, first_name, is_enabled, CASE WHEN CURRENT_TIMESTAMP < DATE_ADD(date_password_token, INTERVAL 1 HOUR) THEN 0 ELSE 1 END AS is_expired FROM admin WHERE password_token = ? AND " . is_active(), array($password_token));

if ($r = getRow($rs)) {
  $is_expired = $r['is_expired'];
  if (!$is_expired) {
    $admin_id = $r['admin_id'];
    $admin_name = $r['admin_name'];
  }
}

?><!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->

<head>
	<meta charset="utf-8" />
	<title><?php echo $_Session->site_name; ?> - Reset Your Password</title>
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
                <img src="<?php echo $_Session->site_logo_url; ?>" height="48" class="pull-right" alt="" /> <?php echo $_Session->site_name; ?>
            </div>
		    <!-- end login-brand -->
		    <!-- begin login-content -->
            <div class="login-content">
              <?php
              if ($admin_id) {
              ?>
                <form  id="f_login" action="" method="post" class="form-input-flat" style="display:none;">
                	<h4 class="text-center m-t-0 m-b-20">Login to your account</h4>
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
                        <a href="" class="text-muted btn-reset-password">Reset your password</a>
                    </div>
                </form>
                <form  id="f_reset-password" action="" method="post" class="form-input-flat">
                	<h4 class="text-center m-t-0 m-b-20">Reset Your Account Password</h4>
                    <div class="alert alert-primary">Welcome back <b><?php echo $admin_name; ?></b>. Please complete the following fields to reset your account password</div>
                    <input type="hidden" name="token" value="<?php echo $password_token; ?>" />
                    <div class="form-group">
                        <input type="password" class="form-control input-lg" name="password1" placeholder="Please enter new password" />
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control input-lg" name="password2" placeholder="Confirm your new password" />
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
              <?php } else {
                echo '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <b>Invalid reset password link</b>
                ' . iif(!$is_expired, '<p>If you are attempting to reset your password, please click on the link directly or copy and paste the link into the address bar. Do you attempt to retype it manually.</p>', '<p>The reset password link your supplied is no longer valid. Please return to login page to request another link.</p>') . '</div>
                <p><a href="/" class="btn btn-default">Return to Login Page</a></p>';
              } ?>
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
