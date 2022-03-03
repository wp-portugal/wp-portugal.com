<?php
if (!defined('ABSPATH')) die('No direct access allowed');

class WP_Optimize_Minify_Admin {

	private $wp_version_required = '4.5';

	/**
	 * Initialize, add actions and filters
	 *
	 * @return void
	 */
	public function __construct() {
		if (WPO_MINIFY_PHP_VERSION_MET) {
			// exclude processing for editors and administrators (fix editors)
			add_action('wp_optimize_admin_page_wpo_minify_status', array($this, 'check_permissions_admin_notices'));
		}

		add_action('wp_optimize_admin_page_wpo_minify_status', array($this, 'admin_notices_activation_errors'));

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

		// This function runs when WordPress updates or installs/remove something. Forces new cache
		add_action('upgrader_process_complete', array('WP_Optimize_Minify_Cache_Functions', 'cache_increment'));
		// This function runs when an active theme or plugin is updated
		add_action('wpo_active_plugin_or_theme_updated', array('WP_Optimize_Minify_Cache_Functions', 'reset'));
		add_action('upgrader_overwrote_package', array('WP_Optimize_Minify_Cache_Functions', 'reset'));
		add_action('after_switch_theme', array('WP_Optimize_Minify_Cache_Functions', 'cache_increment'));
		add_action('updraftcentral_version_updated', array('WP_Optimize_Minify_Cache_Functions', 'reset'));
		add_action('elementor/editor/after_save', array('WP_Optimize_Minify_Cache_Functions', 'reset'));
		add_action('fusion_cache_reset_after', array('WP_Optimize_Minify_Cache_Functions', 'reset'));
		// Output asset preload placeholder, replaced by premium
		add_action('wpo_minify_settings_tabs', array($this, 'output_assets_preload_placeholder'), 10, 1);

		add_action('wp_optimize_register_admin_content', array($this, 'register_content'));
	}

	/**
	 * Register the content
	 *
	 * @return void
	 */
	public function register_content() {
		add_action('wp_optimize_admin_page_wpo_minify_status', array($this, 'output_status'), 20);
		add_action('wp_optimize_admin_page_wpo_minify_settings', array($this, 'output_settings'), 20);
		add_action('wp_optimize_admin_page_wpo_minify_advanced', array($this, 'output_advanced'), 20);
		add_action('wp_optimize_admin_page_wpo_minify_font', array($this, 'output_font_settings'), 20);
		add_action('wp_optimize_admin_page_wpo_minify_css', array($this, 'output_css_settings'), 20);
		add_action('wp_optimize_admin_page_wpo_minify_js', array($this, 'output_js_settings'), 20);
	}

	/**
	 * Load scripts for controlling the admin pages
	 *
	 * @param string $hook
	 * @return void
	 */
	public function admin_enqueue_scripts($hook) {
		$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? WPO_VERSION.'.'.time() : WPO_VERSION;
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';
		if (preg_match('/wp\-optimize/i', $hook)) {
			wp_enqueue_script('wp-optimize-min-js', WPO_PLUGIN_URL.'js/minify' . $min_or_not_internal . '.js', array('jquery', 'wp-optimize-admin-js'), $enqueue_version);
		}
	}

	/**
	 * Conditionally runs upon the WP action admin_notices to display errors
	 *
	 * @return void
	 */
	public function admin_notices_activation_errors() {
		global $wp_version;
		include ABSPATH . WPINC . '/version.php';
		$errors = array();
		
		if (!WPO_MINIFY_PHP_VERSION_MET) {
			$errors[] = sprintf(__('WP-Optimize Minify requires PHP 5.4 or higher. You’re using version %s.', 'wp-optimize'), PHP_VERSION);
		}

		if (!extension_loaded('mbstring')) {
			$errors[] = __('WP-Optimize Minify requires the PHP mbstring module to be installed on the server; please ask your web hosting company for advice on how to enable it on your server.', 'wp-optimize');
		}
		
		if (version_compare($wp_version, $this->wp_version_required, '<')) {
			$errors[] = sprintf(__('WP-Optimize Minify requires WordPress version %s or higher. You’re using version %s.', 'wp-optimize'), $this->wp_version_required, $wp_version);
		}

		foreach ($errors as $error) {
			?>
			<div class="notice notice-error wpo-warning">
				<p><?php echo $error; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Display an admin notice if the user has inadequate filesystem permissions
	 *
	 * @return void
	 */
	public function check_permissions_admin_notices() {
		// get cache path
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		if (is_dir($cache_dir) && !is_writable($cache_dir)) {
			$chmod = substr(sprintf('%o', fileperms($cache_dir)), -4);
			?>
			<div class="notice notice-error wpo-warning">
				<p>
					<?php printf(__('WP-Optimize Minify needs write permissions on the folder %s.', 'wp-optimize'), "<strong>".htmlspecialchars($cache_dir)."</strpmg>"); ?>
				</p>
			</div>
			<div class="notice notice-error wpo-warning">
				<p>
					<?php printf(__('The current permissions for WP-Optimize Minify are chmod %s.', 'wp-optimize'), "<strong>$chmod</strong>"); ?>
				</p>
			</div>
			<div class="notice notice-error wpo-warning">
				<p>
					<?php
						printf(__('If you need something more than %s for it to work, then your server is probably misconfigured.', 'wp-optimize'), '<strong>775</strong>');
						echo " ";
						_e('Please contact your hosting provider.', 'wp-optimize');
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Minify - Outputs the status tab
	 *
	 * @return void
	 */
	public function output_status() {
		if (!class_exists('WP_Optimize_Detect_Minify_Plugins')) {
			require_once(WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-detect-minify-plugins.php');
		}
		$this->found_incompatible_plugins = WP_Optimize_Detect_Minify_Plugins::get_instance()->get_active_minify_plugins();
		$wpo_minify_options = wp_optimize_minify_config()->get();
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		WP_Optimize()->include_template(
			'minify/status-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options,
				'show_information_notice' => !get_user_meta(get_current_user_id(), 'wpo-hide-minify-information-notice', true),
				'cache_dir' => $cache_path['cachedir'],
				'can_purge_the_cache' => WP_Optimize()->can_purge_the_cache(),
				'active_minify_plugins' => apply_filters('wpo_minify_found_incompatible_plugins', $this->found_incompatible_plugins),
			)
		);
	}

	/**
	 * Minify - Outputs the font settings tab
	 *
	 * @return void
	 */
	public function output_font_settings() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		WP_Optimize()->include_template(
			'minify/font-settings-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options
			)
		);
	}

	/**
	 * Minify - Outputs the CSS settings tab
	 *
	 * @return void
	 */
	public function output_css_settings() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		WP_Optimize()->include_template(
			'minify/css-settings-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options
			)
		);
	}

	/**
	 * Minify - Outputs the JS settings tab
	 *
	 * @return void
	 */
	public function output_js_settings() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		WP_Optimize()->include_template(
			'minify/js-settings-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options
			)
		);
	}

	/**
	 * Minify - Outputs the settings tab
	 *
	 * @return void
	 */
	public function output_settings() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		$url = parse_url(get_home_url());
		WP_Optimize()->include_template(
			'minify/settings-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options,
				'default_protocol' => $url['scheme']
			)
		);
	}

	/**
	 * Minify - Outputs the settings tab
	 *
	 * @return void
	 */
	public function output_assets_preload_placeholder($wpo_minify_options) {
		WP_Optimize()->include_template(
			'minify/asset-preload.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options
			)
		);
	}

	/**
	 * Minify - Outputs the advanced tab
	 *
	 * @return void
	 */
	public function output_advanced() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		$files = false;
		if (apply_filters('wpo_minify_status_show_files_on_load', true) && WPO_MINIFY_PHP_VERSION_MET) {
			$files = WP_Optimize_Minify_Cache_Functions::get_cached_files();
		}

		// WP_Optimize_Minify_Functions is only loaded when Minify is active
		if (class_exists('WP_Optimize_Minify_Functions')) {
			$default_ignore = WP_Optimize_Minify_Functions::get_default_ignore();
			$default_ie_blacklist = WP_Optimize_Minify_Functions::get_default_ie_blacklist();
		} else {
			$default_ignore = array();
			$default_ie_blacklist = array();
		}

		WP_Optimize()->include_template(
			'minify/advanced-tab.php',
			false,
			array(
				'wpo_minify_options' => $wpo_minify_options,
				'files' => $files,
				'default_ignore' => $default_ignore,
				'default_ie_blacklist' => $default_ie_blacklist
			)
		);
	}
}
