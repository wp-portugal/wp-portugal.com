<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

/**
 * Class WP_Optimize_Transients_Cache
 */
class WP_Optimize_Transients_Cache {

	private $_cache = array();

	private $_expiration = array();

	private $_keep_free_mem = false;

	/**
	 * WP_Optimize_Transients_Cache constructor.
	 */
	public function __construct() {
	}

	/**
	 * Return instance of WP_Optimize_Transients_Cache.
	 *
	 * @return WP_Optimize_Transients_Cache
	 */
	public static function get_instance() {
		static $instance;
		if (null === $instance) {
			$instance = new self();
		}
		return $instance;

	}

	/**
	 * Save $value to memory or to database depends on free memory.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $expiration
	 */
	public function set($key, &$value, $expiration = 0) {
		$keep_free_mem = $this->_keep_free_mem ? $this->_keep_free_mem : 16 * 1048576;

		$used_memory = memory_get_usage();
		$free_memory = WP_Optimize()->get_free_memory();
		$released_memory = 0;

		$cache_keys = array_keys($this->_cache);

		// release memory while we need.
		while ($keep_free_mem > $free_memory + $released_memory && !empty($cache_keys)) {
			$this->flush_value(array_shift($cache_keys));
			$released_memory = $used_memory - memory_get_usage();
		}

		// if we have enough of free memory then save to memory.
		if (WP_Optimize()->get_free_memory() > $keep_free_mem) {
			$this->_cache[$key] = $value;
			$this->_expiration[$key] = $expiration;
		} else {
			if (isset($this->_cache[$key])) {
				unset($this->_cache[$key]);
			}

			if (isset($this->_expiration[$key])) {
				unset($this->_expiration[$key]);
			}

			$this->set_transient($key, $value, $expiration);
		}
	}

	/**
	 * Return value from cache by $key.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		if (array_key_exists($key, $this->_cache)) return $this->_cache[$key];

		return $this->get_transient($key);
	}

	/**
	 * Delete value from cache.
	 *
	 * @param string $key
	 */
	public function delete($key) {
		if (array_key_exists($key, $this->_cache)) {
			unset($this->_cache[$key], $this->_expiration[$key]);
		}

		$this->delete_transient($key);
	}

	/**
	 * Set transient.
	 *
	 * @param int   $key
	 * @param mixed $value
	 * @param int   $expiration
	 */
	public function set_transient($key, $value, $expiration = 0) {
		if (WP_Optimize()->is_multisite_mode()) {
			set_site_transient($key, $value, $expiration);
		} else {
			set_transient($key, $value, $expiration);
		}
	}

	/**
	 * Get transient.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get_transient($key) {
		if (WP_Optimize()->is_multisite_mode()) {
			$value = get_site_transient($key);
		} else {
			$value = get_transient($key);
		}

		return $value;
	}

	/**
	 * Delete transient.
	 *
	 * @param string $key
	 */
	public function delete_transient($key) {
		if (WP_Optimize()->is_multisite_mode()) {
			delete_site_transient($key);
		} else {
			delete_transient($key);
		}
	}

	/**
	 * Save value to database and remove from $_cache array.
	 *
	 * @param string $key
	 */
	public function flush_value($key) {
		$this->set_transient($key, $this->_cache[$key], $this->_expiration[$key]);
		unset($this->_cache[$key], $this->_expiration[$key]);
	}

	/**
	 * Save all _cache values to database.
	 */
	public function flush() {
		foreach (array_keys($this->_cache) as $key) {
			$this->flush_value($key);
		}
	}
}
