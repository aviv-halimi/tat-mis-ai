<?php
/**
 * Daily Discount Report → QBO: preflight (check connections/mapping/account) and push (create Vendor Credits).
 * POST: action = 'preflight' | 'push', daily_discount_report_brand_id
 */
require_once('../_config.php');
require_once(BASE_PATH . 'inc/qbo.php');

header('Content-Type: application/json');

try {
$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');
$daily_discount_report_brand_id = getVarInt('daily_discount_report_brand_id', 0, 0, 999999);
$single_store_id = getVarInt('single_store_id', 0, 0, 99999);

if (!$daily_discount_report_brand_id) {
    echo json_encode(array('success' => false, 'response' => 'Missing report brand.', 'ok' => false));
    exit;
}

$rb = getRow(getRs(
    "SELECT rb.daily_discount_report_brand_id, rb.brand_id, rb.filename, r.date_start, r.date_end, b.name AS brand_name FROM daily_discount_report_brand rb INNER JOIN daily_discount_report r ON r.daily_discount_report_id = rb.daily_discount_report_id INNER JOIN blaze1.brand b ON b.brand_id = rb.brand_id WHERE rb.daily_discount_report_brand_id = ? AND " . is_enabled('rb,r'),
    array($daily_discount_report_brand_id)
));
if (!$rb) {
    echo json_encode(array('success' => false, 'response' => 'Report brand not found.', 'ok' => false));
    exit;
}

$brand_id = (int)$rb['brand_id'];
$stores_rs = getRs(
    "SELECT s.store_id, s.store_name, s.db AS store_db FROM daily_discount_report_store d INNER JOIN store s ON s.store_id = d.store_id WHERE d.daily_discount_report_brand_id = ? AND " . is_enabled('d,s') . " ORDER BY s.store_id",
    array($daily_discount_report_brand_id)
);
$stores = $stores_rs ?: array();

$need_auth = array();
$need_account = array();
$need_mapping = array();

// Connection status only: return per-store QBO connection (for connection-status modal)
if ($action === 'connection_status') {
    $status_stores = array();
    foreach ($stores as $s) {
        $store_id = (int)$s['store_id'];
        $store_name = isset($s['store_name']) ? $s['store_name'] : 'Store ' . $store_id;
        $params = qbo_get_store_params($store_id);
        $connected = false;
        $auth_url = '';
        if ($params && !empty($params['realm_id']) && !empty($params['refresh_token'])) {
            $token = qbo_get_access_token($store_id);
            $connected = is_array($token) && !empty($token['access_token']);
            if (!$connected && isset($token['auth_url'])) {
                $auth_url = $token['auth_url'];
            }
        }
        if (!$connected && $auth_url === '' && function_exists('qbo_get_auth_url')) {
            $auth_url = qbo_get_auth_url($store_id);
        }
        $status_stores[] = array(
            'store_id' => $store_id,
            'store_name' => $store_name,
            'connected' => $connected,
            'auth_url' => $auth_url,
        );
    }
    echo json_encode(array('success' => true, 'stores' => $status_stores));
    exit;
}

foreach ($stores as $s) {
    $store_id = (int)$s['store_id'];
    $store_name = isset($s['store_name']) ? $s['store_name'] : 'Store ' . $store_id;
    $store_db = isset($s['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s['store_db']) : '';

    $params = qbo_get_store_params($store_id);
    if (!$params || empty($params['realm_id']) || empty($params['refresh_token'])) {
        $auth_url = function_exists('qbo_get_auth_url') ? qbo_get_auth_url($store_id) : '';
        $need_auth[] = array('store_id' => $store_id, 'store_name' => $store_name, 'auth_url' => $auth_url);
        continue;
    }
    $token = qbo_get_access_token($store_id);
    if (!is_array($token) || empty($token['access_token'])) {
        $auth_url = isset($token['auth_url']) ? $token['auth_url'] : (function_exists('qbo_get_auth_url') ? qbo_get_auth_url($store_id) : '');
        $need_auth[] = array('store_id' => $store_id, 'store_name' => $store_name, 'auth_url' => $auth_url);
        continue;
    }

    $account_daily = isset($token['account_id_daily_discount']) ? trim($token['account_id_daily_discount']) : '';
    if ($account_daily === '') {
        $need_account[] = array('store_id' => $store_id, 'store_name' => $store_name);
        continue;
    }

    if ($store_db === '') {
        $need_mapping[] = array('store_id' => $store_id, 'store_name' => $store_name);
        continue;
    }
    $qbo_vendor_id = '';
    $col_check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'qbo_vendor_id'", array($store_db));
    if ($col_check && (int)getRow($col_check)['c'] > 0) {
        $br = getRow(getRs("SELECT qbo_vendor_id FROM `" . str_replace('`', '``', $store_db) . "`.brand WHERE master_brand_id = ?", array($brand_id)));
        $qbo_vendor_id = isset($br['qbo_vendor_id']) ? trim((string)$br['qbo_vendor_id']) : '';
    }
    if ($qbo_vendor_id === '') {
        $need_mapping[] = array('store_id' => $store_id, 'store_name' => $store_name);
    }
}

$ok = (count($need_auth) === 0 && count($need_account) === 0 && count($need_mapping) === 0);

if ($action === 'preflight') {
    echo json_encode(array(
        'success' => true,
        'ok' => $ok,
        'need_auth' => $need_auth,
        'need_account' => $need_account,
        'need_mapping' => $need_mapping,
    ));
    exit;
}

// When pushing a single store (test), allow push even if other stores would fail preflight
if (!$ok && !($action === 'push' && $single_store_id > 0)) {
    echo json_encode(array(
        'success' => false,
        'response' => 'Preflight failed. Connect QBO, set GL account, or map vendors.',
        'ok' => false,
        'need_auth' => $need_auth,
        'need_account' => $need_account,
        'need_mapping' => $need_mapping,
    ));
    exit;
}

// ——— Preview push (store_id = 1 only, for test modal) ———
if ($action === 'preview_push') {
    $preview_store_id = 1;
    $date_start = isset($rb['date_start']) ? $rb['date_start'] : '';
    $date_end = isset($rb['date_end']) ? $rb['date_end'] : '';
    $note = 'Daily discount rebate' . ($date_start && $date_end ? ' ' . $date_start . '–' . $date_end : '');
    $filename_preview = isset($rb['filename']) && trim($rb['filename']) !== '' ? trim($rb['filename']) : 'dd-report-brand-' . $daily_discount_report_brand_id . '-' . date('Ymd-His') . '.pdf';
    $pdf_name_preview = basename($filename_preview);
    $brand_name_preview = isset($rb['brand_name']) ? trim((string)$rb['brand_name']) : '';
    $doc_suffix_preview = ' -' . date('M j') . '-DD';
    $doc_max_brand_preview = 21 - strlen($doc_suffix_preview);
    $doc_base_preview = ($doc_max_brand_preview > 0 && $brand_name_preview !== '') ? mb_substr($brand_name_preview, 0, $doc_max_brand_preview) : 'DD';
    $doc_number_preview = mb_substr($doc_base_preview . $doc_suffix_preview, 0, 21);
    $txn_date_preview = date('Y-m-d');

    $s1 = null;
    foreach ($stores as $s) {
        if ((int)$s['store_id'] === $preview_store_id) {
            $s1 = $s;
            break;
        }
    }
    if (!$s1) {
        echo json_encode(array('success' => false, 'response' => 'Store 1 not in this report.', 'preview' => null));
        exit;
    }
    $store_id = (int)$s1['store_id'];
    $store_name = isset($s1['store_name']) ? $s1['store_name'] : 'Store ' . $store_id;
    $store_db = isset($s1['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s1['store_db']) : '';
    $dr = getRow(getRs("SELECT params FROM daily_discount_report_store WHERE daily_discount_report_brand_id = ? AND store_id = ?", array($daily_discount_report_brand_id, $store_id)));
    $params_json = isset($dr['params']) ? $dr['params'] : '[]';
    $rp = json_decode($params_json, true);
    if (!is_array($rp)) {
        $rp = array();
    }
    $store_total = 0;
    foreach ($rp as $p) {
        $qty = isset($p['quantity']) ? (float)$p['quantity'] : 0;
        $pct = isset($p['rebate_percent']) ? (float)$p['rebate_percent'] : 0;
        $up = isset($p['unit_price']) ? (float)$p['unit_price'] : 0;
        $store_total += $qty * $pct / 100 * $up;
    }
    $store_total = round($store_total, 2);
    $qbo_vendor_id = '';
    if ($store_db !== '') {
        $col_check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'qbo_vendor_id'", array($store_db));
        if ($col_check && (int)getRow($col_check)['c'] > 0) {
            $br = getRow(getRs("SELECT qbo_vendor_id FROM `" . str_replace('`', '``', $store_db) . "`.brand WHERE master_brand_id = ?", array($brand_id)));
            $qbo_vendor_id = isset($br['qbo_vendor_id']) ? trim((string)$br['qbo_vendor_id']) : '';
        }
    }
    $token = qbo_get_access_token($store_id);
    $account_daily = isset($token['account_id_daily_discount']) ? trim($token['account_id_daily_discount']) : '';
    echo json_encode(array(
        'success' => true,
        'preview' => array(
            'store_id' => $store_id,
            'store_name' => $store_name,
            'amount' => $store_total,
            'doc_number' => $doc_number_preview,
            'txn_date' => $txn_date_preview,
            'note' => $note,
            'pdf_filename' => $pdf_name_preview,
            'qbo_vendor_id' => $qbo_vendor_id,
            'account_id_daily_discount' => $account_daily,
        ),
    ));
    exit;
}

// ——— Push ———
require_once(BASE_PATH . 'inc/pdf-report.php');
$log = array('date' => date('Y-m-d H:i:s'), 'by_admin_id' => (isset($_Session) && isset($_Session->admin_id)) ? $_Session->admin_id : null, 'stores' => array());
$dir = MEDIA_PATH . 'daily_discount_report_brand/';
$filename = isset($rb['filename']) ? trim($rb['filename']) : '';
if ($filename === '' || !is_file($dir . $filename)) {
    $filename = 'dd-report-brand-' . $daily_discount_report_brand_id . '-' . date('Ymd-His') . '.pdf';
    $fp = $dir . $filename;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    generateReport(null, $daily_discount_report_brand_id, $fp);
    if (is_file($fp)) {
        dbUpdate('daily_discount_report_brand', array('filename' => $filename), $daily_discount_report_brand_id);
    }
}
$pdf_path = $dir . $filename;
$pdf_name = basename($filename);

$date_start = isset($rb['date_start']) ? $rb['date_start'] : '';
$date_end = isset($rb['date_end']) ? $rb['date_end'] : '';
$note = 'Daily discount rebate' . ($date_start && $date_end ? ' ' . $date_start . '–' . $date_end : '');

if ($single_store_id > 0) {
    $stores = array_values(array_filter($stores, function ($s) use ($single_store_id) {
        return (int)$s['store_id'] === $single_store_id;
    }));
}

$brand_name = isset($rb['brand_name']) ? trim((string)$rb['brand_name']) : '';
$doc_number_suffix = ' -' . date('M j') . '-DD'; // e.g. " -Feb 26-DD"
$doc_number_max_brand = 21 - strlen($doc_number_suffix);
$doc_number_base = ($doc_number_max_brand > 0 && $brand_name !== '') ? mb_substr($brand_name, 0, $doc_number_max_brand) : 'DD';
$doc_number_template = $doc_number_base . $doc_number_suffix;
$doc_number_template = mb_substr($doc_number_template, 0, 21);

foreach ($stores as $s) {
    $store_id = (int)$s['store_id'];
    $store_name = isset($s['store_name']) ? $s['store_name'] : 'Store ' . $store_id;
    $store_db = isset($s['store_db']) ? preg_replace('/[^a-z0-9_]/i', '', $s['store_db']) : '';
    $entry = array('store_id' => $store_id, 'store_name' => $store_name, 'success' => false, 'error' => null, 'qbo_vendor_credit_id' => null);

    $dr = getRow(getRs("SELECT params FROM daily_discount_report_store WHERE daily_discount_report_brand_id = ? AND store_id = ?", array($daily_discount_report_brand_id, $store_id)));
    $params_json = isset($dr['params']) ? $dr['params'] : '[]';
    $rp = json_decode($params_json, true);
    if (!is_array($rp)) {
        $rp = array();
    }
    $store_total = 0;
    foreach ($rp as $p) {
        $qty = isset($p['quantity']) ? (float)$p['quantity'] : 0;
        $pct = isset($p['rebate_percent']) ? (float)$p['rebate_percent'] : 0;
        $up = isset($p['unit_price']) ? (float)$p['unit_price'] : 0;
        $store_total += $qty * $pct / 100 * $up;
    }
    $store_total = round($store_total, 2);
    if ($store_total <= 0) {
        $entry['error'] = 'No rebate amount for this store';
        $log['stores'][] = $entry;
        continue;
    }

    $qbo_vendor_id = '';
    $col_check = getRs("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'brand' AND COLUMN_NAME = 'qbo_vendor_id'", array($store_db));
    if ($col_check && (int)getRow($col_check)['c'] > 0) {
        $br = getRow(getRs("SELECT qbo_vendor_id FROM `" . str_replace('`', '``', $store_db) . "`.brand WHERE master_brand_id = ?", array($brand_id)));
        $qbo_vendor_id = isset($br['qbo_vendor_id']) ? trim((string)$br['qbo_vendor_id']) : '';
    }
    if ($qbo_vendor_id === '') {
        $entry['error'] = 'Brand not mapped to QBO vendor';
        $log['stores'][] = $entry;
        continue;
    }

    $token = qbo_get_access_token($store_id);
    $account_daily = isset($token['account_id_daily_discount']) ? trim($token['account_id_daily_discount']) : '';
    $doc_number = $doc_number_template; // brand name + " -" + month + "-DD", max 21 chars
    $txn_date = date('Y-m-d'); // today's date

    $result = qbo_create_vendor_credit($store_id, $qbo_vendor_id, $store_total, $account_daily, $doc_number, $txn_date, $note);
    if (!empty($result['success']) && !empty($result['VendorCreditId'])) {
        $entry['success'] = true;
        $entry['qbo_vendor_credit_id'] = $result['VendorCreditId'];
        if (is_file($pdf_path)) {
            $att = qbo_attach_file_to_entity($store_id, 'VendorCredit', $result['VendorCreditId'], $pdf_path, $pdf_name);
            if (!empty($att['error'])) {
                $entry['attach_error'] = $att['error'];
            }
        }
    } else {
        $entry['error'] = isset($result['error']) ? $result['error'] : 'Create failed';
    }
    $log['stores'][] = $entry;
}

dbUpdate('daily_discount_report_brand', array('qbo_push_log' => json_encode($log)), $daily_discount_report_brand_id);

echo json_encode(array(
    'success' => true,
    'response' => 'Push complete. See log per store.',
    'ok' => true,
    'log' => $log,
));
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'response' => 'Server error: ' . $e->getMessage(), 'ok' => false, 'error_detail' => $e->getFile() . ':' . $e->getLine()));
} catch (Throwable $e) {
    echo json_encode(array('success' => false, 'response' => 'Server error: ' . $e->getMessage(), 'ok' => false, 'error_detail' => $e->getFile() . ':' . $e->getLine()));
}
exit;
