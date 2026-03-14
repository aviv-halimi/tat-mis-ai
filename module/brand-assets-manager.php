<?php
// ============================================================
// Brand Asset Library Manager
// Lists all active brands from blaze1 and lets admins set a
// Google Drive (or other) folder URL per brand.
// The enrichment workflow uses these links for AI image matching.
// ============================================================

$footer = '
<style>
  #bam-table th, #bam-table td { vertical-align: middle !important; }
  .bam-input { font-size: 12px; font-family: monospace; }
  .bam-status { white-space: nowrap; }
  .bam-hint   { font-size: 11px; color: #aaa; margin-top: 3px; }
</style>
<script>
$(document).ready(function() {

  /* ---- Save / validate a brand folder ---- */
  $(document).on("click", ".btn-bam-save", function() {
    var $btn    = $(this);
    var bid     = $btn.data("brand-id");
    var folder  = $.trim($("#bam_folder_" + bid).val());
    var $status = $("#bam_status_" + bid);

    $btn.prop("disabled", true).html(\'<i class="fa fa-spinner fa-spin"></i>\');
    $status.html(\'<span class="label label-default">Saving…</span>\');

    $.ajax({
      url: "ajax/brand-folder-save.php",
      method: "POST",
      dataType: "json",
      data: { brand_id: bid, brand_folder: folder }
    }).done(function(resp) {
      if (resp && resp.success) {
        var badge = badgeHtml(resp.status);
        $status.html(badge);
        if (resp.folder_id) {
          $btn.closest("tr").find(".bam-hint").text("Folder ID: " + resp.folder_id);
        } else if (!folder) {
          $btn.closest("tr").find(".bam-hint").text("");
        }
      } else {
        $status.html(\'<span class="label label-danger"><i class="fa fa-times"></i> \' +
          (resp && resp.error ? resp.error : "Error") + \'</span>\');
      }
    }).fail(function() {
      $status.html(\'<span class="label label-danger">Request failed</span>\');
    }).always(function() {
      $btn.prop("disabled", false).html(\'<i class="fa fa-save"></i> Save\');
    });
  });

  /* ---- Clear a brand folder ---- */
  $(document).on("click", ".btn-bam-clear", function() {
    var bid = $(this).data("brand-id");
    $("#bam_folder_" + bid).val("");
    $("#bam_status_" + bid).html(\'<span class="label label-default">—</span>\');
    $(this).closest("tr").find(".bam-hint").text("");
  });

  /* ---- Press Enter in input → save ---- */
  $(document).on("keypress", ".bam-input", function(e) {
    if (e.which === 13) {
      var bid = $(this).attr("id").replace("bam_folder_", "");
      $(".btn-bam-save[data-brand-id=\'" + bid + "\']").click();
    }
  });

  /* ---- Quick-search filter ---- */
  $("#bam-search").on("input", function() {
    var q = $(this).val().toLowerCase();
    $("#bam-table tbody tr").each(function() {
      $(this).toggle($(this).find(".bam-brand-name").text().toLowerCase().indexOf(q) > -1);
    });
  });

  function badgeHtml(status) {
    var map = {
      "drive_ok":        \'<span class="label label-success"><i class="fa fa-check-circle"></i> Drive – Accessible ✓</span>\',
      "drive_no_access": \'<span class="label label-warning"><i class="fa fa-lock"></i> Drive – Share with service account</span>\',
      "drive_no_creds":  \'<span class="label label-info"><i class="fa fa-key"></i> Drive – Saved (deploy credentials to verify)</span>\',
      "drive_error":     \'<span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> Drive – Error verifying</span>\',
      "other_saved":     \'<span class="label label-info"><i class="fa fa-link"></i> URL Saved</span>\',
      "saved":           \'<span class="label label-success"><i class="fa fa-check"></i> Saved</span>\',
      "cleared":         \'<span class="label label-default">—</span>\'
    };
    return map[status] || \'<span class="label label-success"><i class="fa fa-check"></i> Saved</span>\';
  }

});
</script>';

include_once('_config.php');

// Get blaze1 DB name (store_id = 1)
$store1    = getRow(getRs("SELECT db FROM store WHERE store_id = 1 LIMIT 1"));
$store1_db = $store1['db'] ?? $_Session->db;

// Load all active brands including brand_folder
$brands = getRs(
    "SELECT brand_id, name, brand_folder
       FROM `{$store1_db}`.brand
      WHERE " . is_enabled() . "
      ORDER BY name",
    []
);

include_once('inc/header.php');

$total    = count($brands);
$with_url = count(array_filter($brands, fn($b) => !empty($b['brand_folder'])));

echo '
<div class="panel panel-default m-b-0">
  <div class="panel-heading clearfix">
    <div class="pull-left">
      <h3 class="panel-title" style="line-height:30px;">
        <i class="fa fa-folder-open-o"></i> Brand Asset Library
        <small class="text-muted" style="font-size:11px;margin-left:8px;">
          Set a Google Drive folder URL per brand for AI-powered image matching during enrichment.
        </small>
      </h3>
    </div>
    <div class="pull-right" style="display:flex;gap:10px;align-items:center;">
      <span class="text-muted" style="font-size:12px;">' . $with_url . ' / ' . $total . ' brands configured</span>
      <input id="bam-search" type="text" class="form-control input-sm" style="width:200px;" placeholder="&#128269; Filter brands…" />
    </div>
  </div>

  <div class="panel-body" style="padding:0;">
    <table id="bam-table" class="table table-bordered table-hover table-condensed" style="margin:0;">
      <thead>
        <tr class="inverse">
          <th style="width:200px;">Brand</th>
          <th>Drive / Asset Folder URL or ID</th>
          <th style="width:230px;" class="text-center">Status</th>
          <th style="width:110px;" class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>';

foreach ($brands as $b) {
    $bid        = (int)    $b['brand_id'];
    $name       = htmlspecialchars((string) $b['name'],          ENT_QUOTES, 'UTF-8');
    $folder     = htmlspecialchars((string) ($b['brand_folder'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hasFolder  = trim((string) ($b['brand_folder'] ?? '')) !== '';

    // Detect Drive folder ID for hint
    $hintText = '';
    if ($hasFolder) {
        $raw = (string) ($b['brand_folder'] ?? '');
        if (preg_match('#/folders/([A-Za-z0-9_\-]{10,})#', $raw, $m)) {
            $hintText = 'Folder ID: ' . $m[1];
        } elseif (preg_match('#^[A-Za-z0-9_\-]{20,}$#', $raw)) {
            $hintText = 'Folder ID: ' . $raw;
        }
    }

    // Initial status badge
    if (!$hasFolder) {
        $statusBadge = '<span class="label label-default">—</span>';
    } elseif (str_contains((string)($b['brand_folder'] ?? ''), 'drive.google.com')
           || preg_match('#^[A-Za-z0-9_\-]{20,}$#', (string)($b['brand_folder'] ?? ''))) {
        $statusBadge = '<span class="label label-info"><i class="fa fa-google"></i> Drive – Click Save to Verify</span>';
    } else {
        $statusBadge = '<span class="label label-info"><i class="fa fa-link"></i> URL Saved</span>';
    }

    echo '
        <tr>
          <td><strong class="bam-brand-name">' . $name . '</strong></td>
          <td>
            <input type="text"
                   id="bam_folder_' . $bid . '"
                   class="form-control input-sm bam-input"
                   value="' . $folder . '"
                   placeholder="https://drive.google.com/drive/folders/… or paste a folder ID" />
            <div class="bam-hint">' . htmlspecialchars($hintText, ENT_QUOTES, 'UTF-8') . '</div>
          </td>
          <td class="text-center bam-status">
            <span id="bam_status_' . $bid . '">' . $statusBadge . '</span>
          </td>
          <td class="text-center">
            <button class="btn btn-xs btn-primary btn-bam-save" data-brand-id="' . $bid . '">
              <i class="fa fa-save"></i> Save
            </button>
            ' . ($hasFolder ? '
            <button class="btn btn-xs btn-default btn-bam-clear" data-brand-id="' . $bid . '" title="Clear folder URL">
              <i class="fa fa-times"></i>
            </button>' : '') . '
          </td>
        </tr>';
}

echo '
      </tbody>
    </table>
  </div>

  <div class="panel-footer" style="font-size:11px;color:#888;">
    <i class="fa fa-info-circle"></i>
    Paste a Google Drive <strong>folder</strong> URL (e.g. <code>https://drive.google.com/drive/folders/ABC123…</code>) or a raw folder ID.
    The service account <code>google-drive-service@gen-lang-client-0619832283.iam.gserviceaccount.com</code> must be shared on the folder.
    A green badge means the folder is also publicly accessible (no sign-in required).
    An orange badge means only the service account can read it — enrichment will still work, but share the folder publicly for the best results.
  </div>
</div>';

include_once('inc/footer.php');
?>
