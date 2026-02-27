<?php
include_once('inc/header.php');
$daily_discount_report_code = getVar('c');
?>
<style>
.dd-format-switch-wrap { cursor: pointer; user-select: none; }
.dd-format-switch-cell { display: inline-block; position: relative; width: 40px; height: 22px; flex-shrink: 0; vertical-align: middle; }
.dd-format-switch-wrap .dd-report-format-switch { position: absolute; left: 0; top: 0; width: 40px; height: 22px; margin: 0; opacity: 0; z-index: 1; cursor: pointer; }
.dd-format-switch-wrap .dd-format-switch-slider {
	display: block;
	width: 40px;
	height: 22px;
	border-radius: 11px;
	background: #ced4da;
	position: absolute;
	left: 0;
	top: 0;
	transition: background-color .2s;
}
.dd-format-switch-wrap .dd-format-switch-slider::after {
	content: '';
	position: absolute;
	left: 2px;
	top: 2px;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: #fff;
	box-shadow: 0 1px 3px rgba(0,0,0,.3);
	transition: transform .2s;
}
.dd-format-switch-wrap .dd-report-format-switch:checked + .dd-format-switch-slider {
	background-color: #28a745;
}
.dd-format-switch-wrap .dd-report-format-switch:checked + .dd-format-switch-slider::after {
	transform: translateX(18px);
}
.dd-format-switch-wrap .dd-report-format-switch:focus + .dd-format-switch-slider { box-shadow: 0 0 0 2px rgba(40,167,69,.25); }
</style>
<?php
echo '<div id="dd-report-qbo-log-box" class="mb-3"><div class="card"><div class="card-header py-2"><strong>QBO Push log</strong></div><div id="dd-report-qbo-log" class="card-body py-2 small" style="max-height:180px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;">Waiting for activity…</div></div></div>';

$rs = getRs("SELECT r.* FROM daily_discount_report r WHERE " . is_enabled('r') . " AND r.daily_discount_report_code = ?", $daily_discount_report_code);
if ($r = getRow($rs)) {
    echo $_Session->TableManager('daily-discount-report-brands', $r['daily_discount_report_id']);
}
include_once('inc/footer.php'); 
?>