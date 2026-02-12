$(document).ready(function(e) {
    defaultAssets();
  });
  
  function defaultAssets() {
    bindForm('login');
    bindForm('forgot');
    bindForm('reset-password');
    $('.btn-forgot').on('click', function(e) {
      e.preventDefault();
      $('#f_login').slideUp();
      $('#f_forgot').slideDown();
    });
    $('.btn-reset-password').on('click', function(e) {
      e.preventDefault();
      $('#f_login').slideUp();
      $('#f_reset-password').slideDown();
    });
    $('.btn-login').on('click', function(e) {
      e.preventDefault();
      $('#f_login').slideDown();
      $('#f_forgot').slideUp();
      $('#f_reset-password').slideUp();
    });
    initAssets();
  }
  
  function initAssets() {  
    initSwitcher.init();
  }