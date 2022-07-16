<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

if (!class_exists('WPO_WebP_Alter_HTML')) :

class WPO_WebP_Alter_HTML {

	private $tags = array('img', 'source', 'input', 'iframe', 'div', 'li', 'link', 'a', 'section', 'video');

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action('template_redirect', array($this, 'start'), 9999);
	}

	/**
	 * Returns singleton instance
	 *
	 * @return WPO_WebP_Alter_HTML
	 */
	public static function get_instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}
	
	/**
	 * Start to alter html in output buffer
	 */
	public function start() {
		if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
			ob_start(array(__CLASS__, 'alter_html'));
		}
	}

	/**
	 * Alter html to change image related tags to specify webp images
	 *
	 * @param string $html - HTML document as string
	 * @return string
	 */
	public function alter_html($html) {
		
		if (!$this->is_valid_html($html)) return $html;

		$this->maybe_include_simple_html_dom();

		$dom = str_get_html($html, false, false, 'UTF-8', false);

		// MAX_FILE_SIZE is defined in simple_html_dom.
		// For safety sake, we make sure it is defined before using
		defined('MAX_FILE_SIZE') || define('MAX_FILE_SIZE', 600000);

		if (false === $dom) {
			if (strlen($html) > MAX_FILE_SIZE) {
				return '<!-- Alter HTML was skipped because the HTML is too big to process! ' .
					'(limit is set to ' . MAX_FILE_SIZE . ' bytes) -->' . "\n" . $html;
			}
			return '<!-- Alter HTML was skipped because the helper library refused to process the html -->' .
				"\n" . $html;
		}

		// Replace attributes (src, srcset, data-src, etc)
		foreach ($this->tags as $tag) {
			$elems = $dom->find($tag);
			foreach ($elems as $elem) {
				$attributes = $elem->getAllAttributes();
				foreach ($attributes as $attr_name => $attr_value) {
					if ($this->is_image_attribute($attr_name)) {
						$elem->setAttribute($attr_name, $this->handle_attribute($attr_value));
					}
				}
			}
		}

		return $dom->save();
	}

	/**
	 * Checks whether supplied string is a valid html document or not
	 *
	 * @param string $html - HTML document as string
	 * @return bool
	 */
	private function is_valid_html($html) {
		if (is_feed()) return false;

		// To prevent issue with `simple_html_dom` class
		// Exit if it doesn't look like HTML
		// https://github.com/rosell-dk/webp-express/issues/228
		if (!preg_match("#^\\s*<#", $html)) return false;

		if ('' == $html) return false;
		return true;
	}

	/**
	 * Include simple html dom script if not available
	 */
	private function maybe_include_simple_html_dom() {
		if (!function_exists('str_get_html')) {
			require_once WPO_PLUGIN_MAIN_PATH . 'vendor/simplehtmldom/simplehtmldom/simple_html_dom.php';
		}
	}

	/**
	 * Append image urls with `.webp` extension
	 *
	 * @param strinng $url - Image URL
	 * @return string
	 */
	private function replace_url($url) {
		return $url . '.webp';
	}

	/**
	 * If webp version for supplied image url is available then replace extension
	 *
	 * @param string $url - URL of image
	 * @return string
	 */
	private function maybe_replace_url($url) {
		if ($this->is_webp_version_available($url)) {
			$url = $this->replace_url($url);
		}
		return $url;
	}

	/**
	 * Modifies src attribute value, if needed
	 *
	 * @param string $attr_value
	 * @return string
	 */
	private function handle_src($attr_value) {
		return $this->maybe_replace_url($attr_value);
	}

	/**
	 * Modified `srcset` attribute value, if needed
	 *
	 * @param string $attr_value
	 * @return string
	 */
	private function handle_srcset($attr_value) {
		// $attr_value is ie: <img data-x="1.jpg 1000w, 2.jpg">
		$srcset_arr = explode(',', $attr_value);
		foreach ($srcset_arr as $i => $srcset_entry) {
			// $srcset_entry is ie "image.jpg 520w", but can also lack width, ie just "image.jpg"
			// it can also be ie "image.jpg 2x"
			$srcset_entry = trim($srcset_entry);
			$entry_parts = preg_split('/\s+/', $srcset_entry, 2);
			if (count($entry_parts) == 2) {
				list($src, $descriptors) = $entry_parts;
			} else {
				$src = $srcset_entry;
				$descriptors = null;
			}

			$url = $this->maybe_replace_url($src);
			$srcset_arr[$i] = $url . (isset($descriptors) ? ' ' . $descriptors : '');
		}
		return implode(', ', $srcset_arr);
	}

	/**
	 * Decides whether given value is a `srcset` or not
	 *
	 * @return bool
	 */
	private function looks_like_srcset($value) {
		if (preg_match('#\s\d*(w|x)#', $value)) {
			return true;
		}
		return false;
	}

	/**
	 * Handle attribute value based on attribute name, src or srcset
	 *
	 * @return string
	 */
	private function handle_attribute($value) {
		if ($this->looks_like_srcset($value)) {
			return $this->handle_srcset($value);
		}
		return $this->handle_src($value);
	}

	/**
	 * Decide whether given attribute name is an image attribute or not
	 *
	 * @return bool
	 */
	private function is_image_attribute($attr_name) {
		return preg_match('#^(src|srcset|poster|(data-[^=]*(lazy|small|slide|img|large|src|thumb|source|set|bg-url)[^=]*))$#i', $attr_name);
	}

	/**
	 * Does webp image file exists
	 *
	 * @param string $url
	 * @return boolean
	 */
	private function is_webp_version_available($url) {
		$filename = $this->get_file_path($url);
		return file_exists($filename . '.webp');
	}

	/**
	 * Get the file path
	 *
	 * @param string $url
	 * @return string
	 */
	private function get_file_path($url) {
		if (is_multisite()) {
			if (function_exists('get_main_site_id')) {
				$site_id = get_main_site_id();
			} else {
				$network = get_network();
				$site_id = $network->site_id;
			}
			switch_to_blog($site_id);
		}
		$upload_dir = wp_upload_dir();
		$uploads_url = trailingslashit($upload_dir['baseurl']);
		$uploads_dir = trailingslashit($upload_dir['basedir']);
		if (is_multisite()) {
			restore_current_blog();
		}
		$possible_urls = array(
			WP_CONTENT_URL => WP_CONTENT_DIR,
			WP_PLUGIN_URL => WP_PLUGIN_DIR,
			$uploads_url => $uploads_dir,
			get_template_directory_uri() => get_template_directory(),
			includes_url() => preg_replace('/wp-content$/', trailingslashit('wp-includes'), WP_CONTENT_DIR),
		);
		$file = '';
		foreach ($possible_urls as $possible_url => $path) {
			$pos = strpos($url, $possible_url);
			if (false !== $pos) {
				$file = substr_replace($url, $path, $pos, strlen($possible_url));
				break;
			}
		}
		return $file;
	}
}

endif;
