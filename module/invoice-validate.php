<?php

// Manual invoice validation module
// - Scans theartisttree.po for records with po_status_id = 5
// - For each record, attempts to read the invoice PDF specified by invoice_filename
// - Compares the AI-extracted total with r_total
// - If they match, sets invoice_validated = 1
// - Renders a simple UI report of what it found/did

$page_title = 'Invoice Validation';
$page_icon  = 'fa-file-invoice-dollar';

include_once(dirname(__FILE__) . '/../inc/header.php');
include_once(dirname(__FILE__) . '/../inc/ai-invoice.php');

$results = array();
$log     = array();

$run = isset($_GET['run']) ? (int)$_GET['run'] : 0;

if ($run) {
    $log[] = 'Starting invoice validation at ' . date('Y-m-d H:i:s');

    // Find POs with status = 5 that have an invoice filename
    $rs = getRs(
        "SELECT po_id, po_number, po_code, r_total, invoice_filename
         FROM po
         WHERE " . is_enabled() . " AND po_status_id = 5 AND LENGTH(invoice_filename)",
        array()
    );

    if (!sizeof($rs)) {
        $log[] = 'No POs found with po_status_id = 5 and an invoice file.';
    } else {
        foreach ($rs as $r) {
            $po_id           = (int)$r['po_id'];
            $po_number       = $r['po_number'];
            $po_code         = $r['po_code'];
            $r_total         = (float)$r['r_total'];
            $invoice_file    = $r['invoice_filename'];
            $status          = 'pending';
            $message         = '';
            $ai_total        = null;
            $raw_ai_response = null;

            $log[] = "PO {$po_number}: processing ...";

            if (!strlen($invoice_file)) {
                $status  = 'skipped';
                $message = 'No invoice_filename on record.';
                $log[]   = "PO {$po_number}: skipped – no invoice_filename.";
            } else {
                $full_path = MEDIA_PATH . 'po/' . $invoice_file;

                if (!file_exists($full_path)) {
                    $status  = 'error';
                    $message = 'Invoice file not found at ' . $full_path;
                    $log[]   = "PO {$po_number}: error – file not found ({$full_path}).";
                } else {
                    $log[] = "PO {$po_number}: found invoice file {$invoice_file} – calling AI parser.";

                    // Call AI invoice parser (stub in inc/ai-invoice.php)
                    $ai_total = parseInvoiceTotalFromPdf($full_path, $raw_ai_response);

                    if ($ai_total === null) {
                        $status  = 'error';
                        $message = 'AI parser did not return a usable total. (Configure parseInvoiceTotalFromPdf.)';
                        $log[]   = "PO {$po_number}: error – AI parser returned null.";
                    } else {
                        $log[] = "PO {$po_number}: AI reported total {$ai_total}; DB r_total is {$r_total}.";

                        // Compare with a small tolerance for rounding
                        if (abs($ai_total - $r_total) <= 0.01) {
                            // Mark as validated
                            dbUpdate('po', array('invoice_validated' => 1), $po_id);
                            $status  = 'validated';
                            $message = 'Totals match. invoice_validated set to 1.';
                            $log[]   = "PO {$po_number}: totals match – invoice_validated updated.";
                        } else {
                            $status  = 'mismatch';
                            $message = 'Totals do not match.';
                            $log[]   = "PO {$po_number}: totals mismatch.";
                        }
                    }
                }
            }

            $results[] = array(
                'po_id'        => $po_id,
                'po_number'    => $po_number,
                'po_code'      => $po_code,
                'r_total'      => $r_total,
                'ai_total'     => $ai_total,
                'status'       => $status,
                'message'      => $message,
            );
        }
    }

    $log[] = 'Invoice validation finished at ' . date('Y-m-d H:i:s');
}

?>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h4 class="panel-title"><i class="fa fa-file-invoice-dollar mr-1"></i> Invoice Validation</h4>
      </div>
      <div class="panel-body">
        <p>
          This tool scans POs with <code>po_status_id = 5</code>, reads their invoice PDFs via an AI agent (stubbed),
          compares the AI total to <code>r_total</code>, and, when they match, sets <code>invoice_validated = 1</code>.
        </p>
        <p>
          Click the button below to run the validation now. Results will be shown in the table and log.
        </p>
        <p>
          <a href="?run=1" class="btn btn-primary">
            <i class="fa fa-play mr-1"></i> Run Invoice Validation
          </a>
        </p>

        <?php if ($run): ?>
          <hr />
          <h5>Summary of POs processed</h5>
          <?php if (!sizeof($results)): ?>
            <p><em>No matching POs were found.</em></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead>
                  <tr>
                    <th>PO #</th>
                    <th>PO Code</th>
                    <th>r_total (DB)</th>
                    <th>Invoice Total (AI)</th>
                    <th>Status</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($results as $row): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['po_number']); ?></td>
                      <td><a href="/po/<?php echo htmlspecialchars($row['po_code']); ?>" target="_blank"><?php echo htmlspecialchars($row['po_code']); ?></a></td>
                      <td><?php echo number_format($row['r_total'], 2); ?></td>
                      <td>
                        <?php
                          if ($row['ai_total'] === null) {
                              echo '<em>n/a</em>';
                          } else {
                              echo number_format($row['ai_total'], 2);
                          }
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($row['status']); ?></td>
                      <td><?php echo htmlspecialchars($row['message']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <hr />
          <h5>Detailed log</h5>
          <pre style="max-height: 300px; overflow:auto;"><?php echo htmlspecialchars(implode("\n", $log)); ?></pre>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
include_once(dirname(__FILE__) . '/../inc/footer.php');
?>

