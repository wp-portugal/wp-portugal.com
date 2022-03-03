<?php

if (!defined('ABSPATH')) die('No direct access allowed');

class WP_Optimize_Detect_Cache_Plugins {

	private static $instance;

	/**
	 * WP_Optimize_Detect_Cache_Plugins constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Detect list of active most popular WordPress cache plugins.
	 *
	 * @return array
	 */
	public function get_active_cache_plugins() {
		// The index is the plugin's slug

		$active_cache_plugins = array();

		foreach ($this->get_plugins() as $plugin_slug => $plugin_title) {

			$function_name = 'is_'.str_replace('-', '_', $plugin_slug).'_plugin_active';

			if (is_callable(array($this, $function_name))) {
				if (call_user_func(array($this, $function_name))) {
					$active_cache_plugins[$plugin_slug] = $plugin_title;
				}
			} else {
				if ($this->is_plugin_active($plugin_slug)) {
					$active_cache_plugins[$plugin_slug] = $plugin_title;
				}
			}
		}

		return $active_cache_plugins;
	}

	/**
	 * Get the plugins list
	 *
	 * @return array
	 */
	protected function get_plugins() {
		return array(
			'w3-total-cache' => 'W3 Total Cache',
			'wp-super-cache' => 'WP Super Cache',
			'wp-rocket' => 'WP Rocket',
			'wp-fastest-cache' => 'WP Fastest Cache',
			'litespeed-cache' => 'LiteSpeed Cache',
			'cache-enabler' => 'Cache Enabler',
			'comet-cache' => 'Comet Cache',
			'hummingbird-performance' => 'Hummingbird',
			'hyper-cache' => 'Hyper Cache',
		);
	}

	/**
	 * Check if W3 Total Cache active.
	 *
	 * @return bool
	 */
	public function is_w3_total_cache_plugin_active() {
		return defined('W3TC_VERSION') || $this->is_plugin_active('w3-total-cache');
	}

	/**
	 * Check if WP Rocket active.
	 *
	 * @return bool
	 */
	public function is_wp_rocket_plugin_active() {
		return defined('WP_ROCKET_VERSION') || $this->is_plugin_active('wp-rocket');
	}

	/**
	 * Check if $plugin is active.
	 *
	 * @param string $plugin - plugin slug
	 *
	 * @return bool
	 */
	private function is_plugin_active($plugin) {
		$status = WP_Optimize()->get_db_info()->get_plugin_status($plugin);

		return $status['active'];
	}

	/**
	 * Instance of WP_Optimize_Detect_Cache_Plugins.
	 *
	 * @return WP_Optimize_Detect_Cache_Plugins
	 */
	static public function instance() {
		static $instance;
		if (empty($instance)) {
			$instance = new self();
		}

		return $instance;
	}
}
