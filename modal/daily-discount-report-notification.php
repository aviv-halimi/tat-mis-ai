<?php
/**
 * Modal: Email daily discount report PDF to brand (notification_type_id = 7).
 * GET/POST: c = daily_discount_report_brand_id, a = notification_type_id (default 7).
 * Brand contact (name/email) is stored on store_id=1 brand table; pre-filled if set.
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
$notification_type_id = getVarNum('a', 7, 1, 999);
$report_format = (isset($_REQUEST['format']) && strtolower(trim($_REQUEST['format'])) === 'xlsx') ? 'xlsx' : 'pdf';

$email = '';
$contact_name = '';
$subject = '';
$message = '';

if (!$daily_discount_report_brand_id) {
    echo '<div class="alert alert-danger">Missing report brand.</div>';
    exit;
}

$rb = getRow(getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id, rb.filename, r.date_start, r.date_end, b.name AS brand_name FROM daily_discount_report_brand rb INNER JOIN daily_discount_report r ON r.daily_discount_report_id = rb.daily_discount_report_id INNER JOIN blaze1.brand b ON b.brand_id = rb.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb,r'),
    array($daily_discount_report_brand_id)
));
if (!$rb) {
    echo '<div class="alert alert-danger">Report brand not found.</div>';
    exit;
}

$brand_id = (int)$rb['brand_id'];
$store1 = getRow(getRs("SELECT db FROM store WHERE store_id = 1 AND " . is_enabled(), array()));
$store1_db = ($store1 && !empty($store1['db'])) ? preg_replace('/[^a-z0-9_]/i', '', $store1['db']) : '';
if ($store1_db !== '') {
    $col_check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME IN ('contact_email', 'contact_name')", array($store1_db));
    $has_contact = $col_check && (int)getRow($col_check)['c'] >= 2;
    if ($has_contact) {
        $br = getRow(getRs("SELECT contact_name, contact_email FROM `" . str_replace('`', '``', $store1_db) . "`.brand WHERE master_brand_id = ?", array($brand_id)));
        if ($br) {
            $contact_name = isset($br['contact_name']) ? trim((string)$br['contact_name']) : '';
            $email = isset($br['contact_email']) ? trim((string)$br['contact_email']) : '';
        }
    }
}

$rs = getRs("SELECT * FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id));
$r = $rs ? getRow($rs) : null;
if (!$r) {
    echo '<div class="alert alert-danger">Notification type not found. Create notification_type_id = 7 for daily discount report to brand.</div>';
    exit;
}

$placeholders = array(
    'brand_name' => isset($rb['brand_name']) ? $rb['brand_name'] : '',
    'contact_name' => $contact_name,
    'contact_email' => $email,
    'date_start' => isset($rb['date_start']) ? $rb['date_start'] : '',
    'date_end' => isset($rb['date_end']) ? $rb['date_end'] : '',
    'filename' => isset($rb['filename']) ? $rb['filename'] : '',
);
$subject = insertPlaceholders($r['subject'], $placeholders);
$message = insertPlaceholders($r['message'], $placeholders);

$pdf_path = MEDIA_PATH . 'daily_discount_report_brand/' . (isset($rb['filename']) ? $rb['filename'] : '');
$has_pdf = !empty($rb['filename']) && is_file($pdf_path);
?>
<form method="post" action="">
<input type="hidden" name="daily_discount_report_brand_id" value="<?php echo (int)$daily_discount_report_brand_id; ?>" />
<input type="hidden" name="notification_type_id" value="<?php echo (int)$notification_type_id; ?>" />
<input type="hidden" name="format" value="<?php echo htmlspecialchars($report_format); ?>" />
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Notification Type:</div>
  <div class="col-sm-10 col-form-label"><b><?php echo htmlspecialchars($r['notification_type_name']); ?></b></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Brand:</div>
  <div class="col-sm-10 col-form-label"><b><?php echo htmlspecialchars($rb['brand_name']); ?></b></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Contact name:</div>
  <div class="col-sm-10"><input type="text" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($contact_name); ?>" placeholder="Brand contact name (saved to brand for store 1)" /></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Recipient E-mail:</div>
  <div class="col-sm-10"><input type="text" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="Recipient E-mail (required; saved to brand for store 1 if blank)" /><small class="text-muted">If you enter an e-mail and it is not yet saved, it will be saved to the brand contact for next time.</small></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Subject:</div>
  <div class="col-sm-10"><input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject); ?>" placeholder="Subject ..." /></div>
</div>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Message:</div>
  <div class="col-sm-10"><textarea name="message" rows="10" id="message_dd_notif" class="ckeditor form-control" placeholder="Your message here ..."><?php echo htmlspecialchars($message); ?></textarea></div>
</div>
<?php if ($report_format === 'xlsx') { ?>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Attachment:</div>
  <div class="col-sm-10 col-form-label"><i class="fa fa-file-excel"></i> Excel report (generated on send)</div>
</div>
<?php } elseif ($has_pdf) { ?>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Attachment:</div>
  <div class="col-sm-10 col-form-label"><i class="fa fa-paperclip"></i> <?php echo htmlspecialchars($rb['filename']); ?> (PDF)</div>
</div>
<?php } else { ?>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Attachment:</div>
  <div class="col-sm-10 col-form-label text-muted">No PDF generated yet for this report brand. Generate report first or use Excel.</div>
</div>
<?php } ?>
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label"></div>
  <div class="col-sm-10">
    <div class="form-status status" id="status_daily-discount-report-notification"></div>
    <div class="form-btns">
      <button type="submit" class="btn btn-primary">Send</button>
    </div>
  </div>
</div>
</form>
