<?php
/**
Plugin Name: WP-Optimize - Clean, Compress, Cache
Plugin URI: https://getwpo.com
Description: WP-Optimize makes your site fast and efficient. It cleans the database, compresses images and caches pages. Fast sites attract more traffic and users.
Version: 3.2.6
Update URI: https://wordpress.org/plugins/wp-optimize/
Author: David Anderson, Ruhani Rabin, Team Updraft
Author URI: https://updraftplus.com
Text Domain: wp-optimize
Domain Path: /languages
License: GPLv2 or later
 */

if (!defined('ABSPATH')) die('No direct access allowed');

// Check to make sure if WP_Optimize is already call and returns.
if (!class_exists('WP_Optimize')) :
define('WPO_VERSION', '3.2.6');
define('WPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPO_PLUGIN_MAIN_PATH', plugin_dir_path(__FILE__));
define('WPO_PREMIUM_NOTIFICATION', false);
define('WPO_MINIFY_PHP_VERSION_MET', version_compare(PHP_VERSION, '5.4', '>=') ? true : false);

class WP_Optimize {

	public $premium_version_link = 'https://getwpo.com/buy/';

	private $template_directories;

	protected static $_instance = null;

	protected static $_optimizer_instance = null;

	protected static $_options_instance = null;

	protected static $_minify_instance = null;

	protected static $_notices_instance = null;

	protected static $_logger_instance = null;

	protected static $_browser_cache = null;

	protected static $_db_info = null;

	protected static $_cache = null;

	protected static $_gzip_compression = null;

	/**
	 * Class constructor
	 */
	public function __construct() {
		spl_autoload_register(array($this, 'loader'));

		// Checks if premium is installed along with plugins needed.
		add_action('plugins_loaded', array($this, 'plugins_loaded'), 1);
		
		register_activation_hook(__FILE__, 'wpo_activation_actions');
		register_deactivation_hook(__FILE__, 'wpo_deactivation_actions');
		register_uninstall_hook(__FILE__, 'wpo_uninstall_actions');
		
		$this->load_admin();
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_bar_menu', array($this, 'cache_admin_bar'), 100, 1);

		add_filter("plugin_action_links_".plugin_basename(__FILE__), array($this, 'plugin_settings_link'));
		add_action('wpo_cron_event2', array($this, 'cron_action'));
		add_filter('cron_schedules', array($this, 'cron_schedules'));

		if (!$this->get_options()->get_option('installed-for', false)) $this->get_options()->update_option('installed-for', time());

		if (!self::is_premium()) {
			add_action('auto_option_settings', array($this->get_options(), 'auto_option_settings'));
		}

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

		add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));

		add_action('wp_ajax_wp_optimize_ajax', array($this, 'wp_optimize_ajax_handler'));

		// Show update to Premium notice for non-premium multisite.
		add_action('wpo_additional_options', array($this, 'show_multisite_update_to_premium_notice'));

		// Action column (show repair button if need).
		add_filter('wpo_tables_list_additional_column_data', array($this, 'tables_list_additional_column_data'), 15, 2);

		/**
		 * Add action for display Images > Compress images tab.
		 */
		add_action('wp_optimize_admin_page_wpo_images_smush', array($this, 'admin_page_wpo_images_smush'));

		include_once(WPO_PLUGIN_MAIN_PATH.'includes/updraftcentral.php');

		include_once(WPO_PLUGIN_MAIN_PATH.'includes/backward-compatibility-functions.php');
				
		register_shutdown_function(array($this, 'log_fatal_errors'));

		$this->schedule_plugin_cron_tasks();

		add_action('wpo_admin_before_closing_wrap', array($this, 'load_modal_template'), 20);

		add_action('upgrader_process_complete', array($this, 'detect_active_plugins_and_themes_updates'), 10, 2);

		$import_done_hooks = array(
			'import_end', // wordpress importer
			'pmxi_after_xml_import', // wp all import
		);

		$db_update_hooks = apply_filters('wp_optimize_db_update_hooks', $import_done_hooks);

		foreach ($db_update_hooks as $hook) {
			add_action($hook, array($this, 'maybe_schedule_update_record_count_event'));
		}
		add_action('wpo_update_record_count_event', array($this->get_db_info(), 'wpo_update_record_count'));
	}

	/**
	 * Auto-loads classes.
	 *
	 * @param string $class_name The name of the class.
	 */
	private function loader($class_name) {
		$dirs = $this->get_class_directories();

		foreach ($dirs as $dir) {
			$class_file = WPO_PLUGIN_MAIN_PATH . $dir . '/class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
			if (file_exists($class_file)) {
				require_once($class_file);
				return;
			}
		}
	}

	/**
	 * Returns an array of class directories
	 *
	 * @return array
	 */
	private function get_class_directories() {
		return array(
			'cache',
			'includes',
			'minify',
			'optimizations',
			'webp',
		);
	}

	/**
	 * Initialize Admin class to load admin UI
	 */
	private function load_admin() {
		$this->get_admin_instance();
	}

	/**
	 * Returns Admin class instance
	 *
	 * @return WP_Optimize_Admin
	 */
	public function get_admin_instance() {
		return WP_Optimize_Admin::instance();
	}
	
	/**
	 * Detect when an active plugin or theme is updated, and trigger an action
	 *
	 * @param object $upgrader_object
	 * @param array  $options
	 * @return void
	 */
	public function detect_active_plugins_and_themes_updates($upgrader_object, $options) {
		if (empty($options) || !isset($options['type'])) return;
		
		$should_purge_cache = false;
		$skin = $upgrader_object->skin;
		if ('plugin' === $options['type']) {
			// A plugin is updated using the default update system (upgrader_overwrote_package is used for the upload method)
			if (property_exists($skin, 'plugin_active') && $skin->plugin_active) {
				$should_purge_cache = true;
			}
		} elseif ('theme' === $options['type']) {
			$active_theme = get_stylesheet();
			$parent_theme = get_template();
			// A theme is updated using the upload system
			if (isset($options['action']) && 'install' === $options['action'] && 'update-theme' === $skin->options['overwrite']) {
				$updated_theme = $upgrader_object->result['destination_name'];
				// Check if the theme is in use
				if ($active_theme == $updated_theme || $parent_theme == $updated_theme) {
					$should_purge_cache = true;
				}
			// A theme is updated using the classic update system
			} elseif (isset($options['action']) && 'update' === $options['action'] && isset($options['themes']) && is_array($options['themes'])) {
				// Check if the theme is in use
				if (in_array($active_theme, $options['themes']) || in_array($parent_theme, $options['themes'])) {
					$should_purge_cache = true;
				}
			}
		}

		/**
		 * Action executed when an active theme or plugin was updated
		 */
		if ($should_purge_cache) do_action('wpo_active_plugin_or_theme_updated');

	}

	/**
	 * Sets a flag to indicate an import action is done, if needed
	 */
	public function maybe_schedule_update_record_count_event() {
		if (!wp_next_scheduled('wpo_update_record_count_event')) {
			wp_schedule_single_event(time() + 60, 'wpo_update_record_count_event');
		}
	}
			
	public function admin_page_wpo_images_smush() {
		$options = Updraft_Smush_Manager()->get_smush_options();
		$custom = 100 != $options['image_quality'] && 60 != $options['image_quality'] ? true : false;
		$this->include_template('images/smush.php', false, array('smush_options' => $options, 'custom' => $custom, 'does_server_allows_local_webp_conversion' => $this->does_server_allows_local_webp_conversion()));
	}

	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public static function get_optimizer() {
		if (empty(self::$_optimizer_instance)) {
			self::$_optimizer_instance = new WP_Optimizer();
		}
		return self::$_optimizer_instance;
	}

	/**
	 * Get and instanciate WP_Optimize_Minify
	 *
	 * @return WP_Optimize_Minify
	 */
	public function get_minify() {
		if (empty(self::$_minify_instance)) {
			self::$_minify_instance = new WP_Optimize_Minify();
		}
		return self::$_minify_instance;
	}

	public static function get_options() {
		if (empty(self::$_options_instance)) {
			self::$_options_instance = new WP_Optimize_Options();
		}
		return self::$_options_instance;
	}

	public static function get_notices() {
		if (empty(self::$_notices_instance)) {
			self::$_notices_instance = new WP_Optimize_Notices();
		}
		return self::$_notices_instance;
	}

	/**
	 * Returns instance if WPO_Page_Cache class.
	 *
	 * @return WPO_Page_Cache
	 */
	public function get_page_cache() {
		return WPO_Page_Cache::instance();
	}

	/**
	 * Returns instance if WP_Optimize_WebP class.
	 *
	 * @return WP_Optimize_WebP
	 */
	public function get_webp_instance() {
		return WP_Optimize_WebP::get_instance();
	}

	/**
	 * Detects if the platform is Kinsta or not
	 *
	 * @return bool Returns true if it is Kinsta platform, otherwise returns false
	 */
	private function is_kinsta() {
		return isset($_SERVER['KINSTA_CACHE_ZONE']);
	}

	/**
	 * Detects whether the server handles cache. eg. Nginx cache
	 */
	public function does_server_handles_cache() {
		return $this->is_kinsta();
	}

	/**
	 * Detects whether the server supports table optimization.
	 *
	 * Some servers prevent table optimization
	 * because InnoDB engine does not optimize table
	 * instead it drops tables and recreate them
	 * which results in elevated disk write operations
	 */
	public function does_server_allows_table_optimization() {
		return !$this->is_kinsta();
	}

	/**
	 * Detects whether the server supports local webp conversion tools
	 */
	private function does_server_allows_local_webp_conversion() {
		return !$this->is_kinsta();
	}

	/**
	 * Create instance of WP_Optimize_Browser_Cache.
	 *
	 * @return WP_Optimize_Browser_Cache
	 */
	public static function get_browser_cache() {
		if (empty(self::$_browser_cache)) {
			self::$_browser_cache = new WP_Optimize_Browser_Cache();
		}
		return self::$_browser_cache;
	}

	/**
	 * Returns WP_Optimize_Database_Information instance.
	 *
	 * @return WP_Optimize_Database_Information
	 */
	public function get_db_info() {
		if (empty(self::$_db_info)) {
			self::$_db_info = new WP_Optimize_Database_Information();
		}
		return self::$_db_info;
	}

	/**
	 * Returns instance of WP_Optimize_Gzip_Compression.
	 *
	 * @return WP_Optimize_Gzip_Compression
	 */
	static public function get_gzip_compression() {
		if (empty(self::$_gzip_compression)) {
			self::$_gzip_compression = new WP_Optimize_Gzip_Compression();
		}
		return self::$_gzip_compression;
	}

	/**
	 * Create instance of WP_Optimize_Htaccess.
	 *
	 * @param string $htaccess_file absolute path to htaccess file, by default it use .htaccess in WordPress root directory.
	 * @return WP_Optimize_Htaccess
	 */
	public static function get_htaccess($htaccess_file = '') {
		return new WP_Optimize_Htaccess($htaccess_file);
	}

	/**
	 * Return instance of Updraft_Logger
	 *
	 * @return Updraft_Logger
	 */
	public static function get_logger() {
		if (empty(self::$_logger_instance)) {
			self::$_logger_instance = new Updraft_Logger();
		}
		return self::$_logger_instance;
	}

	/**
	 * Check if the current page belongs to WP-Optimize.
	 *
	 * @return bool
	 */
	public function is_wpo_page() {
		$current_screen = get_current_screen();

		return (bool) preg_match('/wp\-optimize/i', $current_screen->id);
	}

	/**
	 * Enqueue scripts and styles on WP-Optimize pages.
	 */
	public function admin_enqueue_scripts() {
		$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? WPO_VERSION.'.'.time() : WPO_VERSION;
		$min_or_not = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';

		// Register or enqueue common scripts
		wp_register_script('wp-optimize-send-command', WPO_PLUGIN_URL.'js/send-command'.$min_or_not_internal.'.js', array(), $enqueue_version);
		wp_localize_script('wp-optimize-send-command', 'wp_optimize_send_command_data', array('nonce' => wp_create_nonce('wp-optimize-ajax-nonce')));
		wp_enqueue_style('wp-optimize-global', WPO_PLUGIN_URL.'css/wp-optimize-global'.$min_or_not_internal.'.css', array(), $enqueue_version);

		// load scripts and styles only on WP-Optimize pages.
		if (!$this->is_wpo_page()) return;
				
		wp_enqueue_script('jquery-serialize-json', WPO_PLUGIN_URL.'js/serialize-json/jquery.serializejson'.$min_or_not.'.js', array('jquery'), $enqueue_version);

		wp_register_script('updraft-queue-js', WPO_PLUGIN_URL.'js/queue'.$min_or_not_internal.'.js', array(), $enqueue_version);
		wp_enqueue_script('wp-optimize-modal', WPO_PLUGIN_URL.'js/modal'.$min_or_not_internal.'.js', array('jquery', 'backbone', 'wp-util'), $enqueue_version);
		wp_enqueue_script('wp-optimize-cache-js', WPO_PLUGIN_URL.'js/cache'.$min_or_not_internal.'.js', array('wp-optimize-send-command', 'smush-js'), $enqueue_version);
		wp_enqueue_script('wp-optimize-admin-js', WPO_PLUGIN_URL.'js/wpoadmin'.$min_or_not_internal.'.js', array('jquery', 'updraft-queue-js', 'wp-optimize-send-command', 'smush-js', 'wp-optimize-modal'), $enqueue_version);
		wp_enqueue_style('wp-optimize-admin-css', WPO_PLUGIN_URL.'css/wp-optimize-admin'.$min_or_not_internal.'.css', array(), $enqueue_version);
		// Using tablesorter to help with organising the DB size on Table Information
		// https://github.com/Mottie/tablesorter
		wp_enqueue_script('tablesorter-js', WPO_PLUGIN_URL.'js/tablesorter/jquery.tablesorter'.$min_or_not.'.js', array('jquery', 'wp-optimize-send-command'), $enqueue_version);

		wp_enqueue_script('tablesorter-widgets-js', WPO_PLUGIN_URL.'js/tablesorter/jquery.tablesorter.widgets'.$min_or_not.'.js', array('jquery'), $enqueue_version);

		// wp_enqueue_style('tablesorter-css', WPO_PLUGIN_URL.'css/tablesorter/theme.default.min.css', array(), $enqueue_version);

		$js_variables = $this->wpo_js_translations();
		$js_variables['loggers_classes_info'] = $this->get_loggers_classes_info();

		wp_localize_script('wp-optimize-admin-js', 'wpoptimize', $js_variables);

		do_action('wpo_premium_scripts_styles', $min_or_not_internal, $min_or_not, $enqueue_version);
	}

	/**
	 * Enqueue any required front-end scripts
	 *
	 * @return void
	 */
	public function frontend_enqueue_scripts() {
		if (!current_user_can('manage_options') || !is_admin_bar_showing()) return;
		$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? WPO_VERSION.'.'.time() : WPO_VERSION;
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';

		// Register or enqueue common scripts
		wp_enqueue_style('wp-optimize-global', WPO_PLUGIN_URL.'css/wp-optimize-global'.$min_or_not_internal.'.css', array(), $enqueue_version);
	}

	/**
	 * Load Task Manager
	 */
	public function get_task_manager() {
		include_once(WPO_PLUGIN_MAIN_PATH.'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-tasks-activation.php');

		Updraft_Tasks_Activation::check_updates();

		include_once(WPO_PLUGIN_MAIN_PATH . '/vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-meta.php');
		include_once(WPO_PLUGIN_MAIN_PATH . '/vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-options.php');
		include_once(WPO_PLUGIN_MAIN_PATH . '/vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task.php');
				
		include_once(WPO_PLUGIN_MAIN_PATH . '/includes/class-updraft-smush-task.php');
		include_once(WPO_PLUGIN_MAIN_PATH . '/includes/class-updraft-smush-manager.php');

		return Updraft_Smush_Manager();
	}

	/**
	 * Indicate whether we have an associated instance of WP-Optimize Premium or not.
	 *
	 * @returns Boolean
	 */
	public static function is_premium() {
		if (file_exists(WPO_PLUGIN_MAIN_PATH.'premium.php') && function_exists('WP_Optimize_Premium')) {
			$wp_optimize_premium = WP_Optimize_Premium();
			if (is_a($wp_optimize_premium, 'WP_Optimize_Premium')) return true;
		}
		return false;
	}

	/**
	 * Check if script running on Apache web server. $is_apache is set in wp-includes/vars.php. Also returns true if the server uses litespeed.
	 *
	 * @return bool
	 */
	public function is_apache_server() {
		global $is_apache;
		return $is_apache;
	}

	/**
	 * Check if script running on IIS web server.
	 *
	 * @return bool
	 */
	public function is_IIS_server() {
		global $is_IIS, $is_iis7;
		return $is_IIS || $is_iis7;
	}

	/**
	 * Check if Apache module or modules active.
	 *
	 * @param string|array $module - single Apache module name or list of Apache module names.
	 *
	 * @return bool|null - if null, the result was indeterminate
	 */
	public function is_apache_module_loaded($module) {
		if (!$this->is_apache_server()) return false;
				
		if (!function_exists('apache_get_modules')) return null;

		$module_loaded = true;

		if (is_array($module)) {
			foreach ($module as $single_module) {
				if (!in_array($single_module, apache_get_modules())) {
					$module_loaded = false;
					break;
				}
			}
		} else {
			$module_loaded = in_array($module, apache_get_modules());
		}

		return $module_loaded;
	}

	/**
	 * Checks if this is the premium version and loads it. It also ensures that if the free version is installed then it is disabled with an appropriate error message.
	 */
	public function plugins_loaded() {

		if ($this->does_server_handles_cache()) {
			add_filter('wp_optimize_admin_page_wpo_cache_tabs', array($this, 'filter_cache_tabs'), 99, 1);

			// If newly migrated to server that handles cache, disable wpo cache
			$cache = $this->get_page_cache();
			if ($cache->is_enabled()) {
				$cache->disable();
			}
		}

		add_filter('robots_txt', array($this, 'robots_txt'), 99, 1);

		// Run Premium loader if it exists
		if (file_exists(WPO_PLUGIN_MAIN_PATH.'premium.php') && !class_exists('WP_Optimize_Premium')) {
			include_once(WPO_PLUGIN_MAIN_PATH.'premium.php');
		}

		// load defaults
		WP_Optimize()->get_options()->set_default_options();

		// Initialize loggers.
		$this->setup_loggers();

		if ($this->is_active('premium') && false !== ($free_plugin = $this->is_active('free'))) {
			if (!function_exists('deactivate_plugins')) include_once(ABSPATH.'wp-admin/includes/plugin.php');
			deactivate_plugins($free_plugin);

			// If WPO_ADVANCED_CACHE is defined, we empty advanced-cache.php to regenerate later. Otherwise it contains the path to free.
			if (defined('WPO_ADVANCED_CACHE') && WPO_ADVANCED_CACHE) {
				$advanced_cache_filename = trailingslashit(WP_CONTENT_DIR) . 'advanced-cache.php';

				if (!is_file($advanced_cache_filename) && is_writable(dirname($advanced_cache_filename)) || (is_file($advanced_cache_filename) && is_writable($advanced_cache_filename))) {
					file_put_contents($advanced_cache_filename, '');
				}
			}

					// Registers the notice letting the user know it cannot be active if premium is active.
			add_action('admin_notices', array($this, 'show_admin_notice_premium'));
			return;
		}

				// Loads the task manager
		$this->get_task_manager();

		// Loads the language file.
		load_plugin_textdomain('wp-optimize', false, dirname(plugin_basename(__FILE__)) . '/languages');

		// Load page cache.
		$this->get_page_cache();
		$this->init_page_cache();

		// Include minify
		$this->get_minify();
		$this->run_updates();
		$this->get_webp_instance();
	}

	/**
	 * Filter cache tabs (when it is Kinsta)
	 *
	 * @param  array $tabs An array of tabs
	 *
	 * @return array $tabs An array of tabs
	 */
	public function filter_cache_tabs($tabs) {
		unset($tabs['preload']);
		unset($tabs['advanced']);
		unset($tabs['gzip']);
		unset($tabs['settings']);
		return $tabs;
	}

	/**
	 * Check whether one of free/Premium is active (whether it is this instance or not)
	 *
	 * @param String $which - 'free' or 'premium'
	 *
	 * @return String|Boolean - plugin path (if installed) or false if not
	 */
	private function is_active($which = 'free') {
		$active_plugins = $this->get_active_plugins();
		foreach ($active_plugins as $file) {
			if ('wp-optimize.php' == basename($file)) {
				$plugin_dir = WP_PLUGIN_DIR.'/'.dirname($file);
				if (('free' == $which && !file_exists($plugin_dir.'/premium.php')) || ('free' != $which && file_exists($plugin_dir.'/premium.php'))) return $file;
			}
		}
		return false;
	}

	/**
	 * Gets an array of plugins active on either the current site, or site-wide
	 *
	 * @return Array - a list of plugin paths (relative to the plugin directory)
	 */
	private function get_active_plugins() {

		// Gets all active plugins on the current site
		$active_plugins = get_option('active_plugins');

		if (is_multisite()) {
			$network_active_plugins = get_site_option('active_sitewide_plugins');
			if (!empty($network_active_plugins)) {
				$network_active_plugins = array_keys($network_active_plugins);
				$active_plugins = array_merge($active_plugins, $network_active_plugins);
			}
		}

		return $active_plugins;
	}

	/**
	 * This function checks whether a specific plugin is installed, and returns information about it
	 *
	 * @param  string $name Specify "Plugin Name" to return details about it.
	 * @return array        Returns an array of details such as if installed, the name of the plugin and if it is active.
	 */
	public function is_installed($name) {

		// Needed to have the 'get_plugins()' function
		include_once(ABSPATH.'wp-admin/includes/plugin.php');

		// Gets all plugins available
		$get_plugins = get_plugins();

		$active_plugins = $this->get_active_plugins();

		$plugin_info = array();
		$plugin_info['installed'] = false;
		$plugin_info['active'] = false;

		// Loops around each plugin available.
		foreach ($get_plugins as $key => $value) {
			// If the plugin name matches that of the specified name, it will gather details.
			if ($value['Name'] != $name && $value['TextDomain'] != $name) continue;
			$plugin_info['installed'] = true;
			$plugin_info['name'] = $key;
			$plugin_info['version'] = $value['Version'];
			if (in_array($key, $active_plugins)) {
				$plugin_info['active'] = true;
			}
			break;
		}
		return $plugin_info;
	}

	/**
	 * This is a notice to show users that premium is installed
	 */
	public function show_admin_notice_premium() {
		echo '<div id="wp-optimize-premium-installed-warning" class="error"><p>'.__('WP-Optimize (Free) has been de-activated, because WP-Optimize Premium is active.', 'wp-optimize').'</p></div>';
		if (isset($_GET['activate'])) unset($_GET['activate']);
	}

	/**
	 * Show update to Premium notice for non-premium multisite.
	 */
	public function show_multisite_update_to_premium_notice() {
		if (!is_multisite() || self::is_premium()) return;

		echo '<p><a href="'.$this->premium_version_link.'">'.__('New feature: WP-Optimize Premium can now optimize all sites within a multisite install, not just the main one.', 'wp-optimize').'</a></p>';
	}

	public function admin_init() {
		$pagenow = $GLOBALS['pagenow'];

		$this->register_template_directories();

		if (('index.php' == $pagenow && current_user_can('update_plugins')) || ('index.php' == $pagenow && defined('WP_OPTIMIZE_FORCE_DASHNOTICE') && WP_OPTIMIZE_FORCE_DASHNOTICE)) {
			$options = $this->get_options();

			$dismissed_until = $options->get_option('dismiss_dash_notice_until', 0);

			if (file_exists(WPO_PLUGIN_MAIN_PATH . '/index.html')) {
				$installed = filemtime(WPO_PLUGIN_MAIN_PATH . '/index.html');
				$installed_for = (time() - $installed);
			}

			if (($installed && time() > $dismissed_until && $installed_for > (14 * 86400) && !defined('WP_OPTIMIZE_NOADS_B')) || (defined('WP_OPTIMIZE_FORCE_DASHNOTICE') && WP_OPTIMIZE_FORCE_DASHNOTICE)) {
				add_action('all_admin_notices', array($this, 'show_admin_notice_upgradead'));
			}
		}
		$this->install_or_update_notice = $this->get_install_or_update_notice();
		if ($this->is_wp_smush_installed()) {
			add_filter('transient_wp-smush-conflict_check', array($this, 'modify_wp_smush_conflict_check'), 9, 1);
		}
	}

	/**
	 * Checks whether the WP Smush plugin is active or not
	 *
	 * @return bool
	 */
	private function is_wp_smush_installed() {
		return is_plugin_active('wp-smushit/wp-smush.php');
	}

	/**
	 * Remove WPO plugin name from WP Smushit transient value
	 *
	 * @return array $active_plugins
	 */
	public function modify_wp_smush_conflict_check($active_plugins) {
		// This can be boolean value since it is return value of get_transient
		if (!is_array($active_plugins)) return $active_plugins;

		if (false !== ($key = array_search('WP-Optimize - Clean, Compress, Cache', $active_plugins))) {
			unset($active_plugins[$key]);
		}
		return $active_plugins;
	}

	/**
	 * Get the install or update notice instance
	 *
	 * @return WP_Optimize_Install_Or_Update_Notice
	 */
	public function get_install_or_update_notice() {
		static $instance = null;
		if (is_a($instance, 'WP_Optimize_Install_Or_Update_Notice')) return $instance;
		$instance = new WP_Optimize_Install_Or_Update_Notice();
		return $instance;
	}

	public function show_admin_notice_upgradead() {
		$this->include_template('notices/thanks-for-using-main-dash.php');
	}
			
	public function capability_required() {
		return apply_filters('wp_optimize_capability_required', 'manage_options');
	}

	public function wp_optimize_ajax_handler() {
		$nonce = empty($_POST['nonce']) ? '' : $_POST['nonce'];

		if (!wp_verify_nonce($nonce, 'wp-optimize-ajax-nonce') || empty($_POST['subaction'])) {
			wp_send_json(array(
				'result' => false,
				'error_code' => 'security_check',
				'error_message' => __('The security check failed; try refreshing the page.', 'wp-optimize')
			));
		}

		$subaction = $_POST['subaction'];
		$data = isset($_POST['data']) ? $_POST['data'] : null;

		if (!current_user_can($this->capability_required())) {
			wp_send_json(array(
				'result' => false,
				'error_code' => 'security_check',
				'error_message' => __('You are not allowed to run this command.', 'wp-optimize')
			));
		}


		// Currently the settings are only available to network admins.
		if (is_multisite() && !current_user_can('manage_network_options')) {
		/**
		 * Filters the commands allowed to the subsite admins. Other commands are only available to network admin. Only used in a multisite context.
		 */
			$allowed_commands = apply_filters('wpo_multisite_allowed_commands', array('check_server_status', 'compress_single_image', 'restore_single_image'));
			if (!in_array($subaction, $allowed_commands)) wp_send_json(array(
				'result' => false,
				'error_code' => 'update_failed',
				'error_message' => __('Options can only be saved by network admin', 'wp-optimize')
			));
		}
				
		$options = $this->get_options();

		$results = array();

		// Some commands that are available via AJAX only.
		if (in_array($subaction, array('dismiss_dash_notice_until', 'dismiss_season'))) {
			$options->update_option($subaction, (time() + 366 * 86400));
		} elseif (in_array($subaction, array('dismiss_page_notice_until', 'dismiss_notice'))) {
			$options->update_option($subaction, (time() + 84 * 86400));
		} elseif ('dismiss_review_notice' == $subaction) {
		if (empty($data['dismiss_forever'])) {
			$options->update_option($subaction, time() + 84*86400);
		} else {
			$options->update_option($subaction, 100 * (365.25 * 86400));
		}
		} else {

			$commands = new WP_Optimize_Commands();
			$minify_commands = new WP_Optimize_Minify_Commands();

			
			if (self::is_premium()) {
				$cache_commands = new WP_Optimize_Cache_Commands_Premium();
			} else {
				$cache_commands = new WP_Optimize_Cache_Commands();
			}

			// check if called command not in main commands class and exist in cache commands class then change class.
			if (!is_callable(array($commands, $subaction)) && is_callable(array($minify_commands, $subaction))) {
				$commands = $minify_commands;
			}

			// check if called command not in main commands class and exist in cache commands class then change class.
			if (!is_callable(array($commands, $subaction)) && is_callable(array($cache_commands, $subaction))) {
				$commands = $cache_commands;
			}

			if (!is_callable(array($commands, $subaction))) {
				error_log("WP-Optimize: ajax_handler: no such command (".$subaction.")");
				$results = array(
					'result' => false,
					'error_code' => 'command_not_found',
					'error_message' => sprintf(__('The command "%s" was not found', 'wp-optimize'), $subaction)
				);
			} else {
				$results = call_user_func(array($commands, $subaction), $data);

				// clean status box content, it broke json sometimes.
				if (isset($results['status_box_contents'])) {
					$results['status_box_contents'] = str_replace(array("\n", "\t"), '', $results['status_box_contents']);
				}

				if (is_wp_error($results)) {
					$results = array(
						'result' => false,
						'error_code' => $results->get_error_code(),
						'error_message' => $results->get_error_message(),
						'error_data' => $results->get_error_data(),
					);
				}

				// if nothing was returned for some reason, set as result null.
				if (empty($results)) {
					$results = array(
						'result' => null
					);
				}
			}
		}

		$result = json_encode($results);

		// Requires PHP 5.3+
		$json_last_error = function_exists('json_last_error') ? json_last_error() : false;

		// if json_encode returned error then return error.
		if ($json_last_error) {
			$result = array(
				'result' => false,
				'error_code' => $json_last_error,
				'error_message' => 'json_encode error : '.$json_last_error,
				'error_data' => '',
			);

			$result = json_encode($result);
		}

		echo $result;

		die;
	}

	/**
	 * Returns array of translations used in javascript code.
	 *
	 * @return array
	 */
	public function wpo_js_translations() {
		return apply_filters('wpo_js_translations', array(
			'automatic_backup_before_optimizations' => __('Automatic backup before optimizations', 'wp-optimize'),
			'error_unexpected_response' => __('An unexpected response was received.', 'wp-optimize'),
			'optimization_complete' => __('Optimization complete', 'wp-optimize'),
			'with_warnings' => __('(with warnings - open the browser console for more details)', 'wp-optimize'),
			'optimizing_table' => __('Optimizing table:', 'wp-optimize'),
			'run_optimizations' => __('Run optimizations', 'wp-optimize'),
			'table_optimization_timeout' => 120000,
			'cancel' => __('Cancel', 'wp-optimize'),
			'cancelling' => __('Cancelling...', 'wp-optimize'),
			'enable' => __('Enable', 'wp-optimize'),
			'disable' => __('Disable', 'wp-optimize'),
			'please_select_settings_file' => __('Please, select settings file.', 'wp-optimize'),
			'are_you_sure_you_want_to_remove_logging_destination' => __('Are you sure you want to remove this logging destination?', 'wp-optimize'),
			'fill_all_settings_fields' => __('Before saving, you need to complete the currently incomplete settings (or remove them).', 'wp-optimize'),
			'table_was_not_repaired' => __('%s was not repaired. For more details, please check the logs (configured in your logging destinations settings).', 'wp-optimize'),
			'table_was_not_deleted' => __('%s was not deleted. For more details, please check your logs configured in logging destinations settings.', 'wp-optimize'),
			'table_was_not_converted' => __('%s was not converted to InnoDB. For more details, please check your logs configured in logging destinations settings.', 'wp-optimize'),
			'please_use_positive_integers' => __('Please use positive integers.', 'wp-optimize'),
			'please_use_valid_values' => __('Please use valid values.', 'wp-optimize'),
			'update' => __('Update', 'wp-optimize'),
			'run_now' => __('Run now', 'wp-optimize'),
			'starting_preload' => __('Started preload...', 'wp-optimize'),
			'loading_urls' => __('Loading URLs...', 'wp-optimize'),
			'current_cache_size' => __('Current cache size:', 'wp-optimize'),
			'number_of_files' => __('Number of files:', 'wp-optimize'),
			'toggle_info' => __('Show information', 'wp-optimize'),
			'added_to_list' => __('Added to the list', 'wp-optimize'),
			'added_notice' => __('The file was added to the list', 'wp-optimize'),
			'save_notice' => __('Save the changes', 'wp-optimize'),
			'page_refresh' => __('Refreshing the page to reflect changes...', 'wp-optimize'),
			'settings_have_been_deleted_successfully' => __('WP-Optimize settings have been deleted successfully.', 'wp-optimize'),
			'loading_data' => __('Loading data...', 'wp-optimize'),
			'spinner_src' => esc_attr(admin_url('images/spinner-2x.gif')),
			'settings_page_url' => is_multisite() ? network_admin_url('admin.php?page=wpo_settings') : admin_url('admin.php?page=wpo_settings'),
			'sites' => $this->get_sites(),
			'user_always_ignores_table_delete_warning' => (get_user_meta(get_current_user_id(), 'wpo-ignores-table-delete-warning', true)) ? true : false,
			'post_meta_tweak_completed' => __('The tweak has been performed.', 'wp-optimize'),
			'no_minified_assets' => __('No minified files are present', 'wp-optimize'),
		));
	}

	/**
	 * Manages the admin bar menu for caching (currently page and minify)
	 */
	public function cache_admin_bar($wp_admin_bar) {

		$options = $this->get_options();
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
	 * Add settings link on plugin page
	 *
	 * @param  string $links Passing through the URL to be used within the HREF.
	 * @return string        Returns the Links.
	 */
	public function plugin_settings_link($links) {

		$admin_page_url = $this->get_options()->admin_page_url();
		$settings_page_url = $this->get_options()->admin_page_url('wpo_settings');

		if (false == self::is_premium()) {
			$premium_link = '<a href="' . esc_url($this->premium_version_link) . '" target="_blank">' . __('Premium', 'wp-optimize') . '</a>';
			array_unshift($links, $premium_link);
		}

		$settings_link = '<a href="' . esc_url($settings_page_url) . '">' . __('Settings', 'wp-optimize') . '</a>';
		array_unshift($links, $settings_link);

		$optimize_link = '<a href="' . esc_url($admin_page_url) . '">' . __('Optimize', 'wp-optimize') . '</a>';
		array_unshift($links, $optimize_link);
		return $links;
	}

	/**
	 * Action wpo_tables_list_additional_column_data. Output button Optimize in the action column.
	 *
	 * @param string $content    String for output to column
	 * @param object $table_info Object with table info.
	 *
	 * @return string
	 */
	public function tables_list_additional_column_data($content, $table_info) {
		if ($table_info->is_needing_repair) {
			$content .= '<div class="wpo_button_wrap">'
				. '<button class="button button-secondary run-single-table-repair" data-table="' . esc_attr($table_info->Name) . '">' . __('Repair', 'wp-optimize') . '</button>'
				. '<img class="optimization_spinner visibility-hidden" src="' . esc_attr(admin_url('images/spinner-2x.gif')) . '" width="20" height="20" alt="...">'
				. '<span class="optimization_done_icon dashicons dashicons-yes visibility-hidden"></span>'
				. '</div>';
		}

		// table belongs to plugin.
		if ($table_info->can_be_removed) {
			$content .= '<div>'
				. '<button class="button button-secondary run-single-table-delete" data-table="' . esc_attr($table_info->Name) . '">' . __('Remove', 'wp-optimize') . '</button>'
				. '<img class="optimization_spinner visibility-hidden" src="' . esc_attr(admin_url('images/spinner-2x.gif')) . '" width="20" height="20" alt="...">'
				. '<span class="optimization_done_icon dashicons dashicons-yes visibility-hidden"></span>'
				. '</div>';
		}

		// Add option for MyISAM to InnoDB conversion.
		if ('MyISAM' == $table_info->Engine) {
			$content .= '<div class="wpo_button_convert wpo_button_wrap">'
				. '<button class="button button-secondary toinnodb" data-table="' . esc_attr($table_info->Name) . '">' . __('Convert to InnoDB', 'wp-optimize') . '</button>'
				. '<img class="optimization_spinner visibility-hidden" src="' . esc_attr(admin_url('images/spinner-2x.gif')) . '" width="20" height="20" alt="...">'
				. '<span class="optimization_done_icon dashicons dashicons-yes visibility-hidden"></span>'
				. '</div>';
						
		}
				
		return $content;
	}

	/**
	 * Initialize WP-Optimize page cache.
	 */
	public function init_page_cache() {
		if ($this->get_page_cache()->config->get_option('enable_page_caching', false)) {
			$this->get_page_cache()->enable();
		}
	}

	/**
	 * Schedules cron event based on selected schedule type
	 *
	 * @return void
	 */
	public function cron_activate() {
		$gmt_offset = (int) (3600 * get_option('gmt_offset'));

		$options = $this->get_options();

		if ($options->get_option('schedule') === false) {
			$options->set_default_options();
		} else {
			if ('true' == $options->get_option('schedule')) {
				if (!wp_next_scheduled('wpo_cron_event2')) {
					$schedule_type = $options->get_option('schedule-type', 'wpo_weekly');

					// Backward compatibility
					if ('wpo_otherweekly' == $schedule_type) $schedule_type = 'wpo_fortnightly';

					$this_time = (86400 * 7);

					switch ($schedule_type) {
						case "wpo_daily":
							$this_time = 86400;
							break;

						case "wpo_weekly":
							$this_time = (86400 * 7);
							break;

						case "wpo_fortnightly":
							$this_time = (86400 * 14);
							break;

						case "wpo_monthly":
							$this_time = (86400 * 30);
							break;
					}

					add_action('wpo_cron_event2', array($this, 'cron_action'));
					$result = wp_schedule_event((current_time("timestamp", 0) + $this_time - $gmt_offset), $schedule_type, 'wpo_cron_event2');
					WP_Optimize()->log('running wp_schedule_event()');
					if (is_wp_error($result)) {
						$error_msg = $result->get_error_message();
						WP_Optimize()->log($error_msg);
						WP_Optimize()->log(print_r($result, true));
					} else {
						WP_Optimize()->log($result);
					}
				}
			}
		}
	}

	/**
	 * Clears all cron events
	 *
	 * @return void
	 */
	public function wpo_cron_deactivate() {
		$cron_jobs = _get_cron_array();
		foreach ($cron_jobs as $job) {
			foreach (array_keys($job) as $hook) {
				if (preg_match('/^wpo_/', $hook)) wp_unschedule_hook($hook);
			}
		}
	}

	/**
	 * Scheduler public functions to update schedulers
	 *
	 * @param  array $schedules An array of schedules being passed.
	 * @return array            An array of schedules being returned.
	 */
	public function cron_schedules($schedules) {
		$schedules['wpo_daily'] = array('interval' => 86400, 'display' => 'Once Daily');
		$schedules['wpo_weekly'] = array('interval' => 86400 * 7, 'display' => 'Once Weekly');
		$schedules['wpo_fortnightly'] = array('interval' => 86400 * 14, 'display' => 'Once Every Fortnight');
		$schedules['wpo_monthly'] = array('interval' => 86400 * 30, 'display' => 'Once Every Month');
		return $schedules;
	}

	/**
	 * Returns count of overdue cron jobs.
	 *
	 * @return integer
	 */
	public function howmany_overdue_crons() {
		$how_many_overdue = 0;
		if (function_exists('_get_cron_array') || (is_file(ABSPATH.WPINC.'/cron.php') && include_once(ABSPATH.WPINC.'/cron.php') && function_exists('_get_cron_array'))) {
			$crons = _get_cron_array();
			if (is_array($crons)) {
				$timenow = time();
				foreach ($crons as $jt => $job) {
					if ($jt < $timenow) {
						$how_many_overdue++;
					}
				}
			}
		}
		return $how_many_overdue;
	}

	/**
	 * Run updates on plugin activation.
	 */
	public function run_updates() {
		include_once(WPO_PLUGIN_MAIN_PATH.'includes/class-wp-optimize-updates.php');
		WP_Optimize_Updates::check_updates();
	}

	/**
	 * Returns warning about overdue crons.
	 *
	 * @param int $howmany count of overdue crons
	 * @return string
	 */
	public function show_admin_warning_overdue_crons($howmany) {
		$ret = '<div class="updated below-h2"><p>';
		$ret .= '<strong>'.__('Warning', 'wp-optimize').':</strong> '.sprintf(__('WordPress has a number (%d) of scheduled tasks which are overdue. Unless this is a development site, this probably means that the scheduler in your WordPress install is not working.', 'wp-optimize'), $howmany).' <a href="'.apply_filters('wpoptimize_com_link', "https://getwpo.com/faqs/the-scheduler-in-my-wordpress-installation-is-not-working-what-should-i-do/").'">'.__('Read this page for a guide to possible causes and how to fix it.', 'wp-optimize').'</a>';
		$ret .= '</p></div>';
		return $ret;
	}

	private function wp_normalize_path($path) {
		// Wp_normalize_path is not present before WP 3.9.
		if (function_exists('wp_normalize_path')) return wp_normalize_path($path);
		// Taken from WP 4.6.
		$path = str_replace('\\', '/', $path);
		$path = preg_replace('|(?<=.)/+|', '/', $path);
		if (':' === substr($path, 1, 1)) {
			$path = ucfirst($path);
		}
		return $path;
	}

	public function get_templates_dir() {
		return apply_filters('wp_optimize_templates_dir', $this->wp_normalize_path(WPO_PLUGIN_MAIN_PATH.'templates'));
	}

	public function get_templates_url() {
		return apply_filters('wp_optimize_templates_url', WPO_PLUGIN_URL.'/templates');
	}

	/**
	 * Return or output view content
	 *
	 * @param String  $path                   - path to template, usually relative to templates/ within the WP-O directory
	 * @param Boolean $return_instead_of_echo - what to do with the results
	 * @param Array	  $extract_these		  - key/value pairs for substitution into the scope of the template
	 *
	 * @return String|Void
	 */
	public function include_template($path, $return_instead_of_echo = false, $extract_these = array()) {
		if ($return_instead_of_echo) ob_start();

		if (preg_match('#^([^/]+)/(.*)$#', $path, $matches)) {
			$prefix = $matches[1];
			$suffix = $matches[2];
			if (isset($this->template_directories[$prefix])) {
				$template_file = $this->template_directories[$prefix].'/'.$suffix;
			}
		}

		if (!isset($template_file)) {
			$template_file = WPO_PLUGIN_MAIN_PATH.'templates/'.$path;
		}

		$template_file = apply_filters('wp_optimize_template', $template_file, $path);

		do_action('wp_optimize_before_template', $path, $template_file, $return_instead_of_echo, $extract_these);

		if (!file_exists($template_file)) {
			error_log("WP Optimize: template not found: ".$template_file);
			echo __('Error:', 'wp-optimize').' '.__('template not found', 'wp-optimize')." (".$path.")";
		} else {
				extract($extract_these);
				// The following are useful variables which can be used in the template.
				// They appear as unused, but may be used in the $template_file.
				$wpdb = $GLOBALS['wpdb'];// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $wpdb might be used in the included template
				$wp_optimize = $this;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $wp_optimize might be used in the included template
				$optimizer = $this->get_optimizer();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $optimizer might be used in the included template
				$options = $this->get_options();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $options might be used in the included template
				$wp_optimize_notices = $this->get_notices();// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $wp_optimize_notices might be used in the included template
				include $template_file;
		}

		do_action('wp_optimize_after_template', $path, $template_file, $return_instead_of_echo, $extract_these);

		if ($return_instead_of_echo) return ob_get_clean();
	}

	/**
	 * Build a list of template directories (stored in self::$template_directories)
	 */
	private function register_template_directories() {

		$template_directories = array();

		$templates_dir = $this->get_templates_dir();

		if ($dh = opendir($templates_dir)) {
			while (($file = readdir($dh)) !== false) {
				if ('.' == $file || '..' == $file) continue;
				if (is_dir($templates_dir.'/'.$file)) {
					$template_directories[$file] = $templates_dir.'/'.$file;
				}
			}
				closedir($dh);
		}

		// Optimal hook for most extensions to hook into.
		$this->template_directories = apply_filters('wp_optimize_template_directories', $template_directories);

	}

	/**
	 * Message to debug
	 *
	 * @param string $message Message to insert into the log.
	 * @param array  $context array with variables used in $message like in template,
	 * 						  for ex.
	 *						  $message = 'Hello {message}';
	 * 						  $context = ['message' => 'world']
	 * 						  'Hello world' string will be saved in log.
	 */
	public function log($message, $context = array()) {
		$this->get_logger()->debug($message, $context);
	}

	/**
	 * Format Bytes Into KB/MB
	 *
	 * @param  mixed   $bytes    Number of bytes to be converted.
	 * @param  integer $decimals the number of decimal digits
	 * @return integer        return the correct format size.
	 */
	public function format_size($bytes, $decimals = 2) {
		if (!is_numeric($bytes)) return __('N/A', 'wp-optimize');

		if (1073741824 <= $bytes) {
			$bytes = number_format($bytes / 1073741824, $decimals) . ' GB';
		} elseif (1048576 <= $bytes) {
			$bytes = number_format($bytes / 1048576, $decimals) . ' MB';
		} elseif (1024 <= $bytes) {
			$bytes = number_format($bytes / 1024, $decimals) . ' KB';
		} elseif (1 < $bytes) {
			$bytes = $bytes . ' bytes';
		} elseif (1 == $bytes) {
			$bytes = $bytes . ' byte';
		} else {
			$bytes = '0 bytes';
		}

		return $bytes;
	}

	/**
	 * Format a timestamp into a juman readable date time
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function format_date_time($timestamp) {
		return date_i18n(get_option('date_format').' @ '.get_option('time_format'), ($timestamp + get_option('gmt_offset') * 3600));
	}

	/**
	 * Executed this function on cron event.
	 *
	 * @return void
	 */
	public function cron_action() {

		$optimizer = $this->get_optimizer();
		$options = $this->get_options();

		$this->log('WPO: Starting cron_action()');
		$options->update_option('last-optimized', time());
		if ('true' == $options->get_option('schedule')) {
			$this_options = $options->get_option('auto');

			// Currently the output of the optimizations is not saved/used/logged.
			$optimizer->do_optimizations($this_options, 'auto');
		}

	}

	/**
	 * Schedule cron tasks used by plugin.
	 *
	 * @return void
	 */
	public function schedule_plugin_cron_tasks() {
		if (!wp_next_scheduled('wpo_weekly_cron_tasks')) {
			wp_schedule_event(current_time("timestamp", 0), 'weekly', 'wpo_weekly_cron_tasks');
		}

		add_action('wpo_weekly_cron_tasks', array($this, 'do_weekly_cron_tasks'));
	}

	/**
	 * Do plugin background tasks.
	 *
	 * @return void
	 */
	public function do_weekly_cron_tasks() {
		// add tasks here.
		$this->get_db_info()->update_plugin_json();
	}

	/**
	 * This will customize a URL with a correct Affiliate link
	 * This function can be update to suit any URL as longs as the URL is passed
	 *
	 * @param String  $url					  - URL to be check to see if it an updraftplus match.
	 * @param String  $text					  - Text to be entered within the href a tags.
	 * @param String  $html					  - Any specific HTML to be added.
	 * @param String  $class				  - Specify a class for the href (including the attribute label)
	 * @param Boolean $return_instead_of_echo - if set, then the result will be returned, not echo-ed.
	 *
	 * @return String|void
	 */
	public function wp_optimize_url($url, $text, $html = '', $class = '', $return_instead_of_echo = false) {
		// Check if the URL is UpdraftPlus.
		$url = $this->maybe_add_affiliate_params($url);		// Return URL - check if there is HTML such as images.
		if ('' != $html) {
			$result = '<a '.$class.' href="'.esc_attr($url).'">'.$html.'</a>';
		} else {
			$result = '<a '.$class.' href="'.esc_attr($url).'">'.htmlspecialchars($text).'</a>';
		}
		if ($return_instead_of_echo) return $result;
		echo $result;
	}

	/**
	 * Get an URL with an eventual affiliate ID
	 *
	 * @param string $url
	 * @return string
	 */
	public function maybe_add_affiliate_params($url) {
		// Check if the URL is UpdraftPlus.
		if (false !== strpos($url, '//updraftplus.com')) {
			// Set URL with Affiliate ID.
			$url = add_query_arg(array('afref' => $this->get_notices()->get_affiliate_id()), $url);

			// Apply filters.
			$url = apply_filters('wpoptimize_updraftplus_com_link', $url);
		}
		return apply_filters('wpoptimize_maybe_add_affiliate_params', $url);
	}

	/**
	 * Setup WPO logger(s)
	 */
	public function setup_loggers() {

		$logger = $this->get_logger();
		$loggers = $this->wpo_loggers();

		if (!empty($loggers)) {
			foreach ($loggers as $_logger) {
				$logger->add_logger($_logger);
			}
		}

		add_action('wp_optimize_after_optimizations', array($this, 'after_optimizations_logger_action'));
	}

	/**
	 * Run logger actions after all optimizations done
	 */
	public function after_optimizations_logger_action() {
		$loggers = $this->get_logger()->get_loggers();
		if (!empty($loggers)) {
			foreach ($loggers as $logger) {
				if (is_a($logger, 'Updraft_Email_Logger')) {
					$logger->flush_log();
				}
			}
		}
	}

	/**
	 * Returns list of WPO loggers instances
	 * Apply filter wp_optimize_loggers
	 *
	 * @return array
	 */
	public function wpo_loggers() {

		$loggers = array();
		$loggers_classes_by_id = array();
		$options_keys = array();

		$loggers_classes = $this->get_loggers_classes();

		foreach ($loggers_classes as $logger_class => $source) {
			$loggers_classes_by_id[strtolower($logger_class)] = $logger_class;
		}

		$options = $this->get_options();
				
		$saved_loggers = $options->get_option('logging');
		$logger_additional_options = $options->get_option('logging-additional');

		// create loggers classes instances.
		if (!empty($saved_loggers)) {
			// check for previous version options format.
			$keys = array_keys($saved_loggers);

			// if options stored in old format then reformat it.
			if (false == is_numeric($keys[0])) {
				$_saved_loggers = array();
					foreach ($saved_loggers as $logger_id => $enabled) {
						if ($enabled) {
							$_saved_loggers[] = $logger_id;
						}
					}

					// fill email with admin.
					if (array_key_exists('updraft_email_logger', $saved_loggers) && $saved_loggers['updraft_email_logger']) {
						$logger_additional_options['updraft_email_logger'] = array(
							get_option('admin_email')
						);
					}

					$saved_loggers = $_saved_loggers;
			}

			foreach ($saved_loggers as $i => $logger_id) {

				if (!array_key_exists($logger_id, $loggers_classes_by_id)) continue;

				$logger_class = $loggers_classes_by_id[$logger_id];

				$logger = new $logger_class();

				$logger_options = $logger->get_options_list();

				if (!empty($logger_options)) {
					foreach (array_keys($logger_options) as $option_name) {
						if (array_key_exists($option_name, $options_keys)) {
							$options_keys[$option_name]++;
						} else {
									$options_keys[$option_name] = 0;
						}

						$option_value = isset($logger_additional_options[$option_name][$options_keys[$option_name]]) ? $logger_additional_options[$option_name][$options_keys[$option_name]] : '';

						// if options in old format then get correct value.
						if ('' === $option_value && array_key_exists($logger_id, $logger_additional_options)) {
							$option_value = array_shift($logger_additional_options[$logger_id]);
						}

						$logger->set_option($option_name, $option_value);
					}
				}

				// check if logger is active.
				$active = (!is_array($logger_additional_options) || (array_key_exists('active', $logger_additional_options) && empty($logger_additional_options['active'][$i]))) ? false : true;

				if ($active) {
					$logger->enable();
				} else {
					$logger->disable();
				}

				$loggers[] = $logger;
			}
		}

		$loggers = apply_filters('wp_optimize_loggers', $loggers);

		return $loggers;
	}

	/**
	 * Returns associative array with logger class name in a key and path to class file in a value.
	 *
	 * @return array
	 */
	public function get_loggers_classes() {
		$loggers_classes = array(
			'Updraft_PHP_Logger' => WPO_PLUGIN_MAIN_PATH . 'includes/class-updraft-php-logger.php',
			'Updraft_Email_Logger' => WPO_PLUGIN_MAIN_PATH . 'includes/class-updraft-email-logger.php',
			'Updraft_Ring_Logger' => WPO_PLUGIN_MAIN_PATH . 'includes/class-updraft-ring-logger.php'
		);

		$loggers_classes = apply_filters('wp_optimize_loggers_classes', $loggers_classes);

		if (!empty($loggers_classes)) {
			foreach ($loggers_classes as $logger_class => $logger_file) {
				if (!class_exists($logger_class)) {
					if (is_file($logger_file)) {
						include_once($logger_file);
					}
				}
			}
		}

		return $loggers_classes;
	}

	/**
	 * Returns information about all loggers classes.
	 *
	 * @return array
	 */
	public function get_loggers_classes_info() {
		$loggers_classes = $this->get_loggers_classes();

		$loggers_classes_info = array();

		if (!empty($loggers_classes)) {
			foreach (array_keys($loggers_classes) as $logger_class_name) {

				if (!class_exists($logger_class_name)) continue;

				$logger_id = strtolower($logger_class_name);
				$logger_class = new $logger_class_name();

				$loggers_classes_info[$logger_id] = array(
					'description' => $logger_class->get_description(),
					'available' => $logger_class->is_available(),
					'allow_multiple' => $logger_class->is_allow_multiple(),
					'options' => $logger_class->get_options_list()
				);
			}
		}

		return $loggers_classes_info;
	}

	/**
	 * Returns true if optimization works in multisite mode
	 *
	 * @return boolean
	 */
	public function is_multisite_mode() {
		return (is_multisite() && self::is_premium());
	}

	/**
	 * Returns true if current user can run optimizations.
	 *
	 * @return bool
	 */
	public function can_run_optimizations() {
		// we don't check permissions for cron jobs.
		if (defined('DOING_CRON') && DOING_CRON) return true;

		if (self::is_premium() && false == user_can(get_current_user_id(), 'wpo_run_optimizations')) return false;
		return true;
	}

	/**
	 * Returns true if current user can manage plugin options.
	 *
	 * @return bool
	 */
	public function can_manage_options() {
		if (self::is_premium() && false == user_can(get_current_user_id(), 'wpo_manage_settings')) return false;
		return true;
	}

	/**
	 * CHeck if current user can purge the cache.
	 *
	 * @return bool
	 */
	public function can_purge_the_cache() {
		if (self::is_premium()) {
			return WP_Optimize_Premium()->can_purge_the_cache();
		}

		return true;
	}

	/**
	 * Returns list of all sites in multisite
	 *
	 * @return array
	 */
	public function get_sites() {
		$sites = array();
		// check if function get_sites exists (since 4.6.0) else use wp_get_sites.
		if (function_exists('get_sites')) {
			$sites = get_sites(array('network_id' => null, 'deleted' => 0, 'number' => 999999));
		} elseif (function_exists('wp_get_sites')) {
			$sites = wp_get_sites(array('network_id' => null, 'deleted' => 0, 'limit' => 999999));
		}
		return $sites;
	}

	/**
	 * Returns script memory limit in megabytes.
	 *
	 * @param bool $memory_limit
	 * @return int
	 */
	public function get_memory_limit($memory_limit = false) {
		// Returns in megabytes
		if (false == $memory_limit) $memory_limit = ini_get('memory_limit');
		$memory_limit = rtrim($memory_limit);

		return $this->return_bytes($memory_limit);
	}

	/**
	 * Returns free memory in bytes.
	 *
	 * @return int
	 */
	public function get_free_memory() {
		return $this->get_memory_limit() - memory_get_usage();
	}

	/**
	 * Checks PHP memory_limit and WP_MAX_MEMORY_LIMIT values and return minimal.
	 *
	 * @return int memory limit in bytes.
	 */
	public function get_script_memory_limit() {
		$memory_limit = $this->get_memory_limit();

		if (defined('WP_MAX_MEMORY_LIMIT')) {
			$wp_memory_limit = $this->get_memory_limit(WP_MAX_MEMORY_LIMIT);

			if ($wp_memory_limit > 0 && $wp_memory_limit < $memory_limit) {
				$memory_limit = $wp_memory_limit;
			}
		}

		return $memory_limit;
	}

	/**
	 * Returns max packet size for database.
	 *
	 * @return int|string
	 */
	public function get_max_packet_size() {
		global $wpdb;
		static $mp = 0;

		if ($mp > 0) return $mp;

		$mp = (int) $wpdb->get_var("SELECT @@session.max_allowed_packet");
		// Default to 1MB
		$mp = (is_numeric($mp) && $mp > 0) ? $mp : 1048576;
		// 32MB
		if ($mp < 33554432) {
			$save = $wpdb->show_errors(false);
			@$wpdb->query("SET GLOBAL max_allowed_packet=33554432");// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$wpdb->show_errors($save);

			$mp = (int) $wpdb->get_var("SELECT @@session.max_allowed_packet");
			// Default to 1MB
			$mp = (is_numeric($mp) && $mp > 0) ? $mp : 1048576;
		}

		return $mp;
	}

	/**
	 * Converts shorthand memory notation value to bytes.
	 * From http://php.net/manual/en/function.ini-get.php
	 *
	 * @param string $val shorthand memory notation value.
	 */
	public function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		$val = (int) $val;
		switch ($last) {
			case 'g':
			$val *= 1024;
			// no break
			case 'm':
			$val *= 1024;
			// no break
			case 'k':
			$val *= 1024;
		}

		return $val;
	}

	/**
	 * Log fatal errors to defined log destinations.
	 */
	public function log_fatal_errors() {
		$last_error = error_get_last();

		if (isset($last_error['type']) && E_ERROR === $last_error['type']) {
			$this->get_logger()->critical($last_error['message']);
		}
	}

	/**
	 * Close browser connection and continue script work. - Taken from UpdraftPlus
	 *
	 * @param array $txt Response to browser; this must be JSON (or if not, alter the Content-Type header handling below)
	 * @return void
	 */
	public function close_browser_connection($txt = '') {
		if (!headers_sent()) {
			// Close browser connection so that it can resume AJAX polling
			header('Content-Length: '.(empty($txt) ? '0' : 4+strlen($txt)));
			header('Connection: close');
			header('Content-Encoding: none');
		}

		if (session_id()) session_write_close();
		echo "\r\n\r\n";
		echo $txt;
		// These two added - 19-Feb-15 - started being required on local dev machine, for unknown reason (probably some plugin that started an output buffer).
		$ob_level = ob_get_level();
		while ($ob_level > 0) {
			ob_end_flush();
			$ob_level--;
		}
		flush();
		if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
	}

	/**
	 * Get the current theme's style.css headers
	 *
	 * @return array|WP_Error
	 */
	public function get_stylesheet_headers() {
		static $headers;
		if (isset($headers)) return $headers;

		$style = get_template_directory_uri() . '/style.css';

		/**
		 * Filters wp_remote_get parameters, when checking if browser cache is enabled.
		 *
		 * @param array $request_params Default parameters
		 */
		$request_params = apply_filters('wpoptimize_get_stylesheet_headers_args', array('timeout' => 10));

		// trying to load style.css.
		$response = wp_remote_get($style, $request_params);

		if (is_a($response, 'WP_Error')) return $response;

		$headers = wp_remote_retrieve_headers($response);

		if (is_a($headers, 'Requests_Utility_CaseInsensitiveDictionary')) {
			$headers = $headers->getAll();
		}

		return $headers;
	}

	/**
	 * Try to change PHP script time limit.
	 */
	public function change_time_limit() {
		$time_limit = (defined('WP_OPTIMIZE_SET_TIME_LIMIT') && WP_OPTIMIZE_SET_TIME_LIMIT > 15) ? WP_OPTIMIZE_SET_TIME_LIMIT : 1800;

		// Try to reduce the chances of PHP self-terminating via reaching max_execution_time.
		@set_time_limit($time_limit); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Does the request come from UDC
	 *
	 * @return boolean
	 */
	public function is_updraft_central_request() {
		return defined('UPDRAFTCENTRAL_COMMAND') && UPDRAFTCENTRAL_COMMAND;
	}

	/**
	 * Does the data need to be included in this request. Currently only true if the request is made from UpdraftCentral.
	 *
	 * @return boolean
	 */
	public function template_should_include_data() {
		/**
		 * Filters wether data should be included in certain templates or not.
		 */
		return apply_filters('wpo_template_should_include_data', $this->is_updraft_central_request());
	}

	/**
	 * Load the templates for the modal window
	 */
	public function load_modal_template() {
		$this->include_template('modal.php');
	}

	/**
	 * Delete transients and semaphores data from options table.
	 */
	public function delete_transients_and_semaphores() {
		global $wpdb;

		$masks = array(
			'updraft_locked_wpo_%',
			'updraft_unlocked_wpo_%',
			'updraft_last_lock_time_wpo_%',
			'updraft_semaphore_wpo_%',
			'wpo_locked_%',
			'wpo_unlocked_%',
			'wpo_last_lock_time_%',
			'wpo_semaphore_%',
			'_transient_timeout_wpo_%',
			'_transient_wpo_%',
			'updraft_lock_wpo_%',
		);

		$where_parts = array();
		foreach ($masks as $mask) {
			$where_parts[] = "(`option_name` LIKE '{$mask}')";
		}

		$wpdb->query("DELETE FROM {$wpdb->options} WHERE " . join(' OR ', $where_parts));
	}

	/**
	 * Prevents bots from indexing plugins list
	 */
	public function robots_txt($output) {
		$upload_dir = wp_upload_dir();
		$path = parse_url($upload_dir['baseurl']);
		$output .= "\nDisallow: " . str_replace($path['scheme'].'://'.$path['host'], '', $upload_dir['baseurl']) . "/wpo-plugins-tables-list.json\n";
		return $output;
	}
}

/**
 * Plugin activation actions.
 */
function wpo_activation_actions() {
	// If plugin activated by not a Network Administrator then deactivate plugin and show message.
	if (is_multisite() && !is_network_admin()) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(__('Only Network Administrator can activate WP-Optimize plugin.', 'wp-optimize').
					' <a href="'.admin_url('plugins.php').'">'.__('go back', 'wp-optimize').'</a>');
	}

	// On activation, check if last-optimized option exists. If not, add 'newly-activated' option.
	if (!WP_Optimize()->get_options()->get_option('last-optimized', false)) {
		WP_Optimize()->get_options()->update_option('newly-activated', true);
	}

	WP_Optimize()->get_options()->set_default_options();
	WP_Optimize()->get_minify()->plugin_activate();

	WP_Optimize::get_gzip_compression()->restore();
	WP_Optimize::get_browser_cache()->restore();

	if (!class_exists('Updraft_Tasks_Activation')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-tasks-activation.php');
	Updraft_Tasks_Activation::init_db();
	Updraft_Tasks_Activation::reinstall_if_needed();

	// run premium activation actions.
	if (file_exists(WPO_PLUGIN_MAIN_PATH.'premium.php')) {
		if (!class_exists('WP_Optimize_Premium')) {
			include_once(WPO_PLUGIN_MAIN_PATH.'premium.php');
		}
		WP_Optimize_Premium()->plugin_activation_actions();
	}
}

/**
 * Plugin deactivation actions.
 */
function wpo_deactivation_actions() {
	WP_Optimize()->wpo_cron_deactivate();
	WP_Optimize()->get_page_cache()->disable();
	WP_Optimize()->get_minify()->plugin_deactivate();
	WP_Optimize::get_gzip_compression()->disable();
	WP_Optimize::get_browser_cache()->disable();
}

function wpo_cron_deactivate() {
	WP_Optimize()->log('running wpo_cron_deactivate()');
	wp_clear_scheduled_hook('wpo_cron_event2');
	wp_clear_scheduled_hook('wpo_weekly_cron_tasks');
}

/**
 * Plugin uninstall actions.
 */
function wpo_uninstall_actions() {
	WP_Optimize::get_gzip_compression()->disable();
	WP_Optimize::get_browser_cache()->disable();
	WP_Optimize()->get_options()->delete_all_options();
	WP_Optimize()->get_minify()->plugin_uninstall();
	WP_Optimize()->get_options()->wipe_settings();
	WP_Optimize()->delete_transients_and_semaphores();
}

function WP_Optimize() {
	return WP_Optimize::instance();
}

endif;

$GLOBALS['wp_optimize'] = WP_Optimize();
