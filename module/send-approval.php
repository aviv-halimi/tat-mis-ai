<?php
define('SkipAuth', true);
require_once ('../_config.php');


	if (isset($_GET['poid'])) {
		$admin_id = $_Session->admin_id;
		$admin_name = NULL;
		$admin_email = NULL;
		$po_id = $_GET['poid'];
		$nc = (isset($_GET['nc']))?$_GET['nc']:false;
		$back = (isset($_GET['back']))?$_GET['back']:false;
		$ra = getRs("SELECT * FROM theartisttree.admin a WHERE a.admin_id = {$_Session->admin_id}");
		if ($r = getRow($ra)) {
			$admin_name = $r['admin_name'];
			$admin_email = $r['email'];
		}
		
		$rs = getRs("SELECT s.store_name, s.params, po.po_name, po.po_number, po.vendor_name, po.po_code, st.po_status_id, st.po_status_name
					FROM theartisttree.po 
					INNER JOIN theartisttree.store s on s.store_id = po.store_id
					INNER JOIN theartisttree.po_status st on st.po_status_id = po.po_status_id
					where po.po_id = {$po_id}");
		
		
		if ($r = getRow($rs)) {
			$new_status_id = ($back)?$r['po_status_id'] - 1:$r['po_status_id'] + 1;
			$advance_text = ($back)?'Push Back':'Push Forward';
			$rst = getRs("SELECT st.po_status_name FROM theartisttree.po_status st WHERE st.po_status_id = {$new_status_id}");
			$new_status = ($s = getRow($rst))?$s['po_status_name']:NULL;
			
			$store = $r['store_name'];
			$po_name = $r['po_name'];
			$params = json_decode($r['params'], true);
			$nc_text = $nc?" (Non Conforming)":NULL;

            //$cc = $params$params['po_email'];
			$cc = $admin_email;
			
			$from_name = 'PO Approvals';
			$from_email = 'admin@theartisttree.com';
			$name = 'PO Apprvals';
			$email = "poapproval@theartisttree.com,{$cc}";
			//$email = 'aviv@theartisttree.com';
			$subject = "PO APPROVAL: PO {$r['po_number']} ({$store})";
			$message = "
				<b>{$admin_name}</b> has requested approval to " . strtolower($advance_text) . " <b>PO {$r['po_number']}</b> from <b>'{$r['po_status_name']}'</b> to <b>'{$new_status}{$nc_text}'</b>: <br><br>
				
				PO Number:  <b>{$r['po_number']}</b><br>
				Store Name:  <b>{$r['store_name']}</b><br>
				PO Name:  <b>{$r['po_name']}</b><br>
				Request Type:  <b>{$advance_text} ('{$r['po_status_name']}' to '{$new_status}{$nc_text}')</b><br>
				Vendor:  <b>{$r['vendor_name']}</b><br>
				Link:  <b>" . getCurrentHost() . "po/" . $r['po_code'] . "</b>"
				;
			$footer = '--';
			$send = sendEmail($from_name, $from_email, $name, $email, $subject, $message, $footer);
			$note = 'APPROVAL REQUEST: <b>' . $admin_name . '</b> requested approval to <b>' . strtolower($advance_text) .'</b> from <b>' . $r['po_status_name'] . '</b> to <b>' . $new_status . $nc_text . '.';
			$update = dbPut('file', array('re_tbl' => 'po', 're_id' => $po_id, 'admin_id' => $admin_id, 'description' => $note, 'is_auto' => 1));
		
		}
	}
	//echo $message;
	echo "Email sent.  Redirecting back to PO Page...";

    header( "refresh:5; url= ". getCurrentHost() . "/po/" . $r['po_code']); 

?>
