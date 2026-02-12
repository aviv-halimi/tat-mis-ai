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
    if (strlen($r['content'])) {
      $no_header = true;
      $_content = '
	     <!-- src="embedding.3.0.js" -->
		  <script type="module" src="https://10ax.online.tableau.com/javascripts/api/tableau.embedding.3.latest.js"></script>
		  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" integrity="sha512-E8QSvWZ0eCLGk4km3hxSsNmGWbLtSCSUcewDQPQWZF6pEU8GlT8a5fF32wOl1i8ftdMhssTrF/OhyGWwonTcXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
		  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
		  <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
		  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

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
	  
	 

	  ';
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
    encodedSource = encodedSource.replace(/=+$/, '');
    encodedSource = encodedSource.replace(/\+/g, '-');
    encodedSource = encodedSource.replace(/\//g, '_');
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
  const tableauViz = document.getElementById('tableauViz');

  // Set the token attribute of the tableauViz element
  tableauViz.setAttribute('token', token);
</script>
    }

    $_breadcrumb = $_Session->GetBreadCrumb($r['module_id'], $r['module_name']);

    if (file_exists('module/' . $module_code . '.php')) {
      include_once('module/' . $module_code . '.php');
      exit();
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