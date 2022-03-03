jQuery(function ($) {
	WP_Optimize = WP_Optimize();
	if ('undefined' != typeof WP_Optimize_Cache) WP_Optimize_Cache = WP_Optimize_Cache();
	if ('undefined' != typeof wp_optimize.minify) WP_Optimize_Minify = wp_optimize.minify.init();
});

(function($) {
	/*
	 * Form errors store
	 */
	var errors = [];
	$.fn.form_errors = function() {
		return this;
	}
	$.fn.form_errors.add = function(type, message) {
		if (false !== this.has_error((type))) return;
		errors.push({type: type, message: message});
	};
	$.fn.form_errors.remove = function(type) {
		var found_error = this.has_error(type);
		if (found_error !== false) {
			errors.splice(found_error, 1);
		}
	};
	$.fn.form_errors.has_error = function(type) {
		var has_error = false;
		$.each(errors, function(index, error) {
			console.log(index, error)
			if (type == error.type) {
				has_error = index;
			}
		}.bind(this));
		return has_error;
	};
	$.fn.form_errors.has_errors = function() {
		return errors.length > 0;
	}
})(jQuery);

/**
 * Main WP_Optimize - Function for sending communications.
 */
var WP_Optimize = function () {
	var $ = jQuery;
	var debug_level = 0;
	var queue = new Updraft_Queue();
	var send_command = wp_optimize.send_command;
	var optimization_force = false;
	var optimization_logged_warnings = false;
	var force_single_table_optimization = false;

	/*
	 * Enable select all checkbox for optimizations list.
	 */
	define_select_all_checkbox($('#select_all_optimizations'), $('#optimizations_list .optimization_checkbox'));
	
	/**
	 * Either display normally, or grey-out, the scheduling options, depending on whether any schedule has been selected.
	 *
	 * @return {string}
	 */
	function enable_or_disable_schedule_options() {
		if ($('#enable-schedule').length) {
			var schedule_enabled = $('#enable-schedule').is(':checked');
			if (schedule_enabled) {
				$('#wp-optimize-auto-options').css('opacity', '1');
			} else {
				$('#wp-optimize-auto-options').css('opacity', '0.5')
			}
		}
	}

	enable_or_disable_schedule_options();

	$('#enable-schedule').on('change', function () {
		enable_or_disable_schedule_options();
	});

	// table sorter library.
	// This calls the tablesorter library in order to sort the table information correctly.
	// There is a fix below on line 172 to apply applyWidgets on load to avoid diplay hidden for tabs.
	// add parser through the tablesorter addParser method
	$.tablesorter.addParser({
		// set a unique id
		id: 'sizes',
		is: function(s) {
			// return false so this parser is not auto detected
			return false;
		},
		format: function(cell_content, table, cell) {
			var val = $(cell).data('raw_value');
			if (!val) val = 0;
			return parseInt(val);
		},
		// set type, either numeric or text
		type: 'numeric'
	});

	/*
	 * Triggered when the database tabs are loaded.
	 */

	$(document).on('wpo_database_tabs_loaded', function() {
		/*
		 * Setup table events and tablesorter
		 */

		var table_list_filter = $('#wpoptimize_table_list_filter'),
		table_list = $('#wpoptimize_table_list'),
		table_footer_line = $('#wpoptimize_table_list tbody:last'),
		tables_not_found = $('#wpoptimize_table_list_tables_not_found');

		table_list.tablesorter({
			theme: 'default',
			widgets: ['zebra', 'rows', 'filter'],
			cssInfoBlock: "tablesorter-no-sort",
			// This option is to specify with colums will be disabled for sorting
			headers: {
				2: {sorter: 'digit'},
				3: {sorter: 'sizes'},
				4: {sorter: 'sizes'},
				// For Column Action
				7: {sorter: false }
			},
			widgetOptions: {
				// filter_anyMatch replaced! Instead use the filter_external option
				// Set to use a jQuery selector (or jQuery object) pointing to the
				// external filter (column specific or any match)
				filter_external: table_list_filter,
				// add a default type search to the second table column
				filter_defaultFilter: { 2 : '~{query}' }
			}
		});

		/*
		 * After tables filtered check if we need show table footer and No tables message.
		 */
		table_list.on('filterEnd', function() {
			var search_value = $.trim(table_list_filter.val());
	
			if ('' == search_value) {
				table_footer_line.show();
			} else {
				table_footer_line.hide();
			}
	
			if (0 == $('#the-list tr:visible', table_list).length) {
				tables_not_found.show();
			} else {
				tables_not_found.hide();
			}
		});

		/*
		 * Setup force optimization checkboxes and associated events
		 */

		var optimization_force_checkbox = $('#innodb_force_optimize');
		var optimization_row = optimization_force_checkbox.closest('tr');
		var single_table_optimization_force = $('#innodb_force_optimize_single');

		optimization_force_checkbox.on('change', function() {
			$('button, input[type="checkbox"]', optimization_row).each(function() {
				optimization_force = optimization_force_checkbox.is(':checked');
				var btn = $(this);
				if (btn.data('disabled')) {
					if (optimization_force) {
						btn.prop('disabled', false);
					} else {
						btn.prop('disabled', true);
					}
				}
			});
		});

		// Handle force optimization checkbox on table list tab.
		single_table_optimization_force.on('change', function() {
			force_single_table_optimization = $(this).is(':checked');
			update_single_table_optimization_buttons(force_single_table_optimization);
		});

		// Set initial value
		force_single_table_optimization = single_table_optimization_force.is(':checked');
		optimization_force = optimization_force_checkbox.is(':checked');

		// Update single table optimization buttons state on load.
		update_single_table_optimization_buttons(force_single_table_optimization);

	});

	/**
	 * Temporarily show a dashboard notice, and then remove it. The HTML will be prepended to the .wrap.wp-optimize-wrap element.
	 *
	 * @param {String} html_contents HTML to display.
	 * @param {String} where CSS selector of where to prepend the HTML to.
	 * @param {Number} [delay=15] The number of seconds to wait before removing the message.
	 *
	 * @return {string}
	 */
	function temporarily_display_notice(html_contents, where, delay) {
		where = ('undefined' === typeof where) ? '#wp-optimize-wrap' : where;
		delay = ('undefined' === typeof delay) ? 15 : delay;
		$(html_contents).hide().prependTo(where).slideDown('fast').delay(delay * 1000).slideUp('fast', function () {
			$(this).remove();
		});
	}
	
	/**
	 * Send a request to disable or enable comments or trackbacks
	 *
	 * @param {string}  type - either "comments" or "trackbacks"
	 * @param {boolean} enable - whether to enable, or, to disable
	 *
	 * @return {string}
	 */
	function enable_or_disable_feature(type, enable) {
		var data = {
			type: type,
			enable: enable ? 1 : 0
		};

		$('#' + type + '_spinner').show();
		
		send_command('enable_or_disable_feature', data, function (resp) {
			$('#' + type + '_spinner').hide();
			
			if (resp && resp.hasOwnProperty('output')) {
				for (var i = 0, len = resp.output.length; i < len; i++) {
					var new_html = '<div class="updated"><p>' + resp.output[i] + '</p></div>';
					temporarily_display_notice(new_html, '#' + type + '_notice');
				}
			}
			if (resp && resp.hasOwnProperty('messages')) {
					$('#' + type + '_actionmsg').html(resp.messages.join(' - '));
			}
		});
	}
	
	$('#wp-optimize-disable-enable-trackbacks-enable').on('click', function () {
		enable_or_disable_feature('trackbacks', true);
	});
	
	$('#wp-optimize-disable-enable-trackbacks-disable').on('click', function () {
		enable_or_disable_feature('trackbacks', false);
	});
	
	$('#wp-optimize-disable-enable-comments-enable').on('click', function () {
		enable_or_disable_feature('comments', true);
	});
	
	$('#wp-optimize-disable-enable-comments-disable').on('click', function () {
		enable_or_disable_feature('comments', false);
	});

	// Main menu
	$('.wpo-pages-menu').on('click', 'a', function(e) {
		e.preventDefault();
		if (!$(this).is('.active')) {
			$('.wpo-pages-menu a.active').removeClass('active');
			$('.wpo-page.active').removeClass('active');
			$(this).addClass('active');
			$('.wpo-page[data-whichpage="' + $(this).data('menuslug') + '"]').addClass('active');
			window.scroll(0, 0);

			// Trigger a global event when changing page
			$('#wp-optimize-wrap').trigger('page-change', { page: $(this).data('menuslug') });
		}

		// Close the menu on mobile
		$('#wp-optimize-nav-page-menu').trigger('click');

	});

	$('#wp-optimize-wrap').on('page-change', function(e, params) {
		// When changing the page, trigger a click, in case the page was loaded somewhere else.
		if ('WP-Optimize' == params.page && $('#wp-optimize-nav-tab-WP-Optimize-optimize').is('.nav-tab-active')) {
			$('#wp-optimize-nav-tab-WP-Optimize-optimize').trigger('click');
		}
		// Trigger the global tab change events when changing page
		var active_tab = $('.wpo-page[data-whichpage='+params.page+']').find('.nav-tab-wrapper .nav-tab-active');
		$('#wp-optimize-wrap').trigger('tab-change', { page: params.page, tab: active_tab.data('tab') });
		$('#wp-optimize-wrap').trigger('tab-change/'+params.page+'/'+active_tab.data('tab'), { content: $('#' + active_tab.attr('id') + '-contents')});
	});

	// set a time out, as needs to be done once the rest is loaded
	setTimeout(function() {
		// Trigger page-change event  on load
		$('#wp-optimize-wrap').trigger('page-change', { page: $('.wpo-pages-menu a.active').data('menuslug') });
	}, 500);

	// Tabs menu
	$('.nav-tab-wrapper .nav-tab').on('click', function (e) {
		e.preventDefault();

		var clicked_tab_id = $(this).attr('id'),
			container = $(this).closest('.nav-tab-wrapper');

		if (!clicked_tab_id) { return; }

		toggle_mobile_menu(false);
		// Mobile menu TABS toggle
		if ($(this).is('[role="toggle-menu"]')) {
			toggle_mobile_menu(true);
			return;
		}

		container.find('.nav-tab:not(#wp-optimize-nav-tab-' + clicked_tab_id + ')').removeClass('nav-tab-active');
		
		$(this).addClass('nav-tab-active');
		$(this).closest('.wpo-page').find('.wp-optimize-nav-tab-contents').hide();
		$('#' + clicked_tab_id + '-contents').show();

		// Trigger global events on tab change
		$('#wp-optimize-wrap').trigger('tab-change', { page: $(this).data('page'), tab: $(this).data('tab') });
		$('#wp-optimize-wrap').trigger('tab-change/'+$(this).data('page')+'/'+$(this).data('tab'), { content: $('#' + clicked_tab_id + '-contents')});

	});

	// Mobile menu toggle
	$('#wp-optimize-nav-page-menu').on('click', function(e) {
		e.preventDefault();
		$(this).toggleClass('opened');
	});

	/*
	 * Setup a simple cross-pages/tabs navigation.
	 *
	 * Basic example: <button class="js--wpo-goto" data-page="wpo_minify" data-tab="status">Go to the minify page, status tab</button>
	 */
	$('#wp-optimize-wrap').on('click', '.js--wpo-goto', function(e) {
		e.preventDefault();
		// get the page and tab
		var page = $(this).data('page');
		var tab = $(this).data('tab');
		if (page) {
			// Trigger a page change
			$('.wpo-pages-menu a[data-menuslug="' + page + '"]').trigger('click');
		}
		if (tab) {
			// Change tab change
			$('.wpo-page.active .nav-tab-wrapper a[data-tab="' + tab + '"]').trigger('click');
		}
	});

	/**
	 * Toggle Mobile menu
	 *
	 * @param {bool} open
	 *
	 * @return {void}
	 */
	function toggle_mobile_menu(open) {
		if (open) {
			$('#wp-optimize-wrap').addClass('wpo-mobile-menu-opened');
		} else {
			$('#wp-optimize-wrap').removeClass('wpo-mobile-menu-opened');
		}
	}

	var database_tabs_loaded = false;
	// When showing the tables tab
	$('#wp-optimize-wrap').on('tab-change/WP-Optimize/tables', function(e) {
		get_database_tabs();
	});

	// When showing the tables tab
	$('#wp-optimize-wrap').on('tab-change/WP-Optimize/optimize', function(event, data) {
		get_database_tabs();
	});

	// When showing the settings tab
	$('#wp-optimize-wrap').on('tab-change/wpo_settings/settings', function(event, data) {
		// If the innodb_force_optimize--container is present, fetch the required data.
		// We load the database tabs together, as they need the same processing.
		if (data.content.find('.innodb_force_optimize--container').length) get_database_tabs();
	});

	/**
	 * Get the data for the database tabs. We get them at the same time, as they both need the same time consuming requests.
	 */
	function get_database_tabs() {
		if (database_tabs_loaded) return;
		var container = $('.wpo-page[data-whichpage=WP-Optimize]');
		var shade = container.find('.wpo_shade');
		shade.removeClass('hidden');
		send_command('get_database_tabs', {}, function(response) {
			// Set the satus to true, to prevent loading again.
			database_tabs_loaded = true;

			// Updtate the optimizations tab
			if (response.hasOwnProperty('optimizations')) {
				container.find('.wp-optimize-optimizations-table-placeholder').replaceWith(response.optimizations);
			}

			// Updtate the optimizations tables list
			update_tables_list(response);

			$(document).trigger('wpo_database_tabs_loaded');

		}).always(function() {
			shade.addClass('hidden');
		});
	}

	/**
	 * Gathers the settings from the settings tab and return in selected format.
	 *
	 * @param {string} output_format optional param 'object' or 'string'.
	 *
	 * @return (string) - serialized settings.
	 */
	function gather_settings(output_format) {
		var form_data = '',
			output_format = ('undefined' === typeof output_format) ? 'string' : output_format,
			$form_elements = $("#wp-optimize-database-settings form input[name!='action'], #wp-optimize-database-settings form select, #wp-optimize-database-settings form textarea, #wp-optimize-general-settings form input[name!='action'], #wp-optimize-general-settings form textarea, #wp-optimize-general-settings form select, #wp-optimize-nav-tab-contents-optimize input[type='checkbox'], .wp-optimize-nav-tab-contents input[name^='enable-auto-backup-']");

		if ('object' == output_format) {
			form_data = $form_elements.serializeJSON({useIntKeysAsArrayIndex: true});
		} else {
			// Excluding the unnecessary 'action' input avoids triggering a very mis-conceived mod_security rule seen on one user's site.
			form_data = $form_elements.serialize();

			// Include unchecked checkboxes. user filter to only include unchecked boxes.
			$.each($('#wp-optimize-database-settings form input[type=checkbox], #wp-optimize-general-settings form input[type=checkbox], .wp-optimize-nav-tab-contents input[name^="enable-auto-backup-"]')
					.filter(function (idx) {
						return $(this).prop('checked') == false
					}),
				function (idx, el) {
					// Attach matched element names to the form_data with chosen value.
					var empty_val = '0';
					form_data += '&' + $(el).attr('name') + '=' + empty_val;
				}
			);
		}

		return form_data;
	}

	/**
	 * Runs after all queued commands done and sends optimizations_done command
	 *
	 * @return {string}
	 */
	function process_done() {
		send_command('optimizations_done', {}, function () {});
		// Reset the warning flag
		optimization_logged_warnings = false;
	}
	
	/**
	 * Proceses the queue
	 *
	 * @return void
	 */
	function process_queue() {
		if (!queue.get_lock()) {
			if (debug_level > 0) {
				console.log("WP-Optimize: process_queue(): queue is currently locked - exiting");
			}
			return;
		}
		
		if (debug_level > 0) {
			console.log("WP-Optimize: process_queue(): got queue lock");
		}
		
		var id = queue.peek();
		var blog_id = 0;
		var sites_container = $("#wpo_all_sites");
		var active_site = sites_container.data('active_site');

		// Check to see if an object has been returned.
		if (typeof id == 'object') {
			data = id;
			id = id.optimization_id;
			blog_id = data.blog_id;
			
			if ("undefined" == typeof active_site) {
				sites_container.data('active_site', blog_id);
				do_site_ui_update(blog_id, 0);
			} else if (active_site != blog_id) {
				var prev_blog_id = active_site;
				sites_container.data('active_site', blog_id);
				do_site_ui_update(blog_id, prev_blog_id);
			}

		} else {
			data = {};
		}
		
		if ('undefined' === typeof id) {
			if (debug_level > 0) console.log("WP-Optimize: process_queue(): queue is apparently empty - exiting");
			queue.unlock();
			var prev_blog_id = sites_container.data('active_site');
			do_site_ui_update(0, prev_blog_id);
			process_done();
			return;
		}
		
		if (debug_level > 0) console.log("WP-Optimize: process_queue(): processing item: " + id);
			   
		queue.dequeue();

		$(document).trigger(['do_optimization_', id, '_start'].join(''));

		if ('optimizetables' === id) {
			// Display the table name being optimized
			$('#optimization_info_' + id).html(wpoptimize.optimizing_table + ' ' + data.optimization_table);

			// Set extra options for the request
			var timeout = parseInt(wpoptimize.table_optimization_timeout) ? parseInt(wpoptimize.table_optimization_timeout) : 120000;
			var request_options = {
				timeout: timeout,
				error: function(error, type) {
					optimization_logged_warnings = true;
					if ('timeout' === type) {
						console.warn('The request to optimize the table ' + data.optimization_table + ' timed out after ' + (timeout / 1000) + ' seconds');
					} else {
						console.warn('There was an error when running the optimization "' + id + '".', type, error, data);
					}
					if (queue.is_empty()) {
						var blog_id = sites_container.data('active_site');
						do_site_ui_update(blog_id, 0);
						$('#optimization_spinner_' + id).hide();
						$('#optimization_checkbox_' + id).show();
						$('.optimization_button_' + id).prop('disabled', false);
						$('#optimization_info_' + id).html(wpoptimize.optimization_complete + ' ' + wpoptimize.with_warnings);
					}

					setTimeout(function () {
						queue.unlock(); process_queue();
					}, 500);
				}
			}
		} else {
			var request_options = {};
		}

		send_command('do_optimization', { optimization_id: id, data: data }, function (response) {
			$('#optimization_spinner_' + id).hide();
			$('#optimization_checkbox_' + id).show();
			$('.optimization_button_' + id).prop('disabled', false);

			$(document).trigger(['do_optimization_', id, '_done'].join(''), response);

			if (response) {
				var total_output = '';
				for (var i = 0, len = response.errors.length; i < len; i++) {
					total_output += '<span class="error">' + response.errors[i] + '</span><br>';
				}
				for (var i = 0, len = response.messages.length; i < len; i++) {
					total_output += response.errors[i] + '<br>';
				}
				for (var i = 0, len = response.result.output.length; i < len; i++) {
					total_output += response.result.output[i] + '<br>';
				}
				$('#optimization_info_' + id).html(total_output);
				if (response.hasOwnProperty('status_box_contents')) {
					$('#wp_optimize_status_box').css('opacity', '1').find('.inside').html(response.status_box_contents);
				}
				if (response.hasOwnProperty('table_list')) {
					$('#wpoptimize_table_list tbody').html($(response.table_list).find('tbody').html());
				}
				if (response.hasOwnProperty('total_size')) {
					$('#optimize_current_db_size').html(response.total_size);
				}

				// Status check if optimizing tables.
				if (id == 'optimizetables' && data.optimization_table) {
					if (queue.is_empty()) {
						$('#optimization_spinner_' + id).hide();
						$('#optimization_checkbox_' + id).show();
						$('.optimization_button_' + id).prop('disabled', false);

						$('#optimization_info_' + id).html(wpoptimize.optimization_complete + (optimization_logged_warnings ? (' ' + wpoptimize.with_warnings) : ''));
					} else {
						$('#optimization_checkbox_' + id).hide();
						$('#optimization_spinner_' + id).show();
						$('.optimization_button_' + id).prop('disabled', true);
					}
				}

				// check if we need update unapproved comments count.
				if (response.result.meta && response.result.meta.hasOwnProperty('awaiting_mod')) {
					var awaiting_mod = response.result.meta.awaiting_mod;
					if (awaiting_mod > 0) {
						$('#adminmenu .awaiting-mod .pending-count').remove(awaiting_mod);
					} else {
						// if there is no unapproved comments then remove bullet.
						$('#adminmenu .awaiting-mod').remove();
					}
				}
			}
			setTimeout(function () {
				queue.unlock(); process_queue();
			}, 500);
		}, true, request_options);
	}
	
	/**
	 * Update UI of sites list based on which site is currently being optimizated
	 *
	 * @param {Number} blog_id - current site id being optimized
	 * @param {Number} prev_blog_id - previous site id
	 *
	 * @return void
	 */
	function do_site_ui_update(blog_id, prev_blog_id) {
		if (0 != prev_blog_id) {
			$("#site-" + prev_blog_id).show();
			$("#optimization_spinner_site-" + prev_blog_id).hide();
		}
		$("#site-" + blog_id).hide();
		$("#optimization_spinner_site-" + blog_id).show();
	}

	/**
	 * Runs a specified optimization, displaying the progress and results in the optimization's row
	 *
	 * @param {string} id - The optimization ID
	 *
	 * @return void
	 */
	function do_optimization(id) {
		var $optimization_row = $('#wp-optimize-nav-tab-contents-optimize .wp-optimize-settings-' + id);

		if (!$optimization_row) {
			console.log("do_optimization: row corresponding to this optimization (" + id + ") not found");
		}

		// check if there any additional inputs checkboxes.
		var additional_data = {},
			additional_data_length = 0;

		$('input[type="checkbox"]', $('#optimization_info_' + id)).each(function() {
			var checkbox = $(this);

			if (checkbox.is(':checked')) {
				additional_data[checkbox.attr('name')] = checkbox.val();
				additional_data_length++;
			}
		});

		// don't run optimization if optimization active.
		if (true == $('.optimization_button_' + id).prop('disabled')) return;

		var no_queue_actions_added = false;

		// Check if it is DB optimize.
		if ('optimizetables' == id) {
			var optimization_tables = $('#wpoptimize_table_list #the-list tr'),
				filter_by_blog_id = $('#wpo_sitelist_moreoptions').length > 0 && $('#wpo_sitelist_moreoptions').is(':visible'),
				selected_sites = [];
			
			// Get list of selected sites.
			if (filter_by_blog_id) {
				selected_sites = $('#wpo_sitelist_moreoptions input[type="checkbox"]:checked').map(function() {
					 return parseInt($(this).val());
					}).get();
			}
			
			no_queue_actions_added = true;

			// Check if there are any tables to be optimized.
			$(optimization_tables).each(function (index) {
				// Get information from each td.
				var $table_information = $(this).find('td');

				// Get table type information.
				var table_type = $(this).data('type');
				var table = $(this).data('tablename');
				var optimizable = $(this).data('optimizable');
				var blog_id = $(this).data('blog_id') ? parseInt($(this).data('blog_id')) : 1;
				var in_selected_sites = true;

				
				// Check if table is in the list of selected sites (multisite mode).
				if (filter_by_blog_id && -1 == selected_sites.indexOf(blog_id)) {
					in_selected_sites = false;
				}

				// Make sure the table isnt blank.
				if ('' != table && in_selected_sites) {
					// Check if table is optimizable or optimization forced by user.
					if (1 == parseInt(optimizable) || optimization_force) {
						var data = {
							optimization_id: id,
							blog_id: blog_id,
							optimization_table: table,
							optimization_table_type: table_type,
							optimization_force: optimization_force
						};
						
						queue.enqueue(data);

						no_queue_actions_added = false;
					}
				}
			});
		} else if (additional_data_length > 0) {
			// check if additional data passed for optimization.
			data = {
				optimization_id: id
			};

			for (var i in additional_data) {
				if (!additional_data.hasOwnProperty(i)) continue;
				data[i] = additional_data[i];
			}

			queue.enqueue(data);
		} else {
			queue.enqueue(id);
		}

		// if new actions was not added in tasks queue then we don't process queue.
		if (no_queue_actions_added) return;

		$('#optimization_checkbox_' + id).hide();
		$('#optimization_spinner_' + id).show();
		$('.optimization_button_' + id).prop('disabled', true);

		$('#optimization_info_' + id).html('...');

		process_queue();
	}


	/**
	 * Run action and save selected sites list options.
	 *
	 * @param {function} action - action to do after save.
	 *
	 * @return void
	 */
	function save_sites_list_and_do_action(action) {
		// if multisite mode then save sites list before action.
		if ($('#wpo_settings_sites_list').length) {
			// save wpo-sites settings.
			send_command('save_site_settings', {'wpo-sites': get_selected_sites_list()}, function () {
				// do action.
				if (action) action();
			});
		} else {
			// do action.
			if (action) action();
		}
	}

	/**
	 * Returns list of selected sites list.
	 *
	 * @return {Array}
	 */
	function get_selected_sites_list() {
		var wpo_sites = [];

		$('#wpo_settings_sites_list input[type="checkbox"]').each(function () {
			var checkbox = $(this);
			if (checkbox.is(':checked')) {
				wpo_sites.push(checkbox.attr('value'));
			}
		});

		return wpo_sites;
	}

	/*
	 * Run single optimization click.
	 */
	$('#wp-optimize-nav-tab-WP-Optimize-optimize-contents').on('click', 'button.wp-optimize-settings-optimization-run-button', function () {
		var optimization_id = $(this).closest('.wp-optimize-settings').data('optimization_id');
		if (!optimization_id) {
			console.log("Optimization ID corresponding to pressed button not found");
			return;
		}
		// if run button disabled then don't run this optimization.
		if (true == $('.optimization_button_' + optimization_id).prop('disabled')) return;
		// disable run button before save sites list.
		$('.optimization_button_' + optimization_id).prop('disabled', true);

		save_sites_list_and_do_action(function() {
			$('.optimization_button_' + optimization_id).prop('disabled', false);
			do_optimization(optimization_id);
		});
	});

	/*
	 * Run all optimizations click.
	 */
	$('#wp-optimize-nav-tab-WP-Optimize-optimize-contents').on('click', '#wp-optimize', function (e) {
		var run_btn = $(this);

		e.preventDefault();

		// disable run button to avoid double click.
		run_btn.prop('disabled', true);
		save_sites_list_and_do_action(function() {
			run_btn.prop('disabled', false);
			run_optimizations();
		});
	});

	/**
	 * Sent command to run selected optimizations and auto backup if selected
	 *
	 * @return void
	 */
	function run_optimizations() {
		var auto_backup = false;

		if ($('#enable-auto-backup').is(":checked")) {
			auto_backup = true;
		}

		// Save the click option.
		save_auto_backup_options();

		// Only run the backup if tick box is checked.
		if (auto_backup == true) {
			take_a_backup_with_updraftplus(run_optimization);
		} else {
			// Run optimizations.
			run_optimization();
		}
	}

	/**
	 * Take a backup with UpdraftPlus if possible.
	 *
	 * @param {Function} callback
	 * @param {String}   file_entities
	 *
	 * @return void
	 */
	function take_a_backup_with_updraftplus(callback, file_entities) {
		// Set default for file_entities to empty string
		if ('undefined' == typeof file_entities) file_entities = '';
		var exclude_files = file_entities ? 0 : 1;

		if (typeof updraft_backupnow_inpage_go === 'function') {
			updraft_backupnow_inpage_go(function () {
				// Close the backup dialogue.
				$('#updraft-backupnow-inpage-modal').dialog('close');

				if (callback) callback();

			}, file_entities, 'autobackup', 0, exclude_files, 0, wpoptimize.automatic_backup_before_optimizations);
		} else {
			if (callback) callback();
		}
	}

	/**
	 * Save all auto backup options.
	 *
	 * @return void
	 */
	function save_auto_backup_options() {
		var options = gather_settings('object');
		options['auto_backup'] = $('#enable-auto-backup').is(":checked");

		send_command('save_auto_backup_option', options);
	}

	// Show/hide sites list for multi-site settings.
	var wpo_settings_sites_list = $('#wpo_settings_sites_list'),
		wpo_settings_sites_list_ul = wpo_settings_sites_list.find('ul').first(),
		wpo_settings_sites_list_items = $('input[type="checkbox"]', wpo_settings_sites_list_ul),
		wpo_settings_all_sites_checkbox = wpo_settings_sites_list.find('#wpo_all_sites'),
		wpo_sitelist_show_moreoptions_link = $('#wpo_sitelist_show_moreoptions'),
		wpo_sitelist_moreoptions_div = $('#wpo_sitelist_moreoptions'),

		wpo_settings_sites_list_cron = $('#wpo_settings_sites_list_cron'),
		wpo_settings_sites_list_cron_ul = wpo_settings_sites_list_cron.find('ul').first(),
		wpo_settings_sites_list_cron_items = $('input[type="checkbox"]', wpo_settings_sites_list_cron_ul),
		wpo_settings_all_sites_cron_checkbox = wpo_settings_sites_list_cron.find('#wpo_all_sites_cron'),
		wpo_sitelist_show_moreoptions_cron_link = $('#wpo_sitelist_show_moreoptions_cron'),
		wpo_sitelist_moreoptions_cron_div = $('#wpo_sitelist_moreoptions_cron');

	// sites list for manual run.
	define_moreoptions_settings(
		wpo_sitelist_show_moreoptions_link,
		wpo_sitelist_moreoptions_div,
		wpo_settings_all_sites_checkbox,
		wpo_settings_sites_list_items
	);

	var sites_list_clicked_count = 0;

	$([wpo_settings_all_sites_checkbox, wpo_settings_sites_list_items]).each(function() {
		$(this).on('change', function() {
			sites_list_clicked_count++;
			setTimeout(function() {
				sites_list_clicked_count--;
				if (sites_list_clicked_count == 0) update_optimizations_info();
			}, 1000);
		});
	});

	// sites list for cron run.
	define_moreoptions_settings(
		wpo_sitelist_show_moreoptions_cron_link,
		wpo_sitelist_moreoptions_cron_div,
		wpo_settings_all_sites_cron_checkbox,
		wpo_settings_sites_list_cron_items
	);

	/**
	 * Attach event handlers for more options list showed by clicking on show_moreoptions_link.
	 *
	 * @param show_moreoptions_link
	 * @param more_options_div
	 * @param all_items_checkbox
	 * @param items_list
	 *
	 * @return boolean
	 */
	function define_moreoptions_settings(show_moreoptions_link, more_options_div, all_items_checkbox, items_list) {
		// toggle show options on click.
		show_moreoptions_link.on('click', function () {
			if (!more_options_div.hasClass('wpo_always_visible')) more_options_div.toggleClass('wpo_hidden');
			return false;
		});

		// if "all items" checked/unchecked then check/uncheck items in the list.
		define_select_all_checkbox(all_items_checkbox, items_list);
	}

	/**
	 * Defines "select all" check box, where all_items_checkbox is a select all check box jQuery object
	 * and items_list is a list of checkboxes assigned with all_items_checkbox.
	 *
	 * @param {Object} all_items_checkbox
	 * @param {Object} items_list
	 *
	 * @return void
	 */
	function define_select_all_checkbox(all_items_checkbox, items_list) {
		// if "all items" checked/unchecked then check/uncheck items in the list.
		all_items_checkbox.on('change', function () {
			if (all_items_checkbox.is(':checked')) {
				items_list.prop('checked', true);
			} else {
				items_list.prop('checked', false);
			}

			update_wpo_all_items_checkbox_state(all_items_checkbox, items_list);
		});

		items_list.on('change', function () {
			update_wpo_all_items_checkbox_state(all_items_checkbox, items_list);
		});

		update_wpo_all_items_checkbox_state(all_items_checkbox, items_list);

	}

	/**
	 * Update state of "all items" checkbox depends on state all items in the list.
	 *
	 * @param all_items_checkbox
	 * @param all_items
	 *
	 * @return void
	 */
	function update_wpo_all_items_checkbox_state(all_items_checkbox, all_items) {
		var all_items_count = 0, checked_items_count = 0;

		all_items.each(function () {
			if ($(this).is(':checked')) {
				checked_items_count++;
			}
			all_items_count++;
		});

		// update label text if need.
		if (all_items_checkbox.next().is('label') && all_items_checkbox.next().data('label')) {
			var label = all_items_checkbox.next(),
				label_mask = label.data('label');

			if (all_items_count == checked_items_count) {
				label.text(label_mask);
			} else {
				label.text(label_mask.replace('all', [checked_items_count, ' of ', all_items_count].join('')));
			}
		}

		if (all_items_count == checked_items_count) {
			all_items_checkbox.prop('checked', true);
		} else {
			all_items_checkbox.prop('checked', false);
		}
	}

	/**
	 * Running the optimizations for the selected options
	 *
	 * @return {[type]} optimizations
	 */
	function run_optimization() {
		$optimizations = $('#optimizations_list .optimization_checkbox:checked');

		$optimizations.sort(function (a, b) {
			// Convert to IDs.
			a = $(a).closest('.wp-optimize-settings').data('optimization_run_sort_order');
			b = $(b).closest('.wp-optimize-settings').data('optimization_run_sort_order');
			if (a > b) {
				return 1;
			} else if (a < b) {
				return -1;
			} else {
				return 0;
			}
		});

		var optimization_options = {};

		$optimizations.each(function (index) {
			var optimization_id = $(this).closest('.wp-optimize-settings').data('optimization_id');
			if (!optimization_id) {
				console.log("Optimization ID corresponding to pressed button not found");
				return;
			}
			// An empty object - in future, options may be implemented.
			optimization_options[optimization_id] = { active: 1 };
			do_optimization(optimization_id);
		});

		send_command('save_manual_run_optimization_options', optimization_options);
	}

	/**
	 * Uptate the tables list
	 *
	 * @param {object} response
	 */
	function update_tables_list(response) {
		if (response.hasOwnProperty('table_list')) {
			var resort = true,
				// add a callback, as desired
				callback = function(table) {
					$('#wpoptimize_table_list tbody').css('opacity', '1');
				};

			// update body with new content.
			$("#wpoptimize_table_list tbody").remove();
			$("#wpoptimize_table_list thead").after(response.table_list);

			$("#wpoptimize_table_list").trigger("updateAll", [resort, callback]);
		}

		if (response.hasOwnProperty('total_size')) {
			$('#optimize_current_db_size').html(response.total_size);
		}

		if (response.hasOwnProperty('show_innodb_force_optimize')) {
			$('.innodb_force_optimize--container').toggleClass('hidden', !response.show_innodb_force_optimize);
		}

		change_actions_column_visibility();
		update_single_table_optimization_buttons(force_single_table_optimization);
	}

	$('#wp_optimize_table_list_refresh').on('click', function (e) {
		e.preventDefault();
		var shade = $(this).closest('.wpo-tab-postbox').find('.wpo_shade');
		shade.removeClass('hidden');
		$('#wpoptimize_table_list tbody').css('opacity', '0.5');
		send_command('get_table_list', {refresh_plugin_json: true}, update_tables_list).always(function() {
			shade.addClass('hidden');
		});
	});

	$('#database_settings_form, #settings_form').on('click', '.wpo-save-settings', function (e) {
		e.preventDefault();
		var form = $(this).closest('form');
		var spinner = form.find('.wpo-saving-settings');
		var form_data = gather_settings();

		form.trigger('wpo-saving-form-data');

		if (form.form_errors.has_errors()) return;

		spinner.show();

		// when optimizations list in the document - send information about selected optimizations.
		if ($('#optimizations_list').length) {
			$('#optimizations_list .optimization_checkbox:checked').each(function() {
				var optimization_id = $(this).closest('.wp-optimize-settings').data('optimization_id');
				if (optimization_id) {
					form_data += '&optimization-options['+optimization_id+'][active]=1';
				}
			});
		}

		if ($('#purge_cache_permissions').length) {
			if (!$('#purge_cache_permissions').val()) {
				form_data += '&purge_cache_permissions[]';
			}
		}

		send_command('save_settings', form_data, function (resp) {
			spinner.closest('div').find('.save-done').show().delay(5000).fadeOut();

			if (resp && resp.hasOwnProperty('save_results') && resp.save_results && resp.save_results.hasOwnProperty('errors')) {
				for (var i = 0, len = resp.save_results.errors.length; i < len; i++) {
					var new_html = '<div class="error">' + resp.errors[i] + '</div>';
					temporarily_display_notice(new_html, '#wp-optimize-settings-save-results');
				}
			}
			if (resp && resp.hasOwnProperty('status_box_contents')) {
				$(resp.status_box_contents).each(function(index, el) {
					if ($(el).is('#wp_optimize_status_box')) {
						$('#wp_optimize_status_box').replaceWith($(el));
					}
				});
			}

			if (resp && resp.hasOwnProperty('optimizations_table')) {
				$('#optimizations_list').replaceWith(resp.optimizations_table);
			}

			// Need to refresh the page if enable-admin-menu tick state has changed.
			if (resp.save_results.refresh) {
				location.reload();
			}
		}).always(function() {
			spinner.hide();
		});
	});

	/*
	 * Handle Wipe Settings click.
	 */
	$('#settings_form').on('click', '.wpo-wipe-settings', function() {
		var spinner = $(this).parent().find('.wpo_spinner');

		spinner.show();

		send_command('wipe_settings', {}, function() {
			spinner.next().removeClass('display-none').delay(5000).fadeOut();
			alert(wpoptimize.settings_have_been_deleted_successfully);
			location.replace(wpoptimize.settings_page_url);
		}).always(function() {
			spinner.hide();
		});

	});

	$('#wp-optimize-wrap').on('click', '#wp_optimize_status_box_refresh', function (e) {
		e.preventDefault();
		$('#wp_optimize_status_box').css('opacity', '0.5');
		send_command('get_status_box_contents', null, function (resp) {
			$('#wp_optimize_status_box').css('opacity', '1');
				$(resp).each(function(index, el) {
				if ($(el).is('#wp_optimize_status_box')) {
					$('#wp_optimize_status_box').replaceWith($(el));
				}
			});

		});
	});

	/**
	 * Run single table optimization. Handle click on Optimize button.
	 *
	 * @param {Object} btn jQuery object clicked Optimize button
	 *
	 * @return {void}
	 */
	function run_single_table_optimization(btn) {
		var spinner = btn.next(),
			action_done_icon = spinner.next(),
			table_name = btn.data('table'),
			table_type = btn.data('type'),
			data = {
				optimization_id: 'optimizetables',
				optimization_table: table_name,
				optimization_table_type: table_type
			};
		btn.hide();
		// if checked force button then send force value.
		if (force_single_table_optimization) {
			data['optimization_force'] = true;
		}

		spinner.removeClass('visibility-hidden');

		send_command('do_optimization', { optimization_id: 'optimizetables', data: data }, function (response) {
			if (response.result.meta.error) {
				btn.closest('tr').html('<td colspan="8" class="no-table"><p>' + response.result.meta.message + '</p></td>');
				setTimeout(function() {
					$('#wp_optimize_table_list_refresh').trigger('click');
				}, 2000);
			} else {
				if (response.result.meta.tableinfo) {
					var row = btn.closest('tr'),
						meta = response.result.meta,
						tableinfo = meta.tableinfo;
	
					update_single_table_information(row, tableinfo);
	
					// update total overhead.
					$('#wpoptimize_table_list > tbody:last th:eq(6)').html(['<span style="color:', meta.overhead > 0 ? '#0000FF' : '#004600', '">', meta.overhead_formatted,'</span>'].join(''));
				}
			}

			btn.prop('disabled', false);
			spinner.addClass('visibility-hidden');
			action_done_icon.show().removeClass('visibility-hidden').delay(2500).fadeOut('fast', function() {
				btn.show();
			});
		});
	}

	/**
	 * Update information about single table in the database tables list.
	 *
	 * @param {Object} row       jQuery object for TR html tag.
	 * @param {Object} tableinfo
	 *
	 * @return {void}
	 */
	function update_single_table_information(row, tableinfo) {
		// update table information in row.
		$('td:eq(2)', row).text(tableinfo.rows);
		$('td:eq(3)', row).text(tableinfo.data_size);
		$('td:eq(4)', row).text(tableinfo.index_size);
		$('td:eq(5)', row).text(tableinfo.type);

		if (tableinfo.is_optimizable) {
			$('td:eq(6)', row).html(['<span style="color:', tableinfo.overhead > 0 ? '#0000FF' : '#004600', '">', tableinfo.overhead,'</span>'].join(''));
		} else {
			$('td:eq(6)', row).html('<span color="#0000FF">-</span>');
		}

	}

	/**
	 * Update single table optimization buttons state depends on force_optimization value.
	 *
	 * @param {boolean} force_optimization See if we need to force optimization
	 *
	 * @return {void}
	 */
	function update_single_table_optimization_buttons(force_optimization) {
		$('.run-single-table-optimization').each(function() {
			var btn = $(this);

			if (btn.data('disabled')) {
				if (force_optimization) {
					btn.prop('disabled', false);
				} else {
					btn.prop('disabled', true);
				}
			}
		});
	}

	/**
	 * Returns true if single site mode or multisite and at least one site selected;
	 *
	 * @return {boolean}
	 */
	function is_sites_selected() {
		return (0 == wpo_settings_sites_list.length || 0 != $('input[type="checkbox"]:checked', wpo_settings_sites_list).length);
	}

	/**
	 * Update optimizations info texts.
	 *
	 * @param {Object} response object returned by command get_optimizations_info.
	 *
	 * @return {void}
	 */
	function update_optimizations_info_view(response) {
		var i, dom_id, info;

		// @codingStandardsIgnoreLine
		if (!response) return;

		for (i in response) {
			if (!response.hasOwnProperty(i)) continue;

			dom_id = ['#wp-optimize-settings-', response[i].dom_id].join('');
			info = response[i].info ? response[i].info.join('<br>') : '';

			$(dom_id + ' .wp-optimize-settings-optimization-info').html(info);
		}
	}

	var get_optimizations_info_cache = {};

	/**
	 * Send command for get optimizations info and update view.
	 *
	 * @return {void}
	 */
	function update_optimizations_info() {
		var cache_key = ['', get_selected_sites_list().join('_')].join('');

		// if information saved in cache show it.
		if (get_optimizations_info_cache.hasOwnProperty(cache_key)) {
			update_optimizations_info_view(get_optimizations_info_cache[cache_key]);
		} else {
			// else send command update cache and update view.
			send_command('get_optimizations_info', {'wpo-sites':get_selected_sites_list()}, function(response) {
				// @codingStandardsIgnoreLine
				if (!response) return;
				get_optimizations_info_cache[cache_key] = response;
				update_optimizations_info_view(response);
			});
		}
	}

	/*
	 * Check if settings file selected for import.
	 */
	$('#wpo_import_settings_btn').on('click', function(e) {
		var file_input = $('#wpo_import_settings_file'),
			filename = file_input.val(),
			wpo_import_file_file = file_input[0].files[0],
			wpo_import_file_reader = new FileReader();

		$('#wpo_import_settings_btn').prop('disabled', true);

		if (!/\.json$/.test(filename)) {
			e.preventDefault();
			$('#wpo_import_settings_btn').prop('disabled', false);
			$('#wpo_import_error_message').text(wpoptimize.please_select_settings_file).slideDown();
			return false;
		}

		wpo_import_file_reader.onload = function() {
			import_settings(this.result);
		};

		wpo_import_file_reader.readAsText(wpo_import_file_file);

		return false;
	});

	/**
	 * Send import settings command.
	 *
	 * @param {string} settings  encoded settings in json string.
	 *
	 * @return {void}
	 */
	function import_settings(settings) {
		var loader = $('#wpo_import_spinner'),
			success_message = $('#wpo_import_success_message'),
			error_message = $('#wpo_import_error_message');

		loader.show();
		send_command('import_settings', {'settings': settings}, function(response) {
			loader.hide();
			if (response && response.errors && response.errors.length) {
				error_message.text(response.errors.join('<br>'));
				error_message.slideDown();
			} else if (response && response.messages && response.messages.length) {
				success_message.text(response.messages.join('<br>'));
				success_message.slideDown();
				setTimeout(function() {
					window.location.reload();
					}, 500);
			}

			$('#wpo_import_settings_btn').prop('disabled', false);
		});
	}

	/*
	 * Hide file validation message on change file field value.
	 */
	$('#wpo_import_settings_file').on('change', function() {
		$('#wpo_import_error_message').slideUp();
	});

	/*
	 * Save settings to hidden form field, used for export.
	 */
	$('#wpo_export_settings_btn').on('click', function(e) {
		wpo_download_json_file(gather_settings('object'));
		return false;
	});

	/**
	 * Force download json file with posted data.
	 *
	 * @param {Object} data     data to put in a file.
	 * @param {string} filename
	 *
	 * @return {void}
	 */
	function wpo_download_json_file(data ,filename) {
		// Attach this data to an anchor on page
		var link = document.body.appendChild(document.createElement('a')),
			date = new Date(),
			year = date.getFullYear(),
			month = date.getMonth() < 10 ? ['0', date.getMonth()].join('') : date.getMonth(),
			day = date.getDay() < 10 ? ['0', date.getDay()].join('') : date.getDay();

		filename = filename ? filename : ['wpo-settings-',year,'-',month,'-',day,'.json'].join('');

		link.setAttribute('download', filename);
		link.setAttribute('style', "display:none;");
		link.setAttribute('href', 'data:text/json' + ';charset=UTF-8,' + encodeURIComponent(JSON.stringify(data)));
		link.click();
	}

	/**
	 * Make ajax request to get optimization info.
	 *
	 * @param {Object} optimization_info_container - jquery object obtimization info container.
	 * @param {string} optimization_id             - optimization id.
	 * @param {Object} params                      - custom params posted to optimization get info.
	 *
	 * @return void
	 */
	var optimization_get_info = function(optimization_info_container, optimization_id, params) {
		return send_command('get_optimization_info', {optimization_id: optimization_id, data: params}, function(resp) {
			var meta = (resp && resp.result && resp.result.meta) ? resp.result.meta : {},
				message = (resp && resp.result && resp.result.output) ? resp.result.output.join('<br>') : '...';

			// trigger event about optimization get info in process.
			$(document).trigger(['optimization_get_info_', optimization_id].join(''), [message, meta]);
			// update status message in optimizations list.
			optimization_info_container.html(message);

			if (!meta.finished) {
				setTimeout(function() {
					var xhr = optimization_get_info(optimization_info_container, optimization_id, meta);
					$(document).trigger(['optimization_get_info_xhr_', optimization_id].join(''), [xhr, meta]);
				}, 1);
			} else {
				// trigger event about optimization get info action done.
				$(document).trigger(['optimization_get_info_', optimization_id, '_done'].join(''), resp);
			}
		});
	};

	/*
	 * Handle ajax information for optimizations.
	 *
	 * @return void
	 */
	jQuery(function() {
		$('.wp-optimize-optimization-info-ajax').each(function () {
			var optimization_info = $(this),
				optimization_info_container = optimization_info.parent(),
				optimization_id = optimization_info.data('id');

			// trigger event about optimization get info action started.
			$(document).trigger(['optimization_get_info_', optimization_id, '_start'].join(''));
			optimization_get_info(optimization_info_container, optimization_id, {support_ajax_get_info: true});
		});
	});

	// attach event handlers after database tables template loaded.
	// Handle single optimization click.
	$('#wpoptimize_table_list').on('click', '.run-single-table-optimization', function () {
		var take_backup_checkbox = $('#enable-auto-backup-1'),
			button = $(this);

		// check if backup checkbox is checked for db tables
		if (take_backup_checkbox.is(':checked')) {
			take_a_backup_with_updraftplus(
				function () {
					run_single_table_optimization(button);
				}
			);
		} else {
			run_single_table_optimization(button);
		}
	});

	// Handle repair table click
	$('#wpoptimize_table_list').on('click', '.run-single-table-repair', function () {
		var btn = $(this),
			spinner = btn.next(),
			action_done_icon = spinner.next(),
			table_name = btn.data('table'),
			data = {
				optimization_id: 'repairtables',
				optimization_table: table_name
			};

		spinner.removeClass('visibility-hidden');

		send_command('do_optimization', {optimization_id: 'repairtables', data: data}, function (response) {
			if (response.result.meta.success) {
				var row = btn.closest('tr'),
					tableinfo = response.result.meta.tableinfo;

				btn.prop('disabled', false);
				spinner.addClass('visibility-hidden');
				action_done_icon.show().removeClass('visibility-hidden');

				update_single_table_information(row, tableinfo);

				// keep visible results from previous operation for one second and show optimize button if possible.
				setTimeout(function () {
					var parent_td = btn.closest('td'),
						btn_wrap = btn.closest('.wpo_button_wrap');

					// remove Repair button and show Optimize button.
					btn_wrap.fadeOut('fast', function () {
						btn_wrap.closest('.wpo_button_wrap').remove();

						// if table type is supported for optimization then show OPTIMIZE button.
						if (tableinfo.is_type_supported) {
							$('.wpo_button_wrap', parent_td).removeClass('wpo_hidden');
						}
					});

					change_actions_column_visibility();
				}, 1000);
			} else {
				btn.prop('disabled', false);
				spinner.addClass('visibility-hidden');
				alert(wpoptimize.table_was_not_repaired.replace('%s', table_name));
			}
		});
	});

	// Handle convert table click
	$('#wpoptimize_table_list').on('click', '.toinnodb', function () {
		convert_single_db_table($(this));
	});
	
	var table_to_remove_btn;
	
	// Handle delete table click
	$('#wpoptimize_table_list').on('click', '.run-single-table-delete', function () {
		table_to_remove_btn = $(this);
		var take_backup_checkbox = $('#enable-auto-backup-1');
		if ('' == wpoptimize.user_always_ignores_table_delete_warning || '1' !== wpoptimize.user_always_ignores_table_delete_warning) {
			wp_optimize.modal.open({
				className: 'wpo-confirm',
				events: {
					'click .wpo-modal--bg': 'close',
					'click .delete-table': 'deleteTable',
					'change #confirm_deletion_without_backup': 'changeConfirm',
					'change #confirm_table_deletion': 'changeConfirm'
				},
				content: function() {
					var content_template = wp.template('wpo-table-delete');
					// Get the plugins list
					var plugins_list = table_to_remove_btn.closest('tr').find('.table-plugins').html();
					return content_template({
						no_backup: !take_backup_checkbox.is(':checked'),
						plugins_list: plugins_list,
						table_name: table_to_remove_btn.data('table')
					});
				},
				changeConfirm: function() {
					var enable_button = true;
					var backup_input = this.$('#confirm_deletion_without_backup');
					var confirm_input = this.$('#confirm_table_deletion');
					if (backup_input.length && !backup_input.is(':checked')) {
						enable_button = false;
					}
					if (!confirm_input.is(':checked')) {
						enable_button = false;
					}
					this.$('.delete-table').prop('disabled', !enable_button);
				},
				deleteTable: function() {
					// check if user ignore table warning
					var ignores_table_detele_warning_checkbox = this.$('#ignores_table_delete_warning');
					if (ignores_table_detele_warning_checkbox.is(':checked')) {
						send_command('user_ignores_table_delete_warning', {}, function(response) {
							if (response.success) {
								wpoptimize.user_always_ignores_table_delete_warning = '1';
							}
						});
					}
					// check if backup checkbox is checked for db tables
					if (take_backup_checkbox.is(':checked')) {
						take_a_backup_with_updraftplus(remove_single_db_table);
					} else {
						remove_single_db_table();
					}
					this.close();
	
				}
			});
		} else {
			// check if backup checkbox is checked for db tables
			if (take_backup_checkbox.is(':checked')) {
				take_a_backup_with_updraftplus(remove_single_db_table);
			} else {
				remove_single_db_table();
			}
		}
	});

	/**
	 * Send ajax command to convert single table to InnoDB.
	 *
	 * @param {Object} btn jQuery object of clicked "Convert to InnoDB" button.
	 *
	 * @return void
	 */
	function convert_single_db_table(table_to_convert_btn) {
		var spinner = table_to_convert_btn.next(),
			action_done_icon = spinner.next(),
			table_name = table_to_convert_btn.data('table'),
			data = {
				optimization_id: 'orphanedtables',
				optimization_table: table_name,
				optimization_action: 'toinnodb'
			};

		spinner.removeClass('visibility-hidden');

		send_command('do_optimization', { optimization_id: 'orphanedtables', data: data }, function (response) {
			if (response.result.meta.error) {
				table_to_convert_btn.closest('tr').html('<td colspan="8" class="no-table"><p>' + response.result.meta.message + '</p></td>');
				setTimeout(function() {
					$('#wp_optimize_table_list_refresh').trigger('click');
				}, 2000);
				return;
			}
			if (response.result.meta.success) {
				var row = table_to_convert_btn.closest('tr');

				action_done_icon.show().removeClass('visibility-hidden');

				// remove convert to Innodb button.
				row.find(".toinnodb").remove();
				
				
				setTimeout(function() {
					action_done_icon.fadeOut('slow', function() {
						row.find("[data-colname='Type']").text("InnoDB");
					});
				}, 500);
			} else {
				var message = wpoptimize.table_was_not_converted.replace('%s', table_name);

				if (response.result.meta.message) {
					message += '(' + response.result.meta.message + ')';
				}

				alert(message);
			}
		}).always(function() {
			table_to_convert_btn.prop('disabled', false);
			spinner.addClass('visibility-hidden');
		});
	}



	/**
	 * Send ajax commant to remove single database table.
	 *
	 * @param {Object} btn jQuery object of clicked "Remove" button.
	 *
	 * @return void
	 */
	function remove_single_db_table() {
		var spinner = table_to_remove_btn.next(),
			action_done_icon = spinner.next(),
			table_name = table_to_remove_btn.data('table'),
			data = {
				optimization_id: 'orphanedtables',
				optimization_table: table_name
			};

		spinner.removeClass('visibility-hidden');

		send_command('do_optimization', { optimization_id: 'orphanedtables', data: data }, function (response) {
			if (response.result.meta.success) {
				var row = table_to_remove_btn.closest('tr');

				action_done_icon.show().removeClass('visibility-hidden');

				// remove row for deleted table.
				setTimeout(function() {
					row.fadeOut('slow', function() {
						row.remove();

						change_actions_column_visibility();
					});
				}, 500);
			} else {
				var message = wpoptimize.table_was_not_deleted.replace('%s', table_name);

				if (response.result.meta.message) {
					message += '(' + response.result.meta.message + ')';
				}

				alert(message);
			}
		}).always(function() {
			table_to_remove_btn.prop('disabled', false);
			spinner.addClass('visibility-hidden');
		});
	}

	change_actions_column_visibility();

	/**
	 * Show or hide actions column if need.
	 *
	 * @return void
	 */
	function change_actions_column_visibility() {
		var table = $('#wpoptimize_table_list'),
			hideLastColumn  = true;

		// check if any button exists in the actions column.
		$('tr', table).each(function() {
			var row = $(this);

			if ($('button', row).length > 0) {
				hideLastColumn = false;
				return false;
			}
		});

		// hide or show last column
		$('tr', table).each(function() {
			var row = $(this);

			if (hideLastColumn) {
				$('td:last, th:last', row).hide();
			} else {
				$('td:last, th:last', row).show();
			}
		});
	}

	/*
	 * Validate general settings (loggers).
	 */
	$('#wp-optimize-general-settings').on('wpo-saving-form-data', function() {
		var valid = true;
		var $form = $(this);

		$('.wpo_logger_addition_option, .wpo_logger_type').each(function() {
			if (!validate_field($(this), true)) {
				valid = false;
				$(this).addClass('wpo_error_field');
			} else {
				$(this).removeClass('wpo_error_field');
			}
		});

		if (!valid) {
			$form.form_errors.add('missing-fields', '');
			$('#wp-optimize-settings-save-results')
				.show()
				.addClass('wpo_alert_notice')
				.text(wpoptimize.fill_all_settings_fields)
				.delay(5000)
				.fadeOut(3000, function() {
					$(this).removeClass('wpo_alert_notice');
				});
		} else {
			$form.form_errors.remove('missing-fields');
			$('#wp-optimize-logger-settings .save_settings_reminder').slideUp();
		}

	});

	/**
	 * Validate import field with data-validate attribute.
	 *
	 * @param {object}  field    jquery element
	 * @param {boolean} required
	 *
	 * @return {boolean}
	 */
	function validate_field(field, required) {
		var value = field.val(),
			validate = field.data('validate');

		if (!validate && required) {
			return ('' != $.trim(value));
		}

		if (validate && !required && '' == $.trim(value)) {
			return true;
		}

		var valid = true;

		switch (validate) {
			case 'email':
				var regex = /\S+@\S+\.\S+/,
					emails = value.split(","),
					email = '';

				for (var i = 0; i < emails.length; i++) {
					email = $.trim(emails[i]);

					if ('' == email || !regex.test(email)) {
						valid = false;
					}
				}
				break;

			case 'url':
				// https://gist.github.com/dperini/729294
				// @codingStandardsIgnoreLine
				var regex = /^(?:(?:https?|ftp):\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,}))\.?)(?::\d{2,5})?(?:[/?#]\S*)?$/i;

				valid = regex.test(value);
				break;
		}

		return valid;
	}

	/**
	 * Send check_overdue_crons command and output warning if need.
	 */
	setTimeout(function() {
		send_command('check_overdue_crons', null, function (resp) {
			if (resp && resp.hasOwnProperty('m')) {
				$('#wpo_settings_warnings').append(resp.m);
			}
		});
	}, 11000);

	/**
	 * Hide introduction notice
	 */
	$('.wpo-introduction-notice .notice-dismiss, .wpo-introduction-notice .close').on('click', function(e) {
		$('.wpo-introduction-notice').remove();
		send_command('dismiss_install_or_update_notice', null, function (resp) {
			if (resp && resp.hasOwnProperty('error')) {
				// there was an error.
				console.log('There was an error dismissing the install or update notice (dismiss_install_or_update_notice)', resp);
			}
		});
	});

	return {
		send_command: send_command,
		optimization_get_info: optimization_get_info,
		take_a_backup_with_updraftplus: take_a_backup_with_updraftplus,
		save_auto_backup_options: save_auto_backup_options
	}
}; // END function WP_Optimize()

jQuery(function ($) {
	/**
	 * Show additional options section if optimization enabled
	 *
	 * @param {string} checkbox Logger settings jQuery checkbox object.
	 *
	 * @return {void}
	 */
	function show_hide_additional_logger_options($checkbox) {
		var additional_section_id = ['#', $checkbox.data('additional')].join('');

		if ($checkbox.is(':checked')) {
			$(additional_section_id).show();
		} else {
			$(additional_section_id).hide();
		}
	}

	// Add events handler for each logger.
	$('.wp-optimize-logging-settings').each(function () {
		var $checkbox = $(this);
		show_hide_additional_logger_options($checkbox);
		$checkbox.on('change', function () {
			show_hide_additional_logger_options($checkbox);
		});
	});

	var add_logging_btn = $('#wpo_add_logger_link');

	/**
	 * Handle add logging destination click.
	 */
	add_logging_btn.on('click', function(e) {
		e.preventDefault();
		$('#wp-optimize-logger-settings .save_settings_reminder').after(get_add_logging_form_html());

		filter_select_destinations($('.wpo_logger_type').first());
	});

	/**
	 * Handle logging destination select change.
	 */
	$('#wp-optimize-general-settings').on('change', '.wpo_logger_type', function() {
		var select = $(this),
			logger_id = select.val(),
			options_container = select.parent().find('.wpo_additional_logger_options');

		options_container.html(get_logging_additional_options_html(logger_id));

		if (select.val()) {
			show_logging_save_settings_reminder();
		}
	});

	/**
	 * Show save settings reminder for logging settings.
	 *
	 * @return {void}
	 */
	function show_logging_save_settings_reminder() {
		var reminder = $('#wp-optimize-logger-settings .save_settings_reminder');

		if (!reminder.is(':visible')) {
			reminder.slideDown('normal');
		}
	}

	/**
	 * Handle edit logger click.
	 */
	$('.wpo_logging_actions_row .dashicons-edit').on('click', function() {

		var link = $(this),
			container = link.closest('.wpo_logging_row');

		$('.wpo_additional_logger_options', container).removeClass('wpo_hidden');
		$('.wpo_logging_options_row', container).text('');
		$('.wpo_logging_status_row', container).text('');
		link.hide();

		return false;
	});

	$('#wp-optimize-logger-settings').on('change', '.wpo_logger_addition_option', function() {
		show_logging_save_settings_reminder();
	});

	/**
	 * Handle change of active/inactive status and update hidden field value.
	 */
	$('.wpo_logger_active_checkbox').on('change', function() {
		var checkbox = $(this),
			hidden_input = checkbox.closest('label').find('input[type="hidden"]');

		hidden_input.val(checkbox.is(':checked') ? '1' : '0');
	});

	/**
	 * Handle delete logger destination click.
	 */
	$('#wp-optimize-general-settings').on('click', '.wpo_delete_logger', function() {

		if (!confirm(wpoptimize.are_you_sure_you_want_to_remove_logging_destination)) {
			return false;
		}

		var btn = $(this);
		btn.closest('.wpo_logging_row, .wpo_add_logger_form').remove();
		filter_all_select_destinations();

		if (0 == $('#wp-optimize-logging-options .wpo_logging_row').length) {
			$('#wp-optimize-logging-options').hide();
		}

		show_logging_save_settings_reminder();

		return false;
	});

	/**
	 * Filter all selects with logger destinations, called after some destination deleted.
	 *
	 * @return {void}
	 */
	function filter_all_select_destinations() {
		$('.wpo_logger_type').each(function() {
			filter_select_destinations($(this));
		});
	}

	/**
	 * Filter certain select options depending on currently selected values.
	 *
	 * @param {object} select
	 *
	 * @return {void}
	 */
	function filter_select_destinations(select) {
		var i,
			destination,
			current_destinations = get_current_destinations();

		for (i in current_destinations) {
			destination = current_destinations[i];
			if (wpoptimize.loggers_classes_info[destination].allow_multiple) {
				$('option[value="'+destination+'"]', select).show();
			} else {
				$('option[value="'+destination+'"]', select).hide();
			}
		}
	}

	/**
	 * Returns currently selected loggers destinations.
	 *
	 * @return {Array}
	 */
	function get_current_destinations() {
		var destinations = [];

		$('.wpo_logging_row, .wpo_logger_type').each(function() {
			var destination = $(this).is('select') ? $(this).val() : $(this).data('id');

			if (destination) destinations.push(destination);
		});

		return destinations;
	}

	/**
	 * Return add logging form.
	 *
	 * @return {string}
	 */
	function get_add_logging_form_html() {
		var i,
			select_options = [
				'<option value="">Select destination</option>'
			];

		for (i in wpoptimize.loggers_classes_info) {
			if (!wpoptimize.loggers_classes_info.hasOwnProperty(i)) continue;

			if (!wpoptimize.loggers_classes_info[i].available) continue;

			select_options.push(['<option value="',i,'">',wpoptimize.loggers_classes_info[i].description,'</option>'].join(''));
		}

		return [
			'<div class="wpo_add_logger_form">',
				'<select class="wpo_logger_type" name="wpo-logger-type[]">',
					select_options.join(''),
				'<select>',
				'<a href="#" class="wpo_delete_logger dashicons dashicons-no-alt"></a>',
				'<div class="wpo_additional_logger_options"></div>',
			'</div>'
		].join('');
	}

	/**
	 * Returns logging options html.
	 *
	 * @param {string} logger_id
	 *
	 * @return {string}
	 */
	function get_logging_additional_options_html(logger_id) {
		if (!wpoptimize.loggers_classes_info[logger_id].options) return '';

		var i,
			options = wpoptimize.loggers_classes_info[logger_id].options,
			options_list = [],
			placeholder = '',
			validate = '';

		for (i in options) {
			if (!options.hasOwnProperty(i)) continue;

			if (Array.isArray(options[i])) {
				placeholder = $.trim(options[i][0]);
				validate = $.trim(options[i][1]);
			} else {
				placeholder = $.trim(options[i]);
				validate = '';
			}

			options_list.push([
				'<input class="wpo_logger_addition_option" type="text" name="wpo-logger-options[',i,'][]" value="" ',
				'placeholder="',placeholder,'" ',('' !== validate ? 'data-validate="'+validate+'"' : ''), '/>'
			].join(''));
		}

		// Add hidden field for active/inactive value.
		options_list.push('<input type="hidden" name="wpo-logger-options[active][]" value="1" />');

		return options_list.join('');
	}

	/**
	 * Layout - Adds `is-scrolled` class to the body element when scrolled.
	 */
	var is_scrolled = false;
	$(window).on('scroll', function(e) {
		window.requestAnimationFrame(function() {
			var threshold = $('.wpo-main-header').length ? $('.wpo-main-header')[0].offsetTop : 0;
			if ((window.pageYOffset > threshold - 20) != is_scrolled) {
				is_scrolled = !is_scrolled;
				$('body').toggleClass('is-scrolled', is_scrolled);
			}
		});
	});

	// Opens the video preview / info popup
	$('.wpo-info__trigger').on('click', function(e) {
		e.preventDefault();
		var $container = $(this).closest('.wpo-info');
		$container.toggleClass('opened');
	});

	// Embed videos when clicking the vimeo links
	$('.wpo-video-preview a').on('click', function(e) {
		var video_url = $(this).data('embed');
		if (video_url) {
			e.preventDefault();
			var $iframe = $('<iframe width="356" height="200" allowfullscreen webkitallowfullscreen mozallowfullscreen>').attr('src', video_url);
			$iframe.insertAfter($(this));
			$(this).remove();
			$iframe.focus();
		}
	});
});
