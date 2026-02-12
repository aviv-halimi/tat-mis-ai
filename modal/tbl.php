<?php
echo '
<input type="hidden" name="PageName" id="PageName" value="' . $PageName . '" />
<input type="hidden" name="TableName" id="TableName" value="' . $TableName . '" />
<input type="hidden" name="' . $PrimaryKey . '" id="' . $PrimaryKey . '" value="' . $ItemID . '" />
<input type="hidden" name="RequiredFields" id="RequiredFields" value="' . $RequiredFields . '" />
' . buildForm(false);

?>