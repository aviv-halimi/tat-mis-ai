<?php
/**
 * Invoice validation: run as a CLI script to avoid timeouts.
 * Scans POs with po_status_id = 5, sends invoice PDFs to OpenAI, compares total to r_total,
 * sets invoice_validated = 1 when they match. Prints status updates as it runs.
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
    // CLI: minimal bootstrap, no HTML/session
    define('SkipAuth', true);
    require_once dirname(__FILE__) . '/../_config.php';
    require_once dirname(__FILE__) . '/../inc/ai-invoice.php';

    set_time_limit(0);

    status('Invoice validation started.', $isCli);

    $rs = getRs(
        "SELECT po_id, po_number, po_code, r_total, invoice_filename
         FROM po
         WHERE " . is_enabled() . " AND po_status_id = 5 AND LENGTH(invoice_filename) AND r_total > 0",
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

        status("PO {$po_number}: uploading PDF to OpenAI ...", $isCli);
        $raw_ai_response = null;
        $ai_total        = parseInvoiceTotalFromPdf($full_path, $raw_ai_response);

        if ($ai_total === null) {
            status("PO {$po_number}: error – AI did not return a total.", $isCli);
            $errors++;
            $processed++;
            continue;
        }

        status("PO {$po_number}: AI total = {$ai_total}, DB r_total = {$r_total}", $isCli);

        if (abs($ai_total - $r_total) <= 0.01) {
            dbUpdate('po', array('invoice_validated' => 1), $po_id);
            status("PO {$po_number}: match – invoice_validated set to 1.", $isCli);
            $validated++;
        } else {
            status("PO {$po_number}: mismatch – no update.", $isCli);
            $mismatch++;
        }

        $processed++;
    }

    status('Done. Processed: ' . $processed . ', validated: ' . $validated . ', mismatch: ' . $mismatch . ', errors: ' . $errors, $isCli);
    status('Invoice validation finished.', $isCli);
    exit(0);
}

// --- Web: show instructions and optional “run in background” ---
$page_title = 'Invoice Validation';
$page_icon  = 'fa-file-invoice-dollar';
include_once dirname(__FILE__) . '/../inc/header.php';
?>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h4 class="panel-title"><i class="fa fa-file-invoice-dollar mr-1"></i> Invoice Validation</h4>
      </div>
      <div class="panel-body">
        <p>
          Validation runs as a <strong>script</strong> so it doesn’t time out. It processes all POs with
          <code>po_status_id = 5</code>, sends invoice PDFs to OpenAI, and sets <code>invoice_validated = 1</code>
          when the AI total matches <code>r_total</code>.
        </p>
        <h5>Run from the command line</h5>
        <p>From the project root (where <code>_config.php</code> is):</p>
        <pre class="bg-light p-3">php cron/invoice-validate.php</pre>
        <p>You’ll see live status lines as each PO is processed.</p>
        <p class="text-muted small mb-0">
          To run on a schedule, add a cron entry, e.g. daily at 2am:<br>
          <code>0 2 * * * cd /path/to/theartisttree-mis && php cron/invoice-validate.php</code>
        </p>
      </div>
    </div>
  </div>
</div>
<?php
include_once dirname(__FILE__) . '/../inc/footer.php';
