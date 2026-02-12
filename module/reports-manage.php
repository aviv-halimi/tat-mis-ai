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
  $(\'#reports\')
    .jstree({
      "core" : {
        "animation" : 0,
        "check_callback" : true,
        "themes" : { "stripes" : false },
        "data" : {
          "url" : function (node) {
            return node.id === \'#\' ? \'/ajax/reports.php\' : \'/ajax/reports.php\';
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
      "plugins" : [ ' . iif($_Session->HasModulePermission('reports-manage'), '"contextmenu", "dnd", ') . '"state", "types", "wholerow" ]
    }).bind("move_node.jstree rename_node.jstree create_node.jstree delete_node.jstree", function(event, data) {
      console.log(event.type);
      console.log(data);
      var type = event.type;
      var id = data.node.id;
      var name = data.node.text;
      if (type === \'move_node\') {
        postAjax(\'report-sort\', {id:id, parent:data.parent, sort:data.position}, \'status_report\', function(res) {
        });
      } else if (type === \'rename_node\') {
        postAjax(\'report-rename\', {id: id, name: name}, \'status_report\', function(res) {
          $(\'.report-name\').html(res.name);
        });
      } else if (type === \'create_node\') {
        postAjax(\'report-add\', {parent:data.parent, sort:data.position, name: data.node.text}, \'status_report\', function(res) {
          $(\'#reports\').jstree(true).set_id(data.node, res.id);
        });
      } else if (type === \'delete_node\') {
        postAjax(\'report-del\', {id:id}, \'status_report\', function(res) {
        });
      }

  });

  $(\'.btn-report-edit\').on(\'click\', function(e) {
    e.preventDefault();
    $(\'.report-content\').slideUp();
    $(\'.report-edit\').slideDown();
  });
  
  $(\'.btn-report-save\').on(\'click\', function(e) {
    e.preventDefault();
    postAjax(\'report-save\', {id: $(this).data(\'id\'), content: $(\'#report_content\').val()}, \'status_report\', function(data) {
      $(\'.report-content\').html(data.html);
      $(\'.report-content\').slideDown();
      $(\'.report-edit\').slideUp();
    });
  });

});


$(\'#reports\').off(\'select_node.jstree\').on(\'select_node.jstree\', function(e, data) {
  var id = data.selected[0];
  postAjax(\'report\', {id: id}, \'status_report\', function(res) {
    $(\'.report-name\').html(res.name);
    $(\'.report-content\').html(res.html);
    $(\'#report_content\').val(res.html)
    $(\'.btn-report-save\').data(\'id\', res.id);
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
        <div id="reports" class="demo" style="min-height:200px;"></div>
      </div>
    </div>
  </div>
  <div class="col-md-9">

    <div class="panel panel-inverse">
        <div class="panel-heading">
                  <div class="panel-heading-btn">
                      <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
          <button type="button" class="btn btn-warning btn-sm btn-report-edit"><i class="ion-edit"></i> Edit</button>
                  </div>
            <h4 class="panel-title report-name">Report Details</h4>
        </div>
        <div class="panel-body">
          <div id="status_report"></div>
          <div class="report-content"></div>
          <div class="report-edit" style="display:none">
            <textarea name="report_content" id="report_content" class="form-control" placeholder="Add HTML content" rows="20"></textarea>
            <button type="button" class="btn btn-primary btn-report-save mt-1 mr-2" data-id=""><i class="fa fa-check"></i> Save</button>
            <button type="button" class="btn btn-default btn-report-cancel mt-1"><i class="fa fa-times"></i> Cancel</button>
          </div>
        </div>
    </div>
  </div>
</div>

<?php
include_once('inc/footer.php'); 
?>