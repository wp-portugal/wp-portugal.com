<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Handles cache configuration and related I/O
 */

if (!class_exists('WP_Optimize_Minify_Config')) :

class WP_Optimize_Minify_Config {

	static protected $_instance = null;

	private $_options = array();

	/**
	 * Construct
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Getter to see if Minify is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->get('enabled');
	}

	/**
	 * Removes all Minify settings from the Database if 'preserve_settings_on_uninstall' is false
	 *
	 * @return void
	 */
	public function purge() {
		if (!$this->get('preserve_settings_on_uninstall')) {
			if (is_multisite()) {
				delete_site_option('wpo_minify_config');
			} else {
				delete_option('wpo_minify_config');
			}
		}
	}

	/**
	 * Get config from file or cache
	 *
	 * @param string $option  - An option name
	 * @param mixed  $default - A default for the option
	 * @return array|string
	 */
	public function get($option = null, $default = false) {
		if (empty($this->_options)) {
			if (is_multisite()) {
				$config = get_site_option('wpo_minify_config', array());
			} else {
				$config = get_option('wpo_minify_config', array());
			}
			$this->_options = wp_parse_args($config, $this->get_defaults());
		}
		
		if ($option && isset($this->_options[$option])) {
			return $this->_options[$option];
		} elseif ($option) {
			return $default;
		}
		return $this->_options;
	}

	/**
	 * Update the config
	 *
	 * @param array $config - The new configuration array
	 * @return boolean
	 */
	public function update($config) {
		$prev_settings = $this->get();
		$prev_settings = wp_parse_args($prev_settings, $this->get_defaults());
		$new_settings = wp_parse_args($config, $prev_settings);
		$this->_options = $new_settings;

		if (is_multisite()) {
			update_site_option('wpo_minify_config', $new_settings);
		} else {
			update_option('wpo_minify_config', $new_settings);
		}

		return true;
	}

	/**
	 * Return defaults
	 *
	 * @return array
	 */
	public function get_defaults() {
		$defaults = array(
			// dev tab checkboxes
			'debug' => false,
			'enabled_css_preload' => false,
			'enabled_js_preload' => false,
			'hpreconnect' => '',
			'hpreload' => '',
			'loadcss' => false,
			'remove_css' => false,
			'critical_path_css' => '',
			'critical_path_css_is_front_page' => '',
			// settings tab checkboxes
			'preserve_settings_on_uninstall' => true,
			'disable_when_logged_in' => false,
			'default_protocol' => 'dynamic', // dynamic, http, https
			'html_minification' => true,
			'clean_header_one' => false,
			'emoji_removal' => true,
			'merge_google_fonts' => true,
			'enable_display_swap' => true,
			'remove_googlefonts' => false,
			'gfonts_method' => 'inherit', // inline, async, exclude
			'fawesome_method' => 'inherit', // inline, async, exclude
			'enable_css' => true,
			'enable_css_minification' => true,
			'enable_merging_of_css' => true,
			'remove_print_mediatypes' => false,
			'inline_css' => false,
			'enable_js' => true,
			'enable_js_minification' => true,
			'enable_merging_of_js' => true,
			'enable_defer_js' => 'individual',
			'defer_js_type' => 'defer',
			'defer_jquery' => true,
			'enable_js_trycatch' => false,
			'exclude_defer_login' => true,
			'cdn_url' => '',
			'cdn_force' => false,

			'async_css' => '',
			'async_js' => '',
			'disable_css_inline_merge' => true,
			'ualist' => array('x11.*fox\/54', 'oid\s4.*xus.*ome\/62', 'x11.*ome\/62', 'oobot', 'ighth', 'tmetr', 'eadles', 'ingdo'),
			'blacklist' => array(),
			'ignore_list' => array(),
			'exclude_js' => '',
			'exclude_css' => '',
			'edit_default_exclutions' => false,
			
			'merge_allowed_urls' => '',

			// internal
			'enabled' => false,
			'last-cache-update' => 0,
			'plugin_version' => '0.0.0',
			'cache_lifespan' => 30,
			'merge_inline_extra_css_js' => true,
		);
		return apply_filters('wpo_minify_defaults', $defaults);
	}

	/**
	 * Check whether everything should be purged instead of just incremented
	 *
	 * @return boolean
	 */
	public function always_purge_everything() {
		/**
		 * Filters the result of always_purge_everything
		 *
		 * @return boolean
		 */
		return apply_filters('wpo_minify_always_purge_everything', 0 === intval($this->get('cache_lifespan')) || (defined('WPO_ADVANCED_CACHE') && defined('WP_CACHE') && WP_CACHE));
	}

	/**
	 * Get the instance
	 *
	 * @return WP_Optimize_Minify_Config
	 */
	public static function get_instance() {
		if (!self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

/**
 * Get WPO Minify instance
 *
 * @return WP_Optimize_Minify_Config
 */
function wp_optimize_minify_config() {
	return WP_Optimize_Minify_Config::get_instance();
}
endif;
