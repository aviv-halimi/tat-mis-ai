<?php
require_once('../_config.php');
$tbl = getVar('a');
$code = getVar('b');

echo '
<input type="hidden" name="tbl" value="' . $tbl . '" />
<input type="hidden" name="code" value="' . $code . '" />

<div class="row m-b-10">
  <div class="col-sm-12">' . uploadWidget('file', 'filename', '', '', 'multiple', 'Select file &hellip;', 'btn-info') . '</div>
</div>

<div class="row m-b-10">
  <div class="col-sm-12"><textarea name="description" class="form-control" rows="3" placeholder="Notes ..."></textarea></div>
</div>';
?>