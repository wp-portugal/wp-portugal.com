<?php
/**
 * Page caching functionality
 *
 * Acknowledgement: The page cache functionality was loosely based on the simple cache plugin - https://github.com/tlovett1/simple-cache
 */

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Base cache directory, everything else goes under here
 */
if (!defined('WPO_CACHE_DIR')) define('WPO_CACHE_DIR', untrailingslashit(WP_CONTENT_DIR).'/wpo-cache');

/**
 * Extensions directory.
 */
if (!defined('WPO_CACHE_EXT_DIR')) define('WPO_CACHE_EXT_DIR', dirname(__FILE__).'/extensions');

/**
 * Directory that stores config and related files
 */
if (!defined('WPO_CACHE_CONFIG_DIR')) define('WPO_CACHE_CONFIG_DIR', WPO_CACHE_DIR.'/config');

/**
 * Directory that stores the cache, including gzipped files and mobile specifc cache
 */
if (!defined('WPO_CACHE_FILES_DIR')) define('WPO_CACHE_FILES_DIR', untrailingslashit(WP_CONTENT_DIR).'/cache/wpo-cache');

require_once dirname(__FILE__) . '/file-based-page-cache-functions.php';

if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	require_once dirname(__FILE__) . '/php-5.3-functions.php';
}

wpo_cache_load_extensions();

if (!class_exists('WPO_Page_Cache')) :

class WPO_Page_Cache {

	/**
	 * Cache config object
	 *
	 * @var mixed
	 */
	public $config;

	/**
	 * Logger for this class
	 *
	 * @var mixed
	 */
	public $logger;

	/**
	 * Instance of this class
	 *
	 * @var mixed
	 */
	public static $instance;

	/**
	 * Store last advanced cache file writing status
	 * If true then last writing finished with error
	 *
	 * @var bool
	 */
	public $advanced_cache_file_writing_error;

	/**
	 * Store errors
	 *
	 * @var array
	 */
	private $_errors = array();

	/**
	 * Last advanced cache file content
	 *
	 * @var string
	 */
	public $advanced_cache_file_content;

	/**
	 * Store the latest advanced-cache.php version required
	 *
	 * @var string
	 */
	private $_minimum_advanced_cache_file_version = '3.0.17';

	/**
	 * Set everything up here
	 */
	public function __construct() {
		$this->config = WPO_Cache_Config::instance();
		$this->rules  = WPO_Cache_Rules::instance();
		$this->logger = new Updraft_PHP_Logger();

		add_action('activated_plugin', array($this, 'activate_deactivate_plugin'));
		add_action('deactivate_plugin', array($this, 'activate_deactivate_plugin'));
		add_action('wpo_purge_old_cache', array($this, 'purge_old'));

		/**
		 * Regenerate config file on cache flush.
		 */
		add_action('wpo_cache_flush', array($this, 'update_cache_config'));
		add_action('wpo_cache_flush', array($this, 'delete_cache_size_information'));
		add_action('update_option_permalink_structure', array($this, 'update_option_permalink_structure'), 10, 3);

		// Add purge cache link to admin bar.
		add_filter('wpo_cache_admin_bar_menu_items', array($this, 'admin_bar_purge_cache'), 20, 1);

		// Handle single page purge.
		add_action('wp_loaded', array($this, 'handle_purge_single_page_cache'));

		add_action('admin_init', array($this, 'admin_init'));

		$this->check_compatibility_issues();

		add_filter('cron_schedules', array($this, 'cron_schedules'));
		add_action('wpo_save_images_settings', array($this, 'update_webp_images_option'));
	}

	/**
	 * Activate cron job for deleting expired cache files
	 */
	public function cron_activate() {
		$page_cache_length = $this->config->get_option('page_cache_length');
		if (!wp_next_scheduled('wpo_purge_old_cache')) {
			wp_schedule_event(time() + (false === $page_cache_length ? '86400' : $page_cache_length), 'wpo_purge_old_cache', 'wpo_purge_old_cache');
		}
	}

	/**
	 * Do required actions on activate/deactivate any plugin.
	 */
	public function activate_deactivate_plugin() {

		wp_clear_scheduled_hook('wpo_purge_old_cache');

		$this->update_cache_config();

		/**
		 * Filters whether activating / deactivating a plugin will purge the cache.
		 */
		if (apply_filters('wpo_purge_page_cache_on_activate_deactivate_plugin', true)) {
			$this->purge();
		}
	}

	/**
	 * Check if current user can purge cache.
	 *
	 * @return bool
	 */
	public function can_purge_cache() {
		if (is_multisite()) return $this->is_enabled() && (current_user_can('manage_network_options') || WP_Optimize()->can_purge_the_cache());
		return $this->is_enabled() && (current_user_can('manage_options') || WP_Optimize()->can_purge_the_cache());
	}

	/**
	 * Add Purge from cache in admin bar.
	 *
	 * @param array        $menu_items
	 * @return array
	 */
	public function admin_bar_purge_cache($menu_items) {
		global $pagenow;
		if (!$this->can_purge_cache()) return $menu_items;

		$act_url = remove_query_arg(array('wpo_single_page_cache_purged', 'wpo_all_pages_cache_purged'));

		$cache_size = $this->get_cache_size();
		$cache_size_info = '<h4>'.__('Page cache', 'wp-optimize').'</h4>';
		$cache_size_info .= '<span>'.__('Cache size:', 'wp-optimize').' '. WP_Optimize()->format_size($cache_size['size']).' '.sprintf(__('(%d files)', 'wp-optimize'), $cache_size['file_count']).'</span>';

		$menu_items[] = array(
			'id'    => 'wpo_cache_stats',
			'title' => $cache_size_info,
			'meta'  => array(
				'class' => 'wpo-cache-stats',
			),
			'parent' => 'wpo_purge_cache',
		);

		$menu_items[] = array(
			'id'    => 'wpo_purge_all_pages_cache',
			'title' => __('Purge cache for all pages', 'wp-optimize'),
			'href'  => add_query_arg('_wpo_purge', wp_create_nonce('wpo_purge_all_pages_cache'), $act_url),
			'meta'  => array(
				'title' => __('Purge cache for all pages', 'wp-optimize'),
			),
			'parent' => 'wpo_purge_cache',
		);

		if (!is_admin() || 'post.php' == $pagenow) {
			$menu_items[] = array(
				'id'    => 'wpo_purge_this_page_cache',
				'title' => __('Purge cache for this page', 'wp-optimize'),
				'href'  => add_query_arg('_wpo_purge', wp_create_nonce('wpo_purge_single_page_cache'), $act_url),
				'meta'  => array(
					'title' => __('Purge cache for this page', 'wp-optimize'),
				),
				'parent' => 'wpo_purge_cache',
			);
		}

		return $menu_items;

	}

	/**
	 * Check if purge single page action sent and purge cache.
	 */
	public function handle_purge_single_page_cache() {

		if (!$this->can_purge_cache()) return;

		if (isset($_GET['wpo_single_page_cache_purged']) || isset($_GET['wpo_all_pages_cache_purged'])) {
			if (isset($_GET['wpo_single_page_cache_purged'])) {
				$notice_function = $_GET['wpo_single_page_cache_purged'] ? 'notice_purge_single_page_cache_success' : 'notice_purge_single_page_cache_error';
			} else {
				$notice_function = $_GET['wpo_all_pages_cache_purged'] ? 'notice_purge_all_pages_cache_success' : 'notice_purge_all_pages_cache_error';
			}

			add_action('admin_notices', array($this, $notice_function));

			return;
		}

		if (!isset($_GET['_wpo_purge'])) return;

		if (wp_verify_nonce($_GET['_wpo_purge'], 'wpo_purge_single_page_cache')) {
			$success = false;

			if (is_admin()) {
				$post = isset($_GET['post']) ? (int) $_GET['post'] : 0;
				if ($post > 0) {
					$success = self::delete_single_post_cache($post);
				}
			} else {
				$success = self::delete_cache_by_url(wpo_current_url());
			}

			// remove nonce from url and reload page.
			wp_redirect(add_query_arg('wpo_single_page_cache_purged', $success, remove_query_arg('_wpo_purge')));
			exit;

		} elseif (wp_verify_nonce($_GET['_wpo_purge'], 'wpo_purge_all_pages_cache')) {
			$success = self::purge();

			// remove nonce from url and reload page.
			wp_redirect(add_query_arg('wpo_all_pages_cache_purged', $success, remove_query_arg('_wpo_purge')));
			exit;
		}
	}

	/**
	 * Show notification when page cache purged successfully.
	 */
	public function notice_purge_single_page_cache_success() {
		$this->show_notice(__('The page cache was successfully purged.', 'wp-optimize'), 'success');
	}

	/**
	 * Show notification when page cache wasn't purged.
	 */
	public function notice_purge_single_page_cache_error() {
		$this->show_notice(__('The page cache was not purged.', 'wp-optimize'), 'error');
	}

	/**
	 * Show notification when all pages cache purged successfully.
	 */
	public function notice_purge_all_pages_cache_success() {
		$this->show_notice(__('The page cache was successfully purged.', 'wp-optimize'), 'success');
	}

	/**
	 * Show notification when all pages cache wasn't purged.
	 */
	public function notice_purge_all_pages_cache_error() {
		$this->show_notice(__('The page cache was not purged.', 'wp-optimize'), 'error');
	}

	/**
	 * Show notification in WordPress admin.
	 *
	 * @param string $message HTML (no further escaping is performed)
	 * @param string $type    error, warning, success, or info
	 */
	public function show_notice($message, $type) {
		global $current_screen;
		
		if ($current_screen && is_callable(array($current_screen, 'is_block_editor')) && $current_screen->is_block_editor()) : ?>
			<script>
				window.addEventListener('load', function() {
					(function(wp) {
						if (window.wp && wp.hasOwnProperty('data') && 'function' == typeof wp.data.dispatch) {
							wp.data.dispatch('core/notices').createNotice(
								'<?php echo $type; ?>',
								'<?php echo $message; ?>',
								{
									isDismissible: true,
								}
							);
						}
					})(window.wp);
				});
			</script>
		<?php else : ?>
			<div class="notice wpo-notice notice-<?php echo $type; ?> is-dismissible">
				<p><?php echo $message; ?></p>
			</div>
		<?php
		endif;
	}

	/**
	 * Enables page cache
	 *
	 * @param bool $force_enable - Force regenerating everything. E.g. we want to do that when saving the settings
	 *
	 * @return WP_Error|bool - true on success, error otherwise
	 */
	public function enable($force_enable = false) {
		static $already_ran_enable = false;

		if ($already_ran_enable) return $already_ran_enable;

		$folders_created = $this->create_folders();
		if (is_wp_error($folders_created)) {
			$already_ran_enable = $folders_created;
			return $already_ran_enable;
		}

		// if WPO_ADVANCED_CACHE isn't set, or environment doesn't contain the right constant, force regeneration
		if (!defined('WPO_ADVANCED_CACHE') || !defined('WP_CACHE')) {
			$force_enable = true;
		}

		if (!$force_enable) {
			$already_ran_enable = true;
			return true;
		}

		if (!$this->write_advanced_cache() && version_compare($this->get_advanced_cache_version(), $this->_minimum_advanced_cache_file_version, '<')) {
			$message = sprintf("The request to write the file %s failed. ", htmlspecialchars($this->get_advanced_cache_filename()));
			$message .= ' '.__('Please check file and directory permissions on the file paths up to this point, and your PHP error log.', 'wp-optimize');

			if (!defined('WP_CLI') || !WP_CLI) {
				$message .= "\n\n".sprintf(__('1. Please navigate, via FTP, to the folder - %s', 'wp-optimize'), htmlspecialchars(dirname($this->get_advanced_cache_filename())));
				$message .= "\n".__('2. Edit or create a file with the name advanced-cache.php', 'wp-optimize');
				$message .= "\n".__('3. Copy and paste the following lines into the file:', 'wp-optimize');
			}

			$already_ran_enable = new WP_Error("write_advanced_cache", $message);
			return $already_ran_enable;
		}

		if (!$this->write_wp_config(true)) {
			$already_ran_enable = new WP_Error("write_wp_config", "Could not turn on the WP_CACHE constant in wp-config.php. Check your permissions.");
			return $already_ran_enable;
		}

		if (!$this->verify_cache()) {
			$errors = $this->get_errors();
			$already_ran_enable = new WP_Error("verify_cache", "Could not verify if the cache was enabled: \n".implode("\n- ", $errors));
			return $already_ran_enable;
		}

		$already_ran_enable = true;

		return true;
	}

	/**
	 * Disables page cache
	 *
	 * @return bool - true on success, false otherwise
	 */
	public function disable() {
		$ret = true;

		$advanced_cache_file = $this->get_advanced_cache_filename();

		// N.B. The only use of WP_CACHE in WP core is to include('advanced-cache.php') (and run a function if it's then defined); so, if the decision to leave it enable is, for some unexpected reason, technically incorrect, it still can't cause a problem.
		$disabled_wp_config = $this->write_wp_config(false);
		if (!$disabled_wp_config) {
			$plugin_basename = basename(WPO_PLUGIN_MAIN_PATH);
			$action = "deactivate_".$plugin_basename."/wp-optimize.php";
			if (current_action() === $action) {
				$cache_config = WPO_Cache_Config::instance()->config;
				$cache_config['enable_page_caching'] = false;
				WPO_Cache_Config::instance()->update($cache_config, true);
			}
			$this->log("Could not turn off the WP_CACHE constant in wp-config.php");
			$this->add_warning('error_disabling', __('Could not turn off the WP_CACHE constant in wp-config.php', 'wp-optimize'));
		}

		$disabled_advanced_cache = true;
		// First try to remove (so that it doesn't look to any other plugin like the file is already 'claimed')
		// We only touch advanched-cache.php and wp-config.php if it appears that we were in control of advanced-cache.php
		if (!file_exists($advanced_cache_file) || false !== strpos(file_get_contents($advanced_cache_file), 'WP-Optimize advanced-cache.php')) {
			if (file_exists($advanced_cache_file) && (!unlink($advanced_cache_file) && false === file_put_contents($advanced_cache_file, "<?php\n// WP-Optimize: page cache disabled"))) {
				$disabled_advanced_cache = false;
				$this->log("The request to the filesystem to remove or empty advanced-cache.php failed");
				$this->add_warning('error_disabling', __('The request to the filesystem to remove or empty advanced-cache.php failed', 'wp-optimize'));
			}
		}

		// If both actions failed, the cache wasn't disabled. So we send an error. If only one succeeds, it will still be disabled.
		if (!$disabled_wp_config && !$disabled_advanced_cache) {
			$ret = new WP_Error('error_disabling_cache', __('The page caching could not be disabled: the WP_CACHE constant could not be removed from wp-config.php and the request to the filesystem to remove or empty advanced-cache.php failed.', 'wp-optimize'));
		}

		// Delete cache to avoid stale cache on next activation
		$this->purge();

		return $ret;
	}


	/**
	 * Purges the cache
	 *
	 * @return bool - true on success, false otherwise
	 */
	public function purge() {

		if (!self::delete(WPO_CACHE_FILES_DIR)) {
			$this->log("The request to the filesystem to delete the cache failed");
			return false;
		}

		/**
		 * Fires after purging the cache
		 */
		do_action('wpo_cache_flush');

		return true;
	}

	/**
	 * Purge cache files older than cache life span
	 */
	public function purge_old() {
		$page_cache_length = $this->config->get_option('page_cache_length');
		$expires = time() - $page_cache_length;
		$log = array();
		$cache_folder = WPO_CACHE_FILES_DIR . '/' . str_ireplace(array('http://', 'https://'), '', get_site_url());
		// get all directories that are a direct child of current directory
		if (is_dir($cache_folder) && is_writable($cache_folder)) {
			if ($handle = opendir($cache_folder)) {
				while (false !== ($d = readdir($handle))) {
					if (0 == strcmp($d, '.') || 0 == strcmp($d, '..')) {
						continue;
					}
					
					if ($this->is_front_page_cache($d)) {
						$modified_time = (int) filemtime("$cache_folder/$d");
						if ($modified_time <= $expires) {
							unlink("$cache_folder/$d");
						}
						continue;
					}

					$dir = $cache_folder.'/'.$d;
					if (!is_dir($dir)) continue;

					$stat = stat($dir);
					$modified_time = $stat['mtime'];
					$log[] = "checking if cache has expired - $d";
					if ($modified_time <= $expires) {
						$log[] = "deleting cache in $dir";
						wpo_delete_files($dir, true);
						if (file_exists($dir)) rmdir($dir);
					}
				}
				closedir($handle);
			}
		}
		return $log;
	}

	/**
	 * Finds out given cache file is of front page or not
	 *
	 * @return bool
	 */
	private function is_front_page_cache($d) {
		return in_array($d, array('index.html', 'index.html.gz'));
	}

	/**
	 * Purges the cache
	 *
	 * @return bool - true on success, false otherwise
	 */
	public function clean_up() {

		$this->disable();

		if (!self::delete(WPO_CACHE_DIR, true)) {
			$this->log("The request to the filesystem to clean up the cache failed");
			return false;
		}

		return true;
	}

	/**
	 * Check if cache is enabled and working
	 *
	 * @return bool - true on success, false otherwise
	 */
	public function is_enabled() {

		if (!$this->config->get_option('enable_page_caching')) return false;

		if (!defined('WP_CACHE') || !WP_CACHE) {
			$this->log("WP_CACHE constant is not present in wp-config.php");
			return false;
		}

		if (!defined('WPO_ADVANCED_CACHE') || !WPO_ADVANCED_CACHE) {
			$this->log("WPO_ADVANCED_CACHE constant is not present in advanced-cache.php");
			return false;
		}

		$config_file = WPO_CACHE_CONFIG_DIR . '/'.$this->config->get_cache_config_filename();
		if (!file_exists($config_file)) {
			$this->log("$config_file is not present");
			return false;
		}

		return true;
	}

	/**
	 * Create the folder structure needed for cache to work
	 *
	 * @return bool - true on success, false otherwise
	 */
	private function create_folders() {

		if (!is_dir(WPO_CACHE_DIR) && !wp_mkdir_p(WPO_CACHE_DIR)) {
			return new WP_Error('create_folders', sprintf(__('The request to the filesystem failed: unable to create directory %s. Please check your file permissions.'), str_ireplace(ABSPATH, '', WPO_CACHE_DIR)));
		}

		if (!is_dir(WPO_CACHE_CONFIG_DIR) && !wp_mkdir_p(WPO_CACHE_CONFIG_DIR)) {
			return new WP_Error('create_folders', sprintf(__('The request to the filesystem failed: unable to create directory %s. Please check your file permissions.'), str_ireplace(ABSPATH, '', WPO_CACHE_CONFIG_DIR)));
		}
		
		if (!is_dir(WPO_CACHE_FILES_DIR)) {
			if (!wp_mkdir_p(WPO_CACHE_FILES_DIR)) {
				return new WP_Error('create_folders', sprintf(__('The request to the filesystem failed: unable to create directory %s. Please check your file permissions.'), str_ireplace(ABSPATH, '', WPO_CACHE_FILES_DIR)));
			} else {
				wpo_disable_cache_directories_viewing();
			}
		}

		return true;
	}

	/**
	 * Get advanced-cache.php file name with full path.
	 *
	 * @return string
	 */
	public function get_advanced_cache_filename() {
		return untrailingslashit(WP_CONTENT_DIR) . '/advanced-cache.php';
	}

	/**
	 * Writes advanced-cache.php
	 *
	 * @param boolean $update_required - Whether the update is required or not.
	 * @return bool
	 */
	private function write_advanced_cache($update_required = false) {
		$config_file_basename = $this->config->get_cache_config_filename();
		$cache_file_basename = untrailingslashit(plugin_dir_path(__FILE__));
		$plugin_basename = basename(WPO_PLUGIN_MAIN_PATH);
		$cache_path = '/wpo-cache';
		$cache_files_path = '/cache/wpo-cache';
		$cache_extensions_path = WPO_CACHE_EXT_DIR;
		$wpo_version = WPO_VERSION;
		$wpo_home_url = trailingslashit(home_url());

		// CS does not like heredoc
		// phpcs:disable
		$this->advanced_cache_file_content = <<<EOF
<?php

if (!defined('ABSPATH')) die('No direct access allowed');

// WP-Optimize advanced-cache.php (written by version: $wpo_version) (do not change this line, it is used for correctness checks)

if (!defined('WPO_ADVANCED_CACHE')) define('WPO_ADVANCED_CACHE', true);

\$possible_plugin_locations = array(
	defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR.'/$plugin_basename/cache' : false,
	defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR.'/plugins/$plugin_basename/cache' : false,
	dirname(__FILE__).'/plugins/$plugin_basename/cache',
	'$cache_file_basename',
);

\$plugin_location = false;

foreach (\$possible_plugin_locations as \$possible_location) {
	if (false !== \$possible_location && @file_exists(\$possible_location.'/file-based-page-cache.php')) {
		\$plugin_location = \$possible_location;
		break;
	}
}

if (false === \$plugin_location) {
	if (!defined('WPO_PLUGIN_LOCATION_NOT_FOUND')) define('WPO_PLUGIN_LOCATION_NOT_FOUND', true);
	\$protocol = \$_SERVER['REQUEST_SCHEME'];
	\$host = \$_SERVER['HTTP_HOST'];
	\$request_uri = \$_SERVER['REQUEST_URI'];
	if (strcasecmp('$wpo_home_url', \$protocol . '://' . \$host . \$request_uri) === 0) {
		error_log('WP-Optimize: No caching took place, because the plugin location could not be found');
	}
} else {
	if (!defined('WPO_PLUGIN_LOCATION_NOT_FOUND')) define('WPO_PLUGIN_LOCATION_NOT_FOUND', false);
}

if (is_admin()) { return; }

if (!defined('WPO_CACHE_DIR')) define('WPO_CACHE_DIR', WP_CONTENT_DIR.'$cache_path');
if (!defined('WPO_CACHE_CONFIG_DIR')) define('WPO_CACHE_CONFIG_DIR', WPO_CACHE_DIR.'/config');
if (!defined('WPO_CACHE_FILES_DIR')) define('WPO_CACHE_FILES_DIR', WP_CONTENT_DIR.'$cache_files_path');
if (false !== \$plugin_location) {
	if (!defined('WPO_CACHE_EXT_DIR')) define('WPO_CACHE_EXT_DIR', \$plugin_location.'/extensions');
} else {
	if (!defined('WPO_CACHE_EXT_DIR')) define('WPO_CACHE_EXT_DIR', '$cache_extensions_path');
}

if (!@file_exists(WPO_CACHE_CONFIG_DIR . '/$config_file_basename')) { return; }

\$GLOBALS['wpo_cache_config'] = @json_decode(file_get_contents(WPO_CACHE_CONFIG_DIR . '/$config_file_basename'), true);

if (empty(\$GLOBALS['wpo_cache_config'])) {
	include_once(WPO_CACHE_CONFIG_DIR . '/$config_file_basename');
}

if (empty(\$GLOBALS['wpo_cache_config']) || empty(\$GLOBALS['wpo_cache_config']['enable_page_caching'])) { return; }

if (false !== \$plugin_location) { include_once(\$plugin_location.'/file-based-page-cache.php'); }

EOF;

		// phpcs:enable
		$advanced_cache_filename = $this->get_advanced_cache_filename();

		// If the file content is already up to date, success
		if (is_file($advanced_cache_filename) && file_get_contents($advanced_cache_filename) === $this->advanced_cache_file_content) {
			$this->advanced_cache_file_writing_error = false;
			return true;
		}

		// check if we can't write the advanced cache file
		// case 1: the directory is read-only and the file doesn't exist
		if (!is_file($advanced_cache_filename) && !is_writable(dirname($advanced_cache_filename))) {
			$this->advanced_cache_file_writing_error = true;
			return false;
		}

		// case 2: the file already exists but it's read-only
		if (is_file($advanced_cache_filename) && !is_writable($advanced_cache_filename)) {
			if (version_compare($this->get_advanced_cache_version(), $this->_minimum_advanced_cache_file_version, '<') || $update_required) {
				$this->advanced_cache_file_writing_error = true;
				return false;
			} else {
				$this->advanced_cache_file_writing_error = false;
				return true;
			}
		}

		if (!file_put_contents($this->get_advanced_cache_filename(), $this->advanced_cache_file_content)) {
			$this->advanced_cache_file_writing_error = true;
			return false;
		}

		$this->advanced_cache_file_writing_error = false;
		return true;
	}

	/**
	 * Update advanced cache version if needed.
	 */
	public function maybe_update_advanced_cache() {

		if (!$this->is_enabled()) return;

		if (!defined('WPO_PLUGIN_LOCATION_NOT_FOUND') || (defined('WPO_PLUGIN_LOCATION_NOT_FOUND') && true === WPO_PLUGIN_LOCATION_NOT_FOUND)) {
			if (!$this->write_advanced_cache(true)) {
				add_action('admin_notices', array($this, 'show_admin_notice_advanced_cache'));
			}
		}

		// from 3.0.17 we use more secure way to store cache config files and need update advanced-cache.php
		$advanced_cache_current_version = $this->get_advanced_cache_version();
		if ($advanced_cache_current_version && version_compare($advanced_cache_current_version, $this->_minimum_advanced_cache_file_version, '>=')) return;

		if (!$this->write_advanced_cache()) {
			add_action('admin_notices', array($this, 'notice_advanced_cache_autoupdate_error'));
		} else {
			$this->update_cache_config();
		}
	}

	/**
	 * Show notification when advanced-cache.php could not be updated.
	 */
	public function notice_advanced_cache_autoupdate_error() {
		$this->show_notice(__('The file advanced-cache.php needs to be updated, but the automatic process failed.', 'wp-optimize').
		' <a href="'.admin_url('admin.php?page=wpo_cache').'">'.__('Please try to disable and then re-enable the WP-Optimize cache manually.', 'wp-optimize').'</a>', 'error');
	}

	/**
	 * Get WPO version number from advanced-cache.php file.
	 *
	 * @return bool|mixed
	 */
	public function get_advanced_cache_version() {
		if (!is_file($this->get_advanced_cache_filename())) return false;

		$version = false;
		$content = file_get_contents($this->get_advanced_cache_filename());

		if (preg_match('/WP\-Optimize advanced\-cache\.php \(written by version\: (.+)\)/Ui', $content, $match)) {
			$version = $match[1];
		}

		return $version;
	}

	/**
	 * Set WP_CACHE on or off in wp-config.php
	 *
	 * @param  boolean $status value of WP_CACHE.
	 * @return boolean true if the value was set, false otherwise
	 */
	private function write_wp_config($status = true) {
		// If we changed the value in wp-config, save it, in case we need to change it again in the same run.
		static $changed = false;

		if (defined('WP_CACHE') && WP_CACHE === $status && !$changed) {
			return true;
		}

		$config_path = $this->_get_wp_config();

		// Couldn't find wp-config.php.
		if (!$config_path) {
			return false;
		}

		$config_file_string = file_get_contents($config_path);

		// Config file is empty. Maybe couldn't read it?
		if (empty($config_file_string)) {
			return false;
		}

		$config_file = preg_split("#(\n|\r\n)#", $config_file_string);
		$line_key    = false;

		foreach ($config_file as $key => $line) {
			if (!preg_match('/^\s*define\(\s*(\'|")([A-Z_]+)(\'|")(.*)/i', $line, $match)) {
				continue;
			}

			if ('WP_CACHE' === $match[2]) {
				$line_key = $key;
			}
		}

		if (false !== $line_key) {
			unset($config_file[$line_key]);
		}


		if ($status) {
			array_shift($config_file);
			array_unshift($config_file, '<?php', "define('WP_CACHE', true); // WP-Optimize Cache");
		}

		foreach ($config_file as $key => $line) {
			if ('' === $line) {
				unset($config_file[$key]);
			}
		}
		if (!file_put_contents($config_path, implode(PHP_EOL, $config_file))) {
			return false;
		}
		$changed = true;
		return true;
	}

	/**
	 * Verify we can write to the file system
	 *
	 * @return boolean
	 */
	private function verify_cache() {
		if (function_exists('clearstatcache')) {
			clearstatcache();
		}
		$errors = 0;

		// First check wp-config.php.
		if (!$this->_get_wp_config() && !is_writable($this->_get_wp_config())) {
			$this->log("Unable to write to or find wp-config.php; please check file/folder permissions");
			$this->add_warning('verify_cache', __("Unable to write to or find wp-config.php; please check file/folder permissions.", 'wp-optimize'));
		}

		$advanced_cache_file = untrailingslashit(WP_CONTENT_DIR).'/advanced-cache.php';
		
		// Now check wp-content. We need to be able to create files of the same user as this file.
		if ((!file_exists($advanced_cache_file) || false === strpos(file_get_contents($advanced_cache_file), 'WP-Optimize advanced-cache.php')) && !is_writable($advanced_cache_file) && !is_writable(untrailingslashit(WP_CONTENT_DIR))) {
			$this->log("Unable to write the file advanced-cache.php inside the wp-content folder; please check file/folder permissions");
			$this->add_error('verify_cache', __("Unable to write the file advanced-cache.php inside the wp-content folder; please check file/folder permissions", 'wp-optimize'));
			$errors++;
		}

		if (file_exists(WPO_CACHE_FILES_DIR)) {
			if (!is_writable(WPO_CACHE_FILES_DIR)) {
				$this->log("Unable to write inside the cache files folder; please check file/folder permissions");
				$this->add_warning('verify_cache', sprintf(__("Unable to write inside the cache files folder (%s); please check file/folder permissions (no cache files will be able to be created otherwise)", 'wp-optimize'), WPO_CACHE_FILES_DIR));
			}
		}
		
		if (file_exists(WPO_CACHE_CONFIG_DIR)) {
			if (!is_writable(WPO_CACHE_CONFIG_DIR)) {
				$this->log("Unable to write inside the cache configuration folder; please check file/folder permissions");
				// If the config exists, only send a warning. Otherwise send an error.
				$type = 'warning';
				if (!file_exists(WPO_CACHE_CONFIG_DIR . '/'.$this->config->get_cache_config_filename())) {
					$type = 'error';
					$errors++;
				}
				$this->add_error('verify_cache', sprintf(__("Unable to write inside the cache configuration folder (%s); please check file/folder permissions", 'wp-optimize'), WPO_CACHE_CONFIG_DIR), $type);
			}
		}

		return !$errors;
	}

	/**
	 * Update permalink strucutre in cache config
	 *
	 * @param string $old_value Old value of permalink_structure option
	 * @param string $value 	New value of permalink_structure option
	 * @param string $option 	Option name `permalink_structure`
	 */
	public function update_option_permalink_structure($old_value, $value, $option) {
		$current_config = $this->config->get();
		if ($old_value != $value) {
			$current_config[$option] = $value;
			$this->config->update($current_config, true);
		}
	}

	/**
	 * Update cache config. Used to support 3d party plugins.
	 */
	public function update_cache_config() {
		// get current cache settings.
		$current_config = $this->config->get();
		// and call update to change if need cookies and query variable names.
		$this->config->update($current_config, true);
	}

	/**
	 * Delete information about cache size.
	 */
	public function delete_cache_size_information() {
		delete_transient('wpo_get_cache_size');
	}

	/**
	 * Get current cache size.
	 *
	 * @return array
	 */
	public function get_cache_size() {
		$cache_size = get_transient('wpo_get_cache_size');

		if (!empty($cache_size)) return $cache_size;

		$infos = $this->get_dir_infos(WPO_CACHE_FILES_DIR);
		$cache_size = array(
			'size' => $infos['size'],
			'file_count' => $infos['file_count']
		);

		set_transient('wpo_get_cache_size', $cache_size);

		return $cache_size;
	}

	/**
	 * Fetch directory informations.
	 *
	 * @param string $dir
	 * @return array
	 */
	private function get_dir_infos($dir) {
		$dir_size = 0;
		$file_count = 0;

		$handle = is_dir($dir) ? opendir($dir) : false;

		if (false === $handle) {
			return array('size' => 0, 'file_count' => 0);
		}

		$file = readdir($handle);

		while (false !== $file) {

			if ('.' != $file && '..' != $file) {
				$current_file = $dir.'/'.$file;

				if (is_dir($current_file)) {
					$sub_dir_infos = $this->get_dir_infos($current_file);
					$dir_size += $sub_dir_infos['size'];
					$file_count += $sub_dir_infos['file_count'];
				} elseif (is_file($current_file)) {
					$dir_size += filesize($current_file);
					$file_count++;
				}
			}

			$file = readdir($handle);

		}

		return array('size' => $dir_size, 'file_count' => $file_count);
	}

	/**
	 * Returns the path to wp-config
	 *
	 * @return string|boolean wp-config.php path.
	 */
	private function _get_wp_config() {

		$config_path = false;

		foreach (get_included_files() as $filename) {
			if (preg_match('/(\\\\|\/)wp-config\.php$/i', $filename)) {
				$config_path = $filename;
				break;
			}
		}

		// WP-CLI doesn't include wp-config.php that's why we use function from WP-CLI to locate config file.
		if (!$config_path && is_callable('wpo_wp_cli_locate_wp_config')) {
			$config_path = wpo_wp_cli_locate_wp_config();
		}

		return $config_path;
	}

	/**
	 * Util to delete folders and/or files
	 *
	 * @param string $src
	 * @return boolean
	 */
	public static function delete($src) {

		return wpo_delete_files($src);

	}

	/**
	 * Delete cached files for specific url.
	 *
	 * @param string $url
	 * @param bool   $recursive If true child elements will deleted too
	 *
	 * @return bool
	 */
	public static function delete_cache_by_url($url, $recursive = false) {
		if (!defined('WPO_CACHE_FILES_DIR') || '' == $url) return;

		$path = self::get_full_path_from_url($url);

		do_action('wpo_delete_cache_by_url', $url, $recursive);

		return wpo_delete_files($path, $recursive);
	}

	/**
	 * Delete cached files for single post.
	 *
	 * @param integer $post_id The post ID
	 *
	 * @return bool
	 */
	public static function delete_single_post_cache($post_id) {
	
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$post_url = get_permalink($post_id);
	
		$path = self::get_full_path_from_url($post_url);

		// for posts with pagination run purging cache recursively.
		$post = get_post($post_id);
		$recursive = preg_match('/\<\!--nextpage--\>/', $post->post_content) ? true : false;

		do_action('wpo_delete_cache_by_url', $post_url, $recursive);

		return wpo_delete_files($path, $recursive);
	}

	/**
	 * Delete cached home page files.
	 */
	public static function delete_homepage_cache() {
	
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$homepage_url = get_home_url(get_current_blog_id());

		$path = self::get_full_path_from_url($homepage_url);

		do_action('wpo_delete_cache_by_url', $homepage_url, false);

		wpo_delete_files($path, false);
	}

	/**
	 * Delete sitemap cahche.
	 */
	public static function delete_sitemap_cache() {
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$homepage_url = get_home_url(get_current_blog_id());

		$path = trailingslashit(WPO_CACHE_FILES_DIR) . trailingslashit(wpo_get_url_path($homepage_url));

		if (!is_dir($path)) return;

		$handle = opendir($path);

		if (false !== $handle) {
			$file = readdir($handle);

			while (false !== $file) {
	
				if ('.' != $file && '..' != $file && is_dir($path . $file) && preg_match('/.*sitemap.*\.xml/i', $file)) {
					do_action('wpo_delete_cache_by_url', $path . $file, false);
					wpo_delete_files($path . $file, true);
				}
	
				$file = readdir($handle);
			}
		}

		closedir($handle);
	}

	/**
	 * Delete feed from cache.
	 */
	public static function delete_feed_cache() {
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$homepage_url = get_home_url(get_current_blog_id());

		$path = self::get_full_path_from_url($homepage_url) . 'feed/';

		do_action('wpo_delete_cache_by_url', $path, true);

		wpo_delete_files($path, true);
	}

	/**
	 * Delete post feed from cache.
	 */
	public static function delete_post_feed_cache($post_id) {
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$post_url = get_permalink($post_id);
	
		$path = self::get_full_path_from_url($post_url) . 'feed/';

		do_action('wpo_delete_cache_by_url', $path, true);

		wpo_delete_files($path, true);
	}

	/**
	 * Delete comments feed from cache.
	 */
	public static function delete_comments_feed() {
		if (!defined('WPO_CACHE_FILES_DIR')) return;

		$comments_feed_url = trailingslashit(get_home_url(get_current_blog_id())) . 'comments/feed/';

		$path = self::get_full_path_from_url($comments_feed_url);

		do_action('wpo_delete_cache_by_url', $comments_feed_url, true);

		wpo_delete_files($path, true);

		// delete empty comments dir from the cache
		$comments_url = trailingslashit(get_home_url(get_current_blog_id())) . 'comments/';
		$path = self::get_full_path_from_url($comments_url);

		if (wpo_is_empty_dir($path)) {
			wpo_delete_files($path, true);
		}
	}

	/**
	 * Returns full path to the cache folder by url.
	 *
	 * @param string $url
	 * @return string
	 */
	private static function get_full_path_from_url($url) {
		return trailingslashit(WPO_CACHE_FILES_DIR) . trailingslashit(wpo_get_url_path($url));
	}

	/**
	 * Admin actions
	 *
	 * @return void
	 */
	public function admin_init() {
		// Maybe update the advanced cache.
		if ((!defined('DOING_AJAX') || !DOING_AJAX) && current_user_can('update_plugins')) {
			$this->maybe_update_advanced_cache();
			$this->cron_activate();
		}
	}

	/**
	 * Logs error messages
	 *
	 * @param  string $message
	 * @return null|void
	 */
	public function log($message) {
		if (isset($this->logger)) {
			$this->logger->log($message, 'error');
		}
	}

	/**
	 * Returns an instance of the current class, creates one if it doesn't exist
	 *
	 * @return object
	 */
	public static function instance() {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adds an error to the error store
	 *
	 * @param string $code    - The error code
	 * @param string $message - The error's message
	 * @param string $type    - The error's type (error, warning)
	 * @return void
	 */
	public function add_error($code, $message, $type = 'error') {
		if (!isset($this->_errors[$type])) {
			$this->_errors[$type] = new WP_Error($code, $message);
		} else {
			$this->_errors[$type]->add($code, $message);
		}
	}

	/**
	 * Adds a warning to the error store
	 *
	 * @param string $code    - The error code
	 * @param string $message - The error's message
	 * @return void
	 */
	public function add_warning($code, $message) {
		$this->add_error($code, $message, 'warning');
	}

	/**
	 * Get all recorded errors
	 *
	 * @param string  $type              - The error type
	 * @param boolean $get_messages_only - Whether to get only the messages, or the full WP_Error object
	 * @return boolean|array|WP_Error
	 */
	public function get_errors($type = 'error', $get_messages_only = true) {
		if (!$this->has_errors($type)) return false;
		$errors = $this->_errors[$type];
		if ($get_messages_only) {
			return $errors->get_error_messages();
		}
		return $errors;
	}

	/**
	 * Check if any errors were recorded
	 *
	 * @param string $type - The error type
	 * @return boolean
	 */
	public function has_errors($type = 'error') {
		return isset($this->_errors[$type]) && !empty($this->_errors[$type]) && $this->_errors[$type]->has_errors();
	}

	/**
	 * Check if any warnings were recorded
	 *
	 * @return boolean
	 */
	public function has_warnings() {
		return $this->has_errors('warning');
	}

	/**
	 * Check the cache compatibility issues.
	 */
	public function check_compatibility_issues() {
		if (!$this->is_enabled()) return;

		if ($this->is_pagespeedninja_gzip_active()) add_action('admin_notices', array($this, 'show_pagespeedninja_gzip_notice'));
		if ($this->is_farfutureexpiration_gzip_active()) add_action('admin_notices', array($this, 'show_farfutureexpiration_gzip_notice'));
	}

	/**
	 * Check if PageSpeed Ninja is active and GZIP compression option is enabled.
	 *
	 * @return bool
	 */
	public function is_pagespeedninja_gzip_active() {
		if (!class_exists('PagespeedNinja')) return false;

		$options = get_option('pagespeedninja_config');
		$gzip = !empty($options) ? (bool) $options['psi_EnableGzipCompression'] && (bool) $options['html_gzip'] : false;

		return $gzip;
	}

	/**
	 * Output PageSpeed Ninja Gzip notice.
	 */
	public function show_pagespeedninja_gzip_notice() {
		echo '<div id="wp-optimize-pagespeedninja-gzip-notice" class="error wpo-notice"><p><b>'.__('WP-Optimize:', 'wp-optimize').'</b> '.__('Please disable the feature "Gzip compression" in PageSpeed Ninja to prevent conflicts.', 'wp-optimize').'</p></div>';
	}

	/**
	 * Check if Far Future Expiration is active and GZIP compression option is enabled.
	 *
	 * @return bool
	 */
	public function is_farfutureexpiration_gzip_active() {
		if (!class_exists('farFutureExpiration')) return false;

		$options = get_option('far_future_expiration_settings');
		$gzip = !empty($options) ? (bool) $options['enable_gzip'] : false;

		return $gzip;
	}

	/**
	 * Output Far Future Expiration Gzip notice.
	 */
	public function show_farfutureexpiration_gzip_notice() {
		echo '<div id="wp-optimize-pagespeedninja-gzip-notice" class="error wpo-notice"><p><b>'.__('WP-Optimize:', 'wp-optimize').'</b> '.__('Please disable the feature "Gzip compression" in Far Future Expiration to prevent conflicts.', 'wp-optimize').'</p></div>';
	}

	/**
	 * This is a notice to show users that writing `advanced-cache.php` failed
	 */
	public function show_admin_notice_advanced_cache() {
		$message = sprintf(__('The request to write the file %s failed.', 'wp-optimize'), htmlspecialchars($this->get_advanced_cache_filename()));
		$message .= ' '.__('Please check file and directory permissions on the file paths up to this point, and your PHP error log.', 'wp-optimize');
		WP_Optimize()->include_template('notices/cache-notice.php', false, array('message' => $message));
	}

	/**
	 * Scheduler public functions to update schedulers
	 *
	 * @param  array $schedules An array of schedules being passed.
	 * @return array            An array of schedules being returned.
	 */
	public function cron_schedules($schedules) {
		$page_cache_length = $this->config->get_option('page_cache_length');
		$schedules['wpo_purge_old_cache'] = array('interval' => false === $page_cache_length ? 86400 : $page_cache_length, 'display' => __('Every time after the cache has expired', 'wp-optimize'));
		return $schedules;
	}

	/**
	 * Update cache config file to reflect the webp images option
	 */
	public function update_webp_images_option() {
		if ($this->is_enabled()) {
			$cache_settings = WPO_Cache_Config::instance()->get();
			$cache_settings['use_webp_images'] = WP_Optimize()->get_options()->get_option('webp_conversion');
			WPO_Cache_Config::instance()->update($cache_settings);
		}
	}
}

endif;
