<?php

/*
 * class for session management
 */
 
//require_once ('../_config.php');
 
class SessionManager {

	var $site_id = null;
	var $employee_id = null;
  var $site_name = 'The Artist Tree';
  var $site_logo = null;
  var $site_title = null;
	var $site_logo_url = '/media/site/at.png';
	var $site_image_url = '/media/site/at-bg.jpg';
	var $site_params = array();
	var $db = 'blaze1';
	var $api_url = null;
	var $auth_code = null;
	var $partner_key = null;
  var $store_id = null;
	var $store_ids = null;
	var $employeeId = null;

	var $admin_id = null;
	var $admin_type_id = null;
	var $admin_group_id = null;
	var $admin_name = '';
	var $first_name = '';
	var $last_name = '';
	var $phone = '';
	var $company = '';
	var $title = '';
	var $email = '';
	var $image = '';
	var $image_url = '';
	var $is_superadmin = 0;
	var $app_group_id = null;
	var $is_enabled = 0;
	var $is_verified = 0;
	var $dashboard = '';
	var $permissions = array('modules' => array(), 'workflows' => array(), 'reports' => array());
	var $settings = array();
	var $site_ids = '[]';
	var $module_ids = '[]';
  var $admin_settings = array();
	var $store_settings = array();

	function __construct() {
    $this->InitSession();
	}
	
	function InitSession($check_store = true) {

		global $_SERVER;

		if (strpos($_SERVER['REQUEST_URI'], 'db/') !== false) return;

		if (isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']) ) {
			$rs = getRs("SELECT s.params AS store_settings, a.*, g.module_ids, CONCAT('/media/admin/sm/', CASE WHEN LENGTH(a.image) THEN a.image ELSE 'no.png' END) AS image_url, s.db, s.api_url, s.auth_code, s.partner_key, g.dashboard FROM store s RIGHT JOIN (admin a LEFT JOIN admin_group g ON g.admin_group_id = a.admin_group_id AND " . is_enabled('g') . ") ON s.store_id = a.store_id WHERE (a.is_superadmin = 1 OR ((a.date_start IS NULL OR a.date_start <= CURDATE()) AND (a.date_end IS NULL OR a.date_start >= CURDATE()))) AND " . is_enabled('a') . " AND a.admin_id = ?", array($_SESSION['admin_id']));
			if ($row = getRow($rs)) {
				$this->admin_id = $row['admin_id'];
				$this->first_name = $row['first_name'];
				$this->last_name = $row['last_name'];
				$this->admin_name = $row['first_name'] . ' ' . $row['last_name'];
				$this->email = $row['email'];
				$this->phone = $row['phone'];
				$this->company = $row['company'];
				$this->title = $row['title'];
				$this->image = $row['image'];
				$this->image_url = $row['image_url'];
				$this->module_ids = $row['module_ids'];
				$this->store_ids = $row['store_ids'];
				$this->db = $row['db'];
				$this->api_url = $row['api_url'];
				$this->auth_code = $row['auth_code'];
				$this->partner_key = $row['partner_key'];
				$this->store_id = $row['store_id'];
				$this->employee_id = $row['employee_id'];
				$this->admin_group_id = $row['admin_group_id'];
				$this->is_superadmin = $row['is_superadmin'];
				$this->dashboard = $row['dashboard'];
				if (str_len($row['settings'])) {
					$this->admin_settings = json_decode($row['settings'], true);
				}
				if (str_len($row['store_settings'])) {
					$this->store_settings = json_decode($row['store_settings'], true);
				}
				if ($this->store_ids and $check_store) {
					$_valid_store_id = false;
					$_store_id = $employee_id = null;
					$_a_store_ids = json_decode($this->store_ids, true);
					foreach($_a_store_ids as $_store) {
						if (isset($_store['store_id']) and isset($_store['employee_id'])) {
							if (!$_store_id) {
								$_store_id = $_store['store_id'];
								$employee_id = $_store['employee_id'];
							}
							if ($this->store_id == $_store['store_id']) {
								$_valid_store_id = true;
								break;
							}
						}
					}
					if (!$_valid_store_id) {
						$_ra = getRs("SELECT * FROM admin WHERE admin_id = ?", $this->admin_id);
						dbUpdate('admin', array('store_id' => $_store_id, 'employee_id' => $employee_id), $this->admin_id);
						saveActivity('update', $this->admin_id, 'admin', 'Invalid store detected', getRow($_ra));
						$this->InitSession(false);
					}
				}
      }
      else {
        //$this->Logout();
      }
			$rs = getRs("SELECT setting_code, value FROM setting WHERE " . is_enabled());
			foreach($rs as $row) {
				$this->settings[$row['setting_code']] = $row['value'];
			}
		}
	}


	
	/*************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	// Settings //
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
  *************************************************************************************************************************************/
  
  function ShareTableDisplaySettings($_p) {
    $success = false;
    $response = '';
    $swal = '';

    $module_option_code = getVar('c', $_p);
    $admin_ids = getVarA('admin_ids', $_p);
    $recipients = $subject = $message = null;

    $rm = getRs("SELECT m.module_code, m.module_name, o.module_option_code, o.module_option_name, o.date_created FROM module_option o INNER JOIN module m ON m.module_id = o.module_id WHERE " . is_enabled('o,m') . " AND o.module_option_code = ?", array($module_option_code));
    if ($m = getRow($rm)) {
      $subject = $this->site_name . ': ' . $m['module_option_name'];
      $message = '<p>Please click on the link below to view this indicator / report:</p>
      <p><a href="' . getCurrentHost() . $m['module_code'] . '/' . $m['module_option_code'] . '">' . $m['module_option_name'] . '</a></p>';
    }
    else {
      $swal = 'Error';
      $response = 'Indicator not found';
    }

    if (!str_len($response)) {
      if (is_array($admin_ids) and sizeof($admin_ids)) {
        $rs = getRs("SELECT * FROM admin WHERE FIND_IN_SET(admin_id, ?)", array(implode(',', $admin_ids)));
        foreach($rs as $r) {
          $recipients .= '<div>' . $r['admin_name'] . ' &lt;' . $r['email'] . '&gt;</div>';
          sendEmail($r['admin_name'], $r['email'], $subject, '<p>Hi ' . $r['first_name'] . ' --</p>' . $message);
          saveActivity('email', $r['admin_id'], 'admin', 'Link sent to ' . $r['admin_name'] . ' &lt;' . $r['email'] . '&gt;. RE: ' . $subject);
        }
        $success = true;
        $swal = 'Sent successfully';
        $response = 'Link sent successfully to ' . $recipients;
      }
      else {
        $swal = 'Please select recipient';
        $response = 'You must select at least one user to share this link with';
      }
    }
    
    return array('success' => $success, 'response' => $response, 'swal' => $swal);
  }

	function GetModuleOptions($module_code, $admin_id = null, $limit = null) {
    $params = array($module_code);
    if ($admin_id) array_push($params, $admin_id);
    return getRs("SELECT o.* FROM module_option o INNER JOIN module m ON m.module_id = o.module_id WHERE " . is_enabled('m,o') . " AND m.module_code = ?" . iif($admin_id, " AND o.admin_id = ?") . " ORDER BY COALESCE(o.date_modified, o.date_created) DESC" . iif($limit, " LIMIT {$limit}"), $params);
  }
  
	function SaveTableDisplayOption($_p) {
    $success = true;
    $response = 'Saved';
    
    $module_option_id = getVarANum('id', $_p);
    $module_option_name = getVarA('name', $_p);

    setRs("UPDATE module_option SET module_option_name = ? WHERE module_option_id = ? AND admin_id = ?", array($module_option_name, $module_option_id, $this->admin_id));
    return array('success' => $success, 'response' => $response, 'name' => $module_option_name);
  }
  
	function GetTableDisplaySettings($module_code, $module_option_code = null) {
    if ($module_option_code) {
      $this->LoadTableDisplaySettings(array('module_option_code' => $module_option_code));
    }
		if (isset($this->admin_settings['_ds-' . $module_code])) {
			return $this->admin_settings['_ds-' . $module_code];
		}
		return array();
  }

  function SaveChartSetting($module_code, $chart_type, $chart_stacking, $chart_3d) {

    if (!in_array($chart_type, array('column', 'bar', 'pie', 'line', 'spline', 'area', 'scatter'))) {
      $chart_type = 'column';
    }
    if (!in_array($chart_type, array('bar', 'column'))) {
      $chart_stacking = null;
    }
    if (!in_array($chart_type, array('bar', 'column', 'pie'))) {
      $chart_3d = false;
    }
    if ($chart_stacking) $chart_stacking = 'normal';
    if ($chart_3d) $chart_3d = true;

    $this->admin_settings['_ds-' . $module_code]['chart_type'] = $chart_type;
    $this->admin_settings['_ds-' . $module_code]['chart_stacking'] = $chart_stacking;
    $this->admin_settings['_ds-' . $module_code]['chart_3d'] = $chart_3d;
    setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));

    if (isset($this->admin_settings['_ds-' . $module_code]['id'])) {
      $rs = getRs("SELECT module_option_id, params FROM module_option WHERE module_option_id = ?", array($this->admin_settings['_ds-' . $module_code]['id']));
      if ($r = getRow($rs)) {
        $params = json_decode($r['params'], true);
        $params['chart_type'] = $chart_type;
        $params['chart_stacking'] = $chart_stacking;
        $params['chart_3d'] = $chart_3d;
        dbUpdate('module_option', array('params' => json_encode($params)), $r['module_option_id']);
      }
    }
  }
  
	function LoadTableDisplaySettings($__p) {
    $success = true;
    $response = '';
    $redirect = '';

    $module_option_code = getVarA('module_option_code', $__p);

    $rs = getRs("SELECT o.*, m.module_code FROM module_option o INNER JOIN module m ON m.module_id = o.module_id WHERE " . is_enabled('m,o') . " AND o.module_option_code = ?", array($module_option_code));

    if ($r = getRow($rs)) {
      setRs("UPDATE module_option SET num_views = COALESCE(num_views, 0) + 1 WHERE module_option_id = ?", array($r['module_option_id']));
      $module_code = $r['module_code'];
      $_p = json_decode($r['params'], true);
      //$_p['id'] = $r['module_option_id'];

      if ($this->HasModulePermission($module_code)) {
        unset($_p['module_code']);
        $this->admin_settings['_ds-' . $module_code] = $_p;
        setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));
        $success = true;
        $response = 'Display settings updated successfully.';
        $redirect = '/' . $module_code;
      }
      else {
        $response = 'You do not have access to this module.';
      }
    }
    else {
      $response = 'You do not have access to this resource.';
    }
    return array('success' => $success, 'response' => $response, 'redirect' => $redirect);
  }
  
	function SaveTableDisplaySettings($_p) {
		global $_Fulfillment;
		global $_PO;
		global $_ProductCard;
    $success = false;
    $response = '';
    $redirect = '';

    $module_code = getVarA('module_code', $_p);
    $module_id = $this->GetCodeId('module', $module_code);
    if ($this->HasModulePermission($module_code)) {
      unset($_p['module_code']);
      unset($_p['_r']);
      
      if ($module_code == 'transfer-report') {
        $transfer_report_id = getVarANum('transfer_report_id', $_p);
        if (!$transfer_report_id) {
          $transfer_report_id = $_Fulfillment->NewTransferReport($_p);
          $_p['transfer_report_id'] = $transfer_report_id;
        }
        else {
          $transfer_report_id = $_Fulfillment->UpdateTransferReport($_p);
        }
      }
      
      if ($module_code == 'po') {
        $po_id = getVarANum('po_id', $_p);
        if (!$po_id) {
          $po = $_PO->NewPO($_p);
		  $_p['po_id'] = $po['po_id'];
		  $response = $po['response'];
        }
        else {
          $po_id = $_PO->UpdatePO($_p);
        }
	  }
      
      if ($module_code == 'product-card') {
        $product_card_id = getVarANum('product_card_id', $_p);
        if (!$product_card_id) {
          $pc = $_ProductCard->NewProductCard($_p);
		  $_p['product_card_id'] = $pc['product_card_id'];
		  $success = true;
		  $response = $pc['response'];
		  $redirect = '{refresh}';
        }
        else {
          $product_card_id = $_ProductCard->UpdateProductCard($_p);
        }
	  }
	  
	  if (!str_len($response)) {

		$this->admin_settings['_ds-' . $module_code] = $_p;
		setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));

		unset($_p['fields']);
		if (sizeof($_p)) {
			$module_option_name = array();
			foreach($_p as $_k => $v) {
			$field_name = str_replace('_ids', '', $_k);
			$field_name = str_replace('_id', '', $field_name);
			if (is_array($v)) {
				$field_value = getDisplayNames($field_name, json_encode($v));
				if (str_len($field_value)) array_push($module_option_name, $field_value);
			}
			elseif (str_len($v)) {
				array_push($module_option_name, nicefy($field_name) . ': ' . $v);
			}
			}
			array_unshift($module_option_name, getLongDate('now'));
			$module_option_name = implode(' | ', $module_option_name);
			$module_option_name = shorten($module_option_name, 255, true);
			$module_option_id = dbPut('module_option', array('module_option_name' => $module_option_name, 'module_id' => $module_id, 'admin_id' => $this->admin_id, 'params' => json_encode($_p)));
			$_p['id'] = $module_option_id;
			dbUpdate('module_option', array('params' => json_encode($_p)), $module_option_id);
			$this->admin_settings['_ds-' . $module_code]['id'] = $module_option_id;
			setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));
		}
		$success = true;
		$response = 'Display settings updated successfully.';
		$redirect = '/' . $module_code;
	  }
    }
    else {
      $response = 'You do not have access to this module.';
    }
    return array('success' => $success, 'response' => $response, 'redirect' => $redirect);
	}


	function SaveAdminSettings($k, $v) {
		$this->admin_settings[$k] = $v;
		setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));
	}

	function GetAdminSettings($k) {
		if (isset($this->admin_settings[$k])) return $this->admin_settings[$k];
		else return null;
  }
  
	function GetAdminSetting($k, $default = null, $valid_values = array()) {
		if (isset($this->admin_settings[$k])) {
      if (!sizeof($valid_values)) {
        return $this->admin_settings[$k];
      }
      else if (is_array($valid_values) and in_array($this->admin_settings[$k], $valid_values)) {
        return $this->admin_settings[$k];
      }
    }
    return $default;
  }
  
  function SaveDisplaySettings($_p) {
    $admin_settings = $this->admin_settings;
    $vs = array('account_order_status_id', 'account_order_product_id', 'account_order_depot_id', 'account_order_account_id', 'account_order_date_start', 'account_order_date_end', 'depot_order_status_id', 'depot_order_product_id', 'depot_order_depot_id', 'motwi_surveys_date_start', 'motwi_surveys_date_end');
    foreach($vs as $v) {
      if (isset($_p[$v])) {
        $admin_settings[$v] = getVarA($v, $_p);
      }
    }
    $this->admin_settings = $admin_settings;
    setRs("UPDATE admin SET settings = ? WHERE admin_id = ?", array(json_encode($this->admin_settings), $this->admin_id));

    return array('success' => true, 'response' => 'Saved successfully', 'redirect' => '{refresh}');
  }
	
	/*************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	// Modules //
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	*************************************************************************************************************************************/

	function GetModules($parent_module_id = null) {
		$ret = '';
		$params = array($this->site_id);
    if (!$this->is_superadmin) array_push($params, $this->module_ids);
		$rs = getRs("SELECT module_id, module_code, module_code_alt, module_ref, module_name, image, icon, is_label, params FROM module WHERE " . is_enabled() . " AND is_nav = 1 AND (site_id IS NULL OR site_id = ?)" . iif(!$this->is_superadmin, " AND (is_label OR JSON_CONTAINS(?, CAST(module_id AS CHAR), '$'))") . " AND parent_module_id " . iif($parent_module_id, " = {$parent_module_id}", "IS NULL") . " ORDER BY sort, module_id", $params);
		foreach($rs as $r) {
			if (!$r['is_label']) {
        $alert = '';
        if (isJson($r['params'])) {
          $params = json_decode($r['params'], true);
          if (isset($params['alert'])) {
            $_ra = getRs($params['alert']);
            if ($_a = getRow($_ra)) {
              if ($_a['num_alert']) $alert = ' <span class="badge badge-error">' . $_a['num_alert'] . '</span>';
            }
          }
        }
				$sub_menu = $this->GetModules($r['module_id']);
				$ret .= '<li' . iif(str_len($sub_menu), ' class="has-sub"') . '><a href="' . iif(str_len($sub_menu), 'javascript:;', '/' . $r['module_code']) . '"' . iif(str_len($r['module_code_alt']), ' data-alt="' . $r['module_code_alt'] . '"') . '>' . iif(str_len($sub_menu), '<b class="caret pull-right"></b>') . iif(!$parent_module_id, iif(str_len($r['image']), '<i class="image"><img src="' . $r['image'] . '" /></i>', '<i class="' . $r['icon'] . '"></i>')) . '<span>' . $r['module_name'] . $alert . '</span></a>' . $sub_menu . '</li>';
			}
			else {
				$ret .= '<li class="nav-header">' . $r['module_name'] . '</li>';
			}
		}
		if (!$parent_module_id) {
			$ret .= '<li class="divider has-minify-btn"><a href="javascript:;" class="sidebar-minify-btn" data-click="sidebar-minify"><i class="fa fa-angle-left"></i></a></li>';
		}
		if (str_len($ret)) $ret = '<ul class="' . iif($parent_module_id, 'sub-menu', 'nav') . '">' . $ret . '</ul>';
		return $ret;
	}

	function GetModule($module_code, $admin_id = null) {
		$is_superadmin = $this->is_superadmin;
		$module_ids = $this->module_ids;

		if ($admin_id) {
			$rs = getRs("SELECT a.admin_id, a.is_superadmin, a.store_ids, g.module_ids FROM store s RIGHT JOIN (admin a LEFT JOIN admin_group g ON g.admin_group_id = a.admin_group_id AND " . is_enabled('g') . ") ON s.store_id = a.store_id WHERE (a.is_superadmin = 1 OR ((a.date_start IS NULL OR a.date_start <= CURDATE()) AND (a.date_end IS NULL OR a.date_start >= CURDATE()))) AND " . is_enabled('a') . " AND a.admin_id = ?", array($admin_id));
			if ($r = getRow($rs)) {
				$is_superadmin = $r['is_superadmin'];
				$module_ids = $r['module_ids'];		
				$_a_store_ids = json_decode($r['store_ids'], true);
				$store_access = false;
				foreach($_a_store_ids as $_store) {
					if (isset($_store['store_id']) and $_store['store_id'] == $this->store_id) {
						$store_access = true;
						break;
					}
				}
				if (!$store_access) return array();
			}
		}
		
		$params = array($this->site_id);
		if (!$is_superadmin) array_push($params, $module_ids);
		$_params = $params;
		array_push($params, $module_code);
		$rs = getRs("SELECT module_id, parent_module_id, module_code, module_ref, module_name, image, icon, tbl, content, params, ((site_id IS NULL OR site_id = ?)" . iif(!$is_superadmin, " AND JSON_CONTAINS(?, CAST(module_id AS CHAR), '$')") . ") AS is_access_granted FROM module WHERE " . is_enabled() . " AND module_code = ?", $params);

		if (true) { //!$this->is_superadmin) {		
			// check if parent permissions are intact
			$_rs = $rs;
			while ($_r = getRow($_rs)) {
				if (!str_len($rs[0]['image'])) $rs[0]['image'] =  $_r['image'];
				if (!str_len($rs[0]['icon'])) $rs[0]['icon'] =  $_r['icon'];
				if (!$_r['is_access_granted']) {
					$rs = array(
						array('is_access_granted' => 0)
					);
					break;
				}
				if ($_r['parent_module_id']) {
					$params = $_params;				
					array_push($params, $_r['parent_module_id']);
					$_rs = getRs("SELECT module_id, parent_module_id, module_code, module_name, image, icon, tbl, params, ((site_id IS NULL OR site_id = ?)" . iif(!$is_superadmin, " AND JSON_CONTAINS(?, CAST(module_id AS CHAR), '$')") . ") AS is_access_granted FROM module WHERE " . is_enabled() . " AND module_id = ?", $params);
				}
				else {
					$_rs = array();
				}
			}
		}
		return $rs;
  }
  
  function GetModuleList($module_ids = null, $parent_module_id = null) {
		$ret = '';
		$params = array($this->site_id, $module_ids);
		$rs = getRs("SELECT module_id, module_code, module_code_alt, module_ref, module_name, image, icon, is_label, params FROM module WHERE " . is_enabled() . " AND is_label = 0 AND is_nav = 1 AND (site_id IS NULL OR site_id = ?) AND (is_label OR JSON_CONTAINS(?, CAST(module_id AS CHAR), '$')) AND parent_module_id " . iif($parent_module_id, " = {$parent_module_id}", "IS NULL") . " ORDER BY sort, module_id", $params);
		foreach($rs as $r) {
      $sub_menu = $this->GetModuleList($module_ids, $r['module_id']);
      $ret .= '&bull; ' . iif(!str_len($sub_menu), '<a href="/' . $r['module_code'] . '">' . $r['module_name'] . '</a>', '<b>' . $r['module_name'] . '</b>') . ' ' . $sub_menu;
		}
		return $ret;
	}
  
  function HasModulePermission($module_code, $admin_id = null) {
    $is_access_granted = false;
    $rs = $this->GetModule($module_code, $admin_id);
    if ($r = getRow($rs)) {
      if ($r['is_access_granted']) $is_access_granted = true;
    }
    return $is_access_granted;
  }

	function GetBreadcrumb($module_id, $base = true) {
		$ret = '';
		$rs = getRs("SELECT module_id, parent_module_id, module_code, module_name, tbl, params FROM module WHERE " . is_enabled() . " AND (site_id IS NULL OR site_id = ?) AND module_id = ?", array($this->site_id, $module_id));
		if ($r = getRow($rs)) {
			$ret .= $this->GetBreadcrumb($r['parent_module_id'], false);
			if ($base) {
				$ret .= '<li class="breadcrumb-item active">' . $r['module_name'] . '</li>';
			}
			else {
				$ret .= '<li class="breadcrumb-item"><a href="/' . $r['module_code'] . '">' . $r['module_name'] . '</a></li>';
			}
		}
		if ($base) {
			$ret = '<ol class="breadcrumb pull-right"><li class="breadcrumb-item"><a href="/">Dashboard</a></li>' . $ret . '</ol>';
		}
		return $ret;
	}

	function ModulePermissionOptions($a_modules, $parent_module_id = null, $parent_selected = true) {
		$ret = '';
		$rs = getRs("SELECT module_id, module_name FROM module WHERE " . is_enabled() . " AND parent_module_id " . iif($parent_module_id, " = {$parent_module_id}", "IS NULL") . " AND is_label = 0 AND is_hidden = 0 AND (site_id = ? OR site_id IS NULL) ORDER BY sort, module_id", array($this->site_id));
		foreach($rs as $r) {
			$ret .= '
			<div class="icheck-' . iif($parent_module_id, 'warning', 'info') . ' module-id">
			<input id="module_id_' . $r['module_id'] . '" type="checkbox" name="module_ids[]" value="' . $r['module_id'] . '" data-render="switchery" data-theme="' . iif($parent_module_id, 'warning', 'success') . '"' . iif(in_array($r['module_id'], $a_modules) || (!sizeof($a_modules) and $parent_module_id), ' checked') . ' />
				<label class="mr-1 ml-1" for="module_id_' . $r['module_id'] . '"><b>' . $r['module_name'] . '</b></label>
			</div>' . $this->ModulePermissionOptions($a_modules, $r['module_id'], in_array($r['module_id'], $a_modules) || (!sizeof($a_modules) and $parent_module_id));
		}
		$ret = '<div class="' . iif(!$parent_selected, 'hide') . ' module-ids-' . $parent_module_id . iif($parent_module_id, ' ml-3') . '">' . $ret . '</div>';
		return $ret;
  }
  
  function Revert($_p) {
    $success = false;
    $response = '';
    $redirect = '';
    $re_table = null;
    $re_id = null;

    $activity_code = getVarA('c', $_p);
    $key = getVarA('k', $_p);

    if (true) { //$this->HasModulePermission('logs')) {
      		

      $rs = getRs("SELECT * FROM activity WHERE activity_code = ?", array($activity_code));
      if ($r = getRow($rs)) {
        $re_table = $r['re_table'];
        $re_id = $r['re_id'];
        
        $rs_1 = getRs("SELECT * FROM {$re_table} WHERE {$re_table}_id = ?", array($re_id));
        $r_1 = getRow($rs_1);
        
        $_sql = '';
        $_params = array();

        $params = json_decode($r['params'], true);
        foreach($params as $p) {
          $_key = $p['key'];
          $_prev = $p['prev'];
          if (!is_numeric($_key) and isset($r_1[$_key]) and $r_1[$_key] != $_prev and (!str_len($key) || $key == $_key)) {
            $_sql .= iif(str_len($_sql), ', ') . $_key . ' = ?';
            array_push($_params, $_prev);
          }
        }

        if (sizeof($_params)) {
          $response = sizeof($_params) . ' field' . iif(sizeof($_params) != 1, 's') . ' reverted';
          array_push($_params, $re_id);
 
          setRs("UPDATE {$re_table} SET {$_sql} WHERE {$re_table}_id = ?", $_params);

          $success = true;
          saveActivity('update', $re_id, $re_table, $response, $r_1);
        }
        else {
          $response = 'No fields were reverted';
        }
      }
      else {
        $response = 'Record not found';
      }
      
    }
    else {      
      $response = 'You do not have permission to perform this action.';
    }

    return array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'tbl' => $re_table, 'id' => $re_id);
  }

	
	/*************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	// AUTHENTICATION //
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	*************************************************************************************************************************************/

	function Login($email, $password, $remember = 0, $admin_id = 0, $log = true) {

		$success = false;
		$response = '';
		$redirect = '';
		$swal = false;		

		$params = array($this->site_id);
		if (str_len($email) == 0 and $admin_id == 0) {
			$response = 'Please enter your email address';
		}
		elseif (str_len($password) == 0 and $admin_id == 0) {
			$response = 'Please enter your password';
		}
		else {			
			if ($admin_id == 0) {
				array_push($params, $email);
			}
			else {
				array_push($params, $admin_id);
			}

			$rs = getRs("SELECT a.admin_id, a.first_name, a.last_name, a.email, a.password, a.is_superadmin, a.is_enabled, g.admin_group_id, CASE WHEN (a.is_superadmin = 1 OR ((a.date_start IS NULL OR a.date_start <= CURDATE()) AND (a.date_end IS NULL OR a.date_start >= CURDATE()))) THEN 0 ELSE 1 END AS is_expired FROM admin a LEFT JOIN admin_group g ON g.admin_group_id = a.admin_group_id AND " . is_enabled('g') . " WHERE " . is_active('a') . " AND (a.site_id IS NULL OR a.site_id = ?) AND " . (($admin_id == 0)?'a.email = ?':'a.admin_id = ?'), $params);

			if ($row = getRow($rs)) {
				if ( $admin_id or formatPassword($password) == $row['password'] ) {
					if (!$row['is_superadmin'] and !$row['admin_group_id']) {
						$response = 'Sorry ' . $row['first_name'] . ', you cannot log into your account group at this time. Please contact system administrator.';
					}
					elseif ($row['is_expired']) {
						$response = 'Sorry ' . $row['first_name'] . ', your credentials are no longer valid. Please contact system administrator for more information.';
					}
					else if ($row['is_enabled'] == 1) {
						$this->admin_id = $_SESSION['admin_id'] = $row['admin_id'];

						$this->InitSession();
						saveActivity('login', $row['admin_id'], 'admin', 'Logged in successfully.');

						$success = true;
						$response = 'Logged in successfully.';
						$redirect = '/';
						//if ($log) $this->UserActivity(1, 1, 'Logged in successfully', $row['admin_id']);

						// Handle the keep-logged option
						if ($remember == 1) {
							$access_token = getUniqueCode();
							setRs("UPDATE admin SET access_token = ?, date_access_token = CURRENT_TIMESTAMP WHERE admin_id = ?", array($access_token, $row['admin_id']));
							saveCookie('tat_mis_access_token', $access_token);
						}
						else {
							setRs("UPDATE admin SET access_token = NULL WHERE admin_id = ?", array($row['admin_id']));
							saveCookie('tat_mis_access_token', '');
						}
					}
					else {
						//if ($log) $this->UserActivity(1, 0, 'Admin inactive', $row['user']);
						$response = 'Sorry ' . $row['first_name'] . ', you cannot log into your account at this time. Please contact system administrator.';
						$swal = 'Account Locked';
					}
				}
				else {
					//if ($log) $this->UserActivity(1, 0, 'Failed using password: ' . $password, $row['admin_id']);
					saveActivity('login', $row['admin_id'], 'admin', 'Failed using password: ' . $password, array(), $row['admin_id'], 0);
					$response = 'Invalid email / password combination.';
					saveCookie('tat_mis_access_token', '');
				}
			}
			else {
				$response = 'Wrong email / password combination';
				saveCookie('tat_mis_access_token', '');
			}
		}
		
		return array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'swal' => '');
	}
	
	function Signup($_p) {
		global $IP, $USER_AGENT;
		$success = false;
		$response = '';
		$redirect = 'index.php';
		
		$first_name = getVarA('first_name', $_p);
		$last_name = getVarA('last_name', $_p);
		$email = getVarA('email', $_p);
		$phone = getVarA('phone', $_p);
		$company = getVarA('company', $_p);
		$title = getVarA('title', $_p);
		$password1 = getVarA('password1', $_p);
		$password2 = getVarA('password2', $_p);
		$remember = getVarAInt('remember', $_p);
		$captcha_code = getVarA('captcha_code', $_p);
		
		$securimage = new Securimage();
		if ($securimage->check($captcha_code) == false) {
			$response = 'Invalid image code.';
		}
		
		if (str_len($first_name) == 0 || str_len($last_name) == 0 || str_len($email) == 0) {
			$response = 'First name, last name and email address are required.';
		}
		if (str_len($password1) == 0) {
			$response = 'Password is required.';
		}
		if ($password1 != $password2) {
			$response = 'Passwords do not match.';
		}
		
		if (str_len($response) == 0) {
			$rs = getRs("SELECT account_id, is_enabled FROM account WHERE email = ?", array($email));
			if ($row = getRow($rs)) {
				$response = 'This e-mail address is already registered. ' . iif($row['is_enabled'], 'Please use the Lost password to reset your password.', 'You will be notified when we verify your account.');
			}
			else {
				$is_verified = getSetting(1);
				$account_name = $first_name . ' ' . substr($last_name, 0, 1);
				$account_id = setRs("INSERT INTO account (account_name, first_name, last_name, email, phone, company, title, password, ip_address, user_agent, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", array($account_name, $first_name, $last_name, $email, $phone, $company, $title, formatPassword($password1), $IP, $USER_AGENT, $is_verified));
				$this->Login('', '', $remember, $account_id);
				$success = true;
				$response = 'Signed up successfully. Proceeding.';
				if ($is_verified == 0) {
					$this->SendEmailToken($account_id);
					$response = 'Signed up successfully. Please check your email and click on the link provided to verify your email address.';
				}
			}
		}
		
		return array($success, $response, $redirect);
	}
	
	function SendEmailToken($account_id = 0) {
		return;
		$account_id = ($account_id == 0)?$this->account_id:$account_id;
		
		$success = false;
		$response = '';
		
		$rs = getRs("SELECT a.account_id, a.first_name, a.last_name, a.email, a.is_verified FROM account a WHERE a.account_id = ? AND " . is_active('a'), array($account_id));
		
		if ($row = getRow($rs)) {
			if ($row['is_verified'] == 0) {
				$email_token = getUniqueCode();
				setRs("UPDATE account SET email_token = ?, date_email_token = CURRENT_TIMESTAMP WHERE account_id = ?", array($email_token, $row['account_id']));

				$placeholders = array('verify_email_link' => getCurrentHost() . 'verify-email/' . $email_token, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'email' => $row['email'], 'to_name' => $row['first_name'] . ' ' . $row['last_name'], 'to_email' => $row['email']);
				$a = $this->SendNotification('verify-email', $placeholders);
				$success = true;
				$response = 'Verification e-mail has been sent to "' . $row['email'] . '". Please check your email and click on the link provided.';
			}
			else {
				$response = 'E-mail already verified';
			}
		}
		else {
			$response = 'Account not found';
		}
		
		return array($success, $response);
		
	}
	
	function VerifyEmailToken($email_token) {
		
		$success = false;
		$response = '';
		$redirect = '';
		$swal = false;
		
		$rs = getRs("SELECT a.account_id, a.first_name, a.last_name, a.email, a.is_verified FROM account a WHERE " . is_active('a') . " AND LENGTH(a.email_token) > 0 AND a.email_token = ?", array($email_token));
		if ($row = getRow($rs)) {
			setRs("UPDATE account SET email_token = NULL, is_verified = 1 WHERE account_id = ?", array($row['account_id']));
			$a = $this->Login('', '', 1, $row['account_id']);
			//$a = $this->SendNotice(2);
			$success = true;
			$response = 'Your e-mail address has been verified and you have been successfully logged in.';
			$redirect = '/my-account';
			$swal = 'Welcome back ' . $row['first_name'] . '. Email verified !';
		}
		else {
			$swal = 'Invalid token';
			$response = 'Your email address has not been verified.';
		}
		
		return array($success, $response, $redirect, $swal);
		
	}
	
	function ForgotPassword($_p) {
		
		$success = false;
		$response = '';
		$redirect = '';
		
		$email = getVarA('email', $_p);
		
		if (str_len($email) == 0) {
			$response = 'E-mail address is required.';
		}
		else {
			$rs = getRs("SELECT a.admin_id, a.admin_name, a.first_name, a.last_name, a.email, a.is_enabled, CASE WHEN (a.is_superadmin = 1 OR ((a.date_start IS NULL OR a.date_start <= CURDATE()) AND (a.date_end IS NULL OR a.date_start >= CURDATE()))) THEN 0 ELSE 1 END AS is_expired FROM admin a WHERE a.email = ? AND " . is_active('a'), array($email));
			if ($row = getRow($rs)) {
        if ($row['is_expired']) {
          $response = 'Sorry ' . $row['first_name'] . ', your credentials are no longer valid. Please contact system administrator for more information.';
        }
				elseif ($row['is_enabled'] == 1) {
					$password_token = getUniqueCode();
          setRs("UPDATE admin SET password_token = ?, date_password_token = CURRENT_TIMESTAMP WHERE admin_id = ?", array($password_token, $row['admin_id']));
          $link = getCurrentHost() . 'reset-password/' . $password_token;
          $link = '<a href="' . $link . '">' . $link . '</a>';
					$placeholders = array('link' => $link, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'email' => $row['email'], 'to_name' => $row['admin_name'], 'to_email' => $row['email']);
					$a = $this->SendNotification('forgot-password', $placeholders);
					if ($a['success']) {
						$success = true;
						$response = 'Information on how to reset your password has been sent to: "' . $email . '". Please check your e-mail and click on the link provided.';
						$swal = 'Email sent';
					}
					else {
						$response = $a['response'];	
					}
				}
				else {
					$response = 'Sorry, but you cannot log into your account at this time. Please contact system administrator.';
					$swal = 'Account Locked';
				}
				saveActivity('forgot-password', $row['admin_id'], 'admin', $response, array(), null, null, $success);
			}
			else {
				$response = 'Email address not found.';
			}
		}
		return array($success, $response, $redirect);
	}
	
	function Profile($_p) {
		
		if ($this->admin_id == 0) return array('success' => false, 'response' => 'You must be logged in to edit your profile.');

		$success = false;
		$response = '';
		$redirect = '';
		
		$first_name = getVarA('first_name', $_p);
		$last_name = getVarA('last_name', $_p);
		$phone = getVarA('phone', $_p);
		$email = getVarA('email', $_p);
		$company = getVarA('company', $_p);
		$title = getVarA('title', $_p);
		$image = getVarA('image', $_p);
		
		if (str_len($first_name) == 0 || str_len($last_name) == 0) {
			$response = 'First name and last name are required.';
		}
		if (str_len($email) == 0 || checkEmail($email) == false) {
			$response = 'Please enter a valid e-mail address.';
		}
		else {
			$rs = getRs("SELECT admin_id FROM admin WHERE email = ? AND admin_id <> ?", array($email, $this->admin_id));
			if ($row = getRow($rs)) {
				$response = 'This email address is already registered to another user.';
			}
		}
		
		if (str_len($response) == 0) {
			setRs("UPDATE admin SET first_name = ?, last_name = ?, phone = ?, email = ?, title = ?, image = ?, date_modified = CURRENT_TIMESTAMP WHERE admin_id = ?", array($first_name, $last_name, $phone, $email, $title, $image, $this->admin_id));
			$success = true;
			$response = 'Profile updated successfully';
		}		
		return array('success' => $success, 'response' => $response, 'redirect' => $redirect);
	}
	
	function Password($_p) {

		$success = false;
		$response = '';
		
		$password = getVarA('password', $_p);
		$password1 = getVarA('password1', $_p);
		$password2 = getVarA('password2', $_p);
		
		if (str_len($password1) < 6) {
			$response = 'New password must be at least 6 characters in length.';
		}
		if ($password1 != $password2) {
			$response = 'Passwords do not match.';
		}
		if (str_len($response) == 0) {
			$rs = getRs("SELECT admin_id FROM admin WHERE admin_id = ? AND password = ? AND " . is_enabled(), array($this->admin_id, formatPassword($password)));
			if ($row = getRow($rs)) {
				setRs("UPDATE admin SET password = ?, password_token = NULL, date_password_token = NULL, date_modified = CURRENT_TIMESTAMP WHERE admin_id = ?", array(formatPassword($password1), $row['admin_id']));
				//$this->UserActivity(17, 1, 'Password changed successfully', $row['user_id']);
				$success = true;
				$response = 'Password changed successfully';
			}
			else {
				$response = 'Failed to verify current password';
			}
		}
		
		return array('success' => $success, 'response' => $response);
	}
	
	function ResetPassword($_p) {

		$success = false;
		$response = '';
		$redirect = '';
		$swal = false;
		
		
		$password_token = getVarA('token', $_p);
		$password1 = getVarA('password1', $_p);
		$password2 = getVarA('password2', $_p);
		$remember = 1;
		
		if (str_len($password_token) == 0) {
			$response = 'Invalid token.';
		}
		if (str_len($password1) < 8) {
			$response = 'Your new password must be at least 8 characters in length.';
		}
		if ($password1 != $password2) {
			$response = 'Passwords do not match.';
		}
		if (!str_len($response)) {
			$rs = getRs("SELECT admin_id, email, is_enabled FROM admin WHERE password_token = ? AND " . is_active(), array($password_token));
			if ($row = getRow($rs)) {
				if ($row['is_enabled'] == 1) {
					setRs("UPDATE admin SET password = ?, password_token = NULL, date_password_token = NULL WHERE admin_id = ?", array(formatPassword($password1), $row['admin_id']));
					$a = $this->Login($row['email'], $password1, $remember);
					$success = $a['success'];
					$response = $a['response'];
					$redirect = $a['redirect'];
					saveActivity('reset-password', $row['admin_id'], 'admin', 'Password reset successfully', array(), null, null, $success);
				}
				else {
					$response = 'Sorry, but you cannot log into your account at this time. Please contact system administrator.';
					$swal = 'Account Locked';
         			saveActivity('reset-password', $row['admin_id'], 'admin', $response, array(), null, null, $success);
				}
			}
			else {
				$response = 'Invalid token. Please send another request for password reset.';
			}
		}
		
		return array('success' => $success, 'response' => $response, 'redirect' => $redirect, 'swal' => $swal);
	}
	
	function Logout() {
		setRs("UPDATE admin SET access_token = NULL WHERE admin_id = ?", array($this->admin_id));
		saveCookie('tat_mis_access_token', '');
		unset($_SESSION['admin_id']);
		$this->admin_id = 0;
		return array('success' => true, 'response' => 'Logged out successfully.', 'redirect' => '/');
	}
	
	
	/*************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	// EMAIL NOTIFICATIONS //
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	**************************************************************************************************************************************
	*************************************************************************************************************************************/
	

	

	
	function SendNotification($email_notification_code, $placeholders, $attachment = '', $attachment_name = '') {
		$success = false;
		$response = 'Sorry. System cannot send email at this time. Please try again later.';
		$rs = getRs("SELECT email_notification_id, from_name, from_email, bcc, subject, message FROM email_notification WHERE email_notification_code = ? AND " . is_enabled(), array($email_notification_code));
		if ($row = getRow($rs)) {
			$subject = insertPlaceholders($row['subject'], $placeholders);
			$message = insertPlaceholders($row['message'], $placeholders);
			$arr = sendEmail($row['from_name'], $row['from_email'], $placeholders['to_name'], $placeholders['to_email'], $subject, $message, null);
			$success = $arr['success'];
			$response = $arr['response'];
		}
		return array('success' => $success, 'response' => $response);
	}
	

	/******************************************************

	 * ****************************************************/

  function LogActivity($tbl, $id, $activity_type_code, $notes = '', $filename = '', $user_id = 0, $gps = '') {
    $success = false;
    $response = '';
    if ($user_id == 0) $user_id = $this->user_id;
    global $IP, $USER_AGENT;
    $rs = getRs("SELECT activity_type_id FROM activity_type WHERE activity_type_code = ?", array($activity_type_code));
    if ($row = getRow($rs)) {
      setRs("INSERT INTO {$tbl}_activity({$tbl}_id, user_id, activity_type_id, notes, filename, gps, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", array($id, $user_id, $row['activity_type_id'], $notes, $filename, $gps, $IP, $USER_AGENT));
      $success = true;
      $response = 'Saved successfully';
    }
    else {
      $response = 'Note type not found';
    }
    return array('success' => $success, 'response' => $response);
  }
	
  function GetActivity($tbl, $id = 0) {
		return getRs("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS admin_name, CONCAT('/media/admin/', COALESCE(u.image, 'no.png')) image_url, CONCAT('/media/" . $tbl . "_activity/', COALESCE(a.filename, 'no.png')) filename_url, t.activity_type_name FROM admin u INNER JOIN (activity a INNER JOIN activity_type t ON t.activity_type_id = a.activity_type_id) ON u.admin_id = a.admin_id WHERE a.re_table = ? AND a.re_id = ? ORDER BY a.activity_id DESC", array($tbl, $id));
	}

	function GetActivityID($id) {
		return getRs("SELECT l.*, t.activity_type_name, CONCAT(a.first_name, ' ', a.last_name) AS admin_name, null as user_id, null as user_name FROM admin a RIGHT JOIN (activity l INNER JOIN activity_type t ON t.activity_type_id = l.activity_type_id) ON a.admin_id = l.admin_id WHERE " . is_enabled('l') . " AND l.activity_id = ?", array($id));
	}


	function GetActivityLog($re_table = null, $re_id = null) {
		$params = array($this->site_id);
		if ($re_table) array_push($params, $re_table);
		if ($re_id) array_push($params, $re_id);
		return getRs("SELECT l.*, CONCAT(a.first_name, ' ', a.last_name) AS admin_name, CONCAT('/media/admin/', COALESCE(a.image, 'no.png')) image_url, CONCAT('/media/activity/', COALESCE(l.filename, 'no.png')) filename_url, t.activity_type_name, null as user_id, '' AS user_name FROM admin a RIGHT JOIN (activity l INNER JOIN activity_type t ON t.activity_type_id = l.activity_type_id) ON a.admin_id = l.admin_id WHERE " . is_enabled('l') . " AND l.site_id = ?" . iif($re_table, " AND l.re_table = ?") . iif($re_id, " AND l.re_id = ?") . " ORDER BY l.activity_id DESC", $params);
	}

  /**************************************************************** */

	
	function GetIdName($tbl, $id) {
    $rs = getRs("SELECT {$tbl}_name FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_id = ?", array($id));
    if ($row = getRow($rs)) {
      return $row[$tbl . '_name'];
    }
    else {
      return null;
		}
	}

  function GetCodeId($tbl, $code, $account = true) {
		$sql = "SELECT {$tbl}_id FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_code = ?";
		$params = array($code);
		if (!$account) {
			$sql = "SELECT {$tbl}_id FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_code = ?";
			$params = array($code);
		}
    $rs = getRs($sql, $params);
    if ($row = getRow($rs)) {
      return $row[$tbl . '_id'];
    }
    else {
      return null;
    }
  }

  function GetIdCode($tbl, $id, $account = true) {
		$sql = "SELECT {$tbl}_code FROM {$tbl} WHERE " . is_active() . " AND {$tbl}_id = ?";
		$params = array($id);
    $rs = getRs($sql, $params);
    if ($row = getRow($rs)) {
      return $row[$tbl . '_code'];
    }
    else {
      return null;
    }
  }
	

	function NavSort($_p) {
		$success = false;
		$response = '';

		$nav_location_id = getVarA('nav_location_id', $_p);

		if (str_len($response) == 0) {
			setRs("UPDATE nav SET sort = 0 WHERE " . is_active() . " AND nav_location_id = ?", array($nav_location_id));
			$this->SaveSort('nav', $_p['list']);
			$success = true;
			$response = 'Nav sequence successfully.';
		}

		return array('success' => $success, 'response' => $response);

	}

	function NavEnabled($_p) {
		$success = false;
		$response = '';

		$nav_id = getVarANum('nav_id', $_p);
		$is_enabled = getVarANum('is_enabled', $_p);

		setRs("UPDATE nav SET is_enabled = ? WHERE nav_id = ?", array($is_enabled, $nav_id));
		$success = true;
		$response = 'Saved successfully';
		
		return array('success' => $success, 'response' => $response);

	}

	function SaveSort($tbl, $s = null, $parent = null) {
		$sort = 0;
		foreach($s as $_s) {
			setRs("UPDATE {$tbl} SET sort = ?, parent_{$tbl}_id = ? WHERE {$tbl}_id = ?", array(++$sort, $parent, $_s['id']));
			if (isset($_s['children'])) {
				$this->SaveSort($tbl, $_s['children'], $_s['id']);
			}
		}
	}



	function ShowActivity($re_table = null, $re_id = null) {
		$rs = $this->GetActivityLog($re_table, $re_id);
		$ret = '<table class="table-analytics table table-bordered" cellspacing="0">
		<thead>
				<tr class="inverse">
						<th>ID</th>
						<th>Type</th>
						' . iif(!$re_table, '<th>Reference</th>') . '
						' . iif(!$re_id, '<th>Subject</th>') . '
						<th>Date</th>
						<th>By</th>
						<th>Notes</th>
						<th></th>
				</tr>
		</thead><tbody>';
			
			foreach($rs as $r) {
				$ret .= '
				<tr>
					<td>' . $r['activity_id'] . '</td>
					<td>' . $r['activity_type_name'] . '</td>
					' . iif(!$re_table, '<td>' . nicefy($r['re_table']) . '</td>') . '
					' . iif(!$re_id, '<td>' . $r['re_id'] . '</td>') . '
					<td data-sort="' . $r['date_created'] . '" >' . getLongDate($r['date_created']) . '</td>
					<td>' . $r['admin_name'] . $r['user_name'] . '<br /><small>' . iif($r['user_id'], '<a href="" class="btn-table-dialog" data-url="users" data-title="User: ' . $r['user_name'] . '" data-id="' . $r['user_id'] . '">User</a>', '<a href="" class="btn-table-dialog" data-url="admins" data-title="Admin: ' . $r['admin_name'] . '" data-id="' . $r['admin_id'] . '">Admin</a>') . '</small></td>
					<td>' . shorten($r['notes']) . '</td>
					<td class="text-right"><a href="" class="btn btn-sm btn-info btn-dialog" data-title="Activity Log Details" data-url="log" data-id="' . $r['activity_id'] . '" data-hide-btns="true">Details</a></td>
				</tr>';
			}
			$ret .= '
			</tbody>
		</table>';
		return $ret;
	}

	
	function GetAddress($settlement_id) {
		$rs = getRs("SELECT s.settlement_name, w.ward_name FROM settlement s LEFT JOIN ward w ON w.ward_id = s.ward_id WHERE s.settlement_id = ?", array($settlement_id));
		if ($r = getRow($rs)) {
			return $r['settlement_name'] . ', ' . $r['ward_name'];
		}
  }
  
	function GetFiles($tbl, $id) {
		return getRs("SELECT f.file_id, f.file_code, f.filename, f.description, f.is_auto, f.date_created, CONCAT('/media/admin/sm/', COALESCE(a.image, 'no.png')) AS admin_image_url, CONCAT(a.first_name, ' ', a.last_name) AS admin_name FROM admin a RIGHT JOIN file f ON f.admin_id = a.admin_id WHERE " . is_enabled('f') . " AND f.re_tbl = ? AND f.re_id = ? ORDER BY f.file_id DESC", array($tbl, $id));
	}

	function ShowFiles($rf, $tbl = null, $code = null) {
		if (sizeof($rf)) {
			$ret = '
			<input type="hidden" id="AddFileTableName" value="' . $tbl . '" />
			<input type="hidden" id="AddFileCode" value="' . $code . '" />
			<div class="panel pagination-inverse m-b-0 clearfix">
			<div class="icheck-info nowrap mt-3 ml-4 show-manual-entries-' . $tbl . '"><input type="checkbox" value="1" id="show_manual_entries_' . $tbl . '" name="n_show_manual_entries_' . $tbl . '" data-render="switchery" data-theme="info" checked /><label for="show_manual_entries_' . $tbl . '" class="m-l-5 m-r-10"><b>Show only manual entries</b></label></div>
			<table class="table table-bordered table-hover table-log">
				<thead class="bg-invert">
					<tr>
						<th>ID</th>
						<th>Description</th>
						<th>By</th>
						<th>Date</th>
						<th></th>
					</tr>
				</thead>
				<tbody>';
					foreach($rf as $f) {
					$ret .= '
					<tr data-auto="' . iif($f['is_auto'], 1, 0) . '">
						<td>' . $f['file_id'] . '</td>
						<td>';
						if(str_len($f['filename'])) {
							if (!isJson($f['filename'])) {
								$ret .= '<div><a href="/media/file/' . $f['filename'] . '" target="_blank"><i class="fa fa-paperclip"></i></a> </div>';
							}
							else {
								$fs = json_decode($f['filename'], true);
								if (is_array($fs)) {
								foreach($fs as $_f) {
									$ret .= '<div class="mb-1"><a href="/media/file/' . $_f['name'] . '" target="_blank">' . ((isset($_f['thumbnailUrl']) and str_len($_f['thumbnailUrl']))?'<img src="' . $_f['thumbnailUrl'] . '" class="img-icon" />':'<i class="fa fa-paperclip"></i>') . ' ' . $_f['original_name'] . '</a> <small>(' . fileSizeFormat($_f['size']) . ')</small></div>';
								}
								}
							}
							$ret .= iif(str_len($f['description']), '<div><small>' . nl2br($f['description']) . '</small></div>');
						}
						else {
							$ret .= iif(str_len($f['description']), '<div>' . nl2br($f['description']) . '</div>');
						}
						$ret .= '</td>
						<td><img src="' . $f['admin_image_url'] . '" class=img-person /> ' . $f['admin_name'] . '</td>
						<td>' . getLongDate($f['date_created']) . '</td>
						<td>' . iif(!$f['is_auto'], '<button class="btn btn-danger btn-xs btn-del-file" data-c="' . $f['file_code'] . '">Delete</button>') . '</td>
					</tr>';
					}
					$ret .= '
				</tbody>
			</table>
			</div>';
		}
		else {			
			$ret = '
				<div class="alert alert-info alert-dismissible" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<div class="alert-icon">
						<i class="icon-info"></i>
					</div>
					<div class="alert-message">
						<span><strong>No files / notes</strong> saved.</span>
					</div>
				</div>
				<a href="" class="btn btn-default btn-sm btn-dialog" data-url="file" data-title="Add File / Note" data-a="' . $tbl . '" data-b="' . $code . '">Add File / Note</a>
			';
		}
		return $ret;
	}

	function SaveFile($_p) {
		$success = false;
		$response = '';
		$redirect = '';
		$html = '';
		
		$id = $file_size = $file_ext = $filename = null;
		$tbl = getVarA('tbl', $_p);
		$code = getVarA('code', $_p);
		$file_name = getVarA('file_name', $_p);
		$description = getVarA('description', $_p);

		if (str_len($code)) {
			$id = $this->GetCodeId($tbl, $code);
		}
		if (!$id) {
			$response = 'Not specified';
		}

		if (isset($_p['filename_media_item_data'])) {
			$filename = array();
			foreach($_p['filename_media_item_data'] as $f) {
				array_push($filename, json_decode($f, true));
			}
			$filename = json_encode($filename);
		}
		if (!$filename and !str_len($description)) {
			$response = 'Please enter notes or upload a file';
		}

		if (str_len($response) == 0) {
			setRs("INSERT INTO file (re_tbl, re_id, description, filename, admin_id) VALUES (?, ?, ?, ?, ?)", array($tbl, $id, $description, $filename, $this->admin_id));
			$success = true;
			$response = 'Saved successfully';
			$redirect = '{refresh}';
		}

		return array('success' => $success, 'response' => $response, 'redirect' => $redirect);

  }
  

  ///////////////////////////////////


	function GetSetting($setting_code) {
		if (isset($this->settings[$setting_code])) {
			return $this->settings[$setting_code];
		}
		else {
			return '';
		}
	}

	function StoreSetting($setting_code) {
		
		if (isset($this->store_settings[$setting_code])) {
			return $this->store_settings[$setting_code];
		}
		else {
			return '';
		}
	}


	function TableManager($module_code, $id = null, $add_url = null, $css = 'btn-table-dialog', $header = true, $table_css = null) {
		$ret = '';
		if ($this->HasModulePermission($module_code)) {
			$rs = getRs("SELECT * FROM module WHERE " . is_enabled() . " AND module_code = ?", array($module_code));
			if ($r = getRow($rs)) {
				if ($r['params']) {
					$p = json_decode($r['params'], true);
					if ($p['cols']) {
					$ret = '
						<div class="panel panel-inverse">
							' . iif($header, '<div class="panel-heading mb-2">
								<h4 class="panel-title">' . $r['module_name'] . '</h4>
							</div>');
							if($add_url) {
							$ret .= '<div class="text-right pb-3 pr-3">';
							if ($module_code == 'proceeding-persons') {

								$plaintiff = $respondent = null;
									
								if ($id) {
									$rs1 = getRs("SELECT pt.plaintiff, pt.respondent FROM proceeding_type pt INNER JOIN proceeding g ON pt.proceeding_type_id = g.proceeding_type_id WHERE " . is_active('pt,g') . " AND g.proceeding_id = ?", array($id));
									if($r1 = getRow($rs1)) {
										$plaintiff = $r1['plaintiff'];
										$respondent = $r1['respondent'];
									}
								}

								$rt = getRs("SELECT proceeding_person_type_id, proceeding_person_type_name FROM proceeding_person_type WHERE " . is_enabled() . " ORDER BY sort, proceeding_person_type_id");
								foreach($rt as $t) {
									$__t = $t['proceeding_person_type_name'];
									if ($t['proceeding_person_type_id'] == 1 and $plaintiff) $__t = $plaintiff;
									else if ($t['proceeding_person_type_id'] == 2 and $respondent) $__t = $respondent;
									$ret .= '<button type="button" data-edit="0" data-a="' . $id . '" data-b="' . $t['proceeding_person_type_id'] . '" class="ml-2 btn btn-xs btn-rounded btn-default ' . $css . '" data-url="' . $add_url .'" data-title="Add ' . $__t . '">Add ' . $__t . '</button>';
								}
							}
							else if ($module_code == 'proceeding-companies') {

								$plaintiff = $respondent = null;
									
								if ($id) {
									$rs1 = getRs("SELECT pt.plaintiff, pt.respondent FROM proceeding_type pt INNER JOIN proceeding g ON pt.proceeding_type_id = g.proceeding_type_id WHERE " . is_active('pt,g') . " AND g.proceeding_id = ?", array($id));
									if($r1 = getRow($rs1)) {
										$plaintiff = $r1['plaintiff'];
										$respondent = $r1['respondent'];
									}
								}
								
								$rt = getRs("SELECT proceeding_company_type_id, proceeding_company_type_name FROM proceeding_company_type WHERE " . is_enabled() . " ORDER BY sort, proceeding_company_type_id");
								foreach($rt as $t) {
									$__t = $t['proceeding_company_type_name'];
									if ($t['proceeding_company_type_id'] == 1 and $plaintiff) $__t = $plaintiff . ' Council';
									else if ($t['proceeding_company_type_id'] == 2 and $respondent) $__t = $respondent . ' Council';
									$ret .= '<button type="button" data-edit="0" data-a="' . $id . '" data-b="' . $t['proceeding_company_type_id'] . '" class="ml-2 btn btn-xs btn-rounded btn-default ' . $css . '" data-url="' . $add_url .'" data-title="Add ' . $__t . '">Add ' . $__t . '</button>';
								}
							}
							else {
								$ret .= '<button type="button" data-edit="0" data-a="' . $id . '" class="btn btn-sm btn-yellow mt-2 ' . $css . '" data-url="' . $add_url .'" data-title="Add new">Add new</button>';
							}
							$ret .= '</div>';
							}
							$ret .= '
							<div class="panel-body' . iif(!$header, ' p-0') . '">
								<table data-url="' . $module_code . '" data-a="' . $id . '" class="table table-bordered table-hover w-100 datatable-live large export ' . $table_css . '">
									<thead><tr><th>ID</th>';
									foreach($p['cols'] as $_c) {
										$__c = explode(',', $_c);
										$ret .= '<th>' . ((sizeof($__c) > 1)?$__c[1]:'') . '</th>';
									}
									$ret .= '
									<th>Actions</th></tr></thead>
								<tbody>
								</tbody>
								</table>
							</div>
						</div>';
					}
				}
			}
		}

	return $ret;
	}
	

}

?>