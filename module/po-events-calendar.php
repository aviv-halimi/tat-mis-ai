<?php
$header = '
<link rel="stylesheet" href="/assets/plugins/fullcalendar/fullcalendar.min.css" />';

$footer = '
<script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>
<script src="/assets/js/scheduling.js?v=2022.01.04.14.58"></script>';

include_once('inc/header.php');
echo '
<div class="panel panel-default-1">
    <div class="panel-heading">
      <div class="panel-heading-btn">
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning btn-display-options-toggle" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
        <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger" data-click="panel-remove"><i class="fa fa-times"></i></a>
      </div>
      <h4 class="panel-title">Appointments</h4>
    </div>
    <div class="panel-body">
        <div id="calendar"></div>
    </div>
</div>
';

include_once('inc/footer.php');

?>