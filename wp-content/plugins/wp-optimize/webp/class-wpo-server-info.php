<?php
if (!defined('WPO_PLUGIN_MAIN_PATH')) die('No direct access allowed');

class WPO_Server_Info {

	private static $_instance = null;

	/**
	 * @var $_server_name Web server engine name
	 */
	private $_server_name;

	/**
	 * @var $_rewrite_status Web server's URL rewrite ability
	 */
	private $_rewrite_status;

	/**
	 * Setup server information
	 */
	public function __construct() {
		$this->set_server_name();
		$this->set_rewrite_status();
	}

	/**
	 * Gets singleton instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if (null === self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Detect and set web server names
	 */
	private function set_server_name() {
		global $is_apache, $is_nginx, $is_iis7, $is_IIS;
		
		if ($is_apache) {
			$this->_server_name = 'apache';
		}

		if ($is_iis7) {
			$this->_server_name = 'iis7';
		}

		if ($is_IIS) {
			$this->_server_name = 'iis';
		}

		if ($is_nginx) {
			$this->_server_name = 'nginx';
		}
	}

	/**
	 * Sets web server's rewrite ability
	 */
	private function set_rewrite_status() {
		if ('apache' === $this->_server_name) {
			$this->test_htaccess_capabilities();
		} else {
			$this->_rewrite_status = false;
		}
	}

	/**
	 * Test Apache/LiteSpeed server's htaccess capabilities, and sets status and info
	 */
	private function test_htaccess_capabilities() {
		$htc = WPO_Htaccess_Capabilities::get_instance();
		if ($htc->htaccess_enabled && $htc->mod_rewrite && $htc->mod_headers && $htc->mod_mime) {
			$this->_rewrite_status = true;
		} elseif ($htc->htaccess_enabled) {
			$this->_rewrite_status = false;
		}
	}

	/**
	 * Getter for web server name
	 *
	 * @return string
	 */
	public function get_server_name() {
		return $this->_server_name;
	}

	/**
	 * Getter for web server's rewrite capability
	 *
	 * @return bool
	 */
	public function get_rewrite_status() {
		return $this->_rewrite_status;
	}
}
