var ajaxSearch;

$(document).ready(function(e) {
  defaultAssets();
});

function defaultAssets() {
	bindForm('table-display');
	bindForm('display');
	bindForm('profile');
  bindForm('password');
  $('.transfer-qty').on('change', function(e) {
    var $this = $(this);
    var $div = $(this).closest('div');
    var $tr = $(this).closest('tr');
    var inv_1 = $tr.find('#inv_1_' + $this.data('id')).data('sort');
    var suggested_qty = $tr.find('#suggested_qty_' + $this.data('id')).data('sort');
    $div.removeClass('has-error has-warning has-success');
    $div.find('.fa').remove();
    if ($this.val().length == 0 || isNaN($this.val())) {
    }
    else if ($this.val() < suggested_qty) {
      $div.addClass('has-warning');
      $div.append('<span class="fa fa-exclamation-triangle form-control-feedback"></span>');
    }
    else if ($this.val() <= inv_1) {
      $div.addClass('has-success');
      $div.append('<span class="fa fa-check-circle form-control-feedback"></span>');
    }
    else {
      $div.addClass('has-error');
      $div.append('<span class="fa fa-times form-control-feedback"></span>');
      //showAlert('Error', 'Incorrect amount entered for this product');
    }
    postAjax('transfer-report-product', {c: $('#transfer_report_code').val(), p: $this.data('code'), qty: $this.val()}, 'status', function(data) {
      $('.fulfillment-progress').html(data.progress);
      $('.fulfillment-progress-percent').width(data.percent + '%').html(data.percent + '%');
    });
  });
  $('.btn-transfer-report-api').on('click', function(e) {
    e.preventDefault();
    $(this).remove();
    postAjax('transfer-report-api', {c: $('#transfer_report_code').val()}, 'status_api', function(data) {
    });
  });
  $('.btn-transfer-product-api').on('click', function(e) {
    e.preventDefault();
    $(this).attr('disabled', true);
    var product_id = $(this).data('id');
    var $b = $(this);
    var $i = $(this).find('i');
    $i.removeClass('fa-arrow-right').addClass('fa-circle-notch fa-spin');
    postAjax('transfer-report-api', {c: $('#transfer_report_code').val(), product_id: product_id}, 'status_product_api', function(data) {
      $b.removeClass('btn-danger').addClass('btn-success');
      $i.removeClass('fa-circle-notch fa-spin').addClass('fa-check');
    }, function(data) {
      $b.removeClass('btn-danger').addClass('btn-warning');
      $i.removeClass('fa-circle-notch fa-spin').addClass('fa-exclamation-triangle');
      Swal.fire({
        icon: 'error',
        title: 'Transfer Request Failed.',
        text: data.response
      });
    });
  });
  $('.btn-transfer-product-refresh').on('click', function(e) {
    e.preventDefault();
    var product_id = $(this).data('id');
    var $b = $(this);
    var $i = $(this).find('i');
    $b.attr('disabled', true);
    $i.removeClass('fa-redo').addClass('fa-circle-notch fa-spin');
    postAjax('transfer-report-product-refresh', {transfer_report_id: $('#transfer_report_id').val(), product_id: product_id}, 'status_product_api', function(data) {
      $i.removeClass('fa-circle-notch fa-spin').addClass('fa-redo');
      $b.attr('disabled', false);
      $('.product-' + product_id + ' .inv-1').text(data.inv_1);
      $('.product-' + product_id + ' .inv-2').text(data.inv_2);
    });
  });
  $('.btn-cron-sales').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    $this.hide();
    postAjaxFunc('/cron/daily-sale-batches', {store_id: $('#store_id').val()}, 'status_cron', function(data) {
    }, function(data) { $this.show(); });
  });
  $('.btn-trigger-dd').on('click', function(e) {
	e.preventDefault();
    var $this = $(this);
    $this.hide();
    postAjaxFunc('/cron/trigger-dd', {brand_id: $('#brand_id').val()}, 'status_dd', function(data) {
    }, function(data) { $this.show(); });
  });
  $('.btn-store').on('click', function(e) {
    e.preventDefault();
    postAjax('store', {c: $(this).data('c')}, 'status_store');
  });
  $('.btn-table-display-restore').on('click', function(e) {
    postAjax('table-display', {module_code: $(this).data('c')}, 'status_table_display');
  });
  $('.btn-table-display-load').on('click', function(e) {
    e.preventDefault();
    postAjax('table-display-load', {module_option_code: $(this).data('c')}, 'status_table_display_load');
  });
	$('.nav li a').each(function() {
		var href = $(this).attr('href');
		var a_href = href.split('/');
		href = a_href[a_href.length - 1];
		if (href == $('#page_name').val() || $(this).data('alt') == $('#page_name').val()) {
			$(this).parents('li').addClass('active');
		}
	});
	$('.btn-del-import').on('click', function(e) {
		e.preventDefault();
		var $this = $(this);
		postAjax('import-del', {fn: $this.data('fn'), fd: $this.data('fd')}, '', function(data) {
			$this.closest('tr').fadeOut(500); //.$(this).remove();
		})
	});

	$('.ckeditor-gallery').on('click', function(e) {
		e.preventDefault();
		var id = $(this).attr('id').replace('ckeditor_gallery_', '');
		$('.modal-title').text('Insert Image from Library');
		loadModalGallery(id);
	});

	$('.sidebar-minify-btn').on('click', function(e) {
		postAjax('sidebar-minify', {_m:!$('.page-sidebar-minified').hasClass('page-sidebar-minified')?1:0});
  });
  
  $('.btn-chart-type').on('click', function(e) {
    var $btn = $(this);
    var type = $btn.data('type');
    $btn.blur();
    $('.btn-chart-type').removeClass('btn-success');
    $btn.addClass('btn-success');
    showStatus('status_chart_type', 'Just a sec ... Updating chart ...');
    postAjaxFunc('/' + $('#page_name').val(), {_ajax: 1, _chart_type: $(this).data('type'), _chart_stacking: $('.chart-options-stacking').prop('checked')?1:0, _chart_3d: $('.chart-options-3d').prop('checked')?1:0}, '', function(data) {
      updateChart($btn.data('chart'), data);
      hideStatus('status_chart_type');
      if (type == 'column' || type == 'bar') {
        $('.chart-options-stacking-div').removeClass('hide');
      }
      else {
        $('.chart-options-stacking-div').addClass('hide');        
      }
      if (type == 'pie' || type == 'column' || type == 'bar') {
        $('.chart-options-3d-div').removeClass('hide');
      }
      else {
        $('.chart-options-3d-div').addClass('hide');        
      }
    });
  });

  $('.chart-options').on('change', function(e) {
    showStatus('status_chart_type', 'Just a sec ... Updating chart ...');
    postAjaxFunc('/' + $('#page_name').val(), {_ajax: 1, _chart_type: $('.btn-chart-type.btn-success').data('type'), _chart_stacking: $('.chart-options-stacking').prop('checked')?1:0, _chart_3d: $('.chart-options-3d').prop('checked')?1:0}, '', function(data) {
      updateChart('chart', data);
      hideStatus('status_chart_type');
    });
  });


  $('#modal .close').on('click', function(e) {
    $('#modal .modal-body').html('');
  });

  initAssets();
  initSubtotals();
}



function updateSubtotals(brand_id, category_id) {
  let dollarUSLocale = Intl.NumberFormat('en-US', {
    style: "currency",
    currency: "USD",
  });
  // brand
  var b_total = 0;
  $('.order-qty').each(function() {
    if ($(this).data('brand') == brand_id && $(this).data('category') == category_id) b_total += 1 * $(this).val();
  });
  $('#order_qty_brand_' + brand_id + '_category_' + category_id).text(b_total);
  var b_total = 0;
  $('.order-subtotal').each(function() {
    if ($(this).data('brand') == brand_id && $(this).data('category') == category_id) b_total += 1 * $(this).data('subtotal');
  });
  $('#order_subtotal_brand_' + brand_id + '_category_' + category_id).text(dollarUSLocale.format(b_total));
  // category
  var c_total = 0;
  $('.order-qty').each(function() {
    if ($(this).data('category') == category_id) c_total += 1 * $(this).val();
  });
  $('#order_qty_category_' + category_id).text(c_total);
  var c_total = 0;
  $('.order-subtotal').each(function() {
    if ($(this).data('category') == category_id) c_total += 1 * $(this).data('subtotal');
  });
  $('#order_subtotal_category_' + category_id).text(dollarUSLocale.format(c_total));
}

function initSubtotals() {
  $('.order-qty-brand-category').each(function() {
    updateSubtotals($(this).data('brand'), $(this).data('category'));
  });
}

function initAssets(select2) {
	initSwitcher.init();
	//$('.datepicker').datepicker({todayHighlight:!0,autoclose:!0});
	/*$('.birthdate').daterangepicker(
		{singleDatePicker:!0,showDropdowns:!0},
		function(e,t){$(".birthdate input").val(e.format("D/M/YYYY"));}
  );
  */

  $('form').on('focus', 'input[type=number]', function (e) {
    $(this).on('wheel.disableScroll', function (e) {
      e.preventDefault()
    })
  });
  $('form').on('blur', 'input[type=number]', function (e) {
    $(this).off('wheel.disableScroll')
  });

	$('.datepicker, .birthdate').datetimepicker({'format': 'M/D/YYYY'});
  $('.datetimepicker').datetimepicker({'format': 'M/D/YYYY hh:mm a'});

  $('.__store_id').off('click').on('click', function(e) {
    var $this = $(this).find('input');
    var id = $this.data('id');
    if ($this.prop('checked')) {
      $('.__employees_' + id).show();
      $('.__employees_' + id + ' .select2-container').width('300px');
    }
    else {
      $('.__employees_' + id).hide();
    }
  });

  $('.transfer-product').off('change').on('change', function(e) {
    var id = $(this).data('id');
    postAjax('transfer-product-inventory', {id: $(this).val()}, 'status_transfer_product_inventory', function(data) {
      $('.transfer-product-inventory').html(data.html);
    });
  });

  $('.po-options #vendor_id').off('change').on('change', function(e) {
    var $this = $(this).find(':selected');
    if ($this.data('suspended') == '1') {           
      Swal.fire({
        icon: 'error',
        title: 'Vendor Suspended',
        text: 'This Vendor (' + $this.text() + ') is currently suspended so you cannot place any new POs or Credit Requests'
      });
    }
    $('.po-options #email').val($this.data('email'));
    console.log($this.data('delivery-placeholder'));
    $('.po-options #date_schedule_delivery').prop('placeholder', ($this.data('delivery-placeholder')));
  });

  $('.btn-po-email').off('click').on('click', function(e) {
    var $this = $(this);
    var id = $(this).data('po');
    postAjax('po-email', {po_id: $(this).data('po'), email: $('.po-options #email').val()}, 'status_po_email', function(data) {
      $this.removeClass('btn-default').addClass('btn-success');
      $this.html('<i class="fa fa-check"></i> Saved');
    }, function(data) {
      $this.removeClass('btn-success').addClass('btn-default');
      $this.html('Save');
    });
  });

  $('.po-existing-product').off('change').on('change', function(e) {
    $(this).closest('form').submit();
  });

  $('.order-qty').off('keydown').on('keydown', function(e) {
    var code = e.keyCode;
    var i = $('.order-qty').index($(this));
    //37 -> Left
    //38 -> Up
    //39 -> Right
    //40 -> Down
    if (code == 38) {
      e.preventDefault();
      $('.order-qty').eq(i - 1).focus().select();
    }
    if (code == 39) {
      e.preventDefault();
      $('.order-price').eq(i).focus().select();
    }
    if (code == 40) {
      e.preventDefault();
      $('.order-qty').eq(i + 1).focus().select();
    }
  });
  $('.order-price').off('keydown').on('keydown', function(e) {
    var code = e.keyCode;
    var i = $('.order-price').index($(this));
    if (code == 37) {
      e.preventDefault();
      $('.order-qty').eq(i).focus().select();
    }
    if (code == 38) {
      e.preventDefault();
      $('.order-price').eq(i - 1).focus().select();
    }
    if (code == 40) {
      e.preventDefault();
      $('.order-price').eq(i + 1).focus().select();
    }
  });

  $('.received-qty').off('keydown').on('keydown', function(e) {
    var code = e.keyCode;
    var i = $('.received-qty').index($(this));
    if (code == 38) {
      e.preventDefault();
      $('.received-qty').eq(i - 1).focus().select();
    }
    if (code == 39) {
      e.preventDefault();
      $('.order-paid').eq(i).focus().select();
    }
    if (code == 40) {
      e.preventDefault();
      $('.received-qty').eq(i + 1).focus().select();
    }
  });
  $('.order-paid').off('keydown').on('keydown', function(e) {
    var code = e.keyCode;
    var i = $('.order-paid').index($(this));
    if (code == 37) {
      e.preventDefault();
      $('.received-qty').eq(i).focus().select();
    }
    if (code == 38) {
      e.preventDefault();
      $('.order-paid').eq(i - 1).focus().select();
    }
    if (code == 40) {
      e.preventDefault();
      $('.order-paid').eq(i + 1).focus().select();
    }
  });

  $('.calc-type').off('change').on('change', function(e) {
    var type = $(this).val();
    if (type == 1) {
      $('.calc-rate').show();
      $('.calc-amount').hide();
    }
    else if (type == 2) {
      $('.calc-amount').show();
      $('.calc-rate').hide();
    }
    else {
      $('.calc-rate').hide();
      $('.calc-amount').hide();
    }
  });
  
  
	if (select2 === undefined) {
		$('.modal-body select.select2').each(function(e) {
      var $this = $(this);
			if (!$this.hasClass('select2-hidden-accessible')) {
        $this.select2({
          dropdownParent: $('#modal'),
          minimumResultsForSearch: ($this.find('option').length < 6)?-1:0
        });
      }
		});
		$('.display-options select.select2').each(function(e) {
      var $this = $(this);
			if (!$this.hasClass('select2-hidden-accessible')) {
        $this.select2({
          minimumResultsForSearch: ($this.find('option').length < 6)?-1:0
        });
      }
		});
		$('.t-tbl-inline select.select2').each(function(e) {
      var $this = $(this);
			if (!$this.hasClass('select2-hidden-accessible')) {
        $this.select2({
          minimumResultsForSearch: ($this.find('option').length < 6)?-1:0
        });
      }
		});
		$('.modal-body select.multiple-select').each(function(e) {
			var $this = $(this);
			if (!$this.hasClass('select2-hidden-accessible')) {
				$this.select2({
          minimumResultsForSearch: ($this.find('option').length < 6)?-1:0,
          dropdownParent: $('#modal'),
					allowClear: true
				});
			}
		});
		$('.display-options select.multiple-select').each(function(e) {
			var $this = $(this);
			if (!$this.hasClass('select2-hidden-accessible')) {
				$this.select2({
          minimumResultsForSearch: ($this.find('option').length < 6)?-1:0,
					allowClear: true
				});
			}
    });    
  }
  $('.btn-revert').off('click').on('click', function(e) {
    e.preventDefault();
    var status = 'revert_' + $(this).data('id');
    if ($(this).hasClass('btn-revert-all')) {
      $('.t-log .status').remove();
    }
    postAjax('revert', {c: $(this).data('c'), k: $(this).data('k')}, status);
  });
	$('.btn-remove-img').off('click').on('click', function(e) {
		e.preventDefault();
		var $f = $(this).closest('form');
		var id = $(this).attr('id').replace('_remove', '');
		$f.find('#' + id).val('');
		var none = '';
		if ($('#' + id + '_none').length) none = $('#' + id + '_none').html();
		$(this).closest('.row').find('.upload-preview').hide().html(none);
		$(this).hide();
    if ($(this).hasClass('coa_filenames')) {
      updateCOA();
    }
  });
	$('.btn-remove-media-item').off('click').on('click', function(e) {
		e.preventDefault();
    var $this = $(this);
		var $f = $(this).closest('.media-item');
		$f.fadeOut(500, function() {
      $(this).remove(); 
      if ($this.hasClass('coa_filenames')) {
        updateCOA();
      }
    });
  });
  $('.btn-edit-coa').off('click').on('click', function(e) {
		e.preventDefault();
    $(this).closest('div').hide();
    $('.coa-filenames').show();
  });

  $('.btn-dialog').off('click').on('click', function(e) {
    e.preventDefault();
    typicalDialog($(this));
  });
  $('.ckeditor').each(function(e) {
    CKEDITOR.replace( $(this).attr('id') );
	});
	$('.btn-del').off('click').on('click', function(e) {
    e.preventDefault();
    deleteRecord($(this));
	});
	$('.btn-del-file').off('click').on('click', function(e) {
    e.preventDefault();
    deleteFile($(this));
	});
	$('.module-id').on('click', function(e) {
    var $this = $(this).find('input');
		if ($this.prop('checked')) {
			$('.module-ids-' + $this.val()).removeClass('hide');
		}
		else {
			$('.module-ids-' + $this.val()).addClass('hide');
		}
  });
	$('.po-product-created, .po-product-transferred').on('click', function(e) {
    var $this = $(this).find('input');
		postAjax('product-coordination', {id: $this.data('id'), type:  $this.data('type'), 'checked': $this.prop('checked')?1:0}, 'status', function(data) {
      if (data.hide) {
        $this.closest('tr').fadeOut(500);
      }
    });
  });
  
  $('.analytics-filters a').off('click').on('click', function(e) {
    e.preventDefault();
    var f = $(this).data('f');
    $('.div_' + f).removeClass('hide');
    $(this).parent().addClass('selected');
    if (!$('.analytics-filters a').length) {
      //$('.analytics-filters').closest('.row').remove();
    }
  });
	$('.nav-enabled').off('change').on('change', function(e) {
		var $d = $(this).closest('.dd-edit').next(); //find('.dd-display');
		var is_enabled;
		if ($(this).prop('checked')) {
			$d.removeClass('disabled');
			is_enabled = 1;
		}
		else {
			$d.addClass('disabled');
			is_enabled = 0;
		}
		postAjax('nav-enabled', {nav_id: $(this).val(), is_enabled: is_enabled});
  });
  
  $('.num-items').off('change').on('change', function(e) {
   var name = $(this).data('name');
   var num = $(this).val();
   $('.' + name).hide();
   for (i=1; i<=num; i++) {
     $('.' + name + '-' + i).show();
   }
  });

  $('.btn-po-status').off('click').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    var html = $this.data('title');
    if ($('#date_ordered').length && $('#date_received').length) {
      var _d1 = $('#date_ordered').val().split('/');
      var _d2 = $('#date_received').val().split('/');
      if (_d1.length == 3 && _d2.length == 3) {
        var d1 = new Date(_d1[2], _d1[0], _d1[1]);
        var d2 = new Date(_d2[2], _d2[0], _d2[1]);
        if (d1 > d2) {
          html += '<br /><span class="text-danger">ALERT! The received date that you have entered (' + $('#date_received').val() + ') is ealier than the order date (' + $('#date_ordered').val() + '). Please confirm before proceeding.</span>';
        }
      }
    }
    Swal.fire({
      title: 'Are you sure?',
      html: html,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, continue'
    }).then((result) => {
      if (result.value) {
        $('.btn-status').hide();
        $('#status_po').addClass('mb-2');
        postAjax('po-status', {po_code: $this.data('c'), back: $this.data('d')}, 'status_po', function(data) {
        }, function(data) { 
          $('.btn-status').show();
          if (data.click) {
            $(data.click).trigger('click');
          }
          else {       
            Swal.fire({
              icon: 'error',
              title: 'Something went wrong ...',
              text: data.response
            });
          }
        });
      }
    });
  });

  $('.btn-po-custom-product-del').off('click').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    Swal.fire({
      title: 'Confirm removal?',
      html: 'Are you sure you want to remove <b>' + $this.data('title') + '</b> from this PO?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, remove product'
    }).then((result) => {
      if (result.value) {
        postAjax('po-custom-product', {po_code: $this.data('c'), po_product_code: $this.data('a'), po_product_name: $this.data('title'), del: 1}, 'status', function(data) {
          $('.po-foot').html(data.foot);
          $('.po-progress').html(data.progress);
          $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
          $this.closest('tr').fadeOut(500, function() { $(this).remove(); });
          initAssets();
        }, function(data) {          
          Swal.fire({
            icon: 'error',
            title: 'Something went wrong ...',
            text: data.response
          });
        });
      }
    });
  });

  $('.btn-po-del').off('click').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    Swal.fire({
      title: 'Confirm cancellation?',
      html: $this.data('title'),
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, cancel PO'
    }).then((result) => {
      if (result.value) {
        postAjax('po-del', {po_code: $('#po_code').val()}, 'status');
      }
    });
  });

  bindForm('po-at-discount', function(data) {
    $('.po-foot').html(data.foot);
    $('.po-progress').html(data.progress);
    $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
    initAssets();
    setTimeout(function() { closeDialogs(); }, 1000);
  });
  $('.btn-po-discount-del').off('click').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    Swal.fire({
      title: 'Confirm removal?',
      html: $this.data('title'),
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, remove discount'
    }).then((result) => {
      if (result.value) {
        postAjax('po-at-discount', {po_code: $this.data('c'), po_discount_code: $this.data('d'), del: 1}, 'status', function(data) {
          $('.po-foot').html(data.foot);
          $('.po-progress').html(data.progress);
          $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
          $this.closest('tr').fadeOut(500, function() { $(this).remove(); });
          initAssets();
        }, function(data) {          
          Swal.fire({
            icon: 'error',
            title: 'Something went wrong ...',
            text: data.response
          });
        });
      }
    });
  });

  bindForm('po-discount', function(data) {
    $('.po-foot').html(data.foot);
    $('.po-progress').html(data.progress);
    $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
    initAssets();
    setTimeout(function() { closeDialogs(); }, 1000);
  });
  
  bindForm('po-tax', function(data) {
    $('.po-foot').html(data.foot);
    $('.po-progress').html(data.progress);
    $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
    initAssets();
    setTimeout(function() { closeDialogs(); }, 1000);
  });

  bindForm('po-custom-product', function(data) {
    $('.po-foot').html(data.foot);
    $('.po-progress').html(data.progress);
    $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
    /*
    if ($('#po_product_' + data.po_product_id).length) {
      $('#po_product_' + data.po_product_id + ' .product-name').html(data.po_product_name);
    }
    else {
      if (data.tr) $('table.po tbody.products').append(data.tr);
      if (data.tbody) $('table.po tbody.products').html(data.tbody);
    }
    */
   
    if (data.tbody) $('table.po tbody.products').html(data.tbody);
    if (data.tbody) $('.po-foot').html(data.foot);

    initAssets();
    setTimeout(function() { closeDialogs(); }, 1000);
  });

  $('.order-qty, .order-price').off('change').on('change', function(e) {
    var $this = $(this);
    var id = $(this).data('id');
    var code = $(this).data('code');
    var $qty = $('#order_qty_' + id);
    var $price = $('#order_price_' + id);
    var $div = $qty.closest('div');
    $div.removeClass('has-error has-warning has-success');
    $div.find('.fa').remove();
    if ($qty.val().length == 0 || isNaN($qty.val())) {
    }
    else {
      $div.addClass('has-success');
      $div.append('<span class="fa fa-check-circle form-control-feedback"></span>');
    }
    postAjax('po-product', {c: $('#po_code').val(), p: code, qty: $qty.val(), price: $price.val(), cost: $price.data('cost')}, 'status', function(data) {
      $('#order_subtotal_' + id).html(data.subtotal);
      $('#order_subtotal_' + id).data('subtotal', data.subtotal_r);
      $('.po-foot').html(data.foot);
      $('.po-progress').html(data.progress);
      $('.po-progress-percent').width(data.percent + '%').html(data.percent + '%');
      updateSubtotals($this.data('brand'), $this.data('category'));
      initAssets();
    });
  });

  $('.received-qty, .order-paid').off('change').on('change', function(e) {
    var id = $(this).data('id');
    var code = $(this).data('code');
    var $qty = $('#received_qty_' + id);
    var $paid = $('#order_paid_' + id);
    var $div = $qty.closest('div');
    var $div_paid = $paid.closest('div');
    $div.removeClass('has-error has-warning has-success');
    $div.find('.fa').remove();
    $div_paid.removeClass('has-error has-warning has-success');
    $div_paid.find('.fa').remove();
    if ($qty.val().length == 0 || isNaN($qty.val())) {
    }
    else if ($qty.val() * 1 <= $qty.data('order') * 1) {
      $div.addClass('has-success');
      $div.append('<span class="fa fa-check-circle form-control-feedback"></span>');
    }
    else {
      $div.addClass('has-warning');
      $div.append('<span class="fa fa-exclamation-triangle form-control-feedback"></span>');
    }
    if ($paid.val().length == 0 || isNaN($paid.val())) {
    }
    else if ($paid.val() * 1 <= $paid.data('price') * 1) {
      $div_paid.addClass('has-success');
      $div_paid.append('<span class="fa fa-check-circle form-control-feedback"></span>');
    }
    else {
      $div_paid.addClass('has-warning');
      $div_paid.append('<span class="fa fa-exclamation-triangle form-control-feedback"></span>');
    }
    postAjax('po-product', {c: $('#po_code').val(), p: code, r_qty: $qty.val(), paid: $paid.val(), price: $paid.data('price') }, 'status', function(data) {
      $('#order_r_subtotal_' + id).html(data.subtotal);
      $('.po-foot').html(data.foot);
      initAssets();
    });
  });
  
  $('.suggested-qty').off('click').on('click', function(e) {
    e.preventDefault();
    $('#' + $(this).data('ref')).val($(this).text()).trigger('change');
  });
  $('.suggested-qty-category').off('click').on('click', function(e) {
    e.preventDefault();
    $('.' + $(this).data('ref')).trigger('click');
  });
  $('.suggested-qty-brand').off('click').on('click', function(e) {
    e.preventDefault();
    $('.' + $(this).data('ref')).trigger('click');
  });
  $('.suggested-qty-all').off('click').on('click', function(e) {
    e.preventDefault();
    $('.suggested-qty').trigger('click');
  });

  // po
  
  $('.r-suggested-qty').off('click').on('click', function(e) {
    e.preventDefault();
    $('#received_qty_' + $(this).data('ref')).val($(this).data('qty')).trigger('change');
  });
  $('.r-suggested-qty-all').off('click').on('click', function(e) {
    e.preventDefault();
    $('.r-suggested-qty').trigger('click');
  });

  $('.btn-po-description').off('click').on('click', function(e) {
    $(this).remove();
    $('.po-description').slideDown(500);
  });

  $('.po-data .date-received').off('dp.change').on('dp.change', function(e) {
    postAjax('po-data', {c: $('#po_code').val(), f: 'date_received', v: $('#date_received').val()}, 'status');
  });
  $('.po-data #invoice_number').off('change').on('change', function(e) {
    postAjax('po-data', {c: $('#po_code').val(), f: 'invoice_number', v: $(this).val()}, 'status');
  });
  $('.po-data #invoice_filename').off('change').on('change', function(e) {
    postAjax('po-data', {c: $('#po_code').val(), f: 'invoice_filename', v: $(this).val()}, 'status');
  });
  $('.po-data .description').off('change').on('change', function(e) {
    postAjax('po-data', {c: $('#po_code').val(), f: 'description', v: $('#description').val()}, 'status');
  });
  $('#coa_filename').off('change').on('change', function(e) {
    postAjax('po-data', {c: $('#po_code').val(), f: 'coa_filename', v: $(this).val()}, 'status');
  });
  $('.btn-coa-del').off('click').on('click', function(e) {
    e.preventDefault();
    var $this = $(this);
    Swal.fire({
      title: 'Are you sure?',
      html: $this.data('title'),
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes'
    }).then((result) => {
      if (result.value) {
        postAjax('po-data', {c: $('#po_code').val(), f: 'coa_filename', v: ''}, 'status', function(data) {
          $this.closest('div').remove();
          location.reload();
        });
      }
    });
  });
  initDailyDiscountReportBtn();
  initTableEditor();
	initUploads();
	initUserSearch();
	initDisplaySettings();
}

function updateDisplay($f) {
	var chart_div = 'chart_enrollment'; 
	postAjax('display', $f.serialize(), '', function(data) {
		$f.find('.table-responsive').html(data.html);
		var chart = eval('_' + chart_div);
		while(chart.series.length > 0)
			chart.series[0].remove(true);
		for(var i = 0; i < data.chart.series.length; i++) {
			chart.addSeries({                        
				name: data.chart.series[i].name,
				data: data.chart.series[i].data
			});
		}
		chart.update({
			categories: data.chart.categories,
			series:data.chart.series,
			legend: {
				enabled: data.chart.legend
			}
		});
		chart.redraw();
		chart.xAxis[0].setCategories(data.chart.categories, true, true);
	});
}
function initDisplaySettings() {
	$('.cb-display').off('click').on('click', function(e) {
		var $f = $(this).closest('form');
		updateDisplay($f);
	});
}

function initDailyDiscountReportBtn() {
  var i = 0;
  $('.btn-daily-discount-report').each(function(e) {
    var id = $(this).data('id');
    postAjax('daily-discount-report-btn', {id: id}, 'status', function(data) {
      $('.span-daily-discount-report-' + id).html(data.btn);
      $('.span-daily-discount-report-generated-' + id).html(data.t);
      $('.span-daily-discount-report-total-' + id).html(data.total);
      i++;
      if (i == 1) setTimeout(function() { initDailyDiscountReportBtn(); }, 10000);
    });
  });
}

function initTableEditor() {
  /*
  $('.datepicker').datepicker({
    format: 'd/m/yyyy',
    autoclose: true,
    todayHighlight: true
  });
  */

  if ($('#is_superadmin').prop('checked')) {
    $('.row-admin_group_id, .row-date_start, .row-date_end').hide();
  }
  else {
    $('.row-admin_group_id, .row-date_start, .row-date_end').show();
  }
  $('#is_superadmin').off('click').on('click', function(e) {
    if ($(this).prop('checked')) {
      $('.row-admin_group_id, .row-date_start, .row-date_end').hide();
    }
    else {
      $('.row-admin_group_id, .row-date_start, .row-date_end').show();
    }
  });
  $('#modal #daily_discount_report_type_id').off('select2:select').on('select2:select', function(e) {
    if ($(this).val() == 3) {
      $('.daily_discount_report_type_id_3').show();
    }
    else {
      $('.daily_discount_report_type_id_3').hide();
    }
  });
 if ($('.datatable').length) {
    $('.datatable').off('click', '.btn-table-dialog').on('click', '.btn-table-dialog', function (e) { 
      e.preventDefault();
      tableDialog($(this));
    });
    $('.datatable').off('click', '.btn-dialog').on('click', '.btn-dialog', function (e) { 
      e.preventDefault();
      typicalDialog($(this));
    });
    $('.datatable').off('click', '.btn-del').on('click', '.btn-del', function (e) { 
      e.preventDefault();
      deleteRecord($(this));
    });

    $('.datatable').off('click', '.btn-del-file').on('click', '.btn-del-file', function (e) { 
      e.preventDefault();
      deleteFile($(this));
    });
  }
  else {
    $('.btn-table-dialog').off('click').on('click', function(e) {
      e.preventDefault();
      tableDialog($(this));
    });
  }
  $('.btn-add-dialog').off('click').on('click', function(e) {
    e.preventDefault();
    tableDialog($(this));
  });

	$('.f-tbl').off('submit').on('submit', function(e) {
    e.preventDefault();
    var $f =  $(this);
    var $btns = $f.find('.form-btns');
    var r = ($f.find('.is_enabled input').prop('checked'))?$f.find('#RequiredFields').val():'';
		var e = '';
		for (var instance in CKEDITOR.instances) {
			CKEDITOR.instances[instance].updateElement();
		}
		if (r.length) {
			var arr_r = r.split(',');
			for (var i=0; i<arr_r.length; i++) {
				if ($f.find('#' + arr_r[i]).val().length == 0) {
					e += ((e.length)?', ':'') + $f.find('#' + arr_r[i]).closest('.input-row').find('.input-label').text().replace(':', '');
				}
			}
		}
		if (e.length) {
      Swal.fire({
        icon: 'error',
        title: 'Some info is missing ...',
        text: 'The following fields are required: ' + e,
        //footer: '<a href>Why do I have this issue?</a>'
      });
    }
		else {
      $btns.hide();
			postAjaxFunc('/' + $f.find('#PageName').val(), $f.serialize(), ($('#tbl_status').length)?'tbl_status':'modal_status', function(data) {
        //setTimeout(function() { closeDialogs(); }, 1500);
      }, function(data) {
        $btns.show();
      });
		}
  });

  $('.btn-table-display-share').off('click').on('click', function(e) {
    var id = $(this).data('id');
    $('.module-option-' + id + '-share').removeClass('hide');
  });

  $('.btn-table-display-share-send').off('click').on('click', function(e) {
    var id = $(this).data('id');
    var c = $(this).data('c');
    var $btn = $(this);
    $btn.html('<i class="fa fa-circle-notch fa-spin"></i> Sending ...');
    $btn.prop('disabled', true);
    postAjax('table-display-share', {c: c, admin_ids: $('#admin_ids_' + id).val()}, 'status', function(data) {
      $('.module-option-' + id + '-share').addClass('hide');
      $btn.html('<i class="ion-android-send"></i> Send Link');
      $btn.prop('disabled', false);
    }, function(data) {
      $btn.html('<i class="ion-android-send"></i> Send Link');
      $btn.prop('disabled', false);
    });
  });

  $('.btn-module-option-edit').off('click').on('click', function(e) {
    var id = $(this).data('id');
    $('.module-option-' + id + '-view').addClass('hide');
    $('.module-option-' + id + '-edit').removeClass('hide');
  });

  $('.btn-module-option-save').off('click').on('click', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    var name = $('.module-option-' + id + '-edit input').val();
    postAjax('table-display-option-save', {id: id, name: name}, 'status', function(data) {
      $('.module-option-' + id + '-view').removeClass('hide');
      $('.module-option-' + id + '-edit').addClass('hide');
      $('.module-option-' + id + '-view').html(data.name);
    });
  });

  if ( !$.fn.dataTable.isDataTable( '.table-modal' ) ) {
    $('.table-modal').DataTable({
      buttons: [
        'copy',
        { extend: 'excel',  exportOptions: { modifier: { page: 'all', search: 'none' } } },
        'pdf',
        { extend: 'print',  exportOptions: { modifier: { page: 'all', search: 'none' } } }
      ],
        order: [[ 0, "asc" ]],
        "searching": true,
        "paging": true, 
        "info": true,         
        "lengthChange":true,
        "responsive": true
    });
  }
}

function initUserSearch() {
  $('.user-kw').off('click').on('click', function(e) {
		e.stopPropagation();
  });
  
  $('.btn-user-search-reset').off('click').on('click', function(e) {
    e.preventDefault();    
    $('.user-name').hide();
    $('.user-kw').show().select().focus();
		$('#user_name').val('');
		$('#user_code').val('');
		$('#user_id').val(0);
		$('.ssn').html('-');
		$('.company').html('-');
		$('.address').html('-');
  });

	$('.user-kw').off('keyup').on('keyup', function(e) {
    abortAjax(ajaxSearch);
		ajaxSearch = $.post('/ajax/user-search', {kw: $(this).val(), workflow_type_id: $('#workflow_type_id').val()}, function(data) {
			$('.user-search .suggestions ul').html(data);
			if (data.length) {
				$('.user-search .suggestions-holder').slideDown(100);
			}
			else {
				$('.user-search .suggestions-holder').hide();
      }
      $('.user-search .suggestions-holder a').off('click').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        $('#user_id').val($this.data('id'));
        $('#user_code').val($this.data('code'));
        $('#user_name').val($this.data('name'));
        $('.ssn').html($this.data('ssn'));
				$('.company').html($this.data('company'));
				$('.address').html('<i class="zmdi zmdi-pin-drop"></i> ' + $(this).data('address') + '<br /><i class="icon-phone"></i> ' + $(this).data('phone') + ' <i class="icon-envelope"></i> ' + $(this).data('email'));
        $('.user-name').show();
        $('.user-kw').hide();
        $('.user-kw').val('');
        $('.user-search .suggestions-holder').hide();
      });
		});
	});
}

function updateChart(id, data) {
  $('#t_' + id).html(data.table);
  var chart = eval('_' + id);
  while(chart.series.length > 0)
    chart.series[0].remove(true);
  for(var i = 0; i < data.chart.series.length; i++) {
    chart.addSeries({                        
      name: data.chart.series[i].name,
      data: data.chart.series[i].data,
      //type: data.chart.series[i].type
    });
  }
  chart.update({
    title: {
      text: data.chart.title
    },
    chart: {
      type: data.chart.type,
      options3d: {
        enabled: data.chart.chart_3d
      }
    },
    plotOptions: {
      series: {
        stacking: data.chart.stacking
      }
    },
    categories: data.chart.categories,
    series:data.chart.series,
    yAxis:data.chart.yaxis,
    legend: {
      enabled: data.chart.legend
    }
  });
  if (data.chart.type != 'pie') {
    chart.update({
      chart: {
        options3d: {
          alpha: 10,
          beta: 5,
          depth: 100
        }
      }
    });
  }
  else {
    chart.update({
      chart: {
        options3d: {
          alpha: 45,
          beta: 0,
          depth: 50
        }
      }
    });
  }
  chart.redraw();
  chart.xAxis[0].setCategories(data.chart.categories, true, true);      
  /*
  var chart = eval('_chart');
  for(i=0; i<chart.series.length; i++) {
    chart.series[i].update({
      type: t
    });
  }
  chart.redraw();
  */
}

function deleteRecord($this) {
  var page = $this.data('url');
  var id = $this.data('id');
  Swal.fire({
    title: 'Confirm archive?',
    html: 'Are you sure you want to archive this record? You will no longer have access to the information in this record and it will not be included in any report.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, archive record'
  }).then((result) => {
    if (result.value) {
      postAjaxFunc(page, {del: id}, 'status', function(data) {});
    }
  });
}

function deleteFile($this) {
  var $tr = $this.closest('tr');
  var c = $this.data('c');
  Swal.fire({
    title: 'Confirm archive?',
    html: 'Are you sure you want to archive this record? You will no longer have access to the information in this record.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, archive record'
  }).then((result) => {
    if (result.value) {
      postAjax('file-del', {c: c}, 'status', function(data) {
        $tr.fadeOut(500);
      });
    }
  });
}

function updateCOA() {
  postAjax('po-data', $('#f_po-data').serialize(), 'status');
}

function formatRepo (repo) {
  if (repo.loading) {
    return repo.text;
  }

  var $container = $(
    "<div class='clearfix'>" +
      "<b>" + repo.sku + "</b><br />" + repo.name + "" +
    "</div>"
  );

  return $container;
}

function formatRepoSelection (repo) {
  return repo.text;
}