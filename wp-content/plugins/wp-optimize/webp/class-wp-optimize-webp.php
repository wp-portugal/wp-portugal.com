<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

if (!class_exists('WP_Optimize_WebP')) :

class WP_Optimize_WebP {

	private $_htaccess = null;

	private $_rewrite_status = '';

	private $_should_use_webp = false;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->maybe_set_converter_status();
		$this->set_rewrite_status();
		if (!is_admin()) {
			$this->maybe_decide_webp_serve_method();
		}
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
	 * Detect working webp converters and set converter status, if needed
	 */
	private function maybe_set_converter_status() {
		$converters = WP_Optimize()->get_options()->get_option('webp_converters', false);
		if (empty($converters)) {
			$this->set_converter_status();
		}
	}

	/**
	 * Test Run and find converter status
	 */
	private function set_converter_status() {
		if (!class_exists('WPO_WebP_Test_Run')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-webp-test-run.php';
		}
		$converter_status = WPO_WebP_Test_Run::get_converter_status();
		WP_Optimize()->get_options()->update_option('webp_converters', $converter_status['working_converters']);
	}

	/**
	 * Sets server's rewrite status
	 */
	private function set_rewrite_status() {
		$this->_rewrite_status = WP_Optimize()->get_options()->get_option('rewrite_status', false);
		if ('true' === $this->_rewrite_status) {
			$this->setup_htaccess_file();
			return;
		} elseif ('false' === $this->_rewrite_status) return;

		if (!class_exists('WPO_Htaccess_Capabilities')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-htaccess-capabilities.php';
		}
		$htc = WPO_Htaccess_Capabilities::get_instance();
		
		if ($htc->htaccess_enabled && $htc->mod_rewrite && $htc->mod_headers && $htc->mod_mime) {
			$this->_rewrite_status = 'true';
			$this->setup_htaccess_file();
		} else {
			$this->_rewrite_status = 'false';
		}
		WP_Optimize()->get_options()->update_option('rewrite_status', $this->_rewrite_status);
	}

	/**
	 * If webp images should be used, then decide whether it is possible to server webp
	 * using rewrite rules or using altered html method
	 */
	private function maybe_decide_webp_serve_method() {
		$this->_should_use_webp = WP_Optimize()->get_options()->get_option('webp_conversion');
		if ('true' === $this->_rewrite_status) {
			if (!$this->_should_use_webp) {
				$this->save_htaccess_rules(false);
				return;
			} else {
				if (!$this->is_webp_redirection_possible()) {
					$this->maybe_use_alter_html();
				}
			}
		} else {
			if ($this->_should_use_webp) {
				$this->maybe_use_alter_html();
			}
		}
	}

	/**
	 * If alter html method is possible, then use it
	 */
	private function maybe_use_alter_html() {
		if ($this->is_alter_html_possible()) {
			$this->use_alter_html();
		}
	}

	/**
	 * Even if server support .htaccess rewrite, sometimes it is not possible
	 * to serve webp images. So, find it webp redirection is possible or not
	 *
	 * @return bool
	 */
	private function is_webp_redirection_possible() {
		if (!class_exists('WPO_WebP_Self_Test')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-webp-self-test.php';
		}
		$self_test = WPO_WebP_Self_Test::get_instance();

		if ($self_test->get_webp_image()) {
			$this->save_htaccess_rules($this->_should_use_webp);
			if ($self_test->is_webp_served()) return true;
		}
		$this->save_htaccess_rules();
		return false;
	}

	/**
	 * Decide whether the browser requesting the URL can accept webp images or not
	 *
	 * @return bool
	 */
	private function is_browser_accepting_webp() {
		return (isset($_SERVER['HTTP_ACCEPT']) && false !== strpos($_SERVER['HTTP_ACCEPT'], 'image/webp'));
	}
	
	/**
	 * Detect whether using alter HTML method is possible or not
	 *
	 * @return bool
	 */
	private function is_alter_html_possible() {
		if ($this->is_browser_accepting_webp()) {
			return true;
		}
		return false;
	}

	/**
	 * Setup alter html method
	 */
	private function use_alter_html() {
		if (!class_exists('WPO_WebP_Alter_HTML')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-webp-alter-html.php';
		}
		WPO_WebP_Alter_HTML::get_instance();
	}

	/**
	 * Initialize .htaccess
	 */
	private function setup_htaccess_file() {
		if (null !== $this->_htaccess) return;
		$wp_uploads = wp_get_upload_dir();
		$htaccess_file = $wp_uploads['basedir'] . '/.htaccess';
		if (!file_exists($htaccess_file)) {
			file_put_contents($htaccess_file, '');
		}
		if (!class_exists('WP_Optimize_Htaccess')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'includes/class-wp-optimize-htaccess.php';
		}
		$this->_htaccess = new WP_Optimize_Htaccess($htaccess_file);
		$this->add_webp_mime_type();
	}
	
	/**
	 * Save .htaccess rules
	 *
	 * @return bool
	 */
	private function save_htaccess_rules($webp_conversion_enabled = false) {
		$htaccess_comment_section = 'WP-Optimize WebP Rules';
	
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
		);
	}

	/**
	 * Prepare array of htaccess rules to use webp images.
	 *
	 * @return array
	 */
	private function add_webp_mime_type() {
		$htaccess_comment_section = 'Register webp mime type';
		if ($this->_htaccess->is_exists() && !$this->_htaccess->is_commented_section_exists($htaccess_comment_section)) {
			$webp_mime_type = array(
				array(
					'<IfModule mod_mime.c>',
					'AddType image/webp .webp',
					'</IfModule>',
				),
			);
			$this->_htaccess->update_commented_section($webp_mime_type, $htaccess_comment_section);
			$this->_htaccess->write_file();
		}
	}
}

endif;
