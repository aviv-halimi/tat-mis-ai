 <!-- src="embedding.3.0.js" -->
  <script type="module" src="https://10ax.online.tableau.com/javascripts/api/tableau.embedding.3.latest.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" integrity="sha512-E8QSvWZ0eCLGk4km3hxSsNmGWbLtSCSUcewDQPQWZF6pEU8GlT8a5fF32wOl1i8ftdMhssTrF/OhyGWwonTcXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
  <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

  <tableau-viz id='tableauViz' src='https://us-west-2b.online.tableau.com/t/tatmaster/views/DeliveriesDashboard/DeliveryReport' 
   token='${token}'
   height='840'
   width='1300'
   toolbar="Bottom" hide-tabs>
   </tableau-viz>

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