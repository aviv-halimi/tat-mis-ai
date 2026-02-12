
            </div>
            <!-- begin #footer -->
            <div id="footer" class="footer hide">
                <div class="row">
                <div class="col-sm-6">
                &copy; <?php echo date('Y'); ?>. All Right Reserved
                </div>
                <div class="col-sm-6 text-right">

                <span class="pull-right-1">
                    <a href="javascript:;" class="btn-scroll-to-top" data-click="scroll-top">
                        <i class="fa fa-arrow-up"></i> <span class="hidden-xs">Back to Top</span>
                    </a>
                </span>
                </div>
                </div>
            </div>
            <!-- end #footer -->
		</div>
		<!-- end #content -->
		
	</div>
	<!-- end page container -->
	


  <div id="modal" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title"></h4>
        </div>
        <form action="" method="post">
        <div class="modal-body">
        </div>
        <div class="modal-footer">
          <div id="modal_status" class="status"></div>
          <span class="form-btns">
          <button type="submit" class="btn width-100 btn-primary btn-submit">Save changes</button>
          <button type="button" class="btn width-100 btn-secondary btn-modal-close" data-dismiss="modal">Close</button>
          </span>
        </div>
        </form>
      </div>
    </div>
  </div>


  <input type="hidden" id="page_name" value="<?php echo $module_code; ?>" />

	
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
	<!-- ================== END BASE JS ================== -->
	
    <!-- ================== BEGIN PAGE LEVEL JS ================== -->
    <script src="/assets/plugins/bootstrap-calendar/js/bootstrap_calendar.min.js"></script>
	<script src="/assets/plugins/ionRangeSlider/js/ion-rangeSlider/ion.rangeSlider.min.js"></script>
	<script src="/assets/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js"></script>
	<script src="/assets/plugins/masked-input/masked-input.min.js"></script>
    <script src="/assets/plugins/bootstrap-daterangepicker/moment.js"></script>
    <script src="/assets/plugins/bootstrap-eonasdan-datetimepicker/build/js/bootstrap-datetimepicker.min.js"></script>
    


	<script src="/assets/plugins/DataTables/media/js/jquery.dataTables.js"></script>
	<script src="/assets/plugins/DataTables/media/js/dataTables.bootstrap.min.js"></script>
  <script src="/assets/plugins/DataTables/extensions/Responsive/js/dataTables.responsive.min.js"></script>
	<script src="/assets/plugins/DataTables/extensions/Buttons/js/dataTables.buttons.min.js"></script>
  <script src="/assets/plugins/DataTables/extensions/Buttons/js/buttons.bootstrap.min.js"></script>
  <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
	<script src="/assets/plugins/DataTables/extensions/Buttons/js/buttons.print.min.js"></script>
	<script src="/assets/plugins/DataTables/extensions/Buttons/js/buttons.flash.min.js"></script>
	<script src="/assets/plugins/DataTables/extensions/Buttons/js/buttons.html5.min.js"></script>
  <script src="/assets/plugins/select2/dist/js/select2.min.js"></script>
	<script src="/assets/plugins/switchery/switchery.min.js"></script>

    <script type="text/javascript" src="/assets/plugins/jQuery-File-Upload-master/js/vendor/jquery.ui.widget.js"></script>
    <script type="text/javascript" src="/assets/plugins/jQuery-File-Upload-master/js/jquery.iframe-transport.js"></script>
    <script type="text/javascript" src="/assets/plugins/jQuery-File-Upload-master/js/jquery.fileupload.js"></script>

    <script type="text/javascript" src="/assets/plugins/ckeditor/ckeditor.js"></script>
    
    <script src="/assets/js/demo.min.js"></script>
    <script src="/assets/js/apps.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>

    <script src="/assets/js/switcher.js"></script>
    <script src="/assets/js/functions.js?v=20241217"></script>
    <script src="/assets/js/table.js?v=20250223"></script>
    <script src="/assets/js/default.js?v=20251202"></script>    
    <script src="/assets/plugins/mediaelement/mediaelement-and-player.js"></script>
	
	<script>
		$(document).ready(function() {
		    App.init();
		    Demo.init();
		});
    </script>

<?php if ($module_code == 'inventory' || (isset($charts) and sizeof($charts))) { ?>

<script src="//code.highcharts.com/highcharts.js"></script>
<script src="//code.highcharts.com/highcharts-3d.js"></script>
<script src="//code.highcharts.com/modules/data.js"></script>
<script src="//code.highcharts.com/modules/series-label.js"></script>
<script src="//code.highcharts.com/modules/exporting.js"></script>
<script src="//code.highcharts.com/modules/drilldown.js"></script>
<script src="//code.highcharts.com/highcharts-more.js"></script>
<script src="//code.highcharts.com/modules/accessibility.js"></script>



<script>
  (function(a){"object"===typeof module&&module.exports?(a["default"]=a,module.exports=a):"function"===typeof define&&define.amd?define(function(){return a}):a("undefined"!==typeof Highcharts?Highcharts:void 0)})(function(a){(function(a){a.createElement("link",{href:"https://fonts.googleapis.com/css?family\x3dRoboto+Condensed:400,700",rel:"stylesheet",type:"text/css"},null,document.getElementsByTagName("head")[0]);a.addEvent(a.Chart,"afterGetContainer",function(){this.container.style.background="transparent"});
a.theme={colors:"#fd671a #8085e9 #55BF3B #ff4444 #8d4654 #7798BF #aaeeee #ff0066 #eeaaee #DF5353 #aaeeee #ff00cc".split(" "),chart:{style:{fontFamily:"Roboto Condensed, serif"}},title:{style:{color:"black",fontSize:"16px",fontWeight:"bold"}},subtitle:{style:{color:"black"}},tooltip:{borderWidth:0},legend:{itemStyle:{fontWeight:"bold",fontSize:"13px"}},xAxis:{labels:{style:{color:"#222"}}},yAxis:{labels:{style:{color:"#666"}}},plotOptions:{series:{shadow:!0},candlestick:{lineColor:"#888"},
map:{shadow:!1}},navigator:{xAxis:{gridLineColor:"#999"}},rangeSelector:{buttonTheme:{fill:"black",stroke:"#C0C0C8","stroke-width":1,states:{select:{}}}},scrollbar:{trackBorderColor:"#C0C0C8"}};a.setOptions(a.theme)})(a)});
//# sourceMappingURL=sand-signika.js.map
</script>
<script>
Highcharts.setOptions({
    lang: {
        thousandsSep: ','
    }
});
<?php
foreach($charts as $chart) {
echo 'var _' . $chart['id'] . ' = Highcharts.chart(\'' . $chart['id'] . '\',' . json_encode($_Util->Chart($chart)) . ');';
}
?>

</script>

<?php }

if (isset($footer)) echo $footer;

?>


</body>
</html>