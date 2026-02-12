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
            return node.id === \'#\' ? \'/ajax/sops.php\' : \'/ajax/sops.php\';
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
      "plugins" : [ ' . iif($_Session->HasModulePermission('sop-manage'), '"contextmenu", "dnd", ') . '"state", "types", "wholerow" ]
    }).bind("move_node.jstree rename_node.jstree create_node.jstree delete_node.jstree", function(event, data) {
      console.log(event.type);
      console.log(data);
      var type = event.type;
      var id = data.node.id;
      var name = data.node.text;
      if (type === \'move_node\') {
        postAjax(\'sop-sort\', {id:id, parent:data.parent, sort:data.position}, \'status_sop\', function(res) {
        });
      } else if (type === \'rename_node\') {
        postAjax(\'sop-rename\', {id: id, name: name}, \'status_sop\', function(res) {
          $(\'.sop-name\').html(res.name);
        });
      } else if (type === \'create_node\') {
        postAjax(\'sop-add\', {parent:data.parent, sort:data.position, name: data.node.text}, \'status_sop\', function(res) {
          $(\'#sop\').jstree(true).set_id(data.node, res.id);
        });
      } else if (type === \'delete_node\') {
        postAjax(\'sop-del\', {id:id}, \'status_sop\', function(res) {
        });
      }

  });

  $(\'.btn-sop-edit\').on(\'click\', function(e) {
    e.preventDefault();
    $(\'.sop-content\').slideUp();
    $(\'.sop-edit\').slideDown();
  });

  $(\'.btn-sop-cancel\').on(\'click\', function(e) {
    e.preventDefault();
    $(\'.sop-content\').slideDown();
    $(\'.sop-edit\').slideUp();
  });
  
  $(\'.btn-sop-save\').on(\'click\', function(e) {
    e.preventDefault();
    postAjax(\'sop-save\', {id: $(this).data(\'id\'), url: $(\'#sop_url\').val()}, \'status_sop\', function(data) {
      $(\'.sop-content\').html(data.url + data.link);
      $(\'.sop-content\').slideDown();
      $(\'.sop-edit\').slideUp();
    });
  });

});


$(\'#sop\').off(\'select_node.jstree\').on(\'select_node.jstree\', function(e, data) {
  var id = data.selected[0];
  postAjax(\'sop\', {id: id}, \'status_sop\', function(res) {
    $(\'.sop-name\').html(res.name);
    $(\'.sop-content\').html(res.url + res.link);
    $(\'#sop_url\').val(res.url)
    $(\'.btn-sop-save\').data(\'id\', res.id);
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
            <div class="panel-heading-btn">
                <button type="button" class="btn btn-warning btn-sm btn-sop-edit"><i class="ion-edit"></i> Edit</button>
            </div>
            <h4 class="panel-title sop-name">SOP Details</h4>
        </div>
        <div class="panel-body">
          <div id="status_sop"></div>
          <div class="sop-content"></div>
          <div class="sop-edit" style="display:none">
            <div class="row">
                <div class="col-md-3 input-label">URL</div>
                <div class="col-md-9"><input type="text" name="sop_url" id="sop_url" class="form-control" placeholder="Add URL" /></div>
            </div>
            <div class="row mt-1">
                <div class="col-md-3 input-label">Group Access</div>
                <div class="col-md-9"><?php echo displayKeys('admin_group_ids', null); ?></div>
            </div>
            <div class="row mt-1">
                <div class="col-md-3 input-label">Admin Access</div>
                <div class="col-md-9"><?php echo displayKeys('admin_ids', null); ?></div>
            </div>
            <div class="row">
                <div class="col-md-3 input-label"></div>
                <div class="col-md-9">
                    <button type="button" class="btn btn-primary btn-sop-save mt-1 mr-2" data-id=""><i class="fa fa-check"></i> Save</button>
                    <button type="button" class="btn btn-default btn-sop-cancel mt-1"><i class="fa fa-times"></i> Cancel</button>
                </div>
            </div>
          </div>
        </div>
    </div>
  </div>
</div>

<?php
include_once('inc/footer.php'); 
?>