<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

require_once WPO_PLUGIN_MAIN_PATH . 'includes/class-wp-optimize-htaccess.php';

if (!class_exists('WP_Optimize_WebP')) :

class WP_Optimize_WebP {

	private $_htaccess = null;

	private $_server_info_instance;

	private $_rewrite_status = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->set_converter_status();
		$this->set_server_info_instance();
		$this->set_rewrite_status();
		$this->set_webp_serve_method();
	}

	/**
	 * Returns singleton instance
	 *
	 * @return WP_Optimize_WebP
	 */
	public static function get_instance() {
		static $instance = null;
		if (null === $instance) {
			$instance = new WP_Optimize_WebP();
		}
		return $instance;
	}

	/**
	 * Test Run and find converter status
	 */
	public function set_converter_status() {
		include_once WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-webp-test-run.php';
		$converters = WP_Optimize()->get_options()->get_option('webp_converters', false);

		if (empty($converters)) {
			$converter_status = WPO_WebP_Test_Run::get_converter_status();
			WP_Optimize()->get_options()->update_option('webp_converters', $converter_status['working_converters']);
		}
	}

	/**
	 * Sets server info instance variable
	 */
	private function set_server_info_instance() {
		if (!class_exists('WPO_Server_Info')) {
			require_once(WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-server-info.php');
		}
		$this->_server_info_instance = WPO_Server_Info::get_instance();
	}

	/**
	 * Sets server's rewrite status
	 */
	private function set_rewrite_status() {
		$this->_rewrite_status = $this->_server_info_instance->get_rewrite_status();
	}

	/**
	 * Sets how webp images are served
	 */
	private function set_webp_serve_method() {
		if (true === $this->_rewrite_status) {
			$this->use_htaccess();
		} else {
			$this->use_alter_html();
		}
	}

	/**
	 * Setup .htaccess method
	 */
	private function use_htaccess() {
		$options = Updraft_Smush_Manager()->get_smush_options();
		$webp_conversion_enabled = isset($options['webp_conversion']) ? $options['webp_conversion'] : false;
		$this->save_htaccess_rules($webp_conversion_enabled);
	}

	/**
	 * Setup alter html method
	 */
	private function use_alter_html() {
		// To be continued in another part
		// add_action('template_direct', array(''));
	}

	/**
	 * Save .htaccess rules
	 *
	 * @return bool
	 */
	public function save_htaccess_rules($webp_conversion_enabled = false) {
		$wp_uploads = wp_get_upload_dir();
		$htaccess_file = $wp_uploads['basedir'] . '/.htaccess';
		if (!file_exists($htaccess_file)) {
			file_put_contents($htaccess_file, '');
		}
		$htaccess_comment_section = 'WP-Optimize WebP Rules';
		$this->_htaccess = new WP_Optimize_Htaccess($htaccess_file);
	
		if ($this->_htaccess->is_exists() && !$webp_conversion_enabled) {
			$this->_htaccess->remove_commented_section($htaccess_comment_section);
			$this->_htaccess->write_file();
			return true;
		}
		if ($this->_htaccess->is_commented_section_exists($htaccess_comment_section)) return false;
		$this->_htaccess->update_commented_section($this->prepare_webp_htaccess_rules(), $htaccess_comment_section);
		$this->_htaccess->write_file();
		return true;
	}

	/**
	 * Prepare array of htaccess rules to use webp images.
	 *
	 * @return array
	 */
	private function prepare_webp_htaccess_rules() {
		return array(
			array(
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'',
				'# Redirect to existing converted image in same dir (if browser supports webp)',
				'RewriteCond %{HTTP_ACCEPT} image/webp',
				'RewriteCond %{REQUEST_FILENAME} (?i)(.*)(\.jpe?g|\.png)$',
				'RewriteCond %1%2\.webp -f',
				'RewriteRule (?i)(.*)(\.jpe?g|\.png)$ %1%2\.webp [T=image/webp,E=EXISTING:1,E=ADDVARY:1,L]',
				'',
				'# Make sure that browsers which does not support webp also gets the Vary:Accept header',
				'# when requesting images that would be redirected to webp on browsers that does.',
				array(
					'<IfModule mod_headers.c>',
					array(
						'<FilesMatch "(?i)\.(jpe?g|png)$">',
						'Header append "Vary" "Accept"',
						'</FilesMatch>',
					),
					'</IfModule>',
				),
				'',
				'</IfModule>',
				'',
			),
			array(
				'# Rules for handling requests for webp images',
				'# ---------------------------------------------',
				'',
				'# Set Vary:Accept header if we came here by way of our redirect, which set the ADDVARY environment variable',
				'# The purpose is to make proxies and CDNs aware that the response varies with the Accept header',
				'<IfModule mod_headers.c>',
				array(
					'<IfModule mod_setenvif.c>',
					'# Apache appends "REDIRECT_" in front of the environment variables defined in mod_rewrite, but LiteSpeed does not',
					'# So, the next lines are for Apache, in order to set environment variables without "REDIRECT_"',
					'SetEnvIf REDIRECT_EXISTING 1 EXISTING=1',
					'SetEnvIf REDIRECT_ADDVARY 1 ADDVARY=1',
					'',
					'Header append "Vary" "Accept" env=ADDVARY',
					'',
					'# Set X-WPO-WebP header for diagnose purposes',
					'Header set "X-WPO-WebP" "Redirected directly to existing webp" env=EXISTING',
					'</IfModule>',
				),
				'</IfModule>',
			),
			array(
				'# Register webp mime type',
				'<IfModule mod_mime.c>',
				'AddType image/webp .webp',
				'</IfModule>',
			),
		);
	}
}

endif;
