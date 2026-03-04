<?php
/**
 * Modal: Email daily discount report PDF to brand (notification_type_id 7 or 8).
 * GET/POST: c = daily_discount_report_brand_id, a = notification_type_id (default from blaze1.brand.notification_type_id or 7).
 * Brand contact (name/email) and notification_type_id are stored on store_id=1 brand table.
 */
require_once(__DIR__ . '/../_config.php');
$daily_discount_report_brand_id = getVarInt('c', 0, 0, 999999);
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
$notification_type_id = 7;
$notification_type_id_from_brand = false;
if ($store1_db !== '') {
    $col_check = getRs("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME IN ('contact_email', 'contact_name', 'notification_type_id')", array($store1_db));
    $cols = array();
    if (is_array($col_check)) {
        foreach ($col_check as $row) {
            $cols[] = $row['COLUMN_NAME'];
        }
    }
    $has_contact = in_array('contact_email', $cols) && in_array('contact_name', $cols);
    if ($has_contact || in_array('notification_type_id', $cols)) {
        $select_cols = array('contact_name', 'contact_email');
        if (in_array('notification_type_id', $cols)) {
            $select_cols[] = 'notification_type_id';
        }
        $br = getRow(getRs("SELECT " . implode(', ', $select_cols) . " FROM `" . str_replace('`', '``', $store1_db) . "`.brand WHERE master_brand_id = ?", array($brand_id)));
        if ($br) {
            if ($has_contact) {
                $contact_name = isset($br['contact_name']) ? trim((string)$br['contact_name']) : '';
                $email = isset($br['contact_email']) ? trim((string)$br['contact_email']) : '';
            }
            if (in_array('notification_type_id', $cols) && isset($br['notification_type_id']) && (int)$br['notification_type_id'] >= 1) {
                $notification_type_id = (int)$br['notification_type_id'];
                $notification_type_id_from_brand = true;
            }
        }
    }
}
// Use request param only when brand has no saved notification_type_id (so saved value is recalled)
if (!$notification_type_id_from_brand) {
    $a_param = getVarNum('a', 0, 1, 999);
    if ($a_param >= 1) {
        $notification_type_id = $a_param;
    }
}

$notification_types_rs = getRs("SELECT notification_type_id, notification_type_name FROM notification_type WHERE " . is_enabled() . " AND notification_type_id IN (7, 8) ORDER BY notification_type_id", array());
$notification_types = $notification_types_rs ?: array();
// Fetch full row (subject, message) for the selected type so we can populate the form
$r = getRow(getRs("SELECT notification_type_id, notification_type_name, subject, message FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id)));
if (!$r && count($notification_types) > 0) {
    $notification_type_id = (int)$notification_types[0]['notification_type_id'];
    $r = getRow(getRs("SELECT notification_type_id, notification_type_name, subject, message FROM notification_type WHERE " . is_enabled() . " AND notification_type_id = ?", array($notification_type_id)));
}
if (!$r) {
    echo '<div class="alert alert-danger">Notification type not found. Create notification_type_id 7 and/or 8 for daily discount report.</div>';
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
$subject_base = insertPlaceholders(isset($r['subject']) ? $r['subject'] : '', $placeholders);
$report_date_ts = !empty($rb['date_start']) ? strtotime($rb['date_start']) : time();
$subject_prefix = trim(isset($rb['brand_name']) ? $rb['brand_name'] : '') . ' ' . date('M', $report_date_ts) . ' ' . date('Y', $report_date_ts);
$subject = $subject_prefix . ($subject_base !== '' ? ' ' . $subject_base : '');
$message = insertPlaceholders(isset($r['message']) ? $r['message'] : '', $placeholders);

$pdf_path = MEDIA_PATH . 'daily_discount_report_brand/' . (isset($rb['filename']) ? $rb['filename'] : '');
$has_pdf = !empty($rb['filename']) && is_file($pdf_path);
?>
<form method="post" action="">
<input type="hidden" name="daily_discount_report_brand_id" value="<?php echo (int)$daily_discount_report_brand_id; ?>" />
<input type="hidden" name="format" value="<?php echo htmlspecialchars($report_format); ?>" />
<div class="row m-b-10">
  <div class="col-sm-2 col-form-label">Notification Type:</div>
  <div class="col-sm-10">
    <select name="notification_type_id" class="form-control" style="max-width:280px;">
      <?php foreach ($notification_types as $nt): ?>
      <option value="<?php echo (int)$nt['notification_type_id']; ?>"<?php echo (int)$nt['notification_type_id'] === $notification_type_id ? ' selected="selected"' : ''; ?>><?php echo htmlspecialchars($nt['notification_type_name']); ?></option>
      <?php endforeach; ?>
    </select>
    <small class="text-muted">Saved to brand for next time.</small>
  </div>
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
