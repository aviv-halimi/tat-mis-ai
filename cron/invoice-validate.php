<?php
/**
 * Invoice validation: run as a CLI script to avoid timeouts.
 * Scans POs with po_status_id = 5, sends invoice PDFs to Gemini, compares total to r_total,
 * sets invoice_validated = 1 when they match; optionally sets po.payment_terms from invoice.
 *
 * Run from project root:  php cron/invoice-validate.php
 * Or from cron dir:       php invoice-validate.php  (after cd cron)
 */

function status($msg, $isCli)
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($isCli) {
        echo $line . "\n";
        if (function_exists('flush')) {
            flush();
        }
    } else {
        echo $line . "<br>\n";
        if (function_exists('flush')) {
            flush();
        }
    }
}

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    define('SkipAuth', true);
    require_once dirname(__FILE__) . '/../_config.php';
    require_once dirname(__FILE__) . '/../inc/ai-invoice-gemini.php';

    set_time_limit(0);

    $logDir = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log';
    $pidFile = $logDir . DIRECTORY_SEPARATOR . 'invoice-validate.pid';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($pidFile, (string) getmypid());
    register_shutdown_function(function () use ($pidFile) {
        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
    });

    status('Invoice validation started (Gemini).', $isCli);

    $rs = getRs(
        "SELECT p.po_id, p.po_number, p.po_code, r_total - SUM(d.discount_amount) AS r_total, p.invoice_filename
         FROM po p
         	LEFT JOIN po_discount d ON p.po_id = d.po_id
         WHERE po_status_id = 5 
          AND p.is_active = 1 
          AND p.is_enabled = 1
          AND LENGTH(invoice_filename) 
          AND r_total > 0 
          AND d.is_enabled = 1 
          AND d.is_active = 1 
          AND d.is_receiving = 1
         GROUP BY p.po_id
         LIMIT 5;",
        array()
    );

    if (!is_array($rs) || count($rs) === 0) {
        status('No POs found with po_status_id = 5 and an invoice file.', $isCli);
        exit(0);
    }

    status('Found ' . count($rs) . ' PO(s) to process.', $isCli);

    $processed = 0;
    $validated = 0;
    $errors    = 0;
    $mismatch  = 0;

    foreach ($rs as $r) {
        $po_id        = (int) $r['po_id'];
        $po_number    = $r['po_number'];
        $po_code      = $r['po_code'];
        $r_total      = (float) $r['r_total'];
        $invoice_file = $r['invoice_filename'];

        status("PO {$po_number}: starting ...", $isCli);

        if (strlen($invoice_file) === 0) {
            status("PO {$po_number}: skipped – no invoice_filename.", $isCli);
            $errors++;
            $processed++;
            continue;
        }

        $full_path = MEDIA_PATH . 'po/' . $invoice_file;

        if (!file_exists($full_path)) {
            status("PO {$po_number}: error – file not found.", $isCli);
            $errors++;
            $processed++;
            continue;
        }

        $pdf_url = defined('SITE_URL') && SITE_URL !== ''
            ? (rtrim(SITE_URL, '/') . '/module/po-download-r.php?c=' . urlencode($po_code))
            : ('../module/po-download-r.php?c=' . urlencode($po_code));
        status("PO {$po_number}: invoice PDF: " . $pdf_url, $isCli);

        status("PO {$po_number}: sending PDF to Gemini ...", $isCli);
        $raw_ai_response = null;
        $result = parseInvoiceFromPdfGemini($full_path, $raw_ai_response);

        if (is_array($raw_ai_response) && count($raw_ai_response) > 0) {
            foreach ($raw_ai_response as $line) {
                status("PO {$po_number}: Gemini – " . $line, $isCli);
            }
        }

        if ($result === null || !isset($result['total'])) {
            status("PO {$po_number}: error – Gemini did not return a total.", $isCli);
            $errors++;
            $processed++;
            continue;
        }

        $ai_total = (float) $result['total'];
        $payment_terms = array_key_exists('payment_terms', $result) ? $result['payment_terms'] : null;
        if ($payment_terms !== null) {
            $payment_terms = (int) $payment_terms;
        }

        status("PO {$po_number}: AI total = {$ai_total}, DB r_total = {$r_total}" . ($payment_terms !== null ? ", payment_terms = {$payment_terms}" : ", payment_terms = (none)") . ".", $isCli);

        if (abs($ai_total - $r_total) <= 5) {
            $update = array('invoice_validated' => 1);
            if ($payment_terms !== null) {
                $update['payment_terms'] = $payment_terms;
            }
            dbUpdate('po', $update, $po_id);
            status("PO {$po_number}: match – invoice_validated set to 1" . ($payment_terms !== null ? ", payment_terms = {$payment_terms}" : "") . ".", $isCli);
            $validated++;
        } else {
            status("PO {$po_number}: mismatch – no update.", $isCli);
            $mismatch++;
        }

        $processed++;
    }

    status('Done. Processed: ' . $processed . ', validated: ' . $validated . ', mismatch: ' . $mismatch . ', errors: ' . $errors, $isCli);
    status('Invoice validation finished.', $isCli);
    echo "RUN_COMPLETE\n";
    if (isset($pidFile) && file_exists($pidFile)) {
        @unlink($pidFile);
    }
    exit(0);
}

// --- Web: show instructions and optional “run in background” ---
if (!defined('SkipAuth')) {
    define('SkipAuth', true);
}
require_once dirname(__FILE__) . '/../_config.php';
$page_title = 'Invoice Validation';
$page_icon  = 'fa-file-invoice-dollar';
include_once dirname(__FILE__) . '/../inc/header.php';
?>

<?php
$ajax_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$start_url = rtrim($ajax_base, '/') . '/ajax/invoice-validate-start.php';
$status_url = rtrim($ajax_base, '/') . '/ajax/invoice-validate-status.php';
$stop_url   = rtrim($ajax_base, '/') . '/ajax/invoice-validate-stop.php';
include dirname(__FILE__) . '/../inc/invoice-validate-ui.php';
include_once dirname(__FILE__) . '/../inc/footer.php';
