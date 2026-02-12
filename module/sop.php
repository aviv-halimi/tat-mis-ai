<?php
$header = '<link href="/assets/plugins/jstree/themes/default/style.min.css" rel="stylesheet" />
<style type="text/css">
.jstree-anchor {
  white-space : normal !important;
  height : auto !important;
}
</style>';
$footer = '<script src="/assets/plugins/jstree/jstree.min.js"></script>
<script>
$(function () {
  $(\'#sop\')
    .jstree({
      "core" : {
        "animation" : 0,
        "check_callback" : true,
        "themes" : { "stripes" : false },
        "data" : {
          "url" : function (node) {
            return node.id === \'#\' ? \'/ajax/sop-folders.php\' : \'/ajax/sop-folders.php\';
          },
          "data" : function (node) {
            return { \'id\' : node.id };
          }
        }
      },
      "types" : {
        "#" : { "icon" : "ion-file", "max_depth" : 3 },
        "root" : { "max_depth" : 3, "icon" : "ion-folder" },
        "default" : { "icon" : "ion-folder" },
        "file" : { "icon" : "fa fa-file-alt text-primary" }
      },
      "plugins" : [ ' . iif(false, '"contextmenu", "dnd", ') . '"state", "types", "wholerow" ]
    }).bind("move_node.jstree rename_node.jstree create_node.jstree delete_node.jstree", function(event, data) {
  });
});


$(\'#sop\').off(\'select_node.jstree\').on(\'select_node.jstree\', function(e, data) {
  var id = data.selected[0];
  postAjax(\'sop-content\', {id: id}, \'status_sop\', function(res) {
    $(\'.sop-name\').html(res.name);
    $(\'.sop-content\').html(res.content);
  });
});

</script>

';
include_once('inc/header.php');
?>
<!--
<div class="row">
  <div class="col-md-4 col-sm-8 col-xs-8">
  </div>
  <div class="col-md-2 col-sm-4 col-xs-4" style="text-align:right;">
    <input type="text" value="" style="box-shadow:inset 0 0 4px #eee; width:120px; margin:0; padding:6px 12px; border-radius:4px; border:1px solid silver; font-size:1.1em;" id="demo_q" placeholder="Search" />
  </div>
</div>
-->
<div class="row">
  <div class="col-md-3">
    <div class="widget">
      <div class="widget-header bg-primary py-2">
        <h4 class="text-white">Folders</h4>
      </div>
      <div class="widget-body px-0 py-2">
        <div id="sop" class="demo" style="min-height:200px;"></div>
      </div>
    </div>
  </div>
  <div class="col-md-9">

    <div class="panel panel-inverse display-options">
        <div class="panel-heading">
            <h4 class="panel-title sop-name">SOP Details</h4>
        </div>
        <div class="panel-body">
          <div id="status_sop"></div>
          <div class="sop-content"></div>
        </div>
    </div>
  </div>
</div>

<?php
include_once('inc/footer.php'); 
?>