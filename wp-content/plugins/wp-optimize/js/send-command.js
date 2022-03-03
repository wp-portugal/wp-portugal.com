var wp_optimize = window.wp_optimize || {};

/**
 * Send an action via admin-ajax.php.
 *
 * @param {string}   action     The action to send
 * @param {[type]}   data       Data to send
 * @param {Function} callback   Will be called with the results
 * @param {boolean}  json_parse JSON parse the results
 * @param {object}   options    Optional extra options; current properties supported are 'timeout' (in milliseconds)
 *
 * @return {JSON}
 */
wp_optimize.send_command = function (action, data, callback, json_parse, options) {

	json_parse = ('undefined' === typeof json_parse) ? true : json_parse;

	if (!data) data = {};
	// If the command doesn't have the property, default to true
	if (!data.hasOwnProperty('include_ui_elements')) {
		data.include_ui_elements = true;
	}

	var ajax_data = {
		action: 'wp_optimize_ajax',
		subaction: action,
		nonce: wp_optimize_send_command_data.nonce,
		data: data
	};

	var args = {
		type: 'post',
		data: ajax_data,
		success: function (response) {
			if (json_parse) {
				try {
					var resp = wpo_parse_json(response);
				} catch (e) {
					console.log(e);
					console.log(response);
					alert(wpoptimize.error_unexpected_response);
					return;
				}
				// If result == false and and error code is provided, show the error and return.
				if (!resp.result && resp.hasOwnProperty('error_code') && resp.error_code) {
					wp_optimize.notices.show_notice(resp.error_code, resp.error_message);
					return;
				}
				if ('function' === typeof callback) callback(resp);
			} else {
				if (!response.result && response.hasOwnProperty('error_code') && response.error_code) {
					wp_optimize.notices.show_notice(response.error_code, response.error_message);
					return;
				}
				if ('function' === typeof callback) callback(response);
			}
		}
	};

	// Eventually merge options
	if ('object' === typeof options) {
		if (options.hasOwnProperty('timeout')) { args.timeout = options.timeout; }
		if (options.hasOwnProperty('error') && 'function' === typeof options.error) { args.error = options.error; }
	}

	return jQuery.ajax(ajaxurl, args);
};


/**
 * JS notices
 */
wp_optimize.notices = {
	errors: [],
	show_notice: function(error_code, error_message) {
		// WPO main page
		if (jQuery('#wp-optimize-wrap').length) {
			if (!this.notice) this.add_notice();
			this.notice.show();
			if (!this.errors[error_code]) {
				this.errors[error_code] = jQuery('<p/>').html(error_message).appendTo(this.notice).data('error_code', error_code);
			}
		// Post edit page
		} else if (window.wp && wp.hasOwnProperty('data')) {
			wp.data.dispatch('core/notices').createNotice(
				'error',
				'WP-Optimize: ' + error_message,
				{
					isDismissible: true
				}
			);
		// Other locations
		} else {
			alert('WP-Optimize: ' + error_message);
		}
	},
	add_notice: function() {
		this.notice_container = jQuery('<div class="wpo-main-error-notice"></div>').prependTo('#wp-optimize-wrap');
		this.notice = jQuery('<div class="notice notice-error wpo-notice is-dismissible"><button type="button" class="notice-dismiss"><span class="screen-reader-text">'+commonL10n.dismiss+'</span></button></div>');
		this.notice.appendTo(this.notice_container);
		this.notice.on('click', '.notice-dismiss', function(e) {
			this.notice.hide().find('p').remove();
			this.errors = [];
		}.bind(this));
	}
};
