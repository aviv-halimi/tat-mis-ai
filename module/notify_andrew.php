<?php
define('SkipAuth', true);
require_once ('../_config.php');

	if (isset($_GET['poid'])) {
		$admin_id = $_Session->admin_id;
		$admin_name = NULL;
		$admin_email = NULL;
		$po_id = $_GET['poid'];
		$nc = (isset($_GET['nc']))?$_GET['nc']:false;
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
			$old_status_id = $r['po_status_id'] + 1;
			$rst = getRs("SELECT st.po_status_name FROM theartisttree.po_status st WHERE st.po_status_id = {$old_status_id}");
			$old_status = ($s = getRow($rst))?$s['po_status_name']:NULL;
			
			$store = $r['store_name'];
			$po_name = $r['po_$rs = getRsname'];
			$params = json_decode($r['params'], true);

            //$cc = $params['po_email'];
			$store_email = $params['boh_email'];
			$cc = $admin_email;
			
			$from_email = 'admin@theartisttree.com';
			$name = 'BOH Request';
			$email = 'andrewz@theartisttree.com';
			//$email = 'aviv@theartisttree.com';
			$subject = "PO {$r['po_number']} Needs Your Attention! ({$store})";
			$message = "
				<b>{$admin_name}</b> has notified you that <b>PO {$r['po_number']}</b> needs your attention.<br><br>
				
				PO Name:  <b>{$r['po_name']}</b><br>
				PO Number:  <b>{$r['po_number']}</b><br>
				PO Status:  <b>'{$r['po_status_name']}'</b><br>
				Store Name:  <b>{$r['store_name']}</b><br>
				Vendor:  <b>{$r['vendor_name']}</b><br>
				Link:  <b>" . getCurrentHost() . "po/" . $r['po_code'] . "</b>"
				;
			$footer = '';
			$send = sendEmail($from_name, $from_email, $name, $email, $subject, $message, $footer);
			$note = 'Notify BOH: ' . $admin_name . ' sent an email notification to Andrew.';
			$update = dbPut('file', array('re_tbl' => 'po', 're_id' => $po_id, 'admin_id' => $admin_id, 'description' => $note, 'is_auto' => 0));
		}
		
	}
	//echo $message;
	echo "Email sent.  Redirecting back to PO Page...";

    header( "refresh:4; url= ". getCurrentHost() . "/po/" . $r['po_code']); 

	
  
    
?>
