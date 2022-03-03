<?php

if (!defined('WPO_PLUGIN_MAIN_PATH')) die('No direct access allowed');

require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/autoload.php');
use HtaccessCapabilityTester\HtaccessCapabilityTester;

class WPO_Htaccess_Capabilities {

	private static $_instance = null;

	/**
	 * Tests and sets up server's htacess capabilities as properties
	 */
	public function __construct() {
		$uploads = wp_upload_dir();
		$this->hct = new HtaccessCapabilityTester($uploads['basedir'] . '/wpo/', $uploads['baseurl'] . '/wpo/');
		$this->htaccess_enabled = $this->hct->htaccessEnabled();
		$this->mod_headers = $this->get_mod_header_status();
		$this->mod_mime = $this->get_mod_mime_status();
		$this->mod_rewrite = $this->get_mod_rewrite_status();
	}

	/**
	 * Gets singleton instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if (null === self::$_instance) {
			self::$_instance = new WPO_Htaccess_Capabilities();
		}
		return self::$_instance;
	}

	/**
	 * Gets `mod_headers` status by checking if it is loaded and working
	 *
	 * @return bool
	 */
	public function get_mod_header_status() {
		return (true === $this->hct->moduleLoaded('headers') && true === $this->hct->headerSetWorks());
	}

	/**
	 * Gets `mod_mime` status by checking if it is loaded and working
	 *
	 * @return bool
	 */
	public function get_mod_mime_status() {
		return (true === $this->hct->moduleLoaded('mime') && true === $this->hct->addTypeWorks());
	}

	/**
	 * Gets `mod_rewrite` status by checking if it is loaded and working
	 *
	 * @return bool
	 */
	public function get_mod_rewrite_status() {
		return (true === $this->hct->moduleLoaded('rewrite') && true == $this->hct->rewriteWorks());
	}
}
