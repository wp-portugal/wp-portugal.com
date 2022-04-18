 (function ($) {
	var wp_optimize = window.wp_optimize || {};
	var send_command = wp_optimize.send_command;
	var refresh_frequency = wpoptimize.refresh_frequency || 30000;

	if (!send_command) {
		console.error('WP-Optimize Minify: wp_optimize.send_command is required.');
		return;
	}

	var minify = {};

	/**
	 * Initializing the minify feature and events
	 */
	minify.init = function () {
		
		var minify = this;
		this.enabled = false;

		$(document).on('wp-optimize/minify/toggle-status', function(e, params) {
			if (params.hasOwnProperty('enabled')) {
				$('[data-whichpage="wpo_minify"]').toggleClass('is-enabled', params.enabled)
				minify.enabled = params.enabled;
				if (minify.enabled) minify.getFiles();
			}
		});

		/**
		 * The standard handler for clearing the cache. Safe to use
		 */
		$('.purge_minify_cache').on('click', function(e) {
			e.preventDefault();
			$.blockUI();
			send_command('purge_minify_cache', null, function(response) {
				minify.updateFilesLists(response.files);
				minify.updateStats(response.files);
			}).always(function() {
				$.unblockUI();
			});
		});

		/**
		 * Removes the entire cache dir.
		 * Use with caution, as cached html may still reference those files.
		 */
		$('.purge_all_minify_cache').on('click', function() {
			$.blockUI();
			send_command('purge_all_minify_cache', null, function(response) {
				minify.updateFilesLists(response.files);
				minify.updateStats(response.files);
			}).always(function() {
				$.unblockUI();
			});
		});

		/**
		 * Forces minifiy to create a new cache, safe to use
		 */
		$('.minify_increment_cache').on('click', function() {
			$.blockUI();
			send_command('minify_increment_cache', null, function(response) {
				if (response.hasOwnProperty('files')) {
					minify.updateFilesLists(response.files);
					minify.updateStats(response.files);
				}
			}).always(function() {
				$.unblockUI();
			});
		});
		

		// ======= SLIDERS ========
		// Generic slider save
		$('#wp-optimize-nav-tab-wpo_minify-status-contents form :input, #wp-optimize-nav-tab-wpo_minify-js-contents form :input, #wp-optimize-nav-tab-wpo_minify-css-contents form :input, #wp-optimize-nav-tab-wpo_minify-font-contents form :input, #wp-optimize-nav-tab-wpo_minify-settings-contents form :input, #wp-optimize-nav-tab-wpo_minify-advanced-contents form :input').on('change', function() {
			$(this).closest('form').data('need_saving', true);
		});
		
		$('input[type=checkbox].wpo-save-setting').on('change', function(e) {
			var input = $(this),
			val = input.prop('checked'),
			name = input.prop('name'),
			data = {};
			data[name] = val;
			$.blockUI();
			send_command('save_minify_settings', data, function(response) {
				if (response.success) {
					input.trigger('wp-optimize/minify/saved_setting');
					if (response.hasOwnProperty('files')) {
						minify.updateFilesLists(response.files);
						minify.updateStats(response.files);
					}
				} else {
					console.log('Settings not saved', data)
				}
			}).always(function() {
				$.unblockUI();
			});
		});

		// Slider enable minify
		$('#wpo_min_enable_minify').on('wp-optimize/minify/saved_setting', function() {
			this.enabled = $(this).prop('checked');
			$(document).trigger('wp-optimize/minify/toggle-status', {enabled: this.enabled});
		});
		
		// Toggle wpo-feature-is-disabled class
		$('#wpo_min_enable_minify').on('wp-optimize/minify/saved_setting', function() {
			
			$(this).closest('.wpo_section').toggleClass('wpo-feature-is-disabled', !$(this).is(':checked'));
		});

		// Toggle wpo-feature-is-disabled class
		$('#wpo_min_enable_minify_css, #wpo_min_enable_minify_js')
			// Set value on status change
			.on('wp-optimize/minify/saved_setting', function() {
				$('#wp-optimize-nav-tab-wrapper__wpo_minify a[data-tab="' + $(this).data('tabname') + '"] span.disabled').toggleClass('hidden', $(this).is(':checked'));
			})
			// Set value on page load
			.each(function() {
				$('#wp-optimize-nav-tab-wrapper__wpo_minify a[data-tab="' + $(this).data('tabname') + '"] span.disabled').toggleClass('hidden', $(this).is(':checked'));
			});

		// slider enable Debug mode
		$('#wpo_min_enable_minify_debug').on('wp-optimize/minify/saved_setting', function() {
			// Refresh the page as it's needed to show the extra options
			$.blockUI({message: '<h1>'+wpoptimize.page_refresh+'</h1>'});
			location.href = $('#wp-optimize-nav-tab-wpo_minify-advanced').prop('href');
		});

		// Edit default exclusions
		$('#wpo_min_edit_default_exclutions').on('wp-optimize/minify/saved_setting', function() {
			// Show exclusions section
			$('.wpo-minify-default-exclusions').toggleClass('hidden', !$(this).prop('checked'));
		});

		// Save settings
		$('.wp-optimize-save-minify-settings').on('click', function(e) {
			e.preventDefault();
			var btn = $(this),
				spinner = btn.next(),
				success_icon = spinner.next(),
				$need_refresh_btn = null;
			
			spinner.show();
			$.blockUI();

			var data = {};

			var tabs = $('[data-whichpage="wpo_minify"] .wp-optimize-nav-tab-contents form');
			tabs.each(function() {
				var tab = $(this);
				if (true === tab.data('need_saving')) {
					data = Object.assign(data, gather_data(tab));
					tab.data('need_saving', false);
				}
			});

			/**
			 * Gather data from the given form
			 *
			 * @param {HTMLFormElement} form
			 *
			 * @returns {Array} Array of collected data from the form
			 */
			function gather_data(form) {
				var data = $(form).serializeArray().reduce(form_serialize_reduce_cb, {});
				$(form).find('input[type="checkbox"]').each(function (i) {
					var name = $(this).prop("name");
					if (name.includes('[]')) {
						if (!$(this).is(':checked')) return;
						var newName = name.replace('[]', '');
						if (!data[newName]) data[newName] = [];
						data[newName].push($(this).val());
					} else {
						data[name] = $(this).is(':checked') ? 'true' : 'false';
					}
				});
				return data;
			}
			
			/**
			 * Reduces the form elements array into an object
			 *
			 * @param {Object} collection An empty object
			 * @param {*} item form input element as array element
			 *
			 * @returns {Object} collection An object of form data
			 */
			function form_serialize_reduce_cb(collection, item) {
				// Ignore items containing [], which we expect to be returned as arrays
				if (item.name.includes('[]')) return collection;
				collection[item.name] = item.value;
				return collection;
			}
			send_command('save_minify_settings', data, function(response) {
				if (response.hasOwnProperty('error')) {
					// show error
					console.log(response.error);
					$('.wpo-error__enabling-cache').removeClass('wpo_hidden').find('p').text(response.error.message);
				} else {
					$('.wpo-error__enabling-cache').addClass('wpo_hidden').find('p').text('');
				}
				
				if (response.hasOwnProperty('files')) {
					minify.updateFilesLists(response.files);
					minify.updateStats(response.files);
				}

				spinner.hide();
				success_icon.show();
				setTimeout(function() {
					success_icon.fadeOut('slow', function() {
						success_icon.hide();
					});
				}, 5000);
			}).always(function() {
				$.unblockUI();
			});
		})

		// Dismiss information notice
		$('.wp-optimize-minify-status-information-notice').on('click', '.notice-dismiss', function(e) {
			e.preventDefault();
			send_command('hide_minify_notice');
		});

		// Show logs
		$('#wpo_min_jsprocessed, #wpo_min_cssprocessed').on('click', '.log', function(e) {
			e.preventDefault();
			$(this).nextAll('.wpo_min_log').slideToggle('fast');
		});

		// Handle js excludes
		$('#wpo_min_jsprocessed').on('click', '.exclude', function(e) {
			e.preventDefault();
			var el = $(this);
			var excluded_file = get_excluded_file(el);
			add_excluded_js_file(excluded_file);
			tab_need_saving('js');
			highlight_excluded_item(el);
		});

		// Handle css excludes
		$('#wpo_min_cssprocessed').on('click', '.exclude', function(e) {
			e.preventDefault();
			var el = $(this);
			var excluded_file = get_excluded_file(el);
			add_excluded_css_file(excluded_file);
			tab_need_saving('css');
			highlight_excluded_item(el);
		});

		/**
		 * Get excluded file url
		 *
		 * @param {HTMLElement} el
		 *
		 * @return {string}
		 */
		function get_excluded_file(el) {
			return el.data('url');
		}

		/**
		 * Exclude js file
		 *
		 * @param {string} excluded_file File url
		 */
		function add_excluded_js_file(excluded_file) {
			var $js_textarea = $('#exclude_js');
			var list_of_excluded_files = $js_textarea.val();
			list_of_excluded_files += excluded_file + '\n';
			$js_textarea.val(list_of_excluded_files);
		}

		/**
		 * Exclude css file
		 *
		 * @param {string} excluded_file File url
		 */
		function add_excluded_css_file(excluded_file) {
			var $css_textarea = $('#exclude_css');
			var list_of_excluded_files = $css_textarea.val();
			list_of_excluded_files += excluded_file + '\n';
			$css_textarea.val(list_of_excluded_files);
		}

		// Handle defer
		$('#wpo_min_jsprocessed').on('click', '.defer', function(e) {
			e.preventDefault();
			add_deferred_file($(this));
		});

		// Handle async loading
		$('#wpo_min_cssprocessed').on('click', '.async', function(e) {
			e.preventDefault();
			add_async_file($(this));
		});

		/**
		 * Add deferred file
		 *
		 * @param {HTMLElement} el target element
		 */
		function add_deferred_file(el) {
			var deferred_file = el.data('url');
			var $async_js_textarea = $('#async_js');
			var list_of_deferred_files = $async_js_textarea.val();
			list_of_deferred_files += deferred_file + '\n';
			$async_js_textarea.val(list_of_deferred_files);
			tab_need_saving('js');
			highlight_excluded_item(el);
		}

		/**
		 * Add asynchronously loading file
		 *
		 * @param {HTMLElement} el target element
		 */
		function add_async_file(el) {
			var async_file = el.data('url');
			var $async_css_textarea = $('#async_css');
			var list_of_async_files = $async_css_textarea.val();
			list_of_async_files += async_file + '\n';
			$async_css_textarea.val(list_of_async_files);
			tab_need_saving('css');
			highlight_excluded_item(el);
		}
		
		/**
		 *
		 * @param {string} tab_name Name of the tab that need saving
		 */
		function tab_need_saving(tab_name) {
			$('#wp-optimize-nav-tab-wpo_minify-' + tab_name + '-contents form').data('need_saving', true);
		}

		/**
		 * Update UI after excluding the file
		 *
		 * @param {HTMLElement} el Target element
		 */
		function highlight_excluded_item(el) {
			el.closest('.wpo_min_log').prev().removeClass('hidden').addClass('updated').slideDown();
			el.text(wpoptimize.added_to_list);
			el.removeClass('exclude');
			el.parent().addClass('disable-list-item');
			el.replaceWith($('<span>' + el.text() + '</span>'));
		}

		$('#wp-optimize-minify-advanced').on('click', '.save-exclusions', function(e) {
			e.preventDefault();
			$('.wp-optimize-save-minify-settings').first().trigger('click');
		});

		// Set the initial `enabled` value
		this.enabled = $('#wpo_min_enable_minify').prop('checked');
		$(document).trigger('wp-optimize/minify/toggle-status', {enabled: this.enabled});
		
		// When loading the page and minify is disabled, make sure that the status tab is active.
		if (!this.enabled && !$('#wp-optimize-nav-tab-wrapper__wpo_minify a[data-tab="status"]').is('.nav-tab-active')) {
			$('#wp-optimize-nav-tab-wrapper__wpo_minify a[data-tab="status"]').trigger('click');
		}

		// Enable / disable defer_jquery
		function check_defer_status( e ) {
			$('input[name="enable_defer_js"]').each(function(index, element) {
				$(element).closest('fieldset').removeClass('selected').find('.defer-js-settings').slideUp('fast');
			});
			$('input[name="enable_defer_js"]:checked').closest('fieldset').addClass('selected').find('.defer-js-settings').slideDown('fast');
		}

		$('input[name="enable_defer_js"]').on('change', check_defer_status);
		
		check_defer_status();

		/**
		 * Minify Preloader functionality
		 */
		var run_minify_preload_btn = $('#wp_optimize_run_minify_preload'),
			minify_preload_status_el = $('#wp_optimize_preload_minify_status'),
			check_status_interval = null;

		run_minify_preload_btn.on('click', function() {
			var btn = $(this),
				is_running = btn.data('running'),
				status = minify_preload_status_el.text();

			btn.prop('disabled', true);

			if (is_running) {
				btn.data('running', false);
				clearInterval(check_status_interval);
				check_status_interval = null;
				send_command(
					'cancel_minify_preload',
					null,
					function(response) {
						if (response && response.hasOwnProperty('message')) {
							minify_preload_status_el.text(response.message);
						}
					}
				).always(function() {
						btn.val(wpoptimize.run_now);
						btn.prop('disabled', false);
				});
			} else {
				minify_preload_status_el.text(wpoptimize.starting_preload);
				btn.data('running', true);
				send_command(
					'run_minify_preload',
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

						minify_preload_status_el.text(status);
						btn.prop('disabled', false);
						btn.data('running', false);

						return;
					}

					minify_preload_status_el.text(wpoptimize.loading_urls);
					btn.val(wpoptimize.cancel);
					btn.prop('disabled', false);
					run_update_minify_preload_status();
				});
			}
		});

		/**
		 * If already running then update status
		 */
		if (run_minify_preload_btn.data('running')) {
			run_update_minify_preload_status();
		}

		/**
		 * Create interval action for update preloader status.
		 *
		 * @return void
		 */
		function run_update_minify_preload_status() {
			if (check_status_interval) return;

			check_status_interval = setInterval(function() {
				update_minify_preload_status();
			}, 5000);
		}

		/**
		 * Update minify preload status ajax action.
		 *
		 * @return void
		 */
		function update_minify_preload_status() {
			send_command('get_minify_preload_status', null, function(response) {
				if (response.done) {
					run_minify_preload_btn.val(wpoptimize.run_now);
					run_minify_preload_btn.data('running', false);
					clearInterval(check_status_interval);
					check_status_interval = null;
				} else {
					run_minify_preload_btn.val(wpoptimize.cancel);
					run_minify_preload_btn.data('running', true);
				}
				minify_preload_status_el.text(response.message);
				update_minify_size_information(response);
			});
		}

		/**
		 * Run update information about minify size.
		 *
		 * @return void
		 */
		function update_minify_size_information(response) {
			$('#wpo_min_cache_size').text(response.size);
			$('#wpo_min_cache_total_size').text(response.total_size);
		}
		return this;
	}

	/**
	 * Get the list of files generated by Minify and update the markup.
	 */
	minify.getFiles = function() {
		// Only run if the feature is enabled
		if (!this.enabled) return;

		var data = {
			stamp: new Date().getTime()
		};

		send_command('get_minify_cached_files', data, function(response) {

			minify.updateFilesLists(response);
			minify.updateStats(response);

		});

		if (refresh_frequency) setTimeout(minify.getFiles.bind(this), refresh_frequency);
	}

	minify.updateFilesLists = function(data) {
		// reset
		var wpominarr = [];

		// js
		if (data.js.length > 0) {
			$(data.js).each(function () {
				wpominarr.push(this.uid);
				if ($('#'+this.uid).length == 0) {
					$('#wpo_min_jsprocessed ul.processed').append('\
					<li id="'+this.uid+'">\
						<span class="filename"><a href="'+this.file_url+'" target="_blank">'+this.filename+'</a> ('+this.fsize+')</span>\
						<a href="#" class="log">' + wpoptimize.toggle_info + '</a>\
						<div class="hidden save_notice">\
							<p>' + wpoptimize.added_notice + '</p>\
							<p><button class="button button-primary save-exclusions">' + wpoptimize.save_notice + '</button></p>\
						</div>\
						<div class="hidden wpo_min_log">'+this.log+'</div>\
					</li>\
				');
				}
			});
		}

		$('#wpo_min_jsprocessed ul.processed .no-files-yet').toggle(!data.js.length);

		// css
		if (data.css.length > 0) {
			$(data.css).each(function () {
				wpominarr.push(this.uid);
				if ($('#'+this.uid).length == 0) {
					$('#wpo_min_cssprocessed ul.processed').append('\
					<li id="'+this.uid+'">\
						<span class="filename"><a href="'+this.file_url+'" target="_blank">'+this.filename+'</a> ('+this.fsize+')</span>\
						<a href="#" class="log">' + wpoptimize.toggle_info + '</a>\
						<div class="hidden save_notice">\
							<p>' + wpoptimize.added_to_list + '</p>\
							<p><button class="button button-primary save-exclusions">' + wpoptimize.save_notice + '</button></p>\
						</div>\
						<div class="hidden wpo_min_log">'+this.log+'</div>\
					</li>\
				');
				}
			});
		}

		$('#wpo_min_cssprocessed ul.processed .no-files-yet').toggle(!data.css.length);

		// Remove <li> if it's not in the files array
		$('#wpo_min_jsprocessed ul.processed > li, #wpo_min_cssprocessed ul.processed > li').each(function () {
			if (-1 == jQuery.inArray($(this).attr('id'), wpominarr)) {
				if (!$(this).is('.no-files-yet')) {
					$(this).remove();
				}
			}
		});
	};

	minify.updateStats = function(data) {
		if (data.cachesize.length > 0) {
			$("#wpo_min_cache_size").html(this.enabled ? data.cachesize : wpoptimize.no_minified_assets);
			$("#wpo_min_cache_total_size").html(this.enabled ? data.total_cache_size : wpoptimize.no_minified_assets);
			$("#wpo_min_cache_time").html(this.enabled ? data.cacheTime : '-');
			$("#wpo_min_cache_path").html(data.cachePath);
		}
	};

	wp_optimize.minify = minify;

})(jQuery);