<?php
setRs("SET foreign_key_checks = 0");
setRs("SET default_storage_engine=MyISAM;");

function tableExists($db, $tbl) {
$_rt = getRs("SELECT * FROM information_schema.tables WHERE table_schema = '{$db}' AND table_name = '{$tbl}' LIMIT 1");
    if (sizeof($_rt)) {
        return true;
    }
    else {
        return false;
    }
}
  

function createTable($tbl, $foreign_keys = array(), $fields = array(), $db = null, $code = true) {
  global $num_tables;
  $num_tables++;
  setRs("DROP TABLE IF EXISTS {$db}`{$tbl}`");
  $sql = "CREATE TABLE {$db}`{$tbl}` (
    `{$tbl}_id` int(11) UNSIGNED NOT NULL auto_increment,
    " . iif($code, "`{$tbl}_code` varchar(50),"); // NOT NULL
  $sql .= "`{$tbl}_name` varchar(255),";
  foreach($fields as $fs) {
    $a = explode(',', $fs);
    $f = $a[0];
    if (sizeof($a) > 1) {
      $t = $a[1];
      if ($t == "int") {
        $t .= "(11)";
        //if (sizeof($a) < 3) $t .= " NOT NULL DEFAULT 0";
      }
      else if ($t == "decimal") {
        $t .= "(16,4)";
        //if (sizeof($a) < 3) $t .= " NOT NULL DEFAULT 0";
      }
      else if ($t == "tinyint") {
        $t .= "(1) NOT NULL DEFAULT 0"; // NOT NULL
        //if (sizeof($a) < 3) $t .= " DEFAULT 0";
      }
    }
    else $t = "varchar(50)";
    if (sizeof($a) > 2) $d = " DEFAULT " . $a[2];
    else $d = "";
    $sql .= "`{$f}` {$t} {$d},";
  }
  $sql .= "
    `sort` int(11) NOT NULL default 0,
    `is_enabled` tinyint(1) UNSIGNED NOT NULL default 1,
    `is_active` tinyint(1) UNSIGNED NOT NULL default 1,
    `date_created` datetime NOT NULL default CURRENT_TIMESTAMP,
    `date_modified` datetime ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`{$tbl}_id`)
  )";
  
  setRs($sql);

  if ($code) { 
    setRs("CREATE TRIGGER {$db}before_insert_{$tbl}
    BEFORE INSERT ON {$db}{$tbl}
    FOR EACH ROW
    BEGIN
      IF new.{$tbl}_code IS NULL THEN
        SET new.{$tbl}_code = uuid();
      END IF;
    END");
  
  }

  /*
      IF new.{$fk_1}_code IS NULL THEN
        SET new.{$fk_1}_code = CONCAT(LEFT(MD5(NOW()), 10), NEW.{$fk_1}_id), RIGHT(MD5(NOW()), 10));
      END IF;
  */

  foreach($foreign_keys as $fk) {
    $a_fk = explode(',', $fk);
    $fk_1 = $a_fk[0];
    $fk_2 = (sizeof($a_fk) > 1)?$a_fk[1]:"{$tbl}_id";
    $fk_3 = (sizeof($a_fk) > 2)?false:true;
    $fk_4 = (sizeof($a_fk) > 3)?$a_fk[3]:'';
    setRs("ALTER TABLE {$db}`{$fk_1}` ADD `{$fk_2}` int(11) UNSIGNED " . iif($fk_3 and false, " NOT NULL") . "" . iif(strlen($fk_4), " DEFAULT {$fk_4}") . " AFTER {$fk_1}_id,
    ADD FOREIGN KEY fk_{$fk_1}_{$fk_2}({$fk_2}) REFERENCES {$db}{$tbl}({$tbl}_id)" . iif($fk_3 and false, " ON DELETE RESTRICT"));
  }

}

function addKey($tbl, $ref, $db = null, $key_name = '', $required = false) {
  if (strlen($key_name) == 0) {
    $key_name = $ref . '_id';
  }
  setRs("ALTER TABLE {$db}`{$tbl}` ADD `{$key_name}` int(11) UNSIGNED " . iif($required, " NOT NULL") . " AFTER {$tbl}_id,
  ADD FOREIGN KEY fk_{$tbl}_{$key_name}({$key_name}) REFERENCES {$db}{$ref}({$ref}_id)" . iif($required, " ON DELETE RESTRICT"));
}

function addCode($tbl) {
  setRs("ALTER TABLE `{$tbl}` ADD `{$tbl}_code` varchar(255) AFTER {$tbl}_id");

  setRs("CREATE TRIGGER before_insert_{$tbl}
  BEFORE INSERT ON {$tbl}
  FOR EACH ROW
  BEGIN
    IF new.{$tbl}_code IS NULL THEN
      SET new.{$tbl}_code = uuid();
    END IF;
  END");
}

function addRef($tbl, $name, $prefix = null, $add = true) {
  $id = null;
  $name = trim($name);
  if (strlen($name)) {
    if ($prefix) $name = $prefix . ' ' . $name;
    $rs = getRs("SELECT {$tbl}_id FROM {$tbl} WHERE {$tbl}_name = ?", array($name));
    if ($r = getRow($rs)) {
      $id = $r[$tbl . '_id'];
    }
    else if ($add) {
      $id = setRs("INSERT INTO {$tbl} ({$tbl}_name) VALUES (?)", array($name));
    }
  }
  return $id;
}

function getRef($tbl, $name, $prefix = null) {
  return addRef($tbl, $name, $prefix, false);
}

setRs("SET foreign_key_checks = 1");
?>