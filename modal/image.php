<?php
require_once('../_config.php');
$a = getVar('a');
echo '
<div class="row text-center">
  <div class="col-sm-12"><img src="' . $a . '" class="img-responsive img-fluid" alt="" /></div>
</div>';
?>