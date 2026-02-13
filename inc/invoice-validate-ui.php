<?php
/**
 * Invoice validation UI: trigger button + live log. Expects $start_url and $status_url to be set.
 */
if (!isset($start_url) || !isset($status_url)) {
    return;
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
          Processes all POs with <code>po_status_id = 5</code>, sends invoice PDFs to OpenAI, and sets
          <code>invoice_validated = 1</code> when the AI total matches <code>r_total</code>. The script runs in the
          background so the page does not time out; updates appear below as it runs.
        </p>
        <p>
          <button type="button" class="btn btn-primary" id="invoice-validate-run-btn">
            <i class="fa fa-play mr-1"></i> Run validation
          </button>
          <span id="invoice-validate-status" class="ml-2 text-muted"></span>
        </p>
        <div class="mt-3">
          <label class="font-weight-bold">Live log</label>
          <pre id="invoice-validate-log" class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto; min-height: 120px;">No run yet. Click "Run validation" to start.</pre>
        </div>
        <p class="text-muted small mt-3 mb-0">
          You can also run from the command line: <code>php cron/invoice-validate.php</code>
        </p>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var runBtn = document.getElementById('invoice-validate-run-btn');
  var statusEl = document.getElementById('invoice-validate-status');
  var logEl = document.getElementById('invoice-validate-log');
  var startUrl = <?php echo json_encode($start_url); ?>;
  var statusUrl = <?php echo json_encode($status_url); ?>;
  var pollTimer = null;

  function setStatus(msg, isError) {
    statusEl.textContent = msg;
    statusEl.className = 'ml-2 ' + (isError ? 'text-danger' : 'text-muted');
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    runBtn.disabled = false;
    setStatus('Done.');
  }

  function poll() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', statusUrl, true);
    xhr.onload = function() {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.content) {
          logEl.textContent = data.content;
          logEl.scrollTop = logEl.scrollHeight;
        }
        if (!data.running) {
          stopPolling();
        }
      } catch (e) {}
    };
    xhr.send();
  }

  runBtn.addEventListener('click', function() {
    runBtn.disabled = true;
    setStatus('Starting…');
    logEl.textContent = 'Starting…\n';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', startUrl, true);
    xhr.onload = function() {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.started) {
          setStatus('Running…');
          pollTimer = setInterval(poll, 1500);
          poll();
        } else {
          setStatus(data.message || 'Could not start.', true);
          if (data.running) {
            pollTimer = setInterval(poll, 1500);
            poll();
          }
          runBtn.disabled = false;
        }
      } catch (e) {
        setStatus('Request failed.', true);
        runBtn.disabled = false;
      }
    };
    xhr.onerror = function() {
      setStatus('Request failed.', true);
      runBtn.disabled = false;
    };
    xhr.send();
  });
})();
</script>
