<?php

if (!defined('ABSPATH')) die('No direct access allowed');

class WP_Optimize_Detect_Minify_Plugins {

	/**
	 * Detect list of active most popular WordPress minify plugins.
	 *
	 * @return array
	 */
	public function get_active_minify_plugins() {

		$active_minify_plugins = array();

		foreach ($this->get_plugins() as $plugin_slug => $plugin_title) {
			if ($this->is_plugin_active($plugin_slug) && $this->is_minify_active($plugin_slug)) {
				$active_minify_plugins[$plugin_slug] = $plugin_title;
			}
		}

		return $active_minify_plugins;
	}

	/**
	 * Get the plugins list
	 *
	 * @return array
	 */
	protected function get_plugins() {
		return array(
			'w3-total-cache' => 'W3 Total Cache',
			'autoptimize' => 'Autoptimize',
			'fast-velocity-minify' => 'Fast Velocity Minify',
		);
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
	 * Check if minify feature is active
	 *
	 * @return bool
	 */
	public function is_minify_active($plugin_slug) {
		switch ($plugin_slug) {
			case 'w3-total-cache':
				return (function_exists('w3tc_config') && w3tc_config()->get_boolean('minify.enabled'));
			case 'autoptimize':
				return ('on' == get_option('autoptimize_js', false) || 'on' == get_option('autoptimize_css', false) || 'on' == get_option('autoptimize_html', false));
			case 'fast-velocity-minify':
				return true;
		}
	}

	/**
	 * Instance of WP_Optimize_Detect_Minify_Plugins.
	 *
	 * @return WP_Optimize_Detect_Minify_Plugins
	 */
	public static function get_instance() {
		static $instance = null;
		if (null === $instance) {
			$instance = new self();
		}
		return $instance;
	}
}
