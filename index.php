<?php
require_once('_config.php');
$module_code = getVar('_module_code', 'dashboard');
$rs = $_Session->GetModule($module_code);
$_content = '';
if ($r = getRow($rs)) {
  if ($r['is_access_granted']) {
    $meta_title = $r['module_name'];
    $page_icon = iif(strlen($r['image']), '<span class="mr-2"><img src="' . $r['image'] . '" style="width:32px;" alt="" /></span>', '<i class="' . $r['icon'] . '"></i>');
    $module_code = $r['module_code'];
    $_params = $r['params'];
    $params = $tbl = array();
	$embed_page = true;
	if (strlen($_Session->dashboard) > 0 && $r['module_code'] == 'dashboard') {
		$r['content'] = $_Session->dashboard;
		$embed_page = false;
	}
    if (strlen($r['content'])) {
      $no_header = false;
      $_content = '
      <!-- src="embedding.3.0.js" -->
	  <script type="module" src="https://us-west-2b.online.tableau.com/javascripts/api/tableau.embedding.3.latest.min.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" integrity="sha512-E8QSvWZ0eCLGk4km3hxSsNmGWbLtSCSUcewDQPQWZF6pEU8GlT8a5fF32wOl1i8ftdMhssTrF/OhyGWwonTcXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

      <style>
        /* Force the Tableau viz to span the full width of its container */
        tableau-viz {
          display: block;
          width: 100% !important;
          min-height: 80vh;
        }
        .panel-inverse .panel-body { padding: 0; overflow: hidden; }
      </style>

      <div class="panel panel-inverse"><!--
        <div class="panel-heading">
          <div class="panel-heading-btn">
            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-success" data-click="panel-expand"><i class="fa fa-expand"></i></a>
          </div>
          <h4 class="panel-title report-name">&nbsp;</h4>
        </div>-->
        <div class="panel-body">
        ' . $r['content'] . '
        </div>
      </div>
      
          
    <script>
    function createToken(userid,kid,secret,iss,scp){
      var header = {
        "alg": "HS256",
        "typ": "JWT",
        "iss": iss,
        "kid": kid,
      };
      var stringifiedHeader = CryptoJS.enc.Utf8.parse(JSON.stringify(header));
      var encodedHeader = base64url(stringifiedHeader);
      var claimSet = {
        "sub": userid,
        "aud":"tableau",
        "nbf":Math.round(new Date().getTime()/1000)-100,
        "jti":new Date().getTime().toString(),
        "iss": iss,
        "scp": scp,
        "exp": Math.round(new Date().getTime()/1000)+100
      };
      var stringifiedData = CryptoJS.enc.Utf8.parse(JSON.stringify(claimSet));
      var encodedData = base64url(stringifiedData);
      var token = encodedHeader + "." + encodedData;
      var signature = CryptoJS.HmacSHA256(token, secret);
      signature = base64url(signature);
      var signedToken = token + "." + signature;
      return signedToken;
    }
    
    function base64url(source) {
      encodedSource = CryptoJS.enc.Base64.stringify(source);
      encodedSource = encodedSource.replace(/=+$/, \'\');
      encodedSource = encodedSource.replace(/\+/g, \'-\');
      encodedSource = encodedSource.replace(/\//g, \'_\');
      return encodedSource;
    }
    
  </script>
  
     <script>
     
    var userid = "aviv@theartisttree.com";
    var kid = "6e50392b-373d-416e-9cd1-94f490a5ea8f";
    var secret = "TrYBTN3UA9KD076Tct244v+4V/2nSunukK8+WO0G/0M=";
    var iss = "aa67a889-676a-43e4-ad9f-1904e9adcc5a";
    var scp = ["tableau:views:embed"];
    // Define the token variable
    const token = createToken(userid, kid, secret, iss, scp);
  
    // Get the tableauViz element
    const tableauViz = document.getElementById(\'tableau-viz\');
  
    // Set the token attribute of the tableauViz element
    tableauViz.setAttribute(\'token\', token);

    // Ensure the viz takes up the full available width on initial load.
    // Tableau calculates its render size when it first loads - if the
    // surrounding layout has not finalized, it can come up narrower than
    // the window. Remove any hard-coded width/height from the embed and
    // force a resize once the viz becomes interactive so it fills the
    // container without the user needing to resize the browser window.
    (function() {
      if (!tableauViz) return;

      function sizeToContainer() {
        try {
          tableauViz.removeAttribute(\'width\');
          tableauViz.style.width = \'100%\';
          // Give the viz a usable default height if one was not set
          if (!tableauViz.getAttribute(\'height\') && !tableauViz.style.height) {
            tableauViz.style.height = Math.max(window.innerHeight - 150, 600) + \'px\';
          }
        } catch (e) { /* no-op */ }
      }

      function forceRedraw() {
        // Trigger a window resize which the Tableau embed listens to
        // internally, causing it to recompute its layout at the real
        // container width.
        window.dispatchEvent(new Event(\'resize\'));
      }

      sizeToContainer();

      tableauViz.addEventListener(\'firstinteractive\', function() {
        sizeToContainer();
        // Fire a couple of resize pulses to defeat any race with the
        // sidebar/panel finishing its own layout.
        forceRedraw();
        setTimeout(forceRedraw, 100);
        setTimeout(forceRedraw, 500);
      });

      tableauViz.addEventListener(\'firstvizsizeknown\', function() {
        sizeToContainer();
        forceRedraw();
      });

      // Also observe container width changes in case the sidebar
      // collapses/expands or the browser finishes reflowing after load.
      if (\'ResizeObserver\' in window) {
        var ro = new ResizeObserver(function() { forceRedraw(); });
        var container = tableauViz.parentElement;
        if (container) ro.observe(container);
      }
    })();
  </script>
      
      ';
    }
    $_breadcrumb = $_Session->GetBreadCrumb($r['module_id'], $r['module_name']);

    if (file_exists('module/' . $module_code . '.php') && $embed_page) {
      if ($module_code === 'ai-prompts') {
        ob_start();
        include_once('module/' . $module_code . '.php');
        $_content = ob_get_clean();
      } else {
        include_once('module/' . $module_code . '.php');
        exit();
      }
    }
    else {

      if (strlen($r['module_ref'])) {
        if ($_m = getRow(getRs("SELECT params FROM module WHERE module_code = ?", array($r['module_ref'])))) {
          $_params = $_m['params'];
        }
      }

      if (isJson($_params)) {
        $params = json_decode($_params, true);
      }
      
      if (sizeof($params)) {
        $tbl = $params;
      }
      elseif (strlen($r['tbl'])) {
        $title = $r['tbl'];
        $tbl = array('name' => $r['tbl'], 'title' => nicefy($title));
      }

      if (isset($tbl['redirect'])) {
        $rs = getRs($tbl['redirect'], array(getVar('c')));
        if ($r = getRow($rs)) {
          redirectTo($r[0]);
        }
      }
      if (isset($tbl['name'])) {
        include_once ('./inc/tbl.php');
        exit();
      }
      elseif (isset($tbl['analytics'])) {
        include_once ('./inc/analytics.php');
        exit();
      }
      elseif (isset($tbl['map'])) {
        include_once ('./inc/map.php');
        exit();
      }
    }
  }
  else {
    http_response_code(302);
    $meta_title = 'Access Denied';
    $_content = '<div class="alert alert-danger alert-bordered text-lg">
        <strong>Access Denied!</strong>
        <p>Sorry ' . $_Session->first_name . ', you are not authorized to access this resource. Please contact system administrator for more information.</p>
    </div>';
  }
}
else {
	http_response_code(404);
  $meta_title = '404 - Page Not Found';
  $_content = '<div class="alert alert-danger alert-bordered text-lg">
      <strong>404 - Page Not Found</strong>
      <p>Sorry ' . $_Session->first_name . ', the page you are attempting to load cannot be found. Please contact system administrator for more information.</p>
  </div>';
}
require_once('inc/header.php');
echo $_content;
require_once('inc/footer.php');

?>