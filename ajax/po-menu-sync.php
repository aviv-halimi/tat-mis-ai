<?php
/**
 * Sync PO with brand menu: run Gemini on uploaded menu PDFs and
 * (1) set is_enabled = 0 for PO lines not on the menu (match by name + category),
 * (2) add custom products for menu items not already on the PO.
 * Can run via HTTP POST (sync or async) or CLI: php ajax/po-menu-sync.php <po_id>
 */
$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    if (isset($argv[1])) {
        chdir(dirname(__FILE__) . '/..');
        require_once __DIR__ . '/../_config.php';
        $po_id = (int) $argv[1];
        $po_code = '';
    } else {
        exit(1);
    }
} else {
    require_once dirname(__FILE__) . '/../_config.php';
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: ' . date('r', time() + 86400 * 365));
    header('Content-type: application/json');
    $po_code = isset($_POST['po_code']) ? trim((string) $_POST['po_code']) : '';
    $po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
}

if (!$po_code && !$po_id) {
    if (!$is_cli) {
        echo json_encode(array('success' => false, 'error' => 'Missing po_code or po_id'));
    }
    exit($is_cli ? 1 : 0);
}

$rs = getRs(
    "SELECT po_id, po_code, po_status_id, menu_filenames FROM po WHERE " . is_enabled() . " AND (po_code = ? OR po_id = ?) LIMIT 1",
    array($po_code ?: null, $po_id ?: $po_id)
);
$po = getRow($rs);
if (!$po) {
    echo json_encode(array('success' => false, 'error' => 'PO not found'));
    exit;
}

$po_id = (int) $po['po_id'];
$po_code = $po['po_code'];

if ((int) $po['po_status_id'] !== 1) {
    echo json_encode(array('success' => false, 'error' => 'Sync with menu is only available when PO is in status 1 (Draft).'));
    exit;
}

$menu_filenames = $po['menu_filenames'];
if (!$menu_filenames) {
    echo json_encode(array('success' => false, 'error' => 'Upload brand menu PDFs first, then run Sync.'));
    exit;
}

$files = json_decode($menu_filenames, true);
if (!is_array($files) || empty($files)) {
    echo json_encode(array('success' => false, 'error' => 'No menu PDF files found.'));
    exit;
}

$base_path = defined('MEDIA_PATH') ? rtrim(MEDIA_PATH, '/\\') . '/po/' : '';
$pdf_paths = array();
foreach ($files as $f) {
    $name = isset($f['name']) ? $f['name'] : '';
    if ($name === '') {
        continue;
    }
    $full = $base_path . $name;
    if (file_exists($full)) {
        $pdf_paths[] = $full;
    }
}
if (empty($pdf_paths)) {
    if (!$is_cli) {
        echo json_encode(array('success' => false, 'error' => 'Menu PDF files not found on disk.'));
    }
    exit($is_cli ? 1 : 0);
}

// Optional: run in background to avoid 504 (returns immediately; front-end polls status).
$log_dir = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
if (!$is_cli && !empty($_POST['async']) && $po_id > 0 && (is_dir($log_dir) || @mkdir($log_dir, 0755, true))) {
    $job_file = $log_dir . '/po-menu-sync-job-' . $po_id . '.json';
    @file_put_contents($job_file, json_encode(array('status' => 'running', 'started_at' => date('Y-m-d H:i:s'))), LOCK_EX);
    $php = defined('INVOICE_VALIDATE_PHP_CLI') ? INVOICE_VALIDATE_PHP_CLI : 'php';
    $scriptPath = (defined('BASE_PATH') ? rtrim(BASE_PATH, '/\\') . '/' : dirname(__FILE__) . '/../') . 'ajax/po-menu-sync.php';
    $script = @realpath($scriptPath) ?: $scriptPath;
    if ($script && is_file($script)) {
        $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' ' . (int) $po_id;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B ' . $cmd, 'r'));
        } else {
            exec($cmd . ' > /dev/null 2>&1 &');
        }
        echo json_encode(array('success' => true, 'started' => true, 'message' => 'Sync started. Waiting for result…', 'po_id' => $po_id));
        exit;
    }
}

// PO products: include category and brand so AI matches by name+category (same strain in different categories = different lines).
$db = $_Session->db;
$products_rs = getRs(
    "SELECT t.po_product_id, COALESCE(s.name, t.po_product_name) AS product_name, " .
    "t.category_id, c.name AS category_name, t.brand_id, b.name AS brand_name " .
    "FROM po_product t " .
    "LEFT JOIN {$db}.product s ON s.product_id = t.product_id " .
    "LEFT JOIN {$db}.category c ON c.category_id = t.category_id " .
    "LEFT JOIN {$db}.brand b ON b.brand_id = t.brand_id " .
    "WHERE t.po_id = ? AND t.is_active = 1 AND t.is_enabled = 1",
    array($po_id)
);
$po_products = array();
foreach ($products_rs as $row) {
    $po_products[] = array(
        'po_product_id' => (int) $row['po_product_id'],
        'product_name' => (string) $row['product_name'],
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'category_name' => isset($row['category_name']) ? (string) $row['category_name'] : '',
        'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
        'brand_name' => isset($row['brand_name']) ? (string) $row['brand_name'] : '',
    );
}

// Distinct brands and categories on this PO for Gemini to map new products.
$brands_rs = getRs(
    "SELECT DISTINCT b.brand_id, b.name AS brand_name FROM po_product p " .
    "INNER JOIN {$db}.brand b ON b.brand_id = p.brand_id " .
    "WHERE p.po_id = ? AND p.is_active = 1 AND p.brand_id IS NOT NULL AND p.brand_id > 0",
    array($po_id)
);
$categories_rs = getRs(
    "SELECT DISTINCT c.category_id, c.name AS category_name FROM po_product p " .
    "INNER JOIN {$db}.category c ON c.category_id = p.category_id " .
    "WHERE p.po_id = ? AND p.is_active = 1 AND p.category_id IS NOT NULL AND p.category_id > 0",
    array($po_id)
);
$po_brands = array();
foreach ($brands_rs as $r) {
    $po_brands[] = array('brand_id' => (int) $r['brand_id'], 'brand_name' => (string) $r['brand_name']);
}
$po_categories = array();
foreach ($categories_rs as $r) {
    $po_categories[] = array('category_id' => (int) $r['category_id'], 'category_name' => (string) $r['category_name']);
}
$valid_brand_ids = array_column($po_brands, 'brand_id');
$valid_category_ids = array_column($po_categories, 'category_id');

$log_dir = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';

require_once dirname(__FILE__) . '/../inc/ai-po-menu-gemini.php';

$debug_log = array();
$debug_log[] = '[SYNC] ' . date('Y-m-d H:i:s') . ' PO ' . $po_id . ' (' . $po_code . '). PDFs: ' . count($pdf_paths) . ', PO products: ' . count($po_products) . ', brands: ' . count($po_brands) . ', categories: ' . count($po_categories);

if (is_dir($log_dir) || @mkdir($log_dir, 0755, true)) {
    @file_put_contents($log_dir . '/po-menu-sync.log', '[' . date('Y-m-d H:i:s') . "] PO {$po_id} START – calling Gemini\n" . implode("\n", $debug_log) . "\n", FILE_APPEND | LOCK_EX);
}

$result = matchPoToMenuGemini($pdf_paths, $po_products, $debug_log, $po_brands, $po_categories);

if ($result === null) {
    $log_dir = defined('BASE_PATH') ? BASE_PATH . 'log' : dirname(__FILE__) . '/../log';
    if (is_dir($log_dir) || @mkdir($log_dir, 0755, true)) {
        $log_file = $log_dir . '/po-menu-sync.log';
        @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . "] PO {$po_id} FAIL\n" . implode("\n", $debug_log) . "\n---\n", FILE_APPEND | LOCK_EX);
        if ($is_cli) {
            @file_put_contents($log_dir . '/po-menu-sync-job-' . $po_id . '.json', json_encode(array('status' => 'completed', 'finished_at' => date('Y-m-d H:i:s'))), LOCK_EX);
        }
    }
    if (!$is_cli) {
        echo json_encode(array(
            'success' => false,
            'error' => 'AI could not process the menu PDFs.',
            'debug_log' => $debug_log,
        ));
    }
    exit($is_cli ? 1 : 0);
}

$debug_log[] = '[SYNC] Gemini returned: disable=' . count($result['disable_po_product_ids']) . ', add=' . count($result['add_products']);

$disabled = 0;
foreach ($result['disable_po_product_ids'] as $pid) {
    $pid = (int) $pid;
    if ($pid <= 0) {
        continue;
    }
    setRs("UPDATE po_product SET is_enabled = 0 WHERE po_id = ? AND po_product_id = ?", array($po_id, $pid));
    $disabled++;
}

$added = 0;
$add_errors = array();
foreach ($result['add_products'] as $item) {
    $name = isset($item['name']) ? trim((string) $item['name']) : '';
    $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0;
    if ($name === '') {
        continue;
    }
    $params = array(
        'po_code' => $po_code,
        'po_product_name' => $name,
        'price' => $price,
        'qty' => 0,
        'is_existing_product' => 0,
    );
    if (!empty($valid_brand_ids) && isset($item['brand_id']) && (int) $item['brand_id'] > 0 && in_array((int) $item['brand_id'], $valid_brand_ids, true)) {
        $params['brand_id'] = (int) $item['brand_id'];
    }
    if (!empty($valid_category_ids) && isset($item['category_id']) && (int) $item['category_id'] > 0 && in_array((int) $item['category_id'], $valid_category_ids, true)) {
        $params['category_id'] = (int) $item['category_id'];
    }
    $res = $_PO->SavePOCustomProduct($params);
    if (!empty($res['success'])) {
        $added++;
    } else {
        $add_errors[] = $name . ': ' . (isset($res['response']) ? $res['response'] : 'Failed');
    }
}

$message = 'Sync complete: ' . $disabled . ' line(s) disabled (not on menu), ' . $added . ' product(s) added from menu.';
if (!empty($add_errors)) {
    $message .= ' Add issues: ' . implode('; ', array_slice($add_errors, 0, 5));
    if (count($add_errors) > 5) {
        $message .= ' (+' . (count($add_errors) - 5) . ' more)';
    }
}

$log_file = $log_dir . '/po-menu-sync.log';
@file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . "] PO {$po_id} OK\n" . implode("\n", $debug_log) . "\n---\n", FILE_APPEND | LOCK_EX);

$out = array(
    'success' => true,
    'message' => $message,
    'disabled_count' => $disabled,
    'added_count' => $added,
    'add_errors' => $add_errors,
    'debug_log' => $debug_log,
    'time' => date('Y-m-d H:i:s'),
);
if (is_dir($log_dir)) {
    @file_put_contents($log_dir . '/po-menu-sync-last-' . $po_id . '.json', json_encode($out));
    if ($is_cli) {
        @file_put_contents($log_dir . '/po-menu-sync-job-' . $po_id . '.json', json_encode(array('status' => 'completed', 'started_at' => null, 'finished_at' => date('Y-m-d H:i:s'))), LOCK_EX);
    }
}
if (!$is_cli) {
    echo json_encode($out);
}
exit(0);
