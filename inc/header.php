<?php
require_once(dirname(__FILE__) . '/../_config.php');
$charts = array();
if (!isset($page_title)) {
	if (isset($meta_title)) {
		$page_title = $meta_title;
	}
	else {
		$page_title = '';
	}
}
if (!isset($page_icon)) $page_icon = '';
if (isset($meta_title)) {
	$meta_title .= ' - ' . $_Session->site_name;
}
else {
	$meta_title = $_Session->site_name;
}

if (!isset($no_header)) $no_header = false;

$markers = array();
$charts = array();
$legend = true;
$yaxis = array('min' => 0, array('labels' => array('format' => '{value}'), 'title' => array('text' => 'Category')));

$data = $a_data = $data_1 = $data_2 = $date_3 = $series = $categories = array();
?>
<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<head>
	<meta charset="utf-8" />
	<title><?php echo $meta_title; ?></title>
	<link rel="shortcut icon" href="/assets/img/favicon.png" />
	<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
	
	<!-- ================== BEGIN BASE CSS STYLE ================== -->
	<link href="https://fonts.googleapis.com/css?family=Roboto+Condensed:400,300,700" rel="stylesheet" id="fontFamilySrc" />
	<link href="/assets/plugins/jquery-ui/jquery-ui.min.css" rel="stylesheet" />
	<link href="/assets/plugins/bootstrap/bootstrap-4.1.1/css/bootstrap.min.css" rel="stylesheet" />
	<link href="/assets/plugins/font-awesome/5.1/css/all.css" rel="stylesheet" />
	<link href="/assets/plugins/ionicons/css/ionicons.min.css" rel="stylesheet" />
	<link href="/assets/css/animate.min.css" rel="stylesheet" />
	<link href="/assets/css/style.min.css" rel="stylesheet" />
	<!-- ================== END BASE CSS STYLE ================== -->

	<link href="/assets/plugins/bootstrap-datepicker/css/bootstrap-datepicker.css" rel="stylesheet" />
	<!-- <link href="/assets/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.css" rel="stylesheet" /> -->
	<link href="/assets/plugins/ionRangeSlider/css/ion.rangeSlider.css" rel="stylesheet" />
	<link href="/assets/plugins/ionRangeSlider/css/ion.rangeSlider.skinNice.css" rel="stylesheet" />
	<link href="/assets/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css" rel="stylesheet" />
	<link href="/assets/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css" rel="stylesheet" />
    <link href="/assets/plugins/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />
    <link href="/assets/plugins/bootstrap-eonasdan-datetimepicker/build/css/bootstrap-datetimepicker.min.css" rel="stylesheet" />


	<!-- ================== BEGIN PAGE LEVEL CSS STYLE ================== -->
	<link href="/assets/plugins/bootstrap-calendar/css/bootstrap_calendar.css" rel="stylesheet" />

	<link href="/assets/plugins/DataTables/media/css/dataTables.bootstrap.min.css" rel="stylesheet" />
	<link href="/assets/plugins/DataTables/extensions/Buttons/css/buttons.bootstrap.min.css" rel="stylesheet" />
	<link href="/assets/plugins/DataTables/extensions/Responsive/css/responsive.bootstrap.min.css" rel="stylesheet" />
  <link href="/assets/plugins/select2/dist/css/select2.min.css" rel="stylesheet" />
	<link href="/assets/plugins/switchery/switchery.min.css" rel="stylesheet" />
  <link href="/assets/plugins/jQuery-File-Upload-master/css/jquery.fileupload-ui.css" rel="stylesheet" />
	<link href="/assets/plugins/nestable/css/nestable.css" rel="stylesheet" type="text/css">
	<!-- ================== END PAGE LEVEL CSS STYLE ================== -->
  <link rel="stylesheet" href="/assets/plugins/mediaelement/mediaelementplayer.css">
	<link href="/assets/css/default.css?v=20250914" rel="stylesheet" />
    
	<!-- ================== BEGIN BASE JS ================== -->
	<script src="/assets/plugins/pace/pace.min.js"></script>
	<?php
	if (isset($header)) echo $header;
	?>
	<!-- ================== END BASE JS ================== -->
	
	<!--[if lt IE 9]>
	    <script src="/assets/crossbrowserjs/excanvas.min.js"></script>
	<![endif]-->
</head>
<body class="font-roboto1">
	<!-- begin #page-loader 
	<div id="page-loader" class="page-loader fade in"><span class="spinner">Loading...</span></div>-->
	<!-- end #page-loader -->

	<!-- begin #page-container -->
	<div id="page-container" class="page-container page-header-fixed page-sidebar-fixed page-with-two-sidebar page-with-footer<?php echo (($_Session->GetAdminSettings('sidebar-minify') == 1)?' page-sidebar-minified':''); ?>"<?php echo iif($no_header, ' style="padding-top:0"'); ?>>
		<!-- begin #header -->
		<div id="header" class="header navbar navbar-default navbar-fixed-top<?php echo iif($no_header, ' hide'); ?>">
			<!-- begin container-fluid -->
			<div class="container-fluid">
				<!-- begin mobile sidebar expand / collapse button -->
				<div class="navbar-header">
					<a href="/" class="navbar-brand"><img src="<?php echo $_Session->site_logo_url; ?>" class="logo" alt="" /></a>
					<button type="button" class="navbar-toggle" data-click="sidebar-toggled">
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
				</div>
				<!-- end mobile sidebar expand / collapse button -->
				
				<!-- begin navbar-right -->
				<ul class="nav navbar-nav navbar-right">
					<li class="hide">
						<form class="navbar-form form-input-flat">
							<div class="form-group">
								<input type="text" class="form-control" placeholder="Enter keyword..." />
								<button type="submit" class="btn btn-search"><i class="fa fa-search"></i></button>
							</div>
						</form>
					</li>
					<li class="dropdown">
						<a href="javascript:;" data-toggle="dropdown" class="dropdown-toggle">            
							<span class="image"><img src="/media/site/favicon.png" /></span>
							<?php echo getDisplayName('store', $_Session->store_id); ?>
							<b class="caret"></b>
							</a>
							<ul class="dropdown-menu dropdown-notification pull-right">
							<li class="dropdown-header text-center">
								Select Store
							</li>
							<?php

							if ($_Session->store_ids) {
								$_a_store_ids = json_decode($_Session->store_ids, true);
								foreach($_a_store_ids as $_store) {
									if (isset($_store['store_id']) and isset($_store['employee_id'])) {
										$_store_id = $_store['store_id'];
										$_rs = getRs("SELECT * FROM store WHERE " . is_enabled() . " AND JSON_CONTAINS(?, CAST(store_id AS CHAR), '$')", array($_store_id));
										foreach ($_rs as $_s) {
											echo '
											<li class="notification-item">
												<a href="" class="btn-store" data-c="' . $_s['store_code'] . '" data-title="' . $_s['store_name'] . '">
													<div class="media"><img src="/media/site/favicon.png" /></div>
													<div class="message">
														<h6 class="title">' . $_s['store_name'] . '</h6>
														<div class="time">' . $_s['address'] . '</div>
													</div>' . iif($_Session->store_id == $_s['store_id'], '
													<div class="option" data-toggle="tooltip" data-title="Current Store" data-click="set-message-status" data-status="unread" data-container="body">
														<i class="fa fa-circle"></i>
													</div>') . '
												</a>
											</li>';
										}
									}
								}
							}
							?>
						</ul>
					</li>
					<li class="dropdown navbar-user">
						<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown">
							<span class="image"><img src="<?php echo $_Session->image_url; ?>" alt="<?php echo $_Session->admin_name; ?>" /></span>
							<span class="hidden-xs"><?php echo $_Session->admin_name; ?></span> <b class="caret"></b>
						</a>
						<ul class="dropdown-menu pull-right">
							<li><a href="" class="btn-dialog" data-url="profile" data-title="Edit Profile">Edit Profile</a></li>
							<li><a href="" class="btn-dialog" data-url="password">Change Password</a></li>
							<li class="divider"></li>
							<li><a href="/login">Log Out</a></li>
						</ul>
					</li>
				</ul>
				<!-- end navbar-right -->
			</div>
			<!-- end container-fluid -->
		</div>
		<!-- end #header -->
		
		<!-- begin #sidebar -->
		<div id="sidebar" class="sidebar sidebar-danger"<?php echo iif($no_header, ' style="top:0"'); ?>>
			<!-- begin sidebar scrollbar -->
			<div data-scrollbar="true" data-height="100%">
				<?php echo $_Session->GetModules(); ?>
			</div>
			<!-- end sidebar scrollbar -->
		</div>
		<div class="sidebar-bg"></div>
		<!-- end #sidebar -->
		
		<!-- begin #content -->
		<div id="content" class="content">
			<?php if (!$no_header and strlen($page_title)) {
			if (isset($_breadcrumb)) {
			echo $_breadcrumb;
			}
			else {
			echo '
			<ol class="breadcrumb pull-right">
				<li class="breadcrumb-item"><a href="/">Dashboard</a></li>
				<li class="breadcrumb-item active">'. $page_title . '</li>
			</ol>';
			}
			?>
			<h1 class="page-header"><?php echo $page_icon . ' ' . $page_title; ?> <small></small></h1>
			<?php } ?>
			
	<div class="section-container<?php echo iif(!$no_header, ' section-with-top-border p-b-5'); ?>"<?php echo iif($no_header, ' style="padding-top:0"'); ?>>
