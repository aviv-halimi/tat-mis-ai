
function bindForm(f, callback, callback2, path) {
	if (typeof path === 'undefined') path = '/ajax/';
	$('#f_' + f).off('submit').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btns = $('#f_' + f + ' .form-btns');
		$btns.hide();
		$form.find('.status').attr('id', 'status_' + f);
		postAjaxFunc(path + f, $form.serialize(), 'status_' + f, function(data) {
			if (typeof callback === 'function') callback(data);
		}, function(data) {
			if (data && data.needs_authorization && data.auth_url) {
				openQboAuthAndRetry(data.auth_url, function() {
					$form.trigger('submit');
				});
				return;
			}
			resetCaptcha();
			$btns.show();
			if (typeof callback2 === 'function') callback2(data);
		});
	});
}

function abortAjax(a) {
	if (a) {
		a.abort();
	}
}

/**
 * Open QBO OAuth in a popup; on success (postMessage or window close) run retryFn.
 * @param {string} authUrl - URL to open (qbo-oauth-start.php?store_id=...)
 * @param {function} retryFn - Called after user authorizes or closes the window
 */
function openQboAuthAndRetry(authUrl, retryFn) {
	var w = window.open(authUrl, 'qbo_oauth', 'width=600,height=700,scrollbars=yes');
	if (!w) {
		// Popup blocked: fallback to same window
		window.location.href = authUrl;
		return;
	}
	function onDone() {
		window.removeEventListener('message', onMessage);
		if (typeof retryFn === 'function') retryFn();
	}
	function onMessage(ev) {
		if (ev.data === 'qbo-auth-done') onDone();
	}
	window.addEventListener('message', onMessage);
	var checkClosed = setInterval(function() {
		if (w.closed) {
			clearInterval(checkClosed);
			onDone();
		}
	}, 500);
}

function postAjax(url, data, status, callback, callback_error) {
	postAjaxFunc('/ajax/' + url, data, status, callback, callback_error);
}

function postAjaxFunc(url, data, status, callback, callback_error) {
	if (typeof data === 'undefined') data = {};
	data['_r'] = Math.random();
	if (typeof status === 'undefined') status = 'status';
	showStatus(status, 'Just a moment... Processing request');
	data['_r'] = Math.random();
	$.ajax({
		url: url,
		dataType: 'json',
		type: 'POST',
		data: data,
		success: function(data, textStatus, XMLHttpRequest) {
			setTimeout(function() {
				if (data.swal && data.swal.length) {
					showStatus(status, data.response, (data.success)?'ok':'error', true);
					//swal(data.swal, data.response, (data.success)?'success':'error');
					Swal.fire({
						icon:  (data.success)?'success':'error',
						title: data.swal,
						html: data.response,
						//footer: '<a href>Why do I have this issue?</a>'
					});
				}
				else if (data.nabis) {
					nabis(data);
				}
				else if (data.response.length > 0) {
					showStatus(status, data.response, (data.success)?'ok':'error', true);
					if (data.redirect && data.redirect.length) {
						setTimeout(function() {
							if (data.redirect != '{refresh}') location.href = data.redirect;
							else location.reload();
						}, 1000);
					}
					if (data.dialog) {
						updateDialog2(data.dialog.url, data.dialog.title, data.dialog.a, data.dialog.c);
					}
				}
				else {
					hideStatus(status);
				}
				if (data.success && typeof callback === 'function') {
					callback(data);
				}
				else if (!data.success && typeof callback_error === 'function') {
					callback_error(data);
				}
			}, 0);
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			showStatus(status, 'We encountered an error while attempting to process your request: ' + errorThrown, 'error');
			if (typeof callback_error === 'function') {
				callback_error();
			}
		}
	});
}

var showStatus = function (id, msg, css, keepvisible) {
	if (id == 'null' || id.length == 0) return;
	var icon = '';
	if (!css) {
		css = 'info';
		icon = 'fa fa-circle-notch fa-spin';
	}
	switch (css) {
		case 'ok':
			css = 'success';
			icon = 'fa fa-check';
		break;
		case 'error':
			css = 'danger';
			icon = 'fa fa-exclamation-triangle';
		break;
	}

	if ($('#' + id).length) {
		$('#' + id).html('<div class="alert alert-' + css + '" role="alert">\
		<i class="' + icon + '"></i> <span>' + msg + '</span>\
		</div>').show();
		if ( css == 'ok' && !keepvisible) {
			setTimeout(function() { $('#' + id).fadeOut('slow'); }, 2000);
		}
	}
	else if (css != 'info') {
		/*
		$.toast({
			heading: ((css == 'success')?'Success':'Error!'),
			text: msg,
			position: 'top-right',
			loaderBg: ((css == 'success')?'#5ba035':'#bf441d'),
			icon: ((css == 'success')?'success':'error'),
			hideAfter: 3000,
			stack: 1
		});
		*/
	}
}

var hideStatus = function (id) {
	if (id.length) {
		$('#' + id).hide();
	}
}

function getChecked(n) {
	var arr = [];
	$('input[name="' + n + '"]:checked').each(function() {
		arr.push($(this).val());
	});
	return arr.toString();
}

function showAlert(title, content) {
  //showDialogContent(title, content);
  Swal.fire({
    icon: 'error',
    title: title,
    text: content,
    //footer: '<a href>Why do I have this issue?</a>'
  });
}

function closeDialogs() {
	$('.modal').modal('hide');
}

function showDialogContent(title, content) {
	showDialog(title, undefined, undefined, content);
}

function showDialog(title, url, args, f, hide_btns, path, save_text) {
	closeDialogs();
	if (typeof args === 'undefined') args = {};
	if (typeof path === 'undefined') path = '/modal';
	if (typeof hide_btns === 'undefined') hide_btns = false;
	$('#modal').modal({ show: true, backdrop: 'static', keyboard: false});
	//$('#modal').addClass('modal-xlg');
	$('#modal .modal-title').html(title);
	$('#modal .modal-footer').hide();
	$('#modal .status').hide();
	if (typeof save_text === 'undefined') {
		save_text = 'Save';
	}
	if (typeof url === 'undefined') {
		$('#modal .modal-body').html(f)
		initAssets();
	}
	else {
		args['_r'] = Math.random();
		$('#modal .modal-body').html('<div class="alert alert-info" role="alert"><i class="fa fa-spinner fa-spin"></i> <span>Please wait … Loading …</span></div>')
		$.post(path + '/' + url, args)
		.done(function(data) {
			$('#modal .modal-footer').show();
			$('#modal .modal-body').html(data); // + '<div class="form-group"><div id="status-' + url + '" class="status-' + url + '"></div></div>');
			$('#modal form .btn-submit').text(save_text); //title.replace('Edit ', 'Save '));
			$('#modal form').attr('id', 'f_' + url);
			if (path == '/modal') {
				$('#modal form').removeClass('f-tbl');
			}
			else {
				$('#modal form').addClass('f-tbl');
			}
			setTimeout(function() {
				$('#modal form input[type="text"]:first').not('.datepicker').focus();
			}, 1000);
			if ($('#modal form').length) {
        		$('#modal form .form-btns').show();
				bindForm(url, f, undefined, (path == '/modal')?'/ajax/':'/');
				if (hide_btns) {
					$('#modal .modal-footer').hide();
				}
				else {					
					$('#modal .modal-footer').show();
				}
			}
			var h = window.innerHeight - 200;
			if (h > 300) $('#modal .modal-body').css('max-height', h + 'px');
			setTimeout(function() {
				initAssets();
			}, 500);
		})
		.fail(function(xhr, status, error) {
			$('#modal .modal-body').html('<div class="alert alert-danger" role="alert"><i class="fe fe-exclamation"></i> Error while contacting server: ' + error + '</div>');
		});
	}
}

function typicalDialog($t) {
	var $title = ($t.data('title'))?$t.data('title'):$t.text();
	showDialog($title, $t.data('url'), {id: $t.data('id'), a: $t.data('a'), b: $t.data('b'), c: $t.data('c'), d: $t.data('d')}, function(data) {}, $t.data('hide-btns'), undefined, $t.data('save-text'));
}

function tableDialog($t) {
    showDialog($t.data('title'), $t.data('url'), {_a: 'modal', id: $t.data('id'), __a: $t.data('a'), __b: $t.data('b')}, function() {}, $t.data('hide-btns'), '');
}


function updateDialog2(url, title, a, c) {
	var path = '/modal';
  	var args = {a: a, c: c, '_r': Math.random()};
	$('#modal .modal-body').html('<div class="alert alert-info" role="alert"><i class="fa fa-spinner fa-spin"></i> <span>' + ($('#__lang-please-wait-loading').length ? $('#__lang-please-wait-loading').val() : 'Loading…') + '</span></div>');
	$('#modal').modal({ show: true, backdrop: 'static', keyboard: false });
	$.post(path + '/' + url, args)
	.done(function(data) {
		$('#modal form').attr('id', 'f_' + url);
		$('#modal .modal-title').html(title);
		$('#modal .modal-body').html(data);
		initAssets();
		bindForm(url);
		if (url === 'po-qbo-map-vendor') {
			var storeId = $('#modal #qbo_map_store_id').val();
			var $sel = $('#modal #qbo_vendor_id');
			if (storeId && $sel.length) {
				function loadQboVendors() {
					$.post('/ajax/qbo-vendors.php', { store_id: storeId }, function(res) {
						$('#modal #vendor_qbo_connect_hint').remove();
						if (res.needs_authorization && res.auth_url) {
							$sel.find('option').remove();
							$sel.append($('<option value="">— Connect to QuickBooks first —</option>'));
							$('#modal .modal-body').prepend(
								'<div id="vendor_qbo_connect_hint" class="alert alert-info">' +
								'<strong>Connect to QuickBooks</strong> — This store is not connected yet (or the connection expired). ' +
								'<button type="button" class="btn btn-primary btn-sm ml-2" id="modal_qbo_connect_btn">Connect to QuickBooks</button>' +
								'</div>'
							);
							$('#modal #modal_qbo_connect_btn').off('click').on('click', function() {
								if (typeof openQboAuthAndRetry === 'function') {
									openQboAuthAndRetry(res.auth_url, loadQboVendors);
								} else {
									var w = window.open(res.auth_url, 'qbo_oauth', 'width=600,height=700,scrollbars=yes');
									if (w) {
										var t = setInterval(function() { if (w.closed) { clearInterval(t); loadQboVendors(); } }, 500);
									} else {
										window.location.href = res.auth_url;
									}
								}
							});
							if (typeof $sel.select2 === 'function') {
								if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
								$sel.select2({ dropdownParent: $('#modal'), minimumResultsForSearch: 0, width: '100%' });
							}
							return;
						}
						if (res.needs_authorization && !res.auth_url) {
							$sel.find('option').remove();
							$sel.append($('<option value="">Set QBO_REDIRECT_URI in config, or connect from Vendor Mapping page</option>'));
							$('#modal .modal-body').prepend('<div id="vendor_qbo_connect_hint" class="alert alert-info">QuickBooks not connected. Set QBO_REDIRECT_URI in _config.php, or go to Vendor → QBO Mapping, select this store, and click Connect to QuickBooks.</div>');
							if (typeof $sel.select2 === 'function') {
								if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
								$sel.select2({ dropdownParent: $('#modal'), minimumResultsForSearch: 0, width: '100%' });
							}
							return;
						}
						$sel.find('option').remove();
						$sel.append($('<option value="">— Select QBO vendor —</option>'));
						if (res.success && res.vendors && res.vendors.length) {
							res.vendors.forEach(function(v) {
								$sel.append($('<option></option>').attr('value', v.id).text(v.DisplayName));
							});
						} else {
							$sel.append($('<option value="">' + (res.error || 'No vendors or error') + '</option>'));
						}
						if (typeof $sel.select2 === 'function') {
							if ($sel.hasClass('select2-hidden-accessible')) {
								$sel.select2('destroy');
							}
							$sel.select2({
								dropdownParent: $('#modal'),
								minimumResultsForSearch: 0,
								width: '100%'
							});
						}
					}, 'json');
				}
				loadQboVendors();
			}
		}
	})
  	.fail(function(xhr, status, error) {
    	$('#modal .modal-body').html('<div class="alert alert-danger" role="alert"><i class="fe fe-exclamation"></i> Error while contacting server: ' + error + '</div>');
  	});
}

function updateDialog($t) {
	var title = ($t.data('title'))?$t.data('title'):$t.text();
	var url = $t.data('url');
	var hide_btns = $t.data('hide-btns');
	var path = '/modal';
  	var args = {id: $t.data('id'), a: $t.data('a'), b: $t.data('b'), c: $t.data('c'), d: $t.data('d'), e: $t.data('e'), f: $t.data('f'), '_r': Math.random()};
	$('#modal .modal-body').html('<div class="alert alert-info" role="alert"><i class="fa fa-spinner fa-spin"></i> <span>' + $('#__lang-please-wait-loading').val() + '</span></div>');
	$.post(path + '/' + url, args)
	.done(function(data) {
		$('#modal form').attr('id', 'f_' + url);
		$('#modal .modal-title').html(title);
		$('#modal .modal-body').html(data);
		initAssets();
	})
  	.fail(function(xhr, status, error) {
    	$('#modal .modal-body').html('<div class="alert alert-danger" role="alert"><i class="fe fe-exclamation"></i> Error while contacting server: ' + error + '</div>');
  	});
}

function resetCaptcha() {
	if ($('#captcha').length) $('#captcha').attr('src', '/securimage/securimage_show.php?' + Math.random());
}

/*
function scrollTo($obj) {
	setTimeout(function() {
		var offset = $obj.offset().top;
		//offset -= $('#topnav').height();
		$([document.documentElement, document.body]).animate({scrollTop: offset}, 'fast');
	}, 100);
	return false;
}

function caseTab(t) {
  $('.btn-' + t).trigger('click');
  scrollTo($('.page-header'));
}
*/

function replaceAll(myString, oldChar, newChar, all) {
	if (typeof all == 'undefined') all = true;
	if (myString == "") return myString;
	var i = myString.indexOf(oldChar);
    while (i != -1) {
    	myString = myString.substring(0,i) + newChar + myString.substr(i + oldChar.length);
		if (!all) break;
		i = myString.indexOf(oldChar);
    }
    return myString;
}
	
function initUploads() {
	//var pace = '<div class="pace-demo" style="padding-bottom: 30px;"><div class="theme_bar_xs"><div class="pace_progress" data-progress-text="0%" data-progress="0" style="width:0%;">Uploading <span class="text-blink"></span> <span class="pace-percent">0%</span></div></div></div>';
	var pace = '<div class="progress"><div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">25%</div></div>';
	$('.fileupload').each(function() {
		var $f = $(this).closest('form');
		var $o = $(this).closest('.row');
		var $s = $o.find('.fileupload-progress');
		var $b = $o.find('.fileinput-button');
		var $r = $o.find('.btn-remove-img');
		var $p = $o.find('.upload-preview');
		var $ap = $o.find('.attachment-preview');
		var id = $(this).attr('id').replace('_fileupload', '');
		var notes = ($(this).hasClass('notes-attachment'))?1:0;
		var tbl = $o.find('.fileupload-tbl').val();
		var redirect = $b.data('redirect');
		if ($f.find($('#TableName')).length) {
			//tbl = $f.find($('#TableName')).val();
		}
		var folder_code = ($('#document_folder_code').length)?$('#document_folder_code').val():'';
		var doc_code = ($f.find('#document_code').length)?$f.find('#document_code').val():'';
		var multiple = $(this).hasClass('multiple');
		$(this).fileupload({
			add: function(e, data) {
				var uploadErrors = [];
				var acceptFileTypes = /^image\/(gif|jpe?g|png)$|^application\/(x-zip-compressed|csv|pdf|msword|(vnd\.(ms-|openxmlformats-).*))$|^text\/plain$|^text\/csv$/i;
				if (id == 'import') {
					acceptFileTypes = /^application\/(csv|(vnd\.(ms-|openxmlformats-).*))$|^text\/plain$|^text\/csv$/i;
				}
				console.log(data.originalFiles[0]['type']);
				if (data.originalFiles[0]['type'].length == 0 || (data.originalFiles[0]['type'].length && !acceptFileTypes.test(data.originalFiles[0]['type']))) {
						//uploadErrors.push('Not an accepted file type. Only ' + ((id != 'import')?'images and documents':'spreadsheets') + ' can be uploaded. You tried to upload type: ' + data.originalFiles[0]['type']);
				}
				if(data.originalFiles[0]['size'].length && data.originalFiles[0]['size'] > 5000000) {
						uploadErrors.push('Filesize is too big');
				}
				if (uploadErrors.length > 0) {
						$s.show().html('<div class="alert bg-danger alert-styled-left text-white p-3 mt-2">Error: ' + uploadErrors.join(" ") + '</div>');
				} else {
						data.submit();
				}
			},
			url: '/ajax/upload',
			formData: {tbl: tbl, id: id, folder_code: folder_code, doc_code: doc_code},
			dataType: 'json',
			done: function (e, data) {
				$.each(data.result.files, function (index, file) {
					if (file.error) {
						$b.show();
						$s.show().html('<div class="alert bg-danger alert-styled-left text-white p-3 mt-2">Error: ' + file.error + '</div>');
					}
					else {
						var el = '';
						if (file.type.indexOf('image') > -1) {
							el = '<a href="/media/' + tbl + '/' + file.name + '" target="_blank" class="nothing"><b><img src="/media/' + tbl + '/sm/' + file.name + '" width="20" /> ' + file.original_name + '</a>';
						}
						else if (file.type.indexOf('pdf') > -1) {
							el = '<a href="/media/' + tbl + '/' + file.name + '" target="_blank" class="nothing"><b><i class="fa fa-file-pdf"></i></b> ' + file.original_name + '</a>';
						}
						else if (file.type.indexOf('excel') > -1 || file.type.indexOf('csv') > -1) {
							el = '<a href="/media/' + tbl + '/' + file.name + '" target="_blank" class="nothing"><b><i class="fa fa-file-excel"></i></b> ' + file.original_name + '</a>';
						}
						else if (file.type.indexOf('document') > -1) {
							el = '<a href="/media/' + tbl + '/' + file.name + '" target="_blank" class="nothing"><b><i class="fa fa-file-word"></i></b> ' + file.original_name + '</a>';
						}
						else {
							el = '<a href="/media/' + tbl + '/' + file.name + '" target="_blank" class="nothing"><b><i class="fa fa-file"></i></b> ' + file.original_name + '</a>';
						}
						if (!multiple) {
							$remove = $('<a href="" class="btn btn-danger btn-labeled btn-labeled-right">Remove <b><i class="icon-x"></i></b></a>');
							if (tbl != 'document') {
								if (file.type.indexOf('image') > -1) {
									$p.html('<img src="/media/' + tbl + '/' + ((id == 'profile_image')?'sm/':'') + file.name + '" alt="' + file.name + '" />');
								}
								else if (file.type.indexOf('pdf') > -1) {
									$p.html('<a href="/media/' + tbl + '/' + file.name + '" target="_blank"><i class="icon-file-pdf"></i> ' + file.original_name + '</a>');
								}
								else if (file.type.indexOf('excel') > -1 || file.type.indexOf('csv') > -1) {
									$p.html('<a href="/media/' + tbl + '/' + file.name + '" target="_blank"><i class="icon-file-excel"></i> ' + file.original_name + '</a>');
								}
								else if (file.type.indexOf('document') > -1) {
									$p.html('<a href="/media/' + tbl + '/' + file.name + '" target="_blank"><i class="icon-file-word"></i> ' + file.original_name + '</a>');
								}
								else {
									$p.html('<a href="/media/' + tbl + '/' + file.name + '" target="_blank">' + file.original_name + '</a>');
								}
								$p.show();
								$ap.html(el);
								$f.find('#' + id).val(file.name).trigger('change');;
								$f.find('#' + id + '_data').val(JSON.stringify(file)).trigger('change');;
								$r.show();
								//if (notes) $p.show().html('<div class="alert bg-success alert-styled-left"></div>');
								$ap.append($remove);
							}
							else {
								//$('.request-files').append(file.html);
								//getRequestActivity();
								console.log(file)
							}
							$remove.on('click', function(e) {
								e.preventDefault();
								$b.show();
								$f.find('#' + id).val('').trigger('change');;
								$f.find('#' + id + '_data').val('').trigger('change');;
								$ap.html('');
							});
							if (id == 'import') {
								$('.import-preview').html('<div class="alert-phone-dailing" style="display:block;"><div class="alert bg-info alert-styled-left alert-styled-dailing">Please wait … Loading file <span class="text-blink"></span></div></div>');
								$.post('/ajax/import-preview.php', {fn: file.name, orig_fn: file.original_name}, function(data) {
									$('.import-preview').html(data);
									initAssets();
									bindForm('.f-import', 'import.php', '.import-settings', function(data) { $('.import-results').html(data.html); $('.btn-import').remove(); }, function(data) { $('.import-results').html(data.html); } );
								});
							}
							if (id == 'profile_media') {
								$('.profile-media-photos').append(file.html);
							}
							if (id == 'profile_image') {
								showModal('Crop Profile Image', 'cropper', {fn:file.name}, undefined, function(data) {
									$('#profile_image').val(data.filename);
									$p.html('<img src="/media/' + tbl + '/sm/' + data.filename + '" alt="' + data.filename + '" />');
									hideModal(2000);
								});
							}
							if ($('#media_gallery').length) {
								loadModalGallery($('#media_gallery').val());
							}
							if (typeof redirect !== 'undefined' && redirect.length) {
								location.href = redirect;
							}
						}
						else {
							$p.show();
							var _id = 'tmp_' + Math.round(Math.random() * 10000000000000000);
							$p.append('<div class="media-item"><div class="row"><div class="col-sm-11"><input type="hidden" class="' + id + '_media_item" value="' + file.name + '" /><textarea style="display:none;" id="' + _id + '" name="' + id + '_media_item_data[]">' + JSON.stringify(file) + '</textarea>' + el + '</div><div class="col-sm-1 text-right"><a href="" class="btn btn-danger btn-sm btn-remove-media-item ' + id + '"><i class="fa fa-times"></i></a></div></div></div>');
							//$f.find('#' + _id).val(JSON.stringify(file));
							if (id == 'coa_filenames') {
								setTimeout(function() { 
									updateCOA();
								}, 500);
							}
						}
						initAssets();
					}
				});
			},
			send: function (e, data) {
				$b.hide();
				$s.show().html(pace);
			},
			progressall: function (e, data) {
				var progress = parseInt(data.loaded / data.total * 100, 10);
				$b.hide();
				$s.find('.progress-bar').width(progress + '%');
				$s.find('.progress-bar').html(progress + '%');
				if (data.loaded == data.total) {
					if (!notes) $b.show();
					$s.hide();
					if (tbl == 'document') {
						$('.fileupload-progress').after()
						$('.upload-preview').html('<div class="badge badge-info">Upload complete ... reloading page</div>').show();
						setTimeout(function() { location.href='/docs/' + $('#document_folder_code').val(); }, 2000);
					}
				}
			},
			fail: function (e, data) {
				$b.show();
				$s.show().html('<div class="alert bg-danger alert-styled-left text-white p-3 mt-2">Error: ' + data.errorThrown + '</div>');
			}
		});
	});
}

function getAge(birth_month,birth_day,birth_year) {
	today_date = new Date();
	today_year = today_date.getFullYear();
	today_month = today_date.getMonth();
	today_day = today_date.getDate();
	age = today_year - birth_year;

	if ( today_month < (birth_month - 1)) {
		age--;
	}
	if (((birth_month - 1) == today_month) && (today_day < birth_day)) {
		age--;
	}
	return age;
}

function toggleCheckbox(cb, div) {	
  $('#' + cb + '_1').on('click', function(e) {
    $('.' + div).show();
  });
  $('#' + cb + '_0').on('click', function(e) {
    $('.' + div).hide();
  });
}


function loadModalGallery(id) {	
	$.post('/ajax/gallery.php', {id:id}, function(data) {
		$('.modal-body').html(data);	
		initAssets();
		$('.ckeditor-insert').on('click', function(e) {
			e.preventDefault();
			var $o = $(this);
			var t = '<img src="/media/file/' + $o.attr('rel') + '" alt="" />';
			var id = $(this).attr('id').replace('ckeditor_insert_', '');
			CKEDITOR.instances[id].insertHtml(t);
		});
	})
}

/*
function initTables() {
	$('.datatable').DataTable({
		responsive: true,
		stateSave: true,
		processing: true,
		ajax: {
			url: '/' + $('#page_name').val(),
			type: 'post',
			data: { _a:'list'}
		},
		language: {
			searchPlaceholder: 'Quick Search...',
			sSearch: '',
			lengthMenu: '_MENU_ items/page',
			paginate: { 'first': 'First', 'last': 'Last', 'next': '→', 'previous': '←' },
			processing: '<i class="fa fa-spin fa-spinner"></i> Just a sec … updating records',
			loadingRecords: '<i class="fa fa-spin fa-spinner"></i> Just a sec … loading records'
		}
	});

	$('.dataTables_length select').select2({ minimumResultsForSearch: Infinity });
}
*/