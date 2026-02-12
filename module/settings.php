<?php
require_once ('./_config.php');


if (isset($_POST['TableName'])) {
	$rs = getRs("SELECT setting_id, setting_code, setting_name, type, value FROM setting WHERE " . is_enabled());
	foreach($rs as $row) {
		if ($row['type'] == 'bool') {
			$value = getVarInt('setting_' . $row['setting_id']);
		}
		else {
			$value = getVar('setting_' . $row['setting_id']);
		}
		setRs("UPDATE setting SET value = ? WHERE setting_id = ?", array($value, $row['setting_id']));
	}
	echo json_encode(array('success' => true, 'response' => 'Saved successfully.'));
	exit();
}

$TableName = 'setting';
$PageTitle = 'Setting';
$PageTitles = 'Settings';
$PageIcon = 'icon-cog3';

include_once ('./inc/header.php');

$rs = getRs("SELECT setting_id, setting_code, setting_name, type, options, value FROM setting WHERE " . is_enabled());


echo '<div class="row">
<div class="col-12">
	<div class="card shadow-base">
		<div class="card-header tx-medium"><b>Edit Settings</b></div>
		<div class="card-body">
			<form method="post" action="" class="form-horizontal f-tbl" role="form" id="f_tbl">
				<input type="hidden" name="TableName" id="TableName" value="setting" />
				<input type="hidden" name="PageName" id="PageName" value="settings" />
				';
foreach($rs as $row) {
	echo '<div class="form-group">
	<label class="col-sm-2 control-label">' . iif($row['type'] != 'bool', $row['setting_name'], '<label for="setting_' . $row['setting_id'] . '">' . $row['setting_name'] . '</label>') . ':</label>
	<div class="col-sm-10">';
	if ($row['type'] == 'bool') {
    echo '<input type="hidden" name="setting_' . $row['setting_id'] . '" value="" />
    <div class="icheck-primary">
			<input type="checkbox" class="setting_' . $row['setting_id'] . '" name="setting_' . $row['setting_id'] . '" id="setting_' . $row['setting_id'] . '" data-on-text="Yes" data-off-text="No" value="1" data-render="switchery" data-theme="primary" data-on-color="success" data-off-color="default"' . iif($row['value'] == 1, ' checked="checked"') . '/ >
		</div>';
	}
	elseif (strlen($row['options'])) {
		echo '<select id="setting_' . $row['setting_id'] . '" name="setting_' . $row['setting_id'] . '" class="form-control">';
		$a = explode(',', $row['options']);
		foreach($a as $i) {
			echo '<option value="' . $i . '"' . iif($i == $row['value'], ' selected') . '>' . $i . '</option>';
		}
		echo '</select>';
	}
	elseif ($row['type'] == 'file') {
		echo '<input type="hidden" name="setting_' . $row['setting_id'] . '" id="setting_' . $row['setting_id'] . '" value="' . $row['value'] . '" multiple="multiple" />
		<div class="row"><div class="col-sm-12">
					<span class="btn btn-default btn-file fileinput-button">
						<i class="fa fa-upload"></i> Browse&hellip;
						<input id="setting_' . $row['setting_id'] . '_fileupload" type="file" class="fileupload ' . $TableName . '" name="files[]" />
					</span>
					<div class="fileupload-progress"></div>';

		echo '<div class="upload-preview">' .
		iif(strlen($row['value']), '<img src="/media/' . $TableName . '/' . $row['value']  . '" alt="' . $row['value']  . '" />') . '
		</div><a href="javascript:void(0)" id="setting_' . $row['setting_id'] . '_remove" class="btn-remove-img btn btn-danger btn-xs margin-5"' . iif(strlen($row['value']) == 0, ' style="display:none"') . '>Remove</a>
		</div></div>
		';
	}
	elseif ($row['type'] == 'longtext') {
		echo '<textarea id="setting_' . $row['setting_id'] . '" name="setting_' . $row['setting_id'] . '" rows="3" class="form-control">' . $row['value'] . '</textarea>';
	}
	else {
		echo '<input type="text" id="setting_' . $row['setting_id'] . '" name="setting_' . $row['setting_id'] . '" value="' . $row['value'] . '" class="form-control" />';
	}
	
	echo '</div></div>';
}

echo '<div class="form-group">
			<label class="col-sm-2 control-label"></label>
				<div class="col-sm-10"><div id="tbl_status" class="status mb-1"></div>
					<input type="submit" value="Save Changes" class="btn btn-primary" />
				</div>
			</div>
			</form>
		</div>
	</div>
</div>
</div>';

include_once ('./inc/footer.php');

?>