<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Handles cache configuration and related I/O
 */

if (!class_exists('WPO_Cache_Config')) :

class WPO_Cache_Config {

	/**
	 * Defaults
	 *
	 * @var array
	 */
	public $defaults;

	/**
	 * Instance of this class
	 *
	 * @var mixed
	 */
	public static $instance;


	/**
	 * Set config defaults
	 */
	public function __construct() {
		$this->defaults = $this->get_defaults();
	}

	/**
	 * Get config from file or cache
	 *
	 * @return array
	 */
	public function get() {

		if (is_multisite()) {
			$config = get_site_option('wpo_cache_config', $this->get_defaults());
		} else {
			$config = get_option('wpo_cache_config', $this->get_defaults());
		}

		return wp_parse_args($config, $this->get_defaults());
	}

	/**
	 * Get a specific configuration option
	 *
	 * @param string  $option_key The option identifier
	 * @param boolean $default    Default value if the option doesn't exist (Default to false)
	 * @return mixed
	 */
	public function get_option($option_key, $default = false) {
		$options = $this->get();
		return apply_filters("wpo_option_key_{$option_key}", (isset($options[$option_key]) ? $options[$option_key] : $default));
	}

	/**
	 * Updates the given config object in file and DB
	 *
	 * @param array	  $config						- the cache configuration
	 * @param boolean $skip_disk_if_not_yet_present - only write the configuration file to disk if it already exists. This presents PHP notices if the cache has never been on, and settings are saved.
	 *
	 * @return bool
	 */
	public function update($config, $skip_disk_if_not_yet_present = false) {
		$config = wp_parse_args($config, $this->get_defaults());

		$config['page_cache_length_value'] = intval($config['page_cache_length_value']);
		$config['page_cache_length'] = $this->calculate_page_cache_length($config['page_cache_length_value'], $config['page_cache_length_unit']);

		/**
		 * Filters the cookies used to set cache file names
		 *
		 * @param array $cookies - The cookies
		 * @param array $config  - The new config
		 */
		$wpo_cache_cookies = apply_filters('wpo_cache_cookies', array(), $config);
		sort($wpo_cache_cookies);

		/**
		 * Filters the query variables used to set cache file names
		 *
		 * @param array $wpo_query_variables - The variables
		 * @param array $config              - The new config
		 */
		$wpo_query_variables = apply_filters('wpo_cache_query_variables', array(), $config);
		sort($wpo_query_variables);

		$config['wpo_cache_cookies'] = $wpo_cache_cookies;
		$config['wpo_cache_query_variables'] = $wpo_query_variables;
		
		$config = apply_filters('wpo_cache_update_config', $config);

		if (is_multisite()) {
			update_site_option('wpo_cache_config', $config);
		} else {
			update_option('wpo_cache_config', $config);
		}

		do_action('wpo_cache_config_updated', $config);

		return $this->write($config, $skip_disk_if_not_yet_present);
	}

	/**
	 * Calculate cache expiration value in seconds.
	 *
	 * @param int    $value
	 * @param string $unit  ( hours | days | months )
	 *
	 * @return int
	 */
	private function calculate_page_cache_length($value, $unit) {
		$cache_length_units = array(
			'hours' => 3600,
			'days' => 86400,
			'months' => 2629800, // 365.25 * 86400 / 12
		);

		return $value * $cache_length_units[$unit];
	}

	/**
	 * Deletes config files and options
	 *
	 * @return bool
	 */
	public function delete() {

		if (is_multisite()) {
			delete_site_option('wpo_cache_config');
		} else {
			delete_option('wpo_cache_config');
		}
		
		if (!WPO_Page_Cache::delete(WPO_CACHE_CONFIG_DIR)) {
			return false;
		}

		return true;
	}

	/**
	 * Writes config to file
	 *
	 * @param array	  $config		   - Configuration array.
	 * @param boolean $only_if_present - only writes to the disk if the configuration file already exists
	 *
	 * @return boolean - returns false if an attempt to write failed
	 */
	private function write($config, $only_if_present = false) {

		$config_file = WPO_CACHE_CONFIG_DIR.'/'.$this->get_cache_config_filename();

		$this->config = wp_parse_args($config, $this->get_defaults());

		// from 3.0.17 we use more secure way to store cache config files.
		$advanced_cache_version = WPO_Page_Cache::instance()->get_advanced_cache_version();
		// if advanced-cache.php exists and has at least 3.0.17 version or
		// advanced-cache.php doesn't exist then
		// we write the cache config in a new format.
		if (($advanced_cache_version && (version_compare($advanced_cache_version, '3.0.17', '>='))) || !$advanced_cache_version) {
			$config_content = '<?php' . "\n"
				. 'if (!defined(\'ABSPATH\')) die(\'No direct access allowed\');' . "\n\n"
				. '$GLOBALS[\'wpo_cache_config\'] = json_decode(\'' . json_encode($this->config) . '\', true);' . "\n";
		} else {
			$config_content = json_encode($this->config);
		}

		if ((!$only_if_present || file_exists($config_file)) && !file_put_contents($config_file, $config_content)) {
			return new WP_Error('write_cache_config', sprintf(__('The cache configuration file could not be saved to the disk; please check the file/folder permissions of %s .', 'wp-optimize'), $config_file));
		}

		return true;
	}

	/**
	 * Verify we can write to the file system
	 *
	 * @since  1.0
	 * @return boolean
	 */
	public function verify_file_access() {
		if (function_exists('clearstatcache')) {
			clearstatcache();
		}

		// First check wp-config.php.
		if (!is_writable(ABSPATH . 'wp-config.php') && !is_writable(ABSPATH . '../wp-config.php')) {
			return false;
		}

		// Now check wp-content. We need to be able to create files of the same user as this file.
		if (!$this->_is_dir_writable(untrailingslashit(WP_CONTENT_DIR))) {
			return false;
		}

		// If the cache and config directories exist, make sure they're writeable
		if (file_exists(untrailingslashit(WP_CONTENT_DIR) . '/wpo-cache')) {
			
			if (file_exists(WPO_CACHE_DIR)) {
				if (!$this->_is_dir_writable(WPO_CACHE_DIR)) {
					return false;
				}
			}

			if (file_exists(WPO_CACHE_CONFIG_DIR)) {
				if (!$this->_is_dir_writable(WPO_CACHE_CONFIG_DIR)) {
					return false;
				}
			}
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
			'enable_page_caching'						=> false,
			'page_cache_length_value'					=> 24,
			'page_cache_length_unit'					=> 'hours',
			'page_cache_length'							=> 86400,
			'cache_exception_conditional_tags'			=> array(),
			'cache_exception_urls'						=> array(),
			'cache_exception_cookies'					=> array(),
			'cache_exception_browser_agents'			=> array(),
			'enable_sitemap_preload'					=> false,
			'enable_schedule_preload'					=> false,
			'preload_schedule_type'						=> '',
			'enable_mobile_caching'						=> false,
			'enable_user_caching'						=> false,
			'site_url'									=> network_site_url('/'),
			'enable_cache_per_country'					=> false,
			'permalink_structure'						=> get_option('permalink_structure'),
			'uploads'									=> wp_upload_dir()['basedir'],
		);

		return apply_filters('wpo_cache_defaults', $defaults);
	}

	/**
	 * Get advanced-cache.php file name with full path.
	 *
	 * @return string
	 */
	public function get_cache_config_filename() {
		$url = parse_url(network_site_url());

		if (isset($url['port']) && '' != $url['port'] && 80 != $url['port']) {
			return 'config-'.$url['host'].'-port'.$url['port'].'.php';
		} else {
			return 'config-'.$url['host'].'.php';
		}
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  1.0
	 * @return WPO_Cache_Config
	 */
	public static function instance() {

		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
endif;
