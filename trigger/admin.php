<?php
setRs("UPDATE admin SET admin_name = CONCAT(first_name, ' ', last_name, CASE WHEN LENGTH(title) THEN CONCAT(' (', title, ')') ELSE '' END), store_ids = ? WHERE admin_id = ?", array($__store_ids, $ItemID));
?>