var WP_Optimize_Cache = function () {

	var $ = jQuery;
	var send_command = wp_optimize.send_command;

	var browser_cache_enable_btn = $('#wp_optimize_browser_cache_enable'),
		purge_cache_btn = $('#wp-optimize-purge-cache'),
		enable_page_caching_switch = $('#enable_page_caching'),
		page_cache_length_value_inp = $('#page_cache_length_value');

	/**
	 * Handle purge cache btn.
	 */
	purge_cache_btn.on('click', function() {
		var btn = $(this),
			spinner = btn.next(),
			success_icon = spinner.next();

		spinner.show();

		send_command('purge_page_cache', {}, function(response) {
			spinner.hide();
			success_icon.show();
			setTimeout(function() {
				success_icon.fadeOut('slow', function() {
					success_icon.hide();
				});
				run_update_cache_preload_status();
			}, 5000);
			update_cache_size_information(response);
		});
	});

	/**
	 * Trigger purge cache button click if wpo_purge_cache event fired.
	 */
	$('body').on('wpo_purge_cache', function() {
		purge_cache_btn.trigger('click');
	});

	/**
	 * Trigger click Browser cache button if user push Enter and form start submitting.
	 */
	browser_cache_enable_btn.closest('form').on(
		'submit',
		function(e) {
			e.preventDefault();
			browser_cache_enable_btn.trigger('click');
			return false;
		}
	);

	/**
	 * Disable or enable preload cache lifespan value
	 */
	page_cache_length_value_inp.on('change', function() {
		var value = parseInt(page_cache_length_value_inp.val(), 10);

		$('#preload_schedule_type option[value="wpo_use_cache_lifespan"]').prop('disabled', isNaN(value) || value <= 0);
	});

	/**
	 * Handle Enable Gzip compression button click.
	 */
	$('#wp_optimize_gzip_compression_enable').on('click', function() {
		var button = $(this),
			loader = button.next();

		loader.show();

		send_command('enable_gzip_compression', {enable: button.data('enable')}, function(response) {
			var gzip_status_message = $('#wpo_gzip_compression_status');
			if (response) {
				if (response.enabled) {
					button.text(wpoptimize.disable);
					button.data('enable', '0');
					gzip_status_message.removeClass('wpo-disabled').addClass('wpo-enabled');
				} else {
					button.text(wpoptimize.enable);
					button.data('enable', '1');
					gzip_status_message.addClass('wpo-disabled').removeClass('wpo-enabled');
				}

				if (response.message) {
					$('#wpo_gzip_compression_error_message').text(response.message).show();
				} else {
					$('#wpo_gzip_compression_error_message').hide();
				}

				if (response.output) {
					$('#wpo_gzip_compression_output').html(response.output).show();
				} else {
					$('#wpo_gzip_compression_output').hide();
				}

			} else {
				alert(wpoptimize.error_unexpected_response);
			}

			loader.hide();
		}).fail(function() {
			alert(wpoptimize.error_unexpected_response);
			loader.hide();
		});
	});

	/**
	 * Manually check gzip status
	 */
	$('.wpo-refresh-gzip-status').on('click', function(e) {
		e.preventDefault();
		$link = $(this);
		$link.addClass('loading');
		send_command('get_gzip_compression_status', null, function(response) {
			$link.removeClass('loading');
			var gzip_status_message = $('#wpo_gzip_compression_status');
			if (response.hasOwnProperty('status')) {
				if (response.status) {
					// gzip is enabled
					gzip_status_message.removeClass('wpo-disabled').addClass('wpo-enabled');
				} else {
					// gzip is not enabled
					gzip_status_message.addClass('wpo-disabled').removeClass('wpo-enabled');
				}
			} else if (response.hasOwnProperty('error')) {
				alert(response.error);
				console.log('Gzip status error code: ' + response.code);
				console.log('Gzip status error message: ' + response.message);
			}
		});
	});

	/**
	 * Handle Enable browser cache button click.
	 */
	browser_cache_enable_btn.on('click', function() {
		var browser_cache_expire_days_el = $('#wpo_browser_cache_expire_days'),
			browser_cache_expire_hours_el = $('#wpo_browser_cache_expire_hours'),
			browser_cache_expire_days = parseInt(browser_cache_expire_days_el.val(), 10),
			browser_cache_expire_hours = parseInt(browser_cache_expire_hours_el.val(), 10),
			button = $(this),
			loader = button.next();

		// check for invalid integer.
		if (isNaN(browser_cache_expire_days)) browser_cache_expire_days = 0;
		if (isNaN(browser_cache_expire_hours)) browser_cache_expire_hours = 0;

		if (browser_cache_expire_days < 0 || browser_cache_expire_hours < 0) {
			$('#wpo_browser_cache_error_message').text(wpoptimize.please_use_positive_integers).show();
			return false;
		} else if (browser_cache_expire_hours > 23) {
			$('#wpo_browser_cache_error_message').text(wpoptimize.please_use_valid_values).show();
			return false;
		} else {
			$('#wpo_browser_cache_error_message').hide();
		}

		// set parsed values into input fields.
		browser_cache_expire_days_el.val(browser_cache_expire_days);
		browser_cache_expire_hours_el.val(browser_cache_expire_hours);

		loader.show();

		send_command('enable_browser_cache', {browser_cache_expire_days: browser_cache_expire_days, browser_cache_expire_hours: browser_cache_expire_hours}, function(response) {
			var cache_status_message = $('#wpo_browser_cache_status');
			if (response) {
				if (response.enabled) {
					button.text(wpoptimize.update);
					cache_status_message.removeClass('wpo-disabled').addClass('wpo-enabled');
				} else {
					button.text(wpoptimize.enable);
					cache_status_message.addClass('wpo-disabled').removeClass('wpo-enabled');
				}

				if (response.message) {
					$('#wpo_browser_cache_message').text(response.message).show();
				} else {
					$('#wpo_browser_cache_message').hide();
				}

				if (response.error_message) {
					$('#wpo_browser_cache_error_message').text(response.error_message).show();
				} else {
					$('#wpo_browser_cache_error_message').hide();
				}

				if (response.output) {
					$('#wpo_browser_cache_output').html(response.output).show();
				} else {
					$('#wpo_browser_cache_output').hide();
				}

			} else {
				alert(wpoptimize.error_unexpected_response);
			}

			loader.hide();
		}).fail(function() {
			alert(wpoptimize.error_unexpected_response);
			loader.hide();
		});
	});

	/**
	 * Gather cache settings from forms and return it as an object.
	 *
	 * @return object
	 */
	function gather_cache_settings() {
		var settings = {};

		$('.cache-settings').each(function() {
			var el = $(this),
				name = el.attr('name');

			if (el.is('input[type="checkbox"]')) {
				settings[name] = el.is(':checked') ? 1 : 0;
			} else if (el.is('textarea')) {
				settings[name] = el.val().split("\n");
			} else {
				settings[name] = el.val();
			}
		});

		$('.cache-settings-array').each(function() {
			var el = $(this),
				name = el.attr('name');

			if (!settings.hasOwnProperty(name)) {
				settings[name] = [];
			}

			if (el.is('input[type="checkbox"]')) {
				if ('value' == el.data('saveas')) {
					if (el.is(':checked')) settings[name].push(el.val());
				} else {
					settings[name].push(el.is(':checked') ? 1 : 0);
				}
			} else if (el.is('textarea')) {
				settings[name].push(el.val().split("\n"));
			} else {
				settings[name].push(el.val());
			}
		});

		return settings;
	}

	/**
	 * Handle click on the save settings button for cache.
	 */
	$('#wp-optimize-save-cache-settings, #wp-optimize-save-cache-advanced-rules, #wp-optimize-save-cache-preload-settings').on('click', function() {
		var btn = $(this),
			spinner = btn.next(),
			success_icon = spinner.next();

		spinner.show();
		$.blockUI();

		send_command('save_cache_settings', { 'cache-settings': gather_cache_settings() }, function(response) {

			if (response.hasOwnProperty('js_trigger')) {
				$(document).trigger(response.js_trigger, response);
			}

			if (response.hasOwnProperty('error')) {
				// show error
				console.log(response.error);
				$('.wpo-error__enabling-cache').removeClass('wpo_hidden').find('p').text(response.error.message);
			} else {
				$('.wpo-error__enabling-cache').addClass('wpo_hidden').find('p').text('');
			}

			if (response.hasOwnProperty('warnings')) {
				// show error
				console.log(response.warnings);
				$('.wpo-warnings__enabling-cache').removeClass('wpo_hidden')
					.find('p').text(response.warnings_label);
				var ul = $('.wpo-warnings__enabling-cache').find('ul').html('');
				$.each(response.warnings, function(index, warning) {
					ul.append('<li>'+warning+'</li>');
				});
			} else {
				$('.wpo-warnings__enabling-cache').addClass('wpo_hidden').find('p').text('');
			}

			if (response.hasOwnProperty('advanced_cache_file_writing_error')) {
				$('#wpo_advanced_cache_output')
					.text(response.advanced_cache_file_content)
					.show();
			} else {
				$('#wpo_advanced_cache_output').hide();
			}

			// update the toggle state depending on response.enabled
			enable_page_caching_switch.prop('checked', response.enabled);
			// cache is activated
			if (enable_page_caching_switch.is(':checked')) {
				// show purge button
				$('.purge-cache').show();
				// enable preload button
				$('#wp_optimize_run_cache_preload').removeProp('disabled');
				// disable minify preload
				$('#wp_optimize_run_minify_preload').prop('disabled', true);
				$('#minify-preload').show();
			} else {
				// hide purge button
				$('.purge-cache').hide();
				// disable preload button
				$('#wp_optimize_run_cache_preload').prop('disabled', true);
				// enable minify preload
				$('#wp_optimize_run_minify_preload').prop('disabled', false);
				$('#minify-preload').hide();
			}

			if (response.result) {
				// If Result is true, show the success icon.
				success_icon.show();
				setTimeout(function() {
					success_icon.fadeOut('slow', function() {
						success_icon.hide();
					});
				}, 5000);
			} else {
				var tab_id = $('.wp-optimize-nav-tab-contents .notice:visible').closest('.wp-optimize-nav-tab-contents').attr('id'),
				tab_name = 'cache';

				if (/wpo_cache-(.+)-contents/.test(tab_id)) {
					var match = /wpo_cache-(.+)-contents/.exec(tab_id);
					tab_name = match[1];
				}

				// Navigate to the tab where the notice is shown
				$('.wpo-page.active .nav-tab-wrapper a[data-tab="'+tab_name+'"]').trigger('click');
				// If it's false, scroll to the top where the error is displayed.
				var offset = $('.wpo-page.active').offset();
				window.scroll(0, offset.top - 20);
			}
		}).always(function() {
			$.unblockUI();
			spinner.hide();
		});
	});

	/**
	 * Toggle page cache
	 */
	enable_page_caching_switch.on('change', function() {
		// hide errors
		$('.wpo-error__enabling-cache').addClass('wpo_hidden');
		$('.wpo-warnings__enabling-cache').addClass('wpo_hidden');
		$('#wpo_advanced_cache_output').hide();
		// Trigger the save action
		$('#wp-optimize-save-cache-settings').trigger('click');
	});

	/**
	 * Cache Preloader functionality
	 */

	var run_cache_preload_btn = $('#wp_optimize_run_cache_preload'),
		cache_preload_status_el = $('#wp_optimize_preload_cache_status'),
		check_status_interval = null,
		enable_schedule_preloading = $('#enable_schedule_preload'),
		preloader_schedule_type_select = $('#preload_schedule_type');

	enable_schedule_preloading.on('change', function() {
		if (enable_schedule_preloading.prop('checked')) {
			preloader_schedule_type_select.prop('disabled', false);
		} else {
			preloader_schedule_type_select.prop('disabled', true);
		}
	});

	enable_schedule_preloading.trigger('change');

	run_cache_preload_btn.on('click', function() {
		var btn = $(this),
			is_running = btn.data('running'),
			status = cache_preload_status_el.text();

		btn.prop('disabled', true);

		if (is_running) {
			btn.data('running', false);
			clearInterval(check_status_interval);
			check_status_interval = null;
			send_command(
				'cancel_cache_preload',
				null,
				function(response) {
					if (response && response.hasOwnProperty('message')) {
						cache_preload_status_el.text(response.message);
					}
				}
			).always(function() {
					btn.val(wpoptimize.run_now);
					btn.prop('disabled', false);
			});
		} else {
			cache_preload_status_el.text(wpoptimize.starting_preload);

			btn.data('running', true);
			send_command(
				'run_cache_preload',
				null,
				null,
				true,
				{
					timeout: 3000 // set a timeout in case the server doesn't support our close browser connection function.
				}
			).always(function(response) {
					try {
						var resp = wpo_parse_json(response);
					} catch (e) {
					}

					if (resp && resp.error) {

						var error_text = wpoptimize.error_unexpected_response;

						if (typeof resp.error != 'function') {
							error_text = resp.error;
						} else if (resp.status) {
							error_text = resp.status + ': ' + resp.statusText;
						}

						alert(error_text);

						cache_preload_status_el.text(status);
						btn.prop('disabled', false);
						btn.data('running', false);

						return;
					}

					cache_preload_status_el.text(wpoptimize.loading_urls);
					btn.val(wpoptimize.cancel);
					btn.prop('disabled', false);
					run_update_cache_preload_status();
				});
		}
	});

	/**
	 * If already running then update status
	 */
	if (run_cache_preload_btn.data('running')) {
		run_update_cache_preload_status();
	}

	/**
	 * Create interval action for update preloader status.
	 *
	 * @return void
	 */
	function run_update_cache_preload_status() {
		if (check_status_interval) return;

		check_status_interval = setInterval(function() {
			update_cache_preload_status();
		}, 5000);
	}

	/**
	 * Update cache preload status ajax action.
	 *
	 * @return void
	 */
	function update_cache_preload_status() {
		send_command('get_cache_preload_status', null, function(response) {
			if (response.done) {
				run_cache_preload_btn.val(wpoptimize.run_now);
				run_cache_preload_btn.data('running', false);
				clearInterval(check_status_interval);
				check_status_interval = null;
			} else {
				run_cache_preload_btn.val(wpoptimize.cancel);
				run_cache_preload_btn.data('running', true);
			}
			cache_preload_status_el.text(response.message);
			update_cache_size_information(response);
		});
	}

	/**
	 * Run update information about cache size.
	 *
	 * @return void
	 */
	function update_cache_size_information(response) {
		$('#wpo_current_cache_size_information').text(wpoptimize.current_cache_size + ' ' + response.size);
		$('#wpo_current_cache_file_count').text(wpoptimize.number_of_files + ' ' + response.file_count);
	}
};
