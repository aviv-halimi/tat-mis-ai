<?php
require_once('../_config.php');
$rs = getRs("SELECT * FROM admin WHERE admin_id = ?", array($_Session->admin_id));
if ($r = getRow($rs)) {
echo '
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Name:</div>
<div class="col-sm-5"><input type="text" name="first_name" value="' . $r['first_name'] . '" class="form-control" placeholder="First name ..." /></div>
<div class="col-sm-5"><input type="text" name="last_name" value="' . $r['last_name'] . '" class="form-control" placeholder="Last name ..." /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Title:</div>
<div class="col-sm-10"><input type="text" name="title" value="' . $r['title'] . '" class="form-control" placeholder="" /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Phone:</div>
<div class="col-sm-10"><input type="text" name="phone" value="' . $r['phone'] . '" class="form-control" /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">E-mail Address:</div>
<div class="col-sm-10"><input type="email" name="email" value="' . $r['email'] . '" class="form-control" /></div>
</div>
<div class="row form-input-flat mb-2">
<div class="col-sm-2 col-form-label">Image:</div>
<div class="col-sm-10">' . uploadWidget('admin', 'image', $r['image'], (strlen($r['image'])?'/media/admin/md/' . $r['image']:'')) . '</div>
</div>
';
}
?>