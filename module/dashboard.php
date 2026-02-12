<?php
require_once('inc/header.php');
echo '<div class="alert alert-warning">Welcome back, ' . $_Session->admin_name . '</div>';
?>

<div class="row">
    <?php
    if ($_Session->HasModulePermission('pos')) {
    echo '
    <div class="col-md-4">
        <div class="mb-3">
            <a href="/pos" class="btn btn-lg btn-block btn-warning">
                <span class="d-flex align-items-center text-left">
                <img src="/media/setting/po.png" style="width:60px;margin-right:5px;" />
                <span>
                    <span class="d-block mb-n1"><b>Purchase Orders</b></span>
                </span>
                </span>
            </a>
            <div class="pt-2">
                <a href="/po-new" class="btn btn-sm btn-default">Create PO</a>
                <a href="/pos" class="btn btn-sm btn-default">All POs</a>
            </div>
        </div>
    </div>';
    }
    if ($_Session->HasModulePermission('transfer-reports')) {
    echo '
    <div class="col-md-4">
        <div class="mb-3">
            <a href="/transfer-reports" class="btn btn-lg btn-block btn-info">
                <span class="d-flex align-items-center text-left">
                <img src="/media/setting/fulfillment.png" style="width:60px;margin-right:5px;" />
                <span>
                    <span class="d-block mb-n1"><b>Fullfillment Transfer</b></span>
                </span>
                </span>
            </a>
            <div class="pt-2">
                <a href="/transfer-report-new" class="btn btn-sm btn-default">New Report</a>
                <a href="/transfer-reports" class="btn btn-sm btn-default">All Reports</a>
            </div>
        </div>
    </div>';
    }
    if ($_Session->HasModulePermission('daily-discounts')) {
    echo '
    <div class="col-md-4">
        <div class="mb-3">
            <a href="/daily-discounts" class="btn btn-lg btn-block btn-primary">
                <span class="d-flex align-items-center text-left">
                <img src="/media/setting/discount.png" style="width:60px;margin-right:5px;" />
                <span>
                    <span class="d-block mb-n1"><b>Daily Discounts</b></span>
                </span>
                </span>
            </a>
            <div class="pt-2">
                <a href="/daily-discounts" class="btn btn-sm btn-default">Grid</a>
                <a href="/daily-discount" class="btn btn-sm btn-default">Table</a>
                <a href="/daily-discount-logs" class="btn btn-sm btn-default">Logs</a>
            </div>
        </div>
    </div>';
    }
    ?>
</div>

<?php 

/*
if (false) { //if ($glma) {
    ?>
			<!-- begin row -->
			<div class="row">
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-success text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fab fa-chrome"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Registered Markets</div>
                            <div class="widget-stat-number">4</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 80%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer text-left">3.10% better than last week</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-primary text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-gem"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Facilities</div>
                            <div class="widget-stat-number">71</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 60%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">10.2% better than last week</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-grey text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-hdd"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Herd Owners</div>
                            <div class="widget-stat-number">12</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 70%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">10% higher</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-inverse-dark text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-file"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Activity</div>
                            <div class="widget-stat-number">Up 29%</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 70%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">3 </div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
            </div>
            <!-- end row -->

<?php } else { ?>
			<!-- begin row -->
			<div class="row">
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-success text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fab fa-chrome"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Total Household Surveys</div>
                            <div class="widget-stat-number">839</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 80%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer text-left">3.10% better than last week</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-primary text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-gem"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Agricultural Households</div>
                            <div class="widget-stat-number">71</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 60%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">10.2% better than last week</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-grey text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-hdd"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Total Land Use</div>
                            <div class="widget-stat-number">10,428 ha</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 70%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">10% higher</div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
                <!-- begin col-3 -->
                <div class="col-sm-6 col-lg-3">
                    <!-- begin widget -->
                    <div class="widget widget-stat widget-stat-right bg-inverse-dark text-white">
                        <div class="widget-stat-btn"><a href="javascript:;" data-click="widget-reload"><i class="fa fa-redo"></i></a></div>
                        <div class="widget-stat-icon"><i class="fa fa-file"></i></div>
                        <div class="widget-stat-info">
                            <div class="widget-stat-title">Economic Activity</div>
                            <div class="widget-stat-number">Up 29%</div>
                        </div>
                        <div class="widget-stat-progress">
                            <div class="progress">
                                <div class="progress-bar" style="width: 70%"></div>
                            </div>
                        </div>
                        <div class="widget-stat-footer">3 </div>
                    </div>
                    <!-- end widget -->
                </div>
                <!-- end col-3 -->
            </div>
            <!-- end row -->

			<?php } ?>
			
			<!-- begin row -->
			<div class="row">
			    <!-- begin col-6 -->
			    <div class="col-lg-6">
			        <!-- begin panel -->
			        <div class="panel panel-inverse">
			            <div class="panel-heading">
                            <div class="panel-heading-btn">
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
                            </div>
			                <h4 class="panel-title">Visitors Analytics <small>monthly stat</small></h4>
			            </div>
			            <div class="panel-body">
                            <div class="panel-option">
                                <div class="dropdown pull-right">
                                    <a href="javascript:;" class="btn btn-white btn-rounded btn-sm" data-toggle="dropdown">Change Date <b class="caret"></b></a>
                                    <ul class="dropdown-menu">
                                        <li><a href="javascript:;">Last Week</a></li>
                                        <li><a href="javascript:;">Last Month</a></li>
                                        <li><a href="javascript:;">Last Year</a></li>
                                    </ul>
                                </div>
                                <div class="text-ellipsis">Date: 1 January 2020 - 30 January 2020</div>
                            </div>
			                <div>
			                    <div style="height: 225px"><canvas id="chart-visitor-analytics" height="100%" width="100%"></canvas></div>
                                <div id="visitor-analytics-tooltip" class="chartjs-tooltip"></div>
			                </div>
			            </div>
			        </div>
			        <!-- end panel -->
			    </div>
			    <!-- end col-6 -->
			    <!-- begin col-6 -->
			    <div class="col-lg-6">
			        <!-- begin panel -->
			        <div class="panel panel-inverse">
			            <div class="panel-heading">
                            <div class="panel-heading-btn">
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
                            </div>
			                <h4 class="panel-title"><?php echo iif($glma, 'Market Surveys', 'NASS Completion'); ?> <small>monthly stat</small></h4>
			            </div>
			            <div class="panel-body p-b-0">
			                <!-- begin row -->
                            <div class="row">
                                <!-- begin col-6 -->
                                <div class="col-sm-6 m-b-20">
                                    <div class="chart-summary-container">
                                        <div class="chart-title text-purple"><?php echo iif($glma, 'Week', 'NASS'); ?> 1</div>
                                        <div class="chart-doughnut">
                                            <canvas id="doughnut-chrome" width="85" height="85"></canvas>
                                            <div id="doughnut-chrome-tooltip" class="chartjs-tooltip"></div>
                                        </div>
                                        <div class="chart-info">
                                            <div class="chart-summary">
                                                <div class="text">Total Surveys</div>
                                                <div class="number">192,102 <small>(65.5%)</small></div>
                                            </div>
                                            <div class="chart-summary">
                                                <div class="text">Unique Surveys</div>
                                                <div class="number">52,102</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- end col-6 -->
                                <!-- begin col-6 -->
                                <div class="col-sm-6 m-b-20">
                                    <div class="chart-summary-container">
                                        <div class="chart-title text-primary"><?php echo iif($glma, 'Week', 'NASS'); ?> 2</div>
                                        <div class="chart-doughnut">
                                            <canvas id="doughnut3" width="85" height="85"></canvas>
                                            <div id="doughnut-ie-tooltip" class="chartjs-tooltip"></div>
                                        </div>
                                        <div class="chart-info">
                                            <div class="chart-summary">
                                                <div class="text">Total Surveys</div>
                                                <div class="number">2,102 <small>(2.2%)</small></div>
                                            </div>
                                            <div class="chart-summary">
                                                <div class="text">Unique Surveys</div>
                                                <div class="number">602</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end row -->
                            <!-- begin row -->
                            <div class="row">
                                <!-- begin col-6 -->
                                <div class="col-sm-6 m-b-20">
                                    <div class="chart-summary-container">
                                        <div class="chart-title text-warning"><?php echo iif($glma, 'Week', 'NASS'); ?> 3</div>
                                        <div class="chart-doughnut">
                                            <canvas id="doughnut4" width="85" height="85"></canvas>
                                            <div id="doughnut-firefox-tooltip" class="chartjs-tooltip"></div>
                                        </div>
                                        <div class="chart-info">
                                            <div class="chart-summary">
                                                <div class="text">Total Surveys</div>
                                                <div class="number">62,102 <small>(20.2%)</small></div>
                                            </div>
                                            <div class="chart-summary">
                                                <div class="text">Unique Surveys</div>
                                                <div class="number">8,402</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- end col-6 -->
                                <!-- begin col-6 -->
                                <div class="col-sm-6 m-b-20">
                                    <div class="chart-summary-container">
                                        <div class="chart-title text-success"><?php echo iif($glma, 'Week', 'NASS'); ?> 4</div>
                                        <div class="chart-doughnut">
                                            <canvas id="doughnut5" width="85" height="85"></canvas>
                                            <div id="doughnut-safari-tooltip" class="chartjs-tooltip"></div>
                                        </div>
                                        <div class="chart-info">
                                            <div class="chart-summary">
                                                <div class="text">Total Surveys</div>
                                                <div class="number">22,102 <small>(4.5%)</small></div>
                                            </div>
                                            <div class="chart-summary">
                                                <div class="text">Unique Surveys</div>
                                                <div class="number">5,291</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- end col-6 -->
                            </div>
			                <!-- end row -->
			            </div>
			        </div>
			        <!-- end panel -->
                </div>
                <!-- end col-6 -->
			</div>
			<!-- end row -->
			
			<!-- begin row -->
			<div class="row">
			    <!-- begin col-4 -->
			    <div class="col-lg-4">
			        <!-- begin panel -->
			        <div class="panel panel-inverse">
			            <div class="panel-heading">
                            <div class="panel-heading-btn">
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
                            </div>
			                <h4 class="panel-title">Recent Activities</h4>
			            </div>
			            <div class="panel-body" data-scrollbar="true" data-height="270px">
							<ul class="feeds">
								<li>
									<a href="javascript:;">
										<div class="icon bg-success-light"><i class="fa fa-check"></i></div>
										<div class="time">Just Now</div>
										<div class="desc">Survey Completed</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-info-light"><i class="fa fa-info-circle"></i></div>
										<div class="time">24 mins</div>
										<div class="desc">You have exceeded the limit of reports</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-warning-light"><i class="fa fa-file"></i></div>
										<div class="time">20 mins</div>
										<div class="desc">New alert from Enumerator <span class="label label-inverse m-l-5">910129</span></div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-danger-light"><i class="fa fa-hdd"></i></div>
										<div class="time">25 mins</div>
										<div class="desc">Server Overloaded <span class="label label-danger m-l-5">Urgent!</span></div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-grey-light"><i class="fa fa-bell"></i></div>
										<div class="time">25 mins</div>
										<div class="desc">Message from website</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-grey-light"><i class="fa fa-bug"></i></div>
										<div class="time">30 mins</div>
										<div class="desc">4 Server Script Error</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-success-light"><i class="fa fa-plus"></i></div>
										<div class="time">1 hours</div>
										<div class="desc">New user registered account</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-success-light"><i class="fa fa-check"></i></div>
										<div class="time">2 hours</div>
										<div class="desc">New download added</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-info-light"><i class="fa fa-info-circle"></i></div>
										<div class="time">3 hours</div>
										<div class="desc">You have exceeded the limit of email sender</div>
									</a>
								</li>
								<li>
									<a href="javascript:;">
										<div class="icon bg-warning-light"><i class="fa fa-file"></i></div>
										<div class="time">3 hours</div>
										<div class="desc">New pending invoice <span class="label label-inverse m-l-5">INV-149201</span></div>
									</a>
								</li>
							</ul>
			            </div>
			            <div class="panel-footer bg-white clearfix p-15">
                            <a href="javascript:;" class="text-inverse pull-right m-b-2 m-t-2 m-r-5">See more records <i class="fa fa-angle-double-right"></i></a>
			            </div>
			        </div>
			        <!-- end panel -->
			    </div>
			    <!-- end col-4 -->
			    <!-- begin col-4 -->
			    <div class="col-lg-4">
			        <!-- begin panel -->
			        <div class="panel panel-inverse">
			            <div class="panel-heading">
                            <div class="panel-heading-btn">
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
                            </div>
			                <h4 class="panel-title">Recent Comments</h4>
			            </div>
			            <div class="panel-body" data-scrollbar="true" data-height="270px">
							<ul class="chats">
								<li class="left">
									<a href="javascript:;" class="image"><img alt="" src="/media/admin/sm/alieu.jpg" /></a>
									<div class="message">
										<a href="javascript:;" class="name">Admin</a>
										Lorem ipsum dolor sit amet, consectetuer adipiscing elit volutpat. Praesent mattis interdum arcu eu feugiat.
										<span class="date-time">11:23pm</span>
									</div>
								</li>
								<li class="right">
									<a href="javascript:;" class="image"><img alt="" src="/media/admin/sm/alieu.jpg" /></a>
									<div class="message">
										<a href="javascript:;" class="name">Me</a>
										Nullam posuere, nisl a varius rhoncus, risus tellus hendrerit neque.
										<span class="date-time">08:12am</span>
									</div>
								</li>
								<li class="left">
									<a href="javascript:;" class="image"><img alt="" src="/media/admin/sm/alieu.jpg" /></a>
									<div class="message">
										<a href="javascript:;" class="name">Admin</a>
										Euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.
										<span class="date-time">09:20am</span>
									</div>
								</li>
								<li class="left">
									<a href="javascript:;" class="image"><img alt="" src="/media/admin/sm/alieu.jpg" /></a>
									<div class="message">
										<a href="javascript:;" class="name">Admin</a>
										Nullam iaculis pharetra pharetra. Proin sodales tristique sapien mattis placerat.
										<span class="date-time">11:15am</span>
									</div>
								</li>
							</ul>
                        </div>
                        <div class="panel-footer bg-white">
                            <form name="send_message_form" class="form-input-flat">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="message" placeholder="Enter your message here.">
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary" type="button">Send</button>
                                    </span>
                                </div>
                            </form>
                        </div>
			        </div>
			        <!-- end panel -->
			    </div>
			    <!-- end col-4 -->
			    <!-- begin col-4 -->
			    <div class="col-lg-4">
			        <!-- begin panel -->
			        <div class="panel panel-inverse">
			            <div class="panel-heading">
                            <div class="panel-heading-btn">
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                                <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
                            </div>
			                <h4 class="panel-title">Most active enumerators</h4>
			            </div>
			            <div class="panel-body">
			                <ul class="project-summary">
			                    <li>
			                        <div class="project-title">Foday (1,221)</div>
			                        <div class="progress">
			                            <div class="progress-bar" style="width: 98%"></div>
			                        </div>
			                    </li>
			                    <li>
			                        <div class="project-title">Modou Dem (930)</div>
			                        <div class="progress">
			                            <div class="progress-bar" style="width: 82%"></div>
			                        </div>
			                    </li>
			                    <li>
			                        <div class="project-title">Ida Njie (300)</div>
			                        <div class="progress">
			                            <div class="progress-bar" style="width: 70%"></div>
			                        </div>
			                    </li>
			                    <li>
			                        <div class="project-title">Jim Mendy (123)</div>
			                        <div class="progress">
			                            <div class="progress-bar" style="width: 55%"></div>
			                        </div>
			                    </li>
			                    <li>
			                        <div class="project-title">Admin (92)</div>
			                        <div class="progress">
			                            <div class="progress-bar" style="width: 12%"></div>
			                        </div>
			                    </li>
			                    <li class="users">
			                        <div class="title">People involved </div>
			                        <ul class="image-list">
			                            <li><img src="/assets/img/user_8.jpg" alt="" /></li>
			                            <li><img src="/assets/img/user_9.jpg" alt="" /></li>
			                            <li><img src="/assets/img/user_10.jpg" alt="" /></li>
			                            <li><img src="/assets/img/user_11.jpg" alt="" /></li>
			                            <li><img src="/assets/img/user_12.jpg" alt="" /></li>
			                            <li><img src="/assets/img/user_13.jpg" alt="" /></li>
			                            <li class="add-btn">
			                                <a href="javascript:;"><i class="fa fa-plus"></i></a>
			                            </li>
			                        </ul>   
			                    </li>
			                </ul>
			            </div>
			        </div>
			        <!-- end panel -->
			    </div>
			    <!-- end col-4 -->
			</div>
			<!-- end row -->

<?php

*/

require_once('inc/footer.php');


?>