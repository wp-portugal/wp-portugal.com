<?php
if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('WP_Optimize_Admin')) :

class WP_Optimize_Admin {

	/**
	 * Class constructor
	 */
	public function __construct() {
		if (is_multisite()) {
			add_action('network_admin_menu', array($this, 'admin_menu'));
		} else {
			add_action('admin_menu', array($this, 'admin_menu'));
		}
	}

	/**
	 * Returns singleton instance object
	 *
	 * @return WP_Optimize_Admin Returns `WP_Optimize_Admin` object
	 */
	public static function instance() {
		static $_instance = null;
		if (empty($_instance)) {
			$_instance = new self();
		}
		return $_instance;
	}


	/**
	 * Builds the Tabs that should be displayed
	 *
	 * @return array Returns all tabs specified array
	 */
	public function get_tabs($page) {
		// define tabs for pages.
		$pages_tabs = array(
			'WP-Optimize' => array(
				'optimize' => __('Optimizations', 'wp-optimize'),
				'tables' => __('Tables', 'wp-optimize'),
				'settings' => __('Settings', 'wp-optimize'),
			),
			'wpo_images'  => array(
				'smush' => __('Compress images', 'wp-optimize'),
				'unused' => __('Unused images and sizes', 'wp-optimize').'<span class="menu-pill premium-only">Premium</span>',
				'lazyload' => __('Lazy-load', 'wp-optimize').'<span class="menu-pill premium-only">Premium</span>',
			),
			'wpo_cache' => array(
				'cache' => __('Page cache', 'wp-optimize'),
				'preload' => __('Preload', 'wp-optimize'),
				'advanced' => __('Advanced settings', 'wp-optimize'),
				'gzip' => __('Gzip compression', 'wp-optimize'),
				'settings' => __('Static file headers', 'wp-optimize')  // Adds a settings tab
			),
			'wpo_minify' => array(
				"status" => __('Minify status', 'wp-optimize'),
				"js" => __('JavaScript', 'wp-optimize').'<span class="menu-pill disabled hidden">'.__('Disabled', 'wp-optimize').'</span>',
				"css" => __('CSS', 'wp-optimize').'<span class="menu-pill disabled hidden">'.__('Disabled', 'wp-optimize').'</span>',
				"font" => __('Fonts', 'wp-optimize'),
				"settings" => __('Settings', 'wp-optimize'),
				"preload" => __('Preload', 'wp-optimize'),
				"advanced" => __('Advanced', 'wp-optimize')
			),
			'wpo_settings' => array(
				'settings' => array(
					'title' => __('Settings', 'wp-optimize'),
				),
			),
			'wpo_support' => array('support' => __('Support / FAQs', 'wp-optimize')),
			'wpo_mayalso' => array('may_also' => __('Premium / Plugin family', 'wp-optimize')),
		);

		$tabs = (array_key_exists($page, $pages_tabs)) ? $pages_tabs[$page] : array();

		return apply_filters('wp_optimize_admin_page_'.$page.'_tabs', $tabs);
	}

	/**
	 * Main page structure.
	 */
	public function display_admin() {
		$capability_required = WP_Optimize()->capability_required();
		$can_run_optimizations = WP_Optimize()->can_run_optimizations();
		$can_manage_options = WP_Optimize()->can_manage_options();

		if (!current_user_can($capability_required) || (!$can_run_optimizations && !$can_manage_options())) {
			echo "Permission denied.";
			return;
		}

		$this->register_admin_content();

		echo '<div id="wp-optimize-wrap">';
		
		WP_Optimize()->include_template('admin-page-header.php', false, array('show_notices' => !(WP_Optimize()->get_install_or_update_notice()->show_current_notice())));

		do_action('wpo_admin_after_header');

		echo '<div id="actions-results-area"></div>';
		
		$pages = $this->get_submenu_items();

		foreach ($pages as $page) {
			if (isset($page['menu_slug'])) {
				$this->display_admin_page($page['menu_slug']);
			}
		}

		do_action('wpo_admin_before_closing_wrap');

		// closes main plugin wrapper div. #wp-optimize-wrap
		echo '</div><!-- END #wp-optimize-wrap -->';

	}

	/**
	 * Prepare and display admin page with $page id.
	 *
	 * @param string $page wp-optimize page id i.e. dashboard, database, images, cache, ...
	 */
	public function display_admin_page($page) {

		$active_page = !empty($_REQUEST['page']) ? $_REQUEST['page'] : '';

		echo '<div class="wpo-page' . ($active_page == $page ? ' active' : '') . '" data-whichpage="'.$page.'">';

		echo '<div class="wpo-main">';

		// get defined tabs for $page.
		$tabs = $this->get_tabs($page);

		// if no tabs defined for $page then use $page as $active_tab for load template, doing related actions e t.c.
		if (empty($tabs)) {
			$active_tab = $page;
		} else {
			$tab_keys = array_keys($tabs);
			$default_tab = apply_filters('wp_optimize_admin_'.$page.'_default_tab', $tab_keys[0]);
			$active_tab = isset($_GET['tab']) ? substr($_GET['tab'], 12) : $default_tab;
			if (!in_array($active_tab, array_keys($tabs))) $active_tab = $default_tab;
		}

		do_action('wp_optimize_admin_page_'.$page, $active_tab);

		// if tabs defined then display
		if (!empty($tabs)) {
			WP_Optimize()->include_template('admin-page-header-tabs.php', false, array('page' => $page, 'active_tab' => $active_tab, 'tabs' => $tabs, 'wpo_is_premium' => WP_Optimize::is_premium()));
		}

		foreach ($tabs as $tab_id => $tab_description) {
			// output wrap div for tab with id #wp-optimize-nav-tab-contents-'.$page.'-'.$tab_id
			echo '<div class="wp-optimize-nav-tab-contents" id="wp-optimize-nav-tab-'.$page.'-'.$tab_id.'-contents" '.(($tab_id == $active_tab) ? '' : 'style="display:none;"').'>';
			
			echo '<div class="postbox wpo-tab-postbox">';
			// call action for generate tab content.
			
			do_action('wp_optimize_admin_page_'.$page.'_'.$tab_id);

			// closes postbox.
			echo '</div><!-- END .postbox -->';
			// closes tab wrapper.
			echo '</div><!-- END .wp-optimize-nav-tab-contents -->';
		}

		echo '</div><!-- END .wpo-main -->';

		do_action('wp_optimize_admin_after_page_'.$page, $active_tab);

		echo '</div><!-- END .wpo-page -->';

	}

	/**
	 * Define required actions for admin pages.
	 */
	public function register_admin_content() {

		do_action('wp_optimize_register_admin_content');

		/**
		 * SETTINGS
		 */
		add_action('wp_optimize_admin_page_wpo_settings_settings', array($this, 'output_dashboard_settings_tab'), 20);

		/**
		 * Premium / other plugins
		 */
		add_action('wp_optimize_admin_page_wpo_mayalso_may_also', array($this, 'output_dashboard_other_plugins_tab'), 20);

		/**
		 * DATABASE
		 */
		add_action('wp_optimize_admin_page_WP-Optimize_optimize', array($this, 'output_database_optimize_tab'), 20);
		add_action('wp_optimize_admin_page_WP-Optimize_tables', array($this, 'output_database_tables_tab'), 20);
		add_action('wp_optimize_admin_page_WP-Optimize_settings', array($this, 'output_database_settings_tab'), 20);

		/**
		 * CACHE
		 */
		add_action('wp_optimize_admin_page_wpo_cache_cache', array($this, 'output_page_cache_tab'), 20);
		if (!WP_Optimize()->does_server_handles_cache()) {
			add_action('wp_optimize_admin_page_wpo_cache_preload', array($this, 'output_page_cache_preload_tab'), 20);
			add_action('wp_optimize_admin_page_wpo_cache_advanced', array($this, 'output_page_cache_advanced_tab'), 20);
		}
		add_action('wp_optimize_admin_page_wpo_cache_gzip', array($this, 'output_cache_gzip_tab'), 20);
		add_action('wp_optimize_admin_page_wpo_cache_settings', array($this, 'output_cache_settings_tab'), 20);
		add_action('wpo_page_cache_advanced_settings', array($this, 'output_cloudflare_settings'), 20);
		/**
		 * SUPPORT
		 */
		add_action('wp_optimize_admin_page_wpo_support_support', array($this, 'output_dashboard_support_tab'), 20);
		// Display Support page.

		if (!WP_Optimize::is_premium()) {
			/**
			 * Add action for display Images > Unused images and sizes tab.
			 */
			add_action('wp_optimize_admin_page_wpo_images_unused', array($this, 'admin_page_wpo_images_unused'));

			/**
			 * Add action for display Dashboard > Lazyload tab.
			 */
			add_action('wp_optimize_admin_page_wpo_images_lazyload', array($this, 'admin_page_wpo_images_lazyload'));
		} else {
			/**
			 * Add filter for display footer review message and link.
			 */
			add_filter('admin_footer_text', array($this, 'display_footer_review_message'));
		}
	}

	/**
	 * Database settings
	 */
	public function output_database_settings_tab() {

		if (WP_Optimize()->can_manage_options()) {
			WP_Optimize()->include_template('database/settings.php');
		} else {
			$this->prevent_manage_options_info();
		}
	}

	/**
	 * Dashboard settings
	 */
	public function output_dashboard_settings_tab() {
		$options = WP_Optimize()->get_options();

		if ('POST' == $_SERVER['REQUEST_METHOD']) {
			// Nonce check.
			check_admin_referer('wpo_settings');

			$output = $options->save_settings($_POST);

			if (isset($_POST['wp-optimize-settings'])) {
				// save settings request sent.
				$output = $options->save_settings($_POST);
			}

			$this->wpo_render_output_messages($output);
		}

		if (WP_Optimize()->can_manage_options()) {
			WP_Optimize()->include_template('settings/settings.php');
		} else {
			$this->prevent_manage_options_info();
		}
	}

	/**
	 * Dashboard support tab
	 */
	public function output_dashboard_support_tab() {
		WP_Optimize()->include_template('settings/support-and-faqs.php');
	}

	/**
	 * Dashboard Other plugins / premium tab
	 */
	public function output_dashboard_other_plugins_tab() {
		WP_Optimize()->include_template('settings/may-also-like.php');
	}

	/**
	 * Cache tab
	 */
	public function output_page_cache_tab() {
		$wpo_cache = WP_Optimize()->get_page_cache();
		$wpo_cache_options = $wpo_cache->config->get();
		$display = $wpo_cache->is_enabled() ? "style='display:block'" : "style='display:none'";

		WP_Optimize()->include_template('cache/page-cache.php', false, array(
			'wpo_cache' => $wpo_cache,
			'active_cache_plugins' => WP_Optimize_Detect_Cache_Plugins::instance()->get_active_cache_plugins(),
			'wpo_cache_options' => $wpo_cache_options,
			'cache_size' => $wpo_cache->get_cache_size(),
			'display' => $display,
			'can_purge_the_cache' => WP_Optimize()->can_purge_the_cache(),
			'does_server_handles_cache' => WP_Optimize()->does_server_handles_cache(),
		));
	}

	/**
	 * Preload tab
	 */
	public function output_page_cache_preload_tab() {
		$wpo_cache = WP_Optimize()->get_page_cache();
		$wpo_cache_options = $wpo_cache->config->get();
		$wpo_cache_preloader = WP_Optimize_Page_Cache_Preloader::instance();
		$is_running = $wpo_cache_preloader->is_running();
		$status = $wpo_cache_preloader->get_status_info();

		WP_Optimize()->include_template('cache/page-cache-preload.php', false, array(
			'wpo_cache_options' => $wpo_cache_options,
			'is_running' => $is_running,
			'status_message' => isset($status['message']) ? $status['message'] : '',
			'schedule_options' => array(
				'wpo_use_cache_lifespan' => __('Same as cache lifespan', 'wp-optimize'),
				'wpo_daily' => __('Daily', 'wp-optimize'),
				'wpo_weekly' => __('Weekly', 'wp-optimize'),
				'wpo_fortnightly' => __('Fortnightly', 'wp-optimize'),
				'wpo_monthly' => __('Monthly (approx. - every 30 days)', 'wp-optimize')
			)
		));
	}

	/**
	 * Advanced tab
	 */
	public function output_page_cache_advanced_tab() {
		$wpo_cache = WP_Optimize()->get_page_cache();
		$wpo_cache_options = $wpo_cache->config->get();

		$cache_exception_conditional_tags = is_array($wpo_cache_options['cache_exception_conditional_tags']) ? join("\n", $wpo_cache_options['cache_exception_conditional_tags']) : '';
		$cache_exception_urls = is_array($wpo_cache_options['cache_exception_urls']) ? join("\n", $wpo_cache_options['cache_exception_urls']) : '';
		$cache_exception_cookies = is_array($wpo_cache_options['cache_exception_cookies']) ? join("\n", $wpo_cache_options['cache_exception_cookies']) : '';
		$cache_exception_browser_agents = is_array($wpo_cache_options['cache_exception_browser_agents']) ? join("\n", $wpo_cache_options['cache_exception_browser_agents']) : '';

		WP_Optimize()->include_template('cache/page-cache-advanced.php', false, array(
			'wpo_cache' => $wpo_cache,
			'wpo_cache_options' => $wpo_cache_options,
			'cache_exception_urls' => $cache_exception_urls,
			'cache_exception_conditional_tags' => $cache_exception_conditional_tags,
			'cache_exception_cookies' => $cache_exception_cookies,
			'cache_exception_browser_agents' => $cache_exception_browser_agents,
		));
	}

	/**
	 * Gzip tab
	 */
	public function output_cache_gzip_tab() {
		$wpo_gzip_compression = WP_Optimize()->get_gzip_compression();
		$wpo_gzip_compression_enabled = $wpo_gzip_compression->is_gzip_compression_enabled(true);
		$wpo_gzip_headers_information = $wpo_gzip_compression->get_headers_information();
		$is_cloudflare_site = $this->is_cloudflare_site();
		$is_gzip_compression_section_exists = $wpo_gzip_compression->is_gzip_compression_section_exists();
		$wpo_gzip_compression_enabled_by_wpo = $is_gzip_compression_section_exists && $wpo_gzip_compression_enabled && !$is_cloudflare_site && !(is_array($wpo_gzip_headers_information) && 'brotli' == $wpo_gzip_headers_information['compression']);

		WP_Optimize()->include_template('cache/gzip-compression.php', false, array(
			'wpo_gzip_headers_information' => $wpo_gzip_headers_information,
			'wpo_gzip_compression_enabled' => $wpo_gzip_compression_enabled,
			'is_cloudflare_site' => $is_cloudflare_site,
			'wpo_gzip_compression_enabled_by_wpo' => $wpo_gzip_compression_enabled_by_wpo,
			'wpo_gzip_compression_settings_added' => $is_gzip_compression_section_exists,
			'info_link' => 'https://getwpo.com/gzip-compression-explained/',
			'faq_link' => 'https://getwpo.com/gzip-faq-link/',
			'class_name' => (!is_wp_error($wpo_gzip_compression_enabled) && $wpo_gzip_compression_enabled ? 'wpo-enabled' : 'wpo-disabled')
		));
	}

	/**
	 * Cache tab
	 */
	public function output_cache_settings_tab() {

		$wpo_browser_cache = WP_Optimize()->get_browser_cache();
		$wpo_browser_cache_enabled = $wpo_browser_cache->is_enabled();

		WP_Optimize()->include_template('cache/browser-cache.php', false, array(
			'wpo_browser_cache_enabled' => $wpo_browser_cache_enabled,
			'is_cloudflare_site' => $this->is_cloudflare_site(),
			'wpo_browser_cache_settings_added' => $wpo_browser_cache->is_browser_cache_section_exists(),
			'class_name' => (true === $wpo_browser_cache_enabled ? 'wpo-enabled' : 'wpo-disabled'),
			'wpo_browser_cache_expire_days' => WP_Optimize()->get_options()->get_option('browser_cache_expire_days', '28'),
			'wpo_browser_cache_expire_hours' => WP_Optimize()->get_options()->get_option('browser_cache_expire_hours', '0'),
			'info_link' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching',
			'faq_link' => 'https://www.digitalocean.com/community/tutorials/how-to-implement-browser-caching-with-nginx-s-header-module-on-ubuntu-16-04',
		));
	}

	/**
	 * Check if is the current site handled with Cloudflare.
	 *
	 * @return bool
	 */
	public function is_cloudflare_site() {
		return isset($_SERVER['HTTP_CF_RAY']);
	}

	/**
	 * Include Cloudflare settings template.
	 */
	public function output_cloudflare_settings() {
		if (WP_Optimize::is_premium() || !apply_filters('show_cloudflare_settings', $this->is_cloudflare_site())) return;

		WP_Optimize()->include_template('cache/page-cache-cloudflare-placeholder.php');
	}

	/**
	 * Outputs the DB optimize Tab
	 */
	public function output_database_optimize_tab() {
		$optimizer = WP_Optimize()->get_optimizer();
		$options = WP_Optimize()->get_options();

				// check if nonce passed.
		$nonce_passed = (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'wpo_optimization')) ? true : false;

				// save options.
		if ($nonce_passed && isset($_POST['wp-optimize'])) $options->save_sent_manual_run_optimization_options($_POST, true);

		$optimize_db = ($nonce_passed && isset($_POST["optimize-db"])) ? true : false;

		$optimization_results = (($nonce_passed) ? $optimizer->do_optimizations($_POST) : false);

				// display optimizations table or restricted access message.
		if (WP_Optimize()->can_run_optimizations()) {
			WP_Optimize()->include_template('database/optimize-table.php', false, array('optimize_db' => $optimize_db, 'optimization_results' => $optimization_results, 'load_data' => false, 'does_server_allows_table_optimization' => WP_Optimize()->does_server_allows_table_optimization()));
		} else {
			$this->prevent_run_optimizations_message();
		}
	}

	/**
	 * Outputs the DB Tables Tab
	 */
	public function output_database_tables_tab() {
				// check if nonce passed.
		$nonce_passed = (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'wpo_optimization')) ? true : false;

		$optimize_db = ($nonce_passed && isset($_POST["optimize-db"])) ? true : false;

		if (!WP_Optimize()->does_server_allows_table_optimization()) {
			$message = __('Your server takes care of table optimization', 'wp-optimize');
			$this->prevent_run_optimizations_message($message);
		} elseif (WP_Optimize()->can_run_optimizations()) {
			WP_Optimize()->include_template('database/tables.php', false, array('optimize_db' => $optimize_db, 'load_data' => WP_Optimize()->template_should_include_data()));
		} else {
			$this->prevent_run_optimizations_message();
		}
	}

	/**
	 * Runs upon the WP action admin_page_wpo_images_unused
	 */
	public function admin_page_wpo_images_unused() {
		WP_Optimize()->include_template('images/unused.php');
	}

	/**
	 * Runs upon the WP action wp_optimize_admin_page_wpo_images_lazyload
	 */
	public function admin_page_wpo_images_lazyload() {
		WP_Optimize()->include_template('images/lazyload.php');
	}

	/**
	 * Show footer review message and link.
	 *
	 * @return string
	 */
	public function display_footer_review_message() {
		$message = sprintf(
			__('Enjoyed %s? Please leave us a %s rating. We really appreciate your support!', 'wp-optimize'),
			'<b>WP-Optimize</b>',
			'<a href="https://www.g2.com/products/wp-optimize/reviews" target="_blank">&starf;&starf;&starf;&starf;&starf;</a>'
		);
		return $message;
	}

	/**
	 * Adds menu in admin bar
	 */
	public function wpo_admin_bar() {
		$wp_admin_bar = $GLOBALS['wp_admin_bar'];

		if (defined('WPOPTIMIZE_ADMINBAR_DISABLE') && WPOPTIMIZE_ADMINBAR_DISABLE) return;

		// Show menu item in top bar only for super admins.
		if (is_multisite() & !is_super_admin(get_current_user_id())) return;

		// Add a link called at the top admin bar.
		$args = array(
			'id' => 'wp-optimize-node',
			'title' => apply_filters('wpoptimize_admin_node_title', 'WP-Optimize')
		);
		$wp_admin_bar->add_node($args);

		$pages = $this->get_submenu_items();

		foreach ($pages as $page_id => $page) {

			if (!isset($page['create_submenu']) || !$page['create_submenu']) {
				if (isset($page['icon']) && 'separator' == $page['icon']) {
					$args = array(
						'id' => 'wpo-separator-'.$page_id,
						'parent' => 'wp-optimize-node',
						'meta' => array(
							'class' => 'separator',
						),
					);
					$wp_admin_bar->add_node($args);
				}
				continue;
			}

		// 'menu_slug' => 'WP-Optimize',
					
			$menu_page_url = menu_page_url($page['menu_slug'], false);

			if (is_multisite()) {
				$menu_page_url = network_admin_url('admin.php?page='.$page['menu_slug']);
			}

			$args = array(
				'id' => 'wpoptimize_admin_node_'.$page_id,
				'title' => $page['menu_title'],
				'parent' => 'wp-optimize-node',
				'href' => $menu_page_url,
			);
			$wp_admin_bar->add_node($args);
		}

	}

	/**
	 * Manages the admin bar menu for caching (currently page and minify)
	 */
	public function cache_admin_bar($wp_admin_bar) {

		$options = WP_Optimize()->get_options();
		if (!$options->get_option('enable_cache_in_admin_bar', true)) return;

		/**
		 * The "purge cache" menu items
		 *
		 * @param array  $menu_items - The menu items, in the format required by $wp_admin_bar->add_menu()
		 * @param object $wp_admin_bar
		 */
		$menu_items = apply_filters('wpo_cache_admin_bar_menu_items', array(), $wp_admin_bar);

		if (empty($menu_items) || !is_array($menu_items)) return;

		$wp_admin_bar->add_menu(array(
			'id'    => 'wpo_purge_cache',
			'title' => __('Purge cache', 'wp-optimize'),
			'href'  => '#',
			'meta'  => array(
				'title' => __('Purge cache', 'wp-optimize'),
			),
			'parent' => false,
		));

		foreach ($menu_items as $item) {
			$wp_admin_bar->add_menu($item);
		}
	}

	/**
	 * Adds and displays menu and submenu pages
	 */
	public function admin_menu() {

		$capability_required = WP_Optimize()->capability_required();
		$can_run_optimizations = WP_Optimize()->can_run_optimizations();
		$can_manage_options = WP_Optimize()->can_manage_options();

		if (!current_user_can($capability_required) || (!$can_run_optimizations && !$can_manage_options)) return;

		$icon_svg = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcKICAgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIgogICB4bWxuczpjYz0iaHR0cDovL2NyZWF0aXZlY29tbW9ucy5vcmcvbnMjIgogICB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiCiAgIHhtbG5zOnN2Zz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiAgIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIKICAgdmlld0JveD0iMCAwIDE2IDE2IgogICB2ZXJzaW9uPSIxLjEiCiAgIGlkPSJzdmc0MzE2IgogICBoZWlnaHQ9IjE2IgogICB3aWR0aD0iMTYiPgogIDxkZWZzCiAgICAgaWQ9ImRlZnM0MzE4IiAvPgogIDxtZXRhZGF0YQogICAgIGlkPSJtZXRhZGF0YTQzMjEiPgogICAgPHJkZjpSREY+CiAgICAgIDxjYzpXb3JrCiAgICAgICAgIHJkZjphYm91dD0iIj4KICAgICAgICA8ZGM6Zm9ybWF0PmltYWdlL3N2Zyt4bWw8L2RjOmZvcm1hdD4KICAgICAgICA8ZGM6dHlwZQogICAgICAgICAgIHJkZjpyZXNvdXJjZT0iaHR0cDovL3B1cmwub3JnL2RjL2RjbWl0eXBlL1N0aWxsSW1hZ2UiIC8+CiAgICAgICAgPGRjOnRpdGxlPjwvZGM6dGl0bGU+CiAgICAgIDwvY2M6V29yaz4KICAgIDwvcmRmOlJERj4KICA8L21ldGFkYXRhPgogIDxnCiAgICAgaWQ9ImxheWVyMSI+CiAgICA8cGF0aAogICAgICAgc3R5bGU9ImZpbGw6I2EwYTVhYTtmaWxsLW9wYWNpdHk6MSIKICAgICAgIGlkPSJwYXRoNTciCiAgICAgICBkPSJtIDEwLjc2ODgwOSw2Ljc2MTYwNTEgMCwwIGMgLTAuMDE2ODgsLTAuMDE2ODc4IC0wLjAyNTMxLC0wLjA0MjE4MSAtMC4wMzM3NCwtMC4wNjc0OTkgLTAuMDA4NCwtMC4wMDgzOSAtMC4wMDg0LC0wLjAxNjg3OCAtMC4wMTY4OCwtMC4wMzM3NDMgQyA5Ljk5MjYxMTIsNS4xOTIzMzY2IDguMjIwODU1Nyw0LjU4NDg3ODEgNi43NDQzOTEyLDUuMjkzNTc5NyA1LjY3MjkwMDUsNS44MDgyMzI4IDUuMDU3MDA0Myw2Ljg4ODE2MTMgNS4wNjU0NDIsOC4wMDE4MzY1IDQuNDU3OTgyMiw3LjMxMDAwNzYgMy42OTg2NTg0LDYuNzk1MzU0NSAyLjg1NDk2NDIsNi40OTE2MjUzIDMuMjY4Mzc0Myw1LjA2NTc4MzEgNC4yNTU0OTYsMy44MTcxMTY2IDUuNjg5Nzc0NiwzLjEyNTI4NzggOC4zNjQyODMyLDEuODM0NDM2OCAxMS41NzAzMTksMi45Mzk2NzQ0IDEyLjg4NjQ4MSw1LjU4ODg3MjYgMTMuNDUxNzU1LDYuNzI3ODU5NiAxNC42NDk4MDEsNy4zNTIxOTIxIDE1Ljg0Nzg0Niw3LjIzNDA3NSAxNS43NjM0ODIsNi4zMzk3NiAxNS41MTg4MDUsNS40MzcwMDg2IDE1LjEwNTM5Niw0LjU3NjQ0MDQgMTMuMjE1NTIxLDAuNjg3MDEzNCA4LjUzMzAyMjYsLTAuOTQxMzE2MjcgNC42NDM1OTQzLDAuOTQwMTIxNzkgMi4zMjM0MzcsMi4wNjIyMzM0IDAuODA0Nzg4MTQsNC4xNzk5MDQ0IDAuMzU3NjMxMzIsNi41MzM4MDk4IDIuNDE2MjQzOCw2LjQyNDEyOSA0LjQzMjY3MTcsNy41MDQwNTc0IDUuNDM2NjY2Miw5LjQzNjExNjcgbCAwLjAwODM5LDAgYyAwLjc1OTMxOTIsMS4zNzUyMjAzIDIuNDcyMDE3OCwxLjk0MDQ5NTMgMy45MDYyOTYsMS4yNDg2NjczIDEuMDQ2MTc5OCwtMC41MDYyMTggMS42NTM2NDA4LC0xLjUzNTUyMzggMS42Nzg5NTA4LC0yLjYxNTQ1MTIgMC41ODIxNDgsMC43MDg3MDE4IDEuMzMzMDM1LDEuMjQ4NjY2OCAyLjE1OTg1NiwxLjU3NzcwNjQgLTAuNDM4NzIxLDEuMzU4MzQ3OCAtMS40MDA1MzMsMi41NDc5NTQ4IC0yLjc5MjYyNywzLjIxNDQ3ODggLTIuNTkwMTM4NywxLjI0ODY1OCAtNS42NzgwNTc0LDAuMjUzMTA0IC03LjA2MTcxNTEsLTIuMjI3MzU3IGwgMCwwIEMgMi43NjIxMDQ4LDkuNDUyOTg5NCAxLjUxMzQzODMsOC44MjAyMTkxIDAuMjgxNjQ1OTIsOC45NzIwODQ0IDAuMzgyODg3NjUsOS43OTg5MDQ2IDAuNjE5MTIzMzEsMTAuNjE3Mjg3IDAuOTk4Nzg1MiwxMS40MDE5MjIgYyAxLjg4MTQzNjgsMy44OTc4NjQgNi41NjM5MzcsNS41MjYxOTggMTAuNDYxODAwOCwzLjY0NDc2IDIuMjQ0MjI2LC0xLjA4ODM2OSAzLjczNzU2MiwtMy4xMDQ3OTYgNC4yMzUzNDIsLTUuMzc0MzMyMyAtMS45OTk1NTQsMC4wNDIxODEgLTMuOTQ4NDg2LC0xLjAyOTMwNjMgLTQuOTI3MTcsLTIuOTEwNzQzMyB6IgogICAgICAgY2xhc3M9InN0MTciIC8+CiAgPC9nPgo8L3N2Zz4K';

		// Removes the admin menu items on the left WP bar.
		if (!is_multisite() || (is_multisite() && is_network_admin())) {
			add_menu_page("WP-Optimize", "WP-Optimize", $capability_required, "WP-Optimize", array($this, "display_admin"), $icon_svg);

			$sub_menu_items = $this->get_submenu_items();

			foreach ($sub_menu_items as $menu_item) {
				if ($menu_item['create_submenu']) add_submenu_page('WP-Optimize', $menu_item['page_title'], $menu_item['menu_title'], $capability_required, $menu_item['menu_slug'], $menu_item['function']);
			}
		}

		$options = WP_Optimize()->get_options();

		if ('true' == $options->get_option('enable-admin-menu', 'false')) {
			add_action('wp_before_admin_bar_render', array($this, 'wpo_admin_bar'));
		}
	}

	/**
	 * Get the submenu items
	 *
	 * @return array
	 */
	public function get_submenu_items() {
		$sub_menu_items = array(
			array(
				'page_title' => __('Database', 'wp-optimize'),
				'menu_title' => __('Database', 'wp-optimize'),
				'menu_slug' => 'WP-Optimize',
				'function' => array($this, 'display_admin'),
				'icon' => 'cloud',
				'create_submenu' => true,
				'order' => 20,
			),
			array(
				'page_title' => __('Images', 'wp-optimize'),
				'menu_title' => __('Images', 'wp-optimize'),
				'menu_slug' => 'wpo_images',
				'function' => array($this, 'display_admin'),
				'icon' => 'images-alt2',
				'create_submenu' => true,
				'order' => 30,
			),
			array(
				'page_title' => __('Cache', 'wp-optimize'),
				'menu_title' => __('Cache', 'wp-optimize'),
				'menu_slug' => 'wpo_cache',
				'function' => array($this, 'display_admin'),
				'icon' => 'archive',
				'create_submenu' => true,
				'order' => 40,
			),
			array(
				'page_title' => __('Minify', 'wp-optimize'),
				'menu_title' => __('Minify', 'wp-optimize'),
				'menu_slug' => 'wpo_minify',
				'function' => array($this, 'display_admin'),
				'icon' => 'dashboard',
				'create_submenu' => true,
				'order' => 50,
			),
			array(
				'create_submenu' => false,
				'order' => 55,
				'icon' => 'separator',
			),
			array(
				'page_title' => __('Settings', 'wp-optimize'),
				'menu_title' => __('Settings', 'wp-optimize'),
				'menu_slug' => 'wpo_settings',
				'function' => array($this, 'display_admin'),
				'icon' => 'admin-settings',
				'create_submenu' => true,
				'order' => 60,
			),
			array(
				'page_title' => __('Support & FAQs', 'wp-optimize'),
				'menu_title' => __('Help', 'wp-optimize'),
				'menu_slug' => 'wpo_support',
				'function' => array($this, 'display_admin'),
				'icon' => 'sos',
				'create_submenu' => true,
				'order' => 60,
			),
			array(
				'page_title' => __('Premium Upgrade', 'wp-optimize'),
				'menu_title' => __('Premium Upgrade', 'wp-optimize'),
				'menu_slug' => 'wpo_mayalso',
				'function' => array($this, 'display_admin'),
				'icon' => 'admin-plugins',
				'create_submenu' => true,
				'order' => 70,
			),
		);

		$sub_menu_items = apply_filters('wp_optimize_sub_menu_items', $sub_menu_items);

		usort($sub_menu_items, array($this, 'order_sort'));

		return $sub_menu_items;
	}

	/**
	 * Order sorting function
	 */
	public function order_sort($a, $b) {
		if ($a['order'] == $b['order']) return 0;
		return ($a['order'] > $b['order']) ? 1 : -1;
	}
			
	/**
	 * Output information message for users who have no permissions to run optimizations.
	 *
	 * @param string $message Message to display
	 */
	public function prevent_run_optimizations_message($message = '') {
		if (empty($message)) {
			$message = __('You have no permissions to run optimizations.', 'wp-optimize');
		}
		WP_Optimize()->include_template('info-message.php', false, array('message' => $message));
	}

	/**
	 * Output information message for users who have no permissions to manage settings.
	 */
	public function prevent_manage_options_info() {
		WP_Optimize()->include_template('info-message.php', false, array('message' => __('You have no permissions to manage WP-Optimize settings.', 'wp-optimize')));
	}

	/**
	 * Output success/error messages from $output array.
	 *
	 * @param array $output ['messages' => success messages, 'errors' => error messages]
	 */
	private function wpo_render_output_messages($output) {
		foreach ($output['messages'] as $item) {
			echo '<div class="updated fade below-h2"><strong>'.$item.'</strong></div>';
		}

		foreach ($output['errors'] as $item) {
			echo '<div class="error fade below-h2"><strong>'.$item.'</strong></div>';
		}
	}
}
endif;
