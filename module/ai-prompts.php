<?php
/**
 * AI Prompts — edit prompt text used by modules (e.g. PO menu extraction).
 * Requires ai_prompts table; see doc/ai_prompts-table.sql.
 * Header/footer are added by index.php; this file outputs only the page content.
 */
$footer = '
<script>
$(document).ready(function() {
  var statusEl = "status_ai_prompts";

  function loadPrompts() {
    $.ajax({
      url: "/ajax/ai-prompts-get.php",
      type: "GET",
      dataType: "json"
    }).done(function(res) {
      if (!res || !res.success) {
        showStatus(statusEl, (res && res.error) ? res.error : "Failed to load prompts.", "error", true);
        return;
      }
      var html = "";
      (res.prompts || []).forEach(function(p) {
        var content = (p.content != null) ? $("<div/>").text(p.content).html() : "";
        var updated = (p.date_updated) ? " <small class=\"text-muted\">Last updated: " + $("<div/>").text(p.date_updated).html() + "</small>" : "";
        html += "<div class=\"card shadow-base mb-3\"><div class=\"card-header d-flex justify-content-between align-items-center\">" +
          "<span><b>" + $("<div/>").text(p.prompt_label).html() + "</b>" + updated + "</span>" +
          "<button type=\"button\" class=\"btn btn-sm btn-primary btn-save-prompt\" data-key=\"" + $("<div/>").text(p.prompt_key).html() + "\">Save</button>" +
          "</div><div class=\"card-body\">" +
          "<textarea class=\"form-control font-monospace\" rows=\"14\" data-key=\"" + $("<div/>").text(p.prompt_key).html() + "\" placeholder=\"(Empty = use default from code)\">" + content + "</textarea>" +
          "</div></div>";
      });
      $("#ai-prompts-container").html(html || "<p class=\"text-muted\">No prompts defined. Run doc/ai_prompts-table.sql to create the table and seed rows.</p>");
    }).fail(function(xhr, status, err) {
      var msg = "Could not load prompts.";
      if (xhr && xhr.status) msg += " (HTTP " + xhr.status + ")";
      if (xhr && xhr.responseText && xhr.responseText.length < 200) msg += " " + xhr.responseText;
      showStatus(statusEl, msg, "error", true);
      $("#ai-prompts-container").html("<p class=\"text-muted\">Ensure the <code>ai_prompts</code> table exists — run <code>doc/ai_prompts-table.sql</code> and <code>doc/ai_prompts-module-insert.sql</code>.</p>");
    });
  }

  $(document).on("click", ".btn-save-prompt", function() {
    var btn = $(this);
    var key = btn.data("key");
    var ta = $("textarea[data-key=\"" + key + "\"]");
    var content = ta.val();
    btn.prop("disabled", true);
    showStatus(statusEl, "Saving…", "info");
    $.ajax({
      url: "/ajax/ai-prompts-save.php",
      type: "POST",
      data: { prompt_key: key, content: content },
      dataType: "json"
    }).done(function(res) {
      btn.prop("disabled", false);
      if (res && res.success) {
        showStatus(statusEl, res.message || "Saved.", "ok", true);
        loadPrompts();
      } else {
        showStatus(statusEl, (res && res.error) ? res.error : "Save failed.", "error", true);
      }
    }).fail(function() {
      btn.prop("disabled", false);
      showStatus(statusEl, "Request failed.", "error", true);
    });
  });

  loadPrompts();
});
</script>
';
?>
<div class="row">
  <div class="col-12">
    <div class="card shadow-base">
      <div class="card-header tx-medium"><b>AI Prompts</b></div>
      <div class="card-body">
        <p class="text-muted">Edit the prompt text sent to the AI for PO menu extraction (category mappings and system instruction). Changes apply the next time you run &quot;Extract menu&quot; on a PO. Leave a field empty to use the default from code.</p>
        <div id="status_ai_prompts" class="status mb-2"></div>
        <div id="ai-prompts-container">
          <p class="text-muted">Loading…</p>
        </div>
      </div>
    </div>
  </div>
</div>
