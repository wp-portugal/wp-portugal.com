WP_Optimize_Images_View = function(settings) {

	var $ = jQuery,
		defaults = {
			container: '',
			image_container_class: 'wpo_unused_image',
			image_container_custom_classes: 'wpo_unused_image_row',
			image_container_blog_class_prefix: 'wpo_unused_image_site_',
			view_image_btn_link_class: 'wpo_unused_image_view_link',
			view_image_btn_text: wpoptimize.view_image_link_text,
			row_selector: '.wpo_unused_image_row',
			row_id_class: 'wpo_unused_images_row_id',
			row_thumb_class: 'wpo_unused_images_row_thumb',
			row_file_class: 'wpo_unused_images_row_file',
			row_action_class: 'wpo_unused_images_row_action',
			/**
			 * Array with buttons shown in the list view mode.
			 *
			 *  [
			 *    {
			 *  	'class': 'button_class',
			 *  	'title': 'Button title',
			 *  	'hint': 'Tooltip text'
			 * 	  },
			 * 	  ...
			 * 	]
			 */
			row_action_buttons: [],
			label_class: 'wpo_unused_image_thumb_label',
			loader_additional_html: '',
			action_btn_text: 'Remove',
			action_btn_class: 'button button-primary wpo_unused_images_remove_single',
			checkbox_class: 'wpo_unused_image__input',
			list_mode_class: 'wpo_unused_image_list_view',
			no_images_found_message: 'No images found',
			related_elements: [],
			action_buttons: [],
			hide_when_empty: [],
			load_next_page_callback: null,
			onclear: null
		},
		options = jQuery.extend({}, defaults, settings),
		IMAGES_VIEW_MODE = {
			GRID: 'grid',
			LIST: 'list'
		},
		images_view_mode = IMAGES_VIEW_MODE.GRID,
		images_view_container = options.container,
		image_container_selector = '.' + options.image_container_class,
		checkbox_selector = '.' + options.checkbox_class,
		last_clicked_image_id = '',
		images_loaded_count = {};

	/**
	 * Handle scroll in the images container.
	 */
	images_view_container.on('scroll mousewheel', function() {
		load_next_page_if_need();
		update_loader_position();
	});

	/**
	 * Handle Shift key state.
	 */
	var ctrl_shift_on_image_held = false;

	images_view_container.on('mousedown', options.row_selector, function (e) {
		ctrl_shift_on_image_held = e.shiftKey || e.ctrlKey;
	});

	images_view_container.on('mouseup', options.row_selector, function (e) {
		ctrl_shift_on_image_held = e.shiftKey || e.ctrlKey;
	});

	
	/**
	 * Handle checked status changed for single unused image.
	 */
	images_view_container.on('click' , '.'+options.image_container_class , function(e) {
		
		var check_box = $(this).find('.'+options.checkbox_class);
		var image_id = check_box.attr('id');

		// Toggle class on image container
		if (true === check_box.prop('checked')) {
			$(this).addClass('selected');
		} else {
			$(this).removeClass('selected');
		}
		if (ctrl_shift_on_image_held) {

			if (true === check_box.prop('checked')) {
				$(this).removeClass('selected');
				check_box.prop('checked',false);
			} else {
				$(this).addClass('selected');
				check_box.prop('checked',true);
			}
			if (1 <= get_selected_images().length) {
				if (true === $("#"+last_clicked_image_id).prop('checked')) {
					select_images(last_clicked_image_id, image_id, true === check_box.prop('checked'));
				}
				
			}
		}
		last_clicked_image_id = image_id;
		disable_action_buttons(0 == get_selected_images().length);
			
	});

	/**
	 * Select or deselect images from #first_id to #last_id in the lis of unused images
	 *
	 * @param {string} first_id - first image id in the list
	 * @param {string} last_id  - last image id in the list
	 * @param {bool}   checked  - select or deselect images
	 *
	 * @return void
	 */
	function select_images(first_id, last_id, checked) {
		var image_id = first_id,
			index1,
			index2,
			current,
			first,
			last,
			done = false;

		// if set first and last ids then go through the list.
		if (last_id && first_id) {
			// get positions in then list.
			index1 = $(checkbox_selector).index($('#' + first_id));
			index2 = $(checkbox_selector).index($('#' + last_id));

			// check if both item exists. (posibly one of them was deleted)
			if (-1 == index1) index1 = index2;
			if (-1 == index2) index2 = index1;

			// get correct first and last item.
			if (index1 < index2) {
				current = $(checkbox_selector).eq(index1).closest(image_container_selector);
				last_id = $(checkbox_selector).eq(index2).attr('id');
			} else {
				current = $(checkbox_selector).eq(index2).closest(image_container_selector);
				last_id = $(checkbox_selector).eq(index1).attr('id');
			}

			// select images.
			while (!done) {
				if (checked) {
					current.addClass('selected');
					$(checkbox_selector, current).prop('checked', checked);
				} else {
					current.removeClass('selected');
					$(checkbox_selector, current).prop('checked', checked);
				}

				if ($(checkbox_selector, current).attr('id') == last_id) done = true;

				current = current.next();
			}
		} else {
			// if just one the first id passed then change just the first element state.
			if (checked) {
				$('#' + image_id).closest(image_container_selector).addClass('selected');
			} else {
				$('#' + image_id).closest(image_container_selector).removeClass('selected');
			}
		}

		disable_action_buttons(0 == get_selected_images().length);
	}

	/**
	 * Disable/enable controls.
	 *
	 * @param {boolean} disabled
	 */
	function disable_action_buttons(disabled) {
		if (!options.action_buttons) return;

		$.each(options.action_buttons, function(i, el) {
			el.prop('disabled', disabled)
		});
	}

	/**
	 * Update view in case when then images list is empty.
	 */
	function hide_when_empty_elements() {
		if (options.hide_when_empty) {
			var images_count = $(['.', options.image_container_class,':visible'].join(''), images_view_container).length;

			if (0 === images_count) {
				// show message - no images found.
				if (0 == $('.wpo-images-view-empty', images_view_container).length) {
					images_view_container.append($('<div class="wpo-images-view-empty wpo-fieldgroup" />').text(options.no_images_found_message));
				}

				$('.wpo-images-view-empty', images_view_container).show();
			} else {
				$('.wpo-images-view-empty', images_view_container).hide();
			}

			$.each(options.hide_when_empty, function(i, el) {
				if (images_count > 0) {
					el.show();
				} else {
					el.hide();
				}
			});
		}
	}

	/**
	 * Update view when images are changed.
	 */
	function update_view() {
		if (!is_visible()) return;

		hide_when_empty_elements();
		disable_action_buttons(0 == get_selected_images().length);
	}

	/**
	 * Load the next part images if images container scrolled to the bottom.
	 */
	function load_next_page_if_need() {
		if (images_view_container.scrollTop() + images_view_container.height() + 100 > images_view_container[0].scrollHeight) {
			if ('function' == typeof options.load_next_page_callback) {
				options.load_next_page_callback();
			}
		}
	}

	/**
	 * Append an image to the list.
	 *
	 * @param {int}    blog_id       image blog id
	 * @param {string} value		 value that will returned if image selected
	 * @param {string} href			 url opened when image clicked
	 * @param {string} thumbnail_url url to the image thumbnail
	 * @param {string} title         image title
	 * @param {string} row_file_text text for displaying near image in the list view mode
	 */
	function append_image(blog_id, value, href, thumbnail_url, title, row_file_text) {
		var random_id = 'image_' + (((1+Math.random())*0x10000)|0).toString(16).substring(1),
			row_actions_html = '',
			i;

		// build button
		if (options.row_action_buttons) {
			for (i in options.row_action_buttons) {

				if (!options.row_action_buttons.hasOwnProperty(i)) continue;

				row_actions_html += [
					'<button href="javascript: ;" class="',(options.row_action_buttons[i].class ? options.row_action_buttons[i].class : ''),'"',
					' title="',(options.row_action_buttons[i].hint ? options.row_action_buttons[i].hint : ''),'">',(options.row_action_buttons[i].title ? options.row_action_buttons[i].title : ''),'</button>'
				].join('');
			}
		}

		// count added image.
		if (!images_loaded_count.hasOwnProperty(blog_id)) images_loaded_count[blog_id] = 0;
		images_loaded_count[blog_id]++;

		images_view_container.append(['\
			<div class="',options.image_container_class,' ',options.image_container_custom_classes,' ',options.image_container_blog_class_prefix,blog_id,'">\
				<a class="button ',options.view_image_btn_link_class,'" href="',href,'" target="_blank">',
			options.view_image_btn_text,
			'</a>',
			'<div class="',options.row_id_class,'">\
					<input id="',random_id,'" type="checkbox" class="',options.checkbox_class,'" value="',value,'">\
				</div>\
				<div class="',options.row_thumb_class,'">\
					<a href="',href,'" target="_blank">\
						<img class="lazyload" src="data:image/gif;base64,R0lGODdhAQABAPAAAMPDwwAAACwAAAAAAQABAAACAkQBADs=" data-src="',thumbnail_url,'" title="',title,'" alt="',title,'">\
					</a>\
				</div>\
				<div class="',options.row_file_class,'">\
					<a href="',href,'" target="_blank">',row_file_text,'</a>\
				</div>\
				<div class="',options.row_action_class,'">',row_actions_html,'</div>\
				<label for="',random_id,'" class="',options.label_class,'">\
					<div class="thumbnail">\
						<img class="lazyload" src="data:image/gif;base64,R0lGODdhAQABAPAAAMPDwwAAACwAAAAAAQABAAACAkQBADs=" data-src="',thumbnail_url,'" title="',title,'" alt="',title,'">\
					</div>\
				</label>\
			</div>'
		].join(''));
	}

	/**
	 * Show only images for the selected blog.
	 *
	 * @param {int} blog_id
	 */
	function filter_by_site(blog_id) {
		$(image_container_selector, images_view_container).hide();
		$(['.',options.image_container_blog_class_prefix, blog_id].join(''), images_view_container).show();
		update_view();
	}

	/**
	 * Get count of loaded images for the selected blog.
	 *
	 * @param {int} blog_id
	 *
	 * @return {number}
	 */
	function get_images_count(blog_id) {
		if (!images_loaded_count.hasOwnProperty(blog_id)) return 0;

		return images_loaded_count[blog_id];
	}

	/**
	 * Get count of loaded images currently displayed in the view container.
	 *
	 * @param {int} blog_id
	 */
	function get_visible_images_count(blog_id) {
		return $(['.',options.image_container_blog_class_prefix, blog_id].join(''), images_view_container).length;
	}

	/**
	 * Switch view mode grid/list.
	 *
	 * @param mode
	 */
	function switch_view_mode(mode) {
		if (mode === images_view_mode) return;

		images_view_mode = mode;

		if (mode === IMAGES_VIEW_MODE.GRID) {
			images_view_container.removeClass(options.list_mode_class);
		}

		if (mode === IMAGES_VIEW_MODE.LIST) {
			images_view_container.addClass(options.list_mode_class);
		}
	}

	/**
	 * Get list of selected images.
	 *
	 * @return {Array}
	 */
	function get_selected_images() {
		var selected_images = [];

		// if no any selected images then exit.
		if (0 == $('input[type="checkbox"]', images_view_container).length) return selected_images;

		// build list of visible selected images .
		$(['.',options.image_container_class,':visible input:checked'].join(''), images_view_container).each(function() {
			selected_images.push($(this).val());
		});

		return selected_images;
	}

	/**
	 * Remove selected images.
	 */
	function remove_selected_images() {
		var image_container_selector = ['.',options.image_container_class].join('');
		$([image_container_selector,':visible input:checked'].join(''), images_view_container).each(function() {
			$(this).closest(image_container_selector).remove();
		});
	}

	/**
	 * Show images container.
	 */
	function show() {
		images_view_container.show();

		$(options.related_elements).each(function() {
			$(this).show();
		});

		update_view();

		load_next_page_if_need();
	}

	/**
	 * Hide images container.
	 */
	function hide() {
		images_view_container.hide();

		$(options.related_elements).each(function() {
			$(this).hide();
		});
	}

	/**
	 * Check if images container element is visible.
	 *
	 * @return {boolean}
	 */
	function is_visible() {
		return images_view_container.is(':visible');
	}

	/**
	 * Show loader.
	 */
	function show_loader() {
		show_custom_loader(wpoptimize.loading_data, '', options.loader_additional_html);
	}

	/**
	 * Show loader with custom content.
	 *
	 * @param {string} title
	 * @param {string} message
	 * @param {string} custom_html
	 */
	function show_custom_loader(title, message, custom_html) {
		message = message ? message : '';
		custom_html = custom_html ? custom_html : '';

		images_view_container.css({ 'min-height' : '220px' });
		images_view_container.append([
			'<div class="wpo_shade">',
			'<div class="wpo_shade_inner">',
			'<span class="dashicons dashicons-update-alt wpo-rotate"></span>',
			'<h4>',title,'</h4>',
			'<p class="wpo-shade-progress-message">',message,'</p>',
			custom_html,
			'</div>',
			'</div>',
		].join(''));

		update_loader_position();
	}

	/**
	 * Hide loader.
	 */
	function hide_loader() {
		images_view_container.css('min-height', 'initial');
		$('.wpo_shade', images_view_container).remove();
	}

	/**
	 * Update top property for shade div with loader.
	 */
	function update_loader_position() {
		$('.wpo_shade', images_view_container).css('top', images_view_container.scrollTop() + 'px');
	}

	/**
	 * Set message for under loader icon.
	 *
	 * @param {string} message
	 */
	function loader_message(message) {
		$('.wpo-shade-progress-message', images_view_container).html(message);
	}

	/**
	 * Select all images.
	 */
	function select_all() {
		$('.wpo_unused_image__input').each(function() {
			if (!$(this).prop('checked')) {
				$(this).closest('.wpo_unused_image').addClass('selected');
				$(this).prop('checked', true);
				last_clicked_image_id = null;
				disable_action_buttons(0 == get_selected_images().length);

			}
		 });
	}

	/**
	 * Deselect all images.
	 */
	function select_none() {
		$('.wpo_unused_image__input').each(function() {
			if ($(this).prop('checked')) {
				$(this).closest('.wpo_unused_image').removeClass('selected');
				$(this).prop('checked', false);
				last_clicked_image_id = null;
				disable_action_buttons(0 == get_selected_images().length);
			}
		 });
	}

	/**
	 * Clear images container.
	 */
	function clear() {
		$(['.', options.image_container_class].join(''), images_view_container).remove();
		images_loaded_count = {};
		disable_action_buttons(true);

		// if defined on clear event then call it.
		if ('function' === typeof options.onclear) {
			options.onclear();
		}
	}

	/**
	 * Reload view items.
	 */
	function reload() {
		clear();
		load_next_page_if_need();
	}

	return {
		show: show,
		hide: hide,
		clear: clear,
		reload: reload,
		show_loader: show_loader,
		show_custom_loader: show_custom_loader,
		hide_loader: hide_loader,
		loader_message: loader_message,
		append_image: append_image,
		get_selected_images: get_selected_images,
		remove_selected_images: remove_selected_images,
		get_images_count: get_images_count,
		get_visible_images_count: get_visible_images_count,
		load_next_page_if_need: load_next_page_if_need,
		filter_by_site: filter_by_site,
		switch_view_mode: switch_view_mode,
		select_all: select_all,
		select_none: select_none,
		is_visible: is_visible,
		update_view: update_view
	}
};
