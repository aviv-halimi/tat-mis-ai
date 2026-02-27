/**
 * Push-test modal handler: always loaded with cache-bust when modal opens.
 * Reads window.ddQboPushTestBrandId (set by functions.js) and binds "Push store 1 only".
 */
(function() {
	function log(msg) {
		if (typeof ddReportQboLog === 'function') ddReportQboLog(msg);
		if (typeof console !== 'undefined' && console.log) console.log('[DD-QBO]', msg);
	}
	function esc(s) {
		return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	function run() {
		var brandId = window.ddQboPushTestBrandId;
		var $btn = $('#modal #dd-qbo-push-test-push-one');
		if (!$btn.length) return;
		$btn.off('click.dd_qbo_push_test').on('click.dd_qbo_push_test', function() {
			var payload = { action: 'push', daily_discount_report_brand_id: brandId, single_store_id: 1 };
			var url = '/ajax/daily-discount-report-qbo-push.php';
			log('--- Push button clicked ---');
			log('REQUEST URL: POST ' + url);
			log('REQUEST PAYLOAD: ' + JSON.stringify(payload));
			$btn.prop('disabled', true);
			$.ajax({
				url: url,
				type: 'POST',
				data: payload,
				dataType: 'json'
			}).done(function(res) {
				$btn.prop('disabled', false);
				var $result = $('#modal #dd-qbo-push-test-result');
				$result.show();
				log('RESPONSE (full): ' + JSON.stringify(res));
				if (res && res.push_trace && res.push_trace.length) {
					res.push_trace.forEach(function(line) { log(line); });
				}
				if (res && res.log_saved === false) {
					log('WARNING: Log save verify failed for brand_id=' + (res.daily_discount_report_brand_id || brandId));
				}
				var brandIdForLog = (res && res.daily_discount_report_brand_id) ? res.daily_discount_report_brand_id : brandId;
				var html = '';
				if (res && res.success && res.log && res.log.stores && res.log.stores.length) {
					var s = res.log.stores[0];
					if (s.success) {
						html = '<span class="text-success">Store 1 pushed successfully. Vendor Credit ID: ' + (s.qbo_vendor_credit_id || '—') + (s.attach_error ? ' (attach: ' + s.attach_error + ')' : '') + '</span>' +
							' <a href="#" class="dd-view-push-log ml-2" data-daily-discount-report-brand-id="' + brandIdForLog + '">View detailed log</a>';
					} else {
						html = '<span class="text-danger">Failed: ' + (s.error || 'Unknown') + '</span>' +
							' <a href="#" class="dd-view-push-log ml-2" data-daily-discount-report-brand-id="' + brandIdForLog + '">View detailed log</a>';
					}
				} else {
					html = '<span class="text-danger">' + (res && res.response ? res.response : 'Push failed.') + '</span>' +
						(res && res.daily_discount_report_brand_id ? ' <a href="#" class="dd-view-push-log ml-2" data-daily-discount-report-brand-id="' + brandIdForLog + '">View detailed log</a>' : '');
				}
				if (res && (res.qbo_sdk_request || res.qbo_sdk_response)) {
					html += '<div class="mt-3 border-top pt-2 small"><strong>QBO SDK — request (sent to QuickBooks):</strong><pre class="bg-light p-2 mb-1 mt-1 small" style="white-space:pre-wrap;word-break:break-all;">' + (res.qbo_sdk_request ? esc(res.qbo_sdk_request) : '—') + '</pre>';
					html += '<strong>QBO SDK — response (from QuickBooks):</strong><pre class="bg-light p-2 mb-0 mt-1 small" style="white-space:pre-wrap;word-break:break-all;">' + (res.qbo_sdk_response ? esc(res.qbo_sdk_response) : '—') + '</pre></div>';
				}
				$result.html(html);
				log('Push result: success=' + (res && res.success) + ' brand_id=' + brandIdForLog + ' log_saved=' + (res && res.log_saved));
			}).fail(function(xhr, status, errMsg) {
				$btn.prop('disabled', false);
				var respText = (xhr && xhr.responseText) ? xhr.responseText.substring(0, 500) : '';
				log('REQUEST FAILED: status=' + status + ' HTTP ' + (xhr && xhr.status ? xhr.status : '?') + ' error=' + (errMsg || ''));
				log('RESPONSE BODY (first 500 chars): ' + respText);
				$('#modal #dd-qbo-push-test-result').show().html('<span class="text-danger">Request failed' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '</span>');
			});
		});
	}
	var $ = window.jQuery;
	if ($ && $.fn && $.fn.ready) {
		$(function() { run(); });
	} else {
		run();
	}
})();
