<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Extensions directory.
 */
if (!defined('WPO_CACHE_EXT_DIR')) define('WPO_CACHE_EXT_DIR', dirname(__FILE__).'/extensions');

/**
 * Holds utility functions used by file based cache
 */

/**
 * Cache output before it goes to the browser. If moving/renaming this function, then also change the check above.
 *
 * @param  String $buffer Page HTML.
 * @param  Int    $flags  OB flags to be passed through.
 *
 * @return String
 */
if (!function_exists('wpo_cache')) :
function wpo_cache($buffer, $flags) {
	
	// This case appears to happen for unclear reasons without WP being fully loaded, e.g. https://wordpress.org/support/topic/fatal-error-since-wp-5-8-update/ . It is simplest just to short-circuit it.
	if ('' === $buffer) return '';
	
	// This array records reasons why no cacheing took place. Be careful not to allow actions to proceed that should not - i.e. take note of its state appropriately.
	$no_cache_because = array();

	if (strlen($buffer) < 255) {
		$no_cache_because[] = sprintf(__('Output is too small (less than %d bytes) to be worth caching', 'wp-optimize'), 255);
	}

	// Don't cache pages for logged in users.
	if (empty($GLOBALS['wpo_cache_config']['enable_user_specific_cache']) && (!function_exists('wpo_we_cache_per_role') || !wpo_we_cache_per_role()) && (!function_exists('is_user_logged_in') || (function_exists('wp_get_current_user') && is_user_logged_in()))) {
		$no_cache_because[] = __('User is logged in', 'wp-optimize');
	}

	$restricted_page_type_cache = apply_filters('wpo_restricted_cache_page_type', false);
	if ($restricted_page_type_cache) {
		$no_cache_because[] = $restricted_page_type_cache;
	}

		$conditional_tag_exceptions = apply_filters('wpo_url_in_conditional_tags_exceptions', false);
	if ($conditional_tag_exceptions) {
		$no_cache_because[] = $conditional_tag_exceptions;
	}

	// No root cache folder, so short-circuit here
	if (!file_exists(WPO_CACHE_DIR)) {
		$no_cache_because[] = __('WP-O cache parent directory was not found', 'wp-optimize').' ('.WPO_CACHE_DIR.')';
	} elseif (!file_exists(WPO_CACHE_FILES_DIR)) {
		// Try creating a folder for cached files, if it was flushed recently
		if (!mkdir(WPO_CACHE_FILES_DIR)) {
			$no_cache_because[] = __('WP-O cache directory was not found', 'wp-optimize').' ('.WPO_CACHE_FILES_DIR.')';
		} else {
			wpo_disable_cache_directories_viewing();
		}
	}

	// If comments are opened and the user has saved his information.
	if (function_exists('comments_open') && function_exists('get_post') && get_post() && comments_open()) {
		$commenter = wp_get_current_commenter();
		// if any of the fields contain something, do not save to cache
		if ('' != $commenter['comment_author'] || '' != $commenter['comment_author_email'] || '' != $commenter['comment_author_url']) {
			$no_cache_because[] = __('Comments are opened and the visitor saved his information.', 'wp-optimize');
		}
	}

	$can_cache_page = true;
	
	if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
		$can_cache_page = false;
	}

	/**
	 * Defines if the page can be cached or not
	 *
	 * @param boolean $can_cache_page
	 */
	$can_cache_page_filter = apply_filters('wpo_can_cache_page', $can_cache_page);

	if (!$can_cache_page_filter) {
		if ($can_cache_page) {
			$can_cache_page = false;
			$no_cache_because[] = __('wpo_can_cache_page filter forbade it', 'wp-optimize');
		} else {
			$no_cache_because[] = __('DONOTCACHEPAGE constant forbade it and wpo_can_cache_page filter did not over-ride it', 'wp-optimize');
		}
	}

	if (defined('REST_REQUEST') && REST_REQUEST) {
		$no_cache_because[] = __('This is a REST API request (identified by REST_REQUEST constant)', 'wp-optimize');
	}

	// Don't cache with fatal error pages.
	$last_error = error_get_last();
	if (is_array($last_error) && E_ERROR == $last_error['type']) {
		$no_cache_because[] = __('This page has a fatal error', 'wp-optimize');
	}

	if (http_response_code() >= 500) {
		$no_cache_because[] = sprintf(__('This page has a critical error (HTTP code %s)', 'wp-optimize'), http_response_code());
	} elseif (http_response_code() >= 400) {
		$no_cache_because[] = sprintf(__('This page returned an HTTP unauthorised response code (%s)', 'wp-optimize'), http_response_code());
	}

	if (empty($no_cache_because)) {

		$buffer = apply_filters('wpo_pre_cache_buffer', $buffer, $flags);

		$url_path = wpo_get_url_path();

		$dirs = explode('/', $url_path);

		$path = WPO_CACHE_FILES_DIR;

		foreach ($dirs as $dir) {
			if (!empty($dir)) {
				$path .= '/' . $dir;

				if (!file_exists($path)) {
					if (!mkdir($path)) {
						$no_cache_because[] = __('Attempt to create subfolder within cache directory failed', 'wp-optimize')." ($path)";
						break;
					}
				}
			}
		}
	}

	if (!empty($no_cache_because)) {

		$message = implode(', ', $no_cache_because);

		// Add http headers
		wpo_cache_add_nocache_http_header($message);

		// Only output if the user has turned on debugging output
		if (((defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['wpo_cache_debug'])) && (!defined('DOING_CRON') || !DOING_CRON) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
			$buffer .= "\n<!-- WP Optimize page cache - https://getwpo.com - page NOT cached because: ".htmlspecialchars($message)." -->\n";
		}
		
		return $buffer;
	
	} else {
	
		// Prevent mixed content when there's an http request but the site URL uses https.
		$home_url = get_home_url();

		if (!is_ssl() && 'https' === strtolower(parse_url($home_url, PHP_URL_SCHEME))) {
			$https_home_url = $home_url;
			$http_home_url = str_ireplace('https://', 'http://', $https_home_url);
			$buffer = str_replace(esc_url($http_home_url), esc_url($https_home_url), $buffer);
		}

		$modified_time = time(); // Take this as soon before writing as possible

		$add_to_footer = '';
		
		/**
		 * Filter wether to display the html comment <!-- Cached by WP-Optimize ... -->
		 *
		 * @param boolean $show - Wether to display the html comment
		 * @return boolean
		 */
		if (preg_match('#</html>#i', $buffer) && (apply_filters('wpo_cache_show_cached_by_comment', true) || (defined('WP_DEBUG') && WP_DEBUG))) {
			if (!empty($GLOBALS['wpo_cache_config']['enable_mobile_caching']) && wpo_is_mobile()) {
				$add_to_footer .= "\n<!-- Cached by WP-Optimize - for mobile devices - https://getwpo.com - Last modified: " . gmdate('D, d M Y H:i:s', $modified_time) . " GMT -->\n";
			} else {
				$add_to_footer .= "\n<!-- Cached by WP-Optimize - https://getwpo.com - Last modified: " . gmdate('D, d M Y H:i:s', $modified_time) . " GMT -->\n";
			}
		}

		// Create an empty index.php file in the cache directory for disable directory viewing.
		if (!is_file($path . '/index.php')) file_put_contents($path . '/index.php', '');

		/**
		 * Save $buffer into cache file.
		 */
		$file_ext = '.html';

		if (wpo_feeds_caching_enabled()) {
			if (is_feed()) {
				$file_ext = '.rss-xml';
			}
		}

		$cache_filename = wpo_cache_filename($file_ext);
		$cache_file = $path . '/' .$cache_filename;

		if (defined('WPO_CACHE_FILENAME_DEBUG') && WPO_CACHE_FILENAME_DEBUG) {
			$add_to_footer .= "\n<!-- WP Optimize page cache debug information -->\n";
			if (!empty($GLOBALS['wpo_cache_filename_debug']) && is_array($GLOBALS['wpo_cache_filename_debug'])) {
				$add_to_footer .= "<!-- \n" . join("\n", array_map('htmlspecialchars', $GLOBALS['wpo_cache_filename_debug'])) . "\n --->";
			}
		}

		// if we can then cache gzipped content in .gz file.
		if (function_exists('gzencode') && apply_filters('wpo_allow_cache_gzip_files', true)) {
			// Only replace inside the addition, not inside the main buffer (e.g. post content)
			file_put_contents($cache_file . '.gz', gzencode($buffer.str_replace('by WP-Optimize', 'by WP-Optimize (gzip)', $add_to_footer), apply_filters('wpo_cache_gzip_level', 6)));
		}

		file_put_contents($cache_file, $buffer.$add_to_footer);

		if (is_callable('WP_Optimize')) {
			// delete cached information about cache size.
			WP_Optimize()->get_page_cache()->delete_cache_size_information();
		} else {
			// If the shutdown occurs before plugins are loaded,
			// then this will trigger a fatal error, so, we check first
			if (!doing_action('shutdown')) {
				error_log('[WPO_CACHE] WP_Optimize() is not callable.');
				$message = 'Please report this to WP-O support: ';
				if (function_exists('wp_debug_backtrace_summary')) {
					$message .= wp_debug_backtrace_summary();
				} else {
					$message .= wpo_debug_backtrace_summary();
				}
				error_log($message);
			}
		}

		header('Cache-Control: no-cache'); // Check back every time to see if re-download is necessary.
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified_time) . ' GMT');
		header('WPO-Cache-Status: saving to cache');

		if (wpo_cache_can_output_gzip_content()) {
		
			if (!wpo_cache_is_in_response_headers_list('Content-Encoding', 'gzip')) {
				header('Content-Encoding: gzip');
			}
		
			// disable php gzip to avoid double compression.
			ini_set('zlib.output_compression', 'Off'); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set

			return ob_gzhandler($buffer, $flags);
		} else {
			return $buffer;
		}
	}
}
endif;

/**
 * Load files for support plugins.
 */
if (!function_exists('wpo_cache_load_extensions')) :
function wpo_cache_load_extensions() {
	$extensions = glob(WPO_CACHE_EXT_DIR . '/*.php');

	// Add external extensions
	if (defined('WPO_CACHE_CUSTOM_EXT_DIR') && is_dir(WPO_CACHE_CUSTOM_EXT_DIR)) {
		$extensions = array_merge($extensions, glob(WPO_CACHE_CUSTOM_EXT_DIR . '/*.php'));
	}

	if (empty($extensions)) return;

	foreach ($extensions as $extension) {
		if (is_file($extension)) require_once $extension;
	}
}
endif;

if (!function_exists('wpo_restricted_cache_page_type')) {
function wpo_restricted_cache_page_type($restricted) {
	global $post;

	// Don't cache search or password protected.
	if ((function_exists('is_search') && is_search()) || (function_exists('is_404') && is_404()) || !empty($post->post_password)) {
		$restricted = __('Page type is not cacheable (search, 404 or password-protected)', 'wp-optimize');
	}

	// Don't cache the front page if option is set.
	if (in_array('/', wpo_get_url_exceptions()) && function_exists('is_front_page') && is_front_page()) {

		$restricted = __('In the settings, caching is disabled for the front page', 'wp-optimize');
	}

	// Don't cache htacesss. Remember to properly escape any output to prevent injection.
	if (strpos($_SERVER['REQUEST_URI'], '.htaccess') !== false) {
		$restricted = 'The file path is unsuitable for caching ('.$_SERVER['REQUEST_URI'].')';
	}

	// Don't cache feeds.
	if (function_exists('is_feed') && is_feed() && !wpo_feeds_caching_enabled()) {
		$restricted = __('We don\'t cache RSS feeds', 'wp-optimize');
	}

	return $restricted;
}
}

/**
 * Returns true if we need cache content for loggedin users.
 *
 * @return bool
 */
if (!function_exists('wpo_cache_loggedin_users')) :
function wpo_cache_loggedin_users() {
	return !empty($GLOBALS['wpo_cache_config']['enable_user_caching']) || !empty($GLOBALS['wpo_cache_config']['enable_user_specific_cache']) || (function_exists('wpo_we_cache_per_role') && wpo_we_cache_per_role());
}
endif;

/**
 * Returns true if we need to cache content for loggedin users.
 *
 * @return bool
 */
if (!function_exists('wpo_user_specific_cache_enabled')) :
	function wpo_user_specific_cache_enabled() {
		return !empty($GLOBALS['wpo_cache_config']['enable_user_specific_cache']) && !empty($GLOBALS['wpo_cache_config']['wp_salt_auth']) && !empty($GLOBALS['wpo_cache_config']['wp_salt_logged_in']);
	}
endif;

/**
 * Get filename for store cache, depending on gzip, mobile and cookie settings.
 *
 * @param string $ext
 * @return string
 */
if (!function_exists('wpo_cache_filename')) :
function wpo_cache_filename($ext = '.html') {

	$wpo_cache_filename_debug = array();

	$filename = 'index';

	if (wpo_cache_mobile_caching_enabled() && wpo_is_mobile()) {
		$filename = 'mobile.' . $filename;
	}

	if (wpo_webp_images_enabled() && !wpo_is_using_webp_images_redirection() && wpo_is_using_alter_html()) {
		$filename = $filename . '.webp';
	}

	$cookies = wpo_cache_cookies();

	$cache_key = '';

	/**
	 * Add cookie values to filename if need.
	 * This section was inspired by things learned from WP-Rocket.
	 */
	if (!empty($cookies)) {
		foreach ($cookies as $key => $cookie_name) {
			if (is_array($cookie_name) && isset($_COOKIE[$key])) {
				foreach ($cookie_name as $cookie_key) {
					if (isset($_COOKIE[$key][$cookie_key]) && '' !== $_COOKIE[$key][$cookie_key]) {
						$_cache_key = $cookie_key.'='.$_COOKIE[$key][$cookie_key];
						$_cache_key = preg_replace('/[^a-z0-9_\-\=]/i', '-', $_cache_key);
						$cache_key .= '-' . $_cache_key;
						$wpo_cache_filename_debug[] = 'Cookie: name: ' . $key . '[' . $cookie_key . '], value: *** , cache_key:' . $_cache_key;
					}
				}
				continue;
			}

			if (isset($_COOKIE[$cookie_name]) && '' !== $_COOKIE[$cookie_name]) {
				$_cache_key = $cookie_name.'='.$_COOKIE[$cookie_name];
				$_cache_key = preg_replace('/[^a-z0-9_\-\=]/i', '-', $_cache_key);
				$cache_key .= '-' . $_cache_key;
				$wpo_cache_filename_debug[] = 'Cookie: name: ' . $cookie_name . ', value: *** , cache_key:' . $_cache_key;
			}
		}
	}

	$query_variables = wpo_cache_query_variables();

	/**
	 * Add GET variables to cache file name if need.
	 */
	if (!empty($query_variables)) {
		foreach ($query_variables as $variable) {
			if (isset($_GET[$variable]) && !empty($_GET[$variable])) {
				$_cache_key = $variable.'='.$_GET[$variable];
				$_cache_key = preg_replace('/[^a-z0-9_\-\=]/i', '-', $_cache_key);
				$cache_key .= '-' . $_cache_key;
				$wpo_cache_filename_debug[] = 'GET parameter: name: ' . $variable . ', value:' . htmlentities($_GET[$variable]) . ', cache_key:' . $_cache_key;
			}
		}
	}

	// add hash of queried cookies and variables to cache file name.
	if ('' !== $cache_key) {
		$hash = md5($cache_key);
		$filename .= '-'.$hash;
		$wpo_cache_filename_debug[] = 'Hash: ' . $hash;
	}

	$filename = apply_filters('wpo_cache_filename', $filename);

	$wpo_cache_filename_debug[] = 'Extension: ' . $ext;
	$wpo_cache_filename_debug[] = 'Filename: ' . $filename.$ext;

	$GLOBALS['wpo_cache_filename_debug'] = $wpo_cache_filename_debug;

	return $filename . $ext;
}
endif;

/**
 * Returns site url from site_url() function or if it is not available from cache configuration.
 */
if (!function_exists('wpo_site_url')) :
function wpo_site_url() {
	if (is_callable('site_url')) return site_url('/');

	$site_url = empty($GLOBALS['wpo_cache_config']['site_url']) ? '' : $GLOBALS['wpo_cache_config']['site_url'];
	return $site_url;
}
endif;

/**
 * Get cookie names which impact on cache file name.
 *
 * @return array
 */
if (!function_exists('wpo_cache_cookies')) :
function wpo_cache_cookies() {
	$cookies = empty($GLOBALS['wpo_cache_config']['wpo_cache_cookies']) ? array() : $GLOBALS['wpo_cache_config']['wpo_cache_cookies'];
	return $cookies;
}
endif;

/**
 * Get GET variable names which impact on cache file name.
 *
 * @return array
 */
if (!function_exists('wpo_cache_query_variables')) :
function wpo_cache_query_variables() {
	if (defined('WPO_CACHE_URL_PARAMS') && WPO_CACHE_URL_PARAMS) {
		$variables = array_keys($_GET);
	} else {
		$variables = empty($GLOBALS['wpo_cache_config']['wpo_cache_query_variables']) ? array() : $GLOBALS['wpo_cache_config']['wpo_cache_query_variables'];
	}

	if (!empty($variables)) {
		sort($variables);
	}

	return wpo_cache_maybe_ignore_query_variables($variables);
}
endif;

/**
 * Get list of all received HTTP headers.
 *
 * @return array
 */
if (!function_exists('wpo_get_http_headers')) :
function wpo_get_http_headers() {

	static $headers;

	if (!empty($headers)) return $headers;

	$headers = array();

	// if is apache server then use get allheaders() function.
	if (function_exists('getallheaders')) {
		$headers = getallheaders();
	} else {
		// https://www.php.net/manual/en/function.getallheaders.php
		foreach ($_SERVER as $key => $value) {

			$key = strtolower($key);

			if ('HTTP_' == substr($key, 0, 5)) {
				$headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', substr($key, 5))))] = $value;
			} elseif ('content_type' == $key) {
				$headers["Content-Type"] = $value;
			} elseif ('content_length' == $key) {
				$headers["Content-Length"] = $value;
			}
		}
	}

	return $headers;
}
endif;

/**
 * Check if requested Accept-Encoding headers has gzip value.
 *
 * @return bool
 */
if (!function_exists('wpo_cache_gzip_accepted')) :
function wpo_cache_gzip_accepted() {
	$headers = wpo_get_http_headers();

	if (isset($headers['Accept-Encoding']) && preg_match('/gzip/i', $headers['Accept-Encoding'])) return true;

	return false;
}
endif;

/**
 * Check if we can output gzip content in current answer, i.e. check Accept-Encoding headers has gzip value
 * and function ob_gzhandler is available.
 *
 * @return bool
 */
if (!function_exists('wpo_cache_can_output_gzip_content')) :
function wpo_cache_can_output_gzip_content() {
	return wpo_cache_gzip_accepted() && function_exists('ob_gzhandler');
}
endif;

/**
 * Check if header with certain name exists in already prepared headers and has value comparable with $header_value.
 *
 * @param string $header_name  header name
 * @param string $header_value header value as regexp.
 *
 * @return bool
 */
if (!function_exists('wpo_cache_is_in_response_headers_list')) :
function wpo_cache_is_in_response_headers_list($header_name, $header_value) {
	$headers_list = headers_list();

	if (!empty($headers_list)) {
		$header_name = strtolower($header_name);

		foreach ($headers_list as $value) {
			$value = explode(':', $value);

			if (strtolower($value[0]) == $header_name) {
				if (preg_match('/'.$header_value.'/', $value[1])) {
					return true;
				} else {
					return false;
				}
			}
		}
	}

	return false;
}
endif;

/**
 * Check if mobile cache is enabled and current request is from moblile device.
 *
 * @return bool
 */
if (!function_exists('wpo_cache_mobile_caching_enabled')) :
function wpo_cache_mobile_caching_enabled() {
	if (!empty($GLOBALS['wpo_cache_config']['enable_mobile_caching'])) return true;
	return false;
}
endif;

/**
 * Check if webp images enabled
 *
 * @return bool
 */
if (!function_exists('wpo_webp_images_enabled')) :
	function wpo_webp_images_enabled() {
		if (!empty($GLOBALS['wpo_cache_config']['use_webp_images'])) return true;
		return false;
	}
endif;

/**
 * Check whether webp images using alter html method or not
 *
 * @return bool
 */
if (!function_exists('wpo_is_using_alter_html')) :
	function wpo_is_using_alter_html() {
		return (isset($_SERVER['HTTP_ACCEPT']) && false !== strpos($_SERVER['HTTP_ACCEPT'], 'image/webp'));
	}
endif;

/**
 * Check whether webp images are served using redirect
 *
 * @return bool
 */
if (!function_exists('wpo_is_using_webp_images_redirection')) :
	function wpo_is_using_webp_images_redirection() {
		if (empty($GLOBALS['wpo_cache_config']['uploads'])) return false;

		$uploads_dir =  $GLOBALS['wpo_cache_config']['uploads'];
		$htaccess_file = $uploads_dir . '/.htaccess';
		if (!file_exists($htaccess_file)) return false;
		$htaccess_content = file_get_contents($htaccess_file);
		$comment_sections = array('Register webp mime type', 'WP-Optimize WebP Rules');

		if (function_exists('str_contains')) {
			return str_contains($htaccess_content, $comment_sections[0]) && str_contains($htaccess_content, $comment_sections[1]);
		} else {
			return strpos($htaccess_content, $comment_sections[0]) && strpos($htaccess_content, $comment_sections[1]);
		}
	}
endif;

/**
 * Serves the cache and exits
 */
if (!function_exists('wpo_serve_cache')) :
function wpo_serve_cache() {
	$file_name = wpo_cache_filename();

	$file_name_rss_xml = wpo_cache_filename('.rss-xml');
	$send_as_feed = false;

	$path_dir = WPO_CACHE_FILES_DIR . '/' . wpo_get_url_path() . '/';
	$path = $path_dir . $file_name;

	if (wpo_feeds_caching_enabled()) {
		// check for .xml cache file if .html cache file doesn't exist
		if (!file_exists($path_dir . $file_name) && file_exists($path_dir . $file_name_rss_xml)) {
			$path = $path_dir . $file_name_rss_xml;
			$send_as_feed = true;
		}
	}

	$use_gzip = false;

	// if we can use gzip and gzipped file exist in cache we use it.
	// if headers already sent we don't use gzipped file content.
	if (!headers_sent() && wpo_cache_gzip_accepted() && file_exists($path . '.gz')) {
		$path .= '.gz';
		$use_gzip = true;
	}

	$modified_time = file_exists($path) ? (int) filemtime($path) : time();

	// Cache has expired, purge and exit.
	if (!empty($GLOBALS['wpo_cache_config']['page_cache_length'])) {
		if (time() > ($GLOBALS['wpo_cache_config']['page_cache_length'] + $modified_time)) {
			wpo_delete_files($path);
			return;
		}
	}

	// disable zlib output compression to avoid double content compression.
	if ($use_gzip) {
		ini_set('zlib.output_compression', 'Off'); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
	}

	$gzip_header_already_sent = wpo_cache_is_in_response_headers_list('Content-Encoding', 'gzip');

	header('Cache-Control: no-cache'); // Check back later

	if (!empty($modified_time) && !empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $modified_time) {
		if ($use_gzip && !$gzip_header_already_sent) {
			header('Content-Encoding: gzip');
		}

		if ($send_as_feed) {
			header('Content-type: application/rss+xml');
		}

		header('WPO-Cache-Status: cached');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified_time) . ' GMT');
		header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304);
		exit;
	}

	if (file_exists($path) && is_readable($path)) {

		if (wpo_is_canonical_redirection_needed()) return;

		if ($use_gzip && !$gzip_header_already_sent) {
			header('Content-Encoding: gzip');
		}

		// send correct headers for xml and txt files
		$filename = basename(dirname($path));

		if (preg_match('/\.xml$/i', $filename)) {
			header('Content-type: text/xml');
		}

		if (preg_match('/\.txt$/i', $filename)) {
			header('Content-type: text/plain');
		}

		if ($send_as_feed) {
			header('Content-type: application/rss+xml');
		}

		header('WPO-Cache-Status: cached');
		if (!empty($modified_time)) {
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified_time) . ' GMT');
		}

		readfile($path);

		exit;
	}
}
endif;

/**
 * Checks and does redirection, if needed
 *
 * @return bool
 */
if (!function_exists('wpo_is_canonical_redirection_needed')) :
	function wpo_is_canonical_redirection_needed() {
		$permalink_structure = isset($GLOBALS['wpo_cache_config']['permalink_structure']) ? $GLOBALS['wpo_cache_config']['permalink_structure'] : '';
		$site_url = $GLOBALS['wpo_cache_config']['site_url'];
		
		$schema = isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? "https" : "http";
		$url_part = "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$requested_url = $schema . $url_part;
		$url_parts = parse_url($requested_url);
		$extension = pathinfo($url_parts['path'], PATHINFO_EXTENSION);
		
		if (!empty($permalink_structure) && $requested_url != $site_url) {
			if ('/' == substr($permalink_structure, -1) && empty($extension) && empty($url_parts['query']) && empty($url_parts['fragment'])) {
				$url = preg_replace('/(.+?)([\/]*)(\[\?\#][^\/]+|$)/', '$1/$3', $_SERVER['REQUEST_URI']);
				if (0 !== strcmp($_SERVER['REQUEST_URI'], $url)) return true;
			} else {
				$url = rtrim($_SERVER['REQUEST_URI'], '/');
				if (0 !== strcmp($_SERVER['REQUEST_URI'], $url)) return true;
			}
		}
		return false;
	}
endif;

/**
 * Clears the cache
 */
if (!function_exists('wpo_cache_flush')) :
function wpo_cache_flush() {

	if (defined('WPO_CACHE_FILES_DIR') && '' != WPO_CACHE_FILES_DIR) wpo_delete_files(WPO_CACHE_FILES_DIR);

	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}

	do_action('wpo_cache_flush');
}
endif;

/**
 * Get URL path for caching
 *
 * @since  1.0
 * @return string
 */
if (!function_exists('wpo_get_url_path')) :
function wpo_get_url_path($url = '') {
	$url = '' == $url ? wpo_current_url() : $url;
	$url_parts = parse_url($url);
	
	if (isset($url_parts['path']) && false !== stripos($url_parts['path'], '/index.php')) {
		$url_parts['path'] = preg_replace('/(.*?)index\.php(\/.+)/i', '$1index-php$2', $url_parts['path']);
	}

	if (!isset($url_parts['host'])) $url_parts['host'] = '';
	if (!isset($url_parts['path'])) $url_parts['path'] = '';

	return $url_parts['host'].$url_parts['path'];
}
endif;

/**
 * Get requested url.
 *
 * @return string
 */
if (!function_exists('wpo_current_url')) :
function wpo_current_url() {
	// Note: We use `static $url` to save the first value we retrieve, as some plugins change $_SERVER later on in the process (e.g. Weglot).
	// Otherwise this function would return a different URL at the begining and end of the cache process.
	static $url = '';
	if ('' != $url) return $url;
	$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
	$url = rtrim('http' . ((isset($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS']) ||
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) ? 's' : '' )
		. '://' . $http_host.$_SERVER['REQUEST_URI'], '/');
	return $url;
}
endif;

/**
 * Return list of conditional tag exceptions.
 *
 * @return array
 */
if (!function_exists('wpo_get_conditional_tags_exceptions')) :
function wpo_get_conditional_tags_exceptions() {
	static $exceptions = null;

	if (null !== $exceptions) return $exceptions;
	
	if (!empty($GLOBALS['wpo_cache_config'])) {
		if (empty($GLOBALS['wpo_cache_config']['cache_exception_conditional_tags'])) {
			$exceptions = array();

		} else {
			
			$exceptions = $GLOBALS['wpo_cache_config']['cache_exception_conditional_tags'];

		}
		
	} elseif (class_exists('WPO_Page_Cache')) {
	
		$config = WPO_Page_Cache::instance()->config->get();

		if (is_array($config) && array_key_exists('cache_exception_conditional_tags', $config)) {
			$exceptions = $config['cache_exception_conditional_tags'];
		} else {
			$exceptions = array();
		}

		$exceptions = is_array($exceptions) ? $exceptions : preg_split('#(\n|\r|\r\n)#', $exceptions);
		$exceptions = array_filter($exceptions, 'trim');
		
	} else {
		$exceptions = array();
	}

	return $exceptions;
}
endif;

/**
 * Return list of url exceptions.
 *
 * @return array
 */
if (!function_exists('wpo_get_url_exceptions')) :
function wpo_get_url_exceptions() {
	static $exceptions = null;

	if (null !== $exceptions) return $exceptions;

	// if called from file-based-page-cache.php when WP loading
	// and cache settings exists then use it otherwise get settings from database.
	if (!empty($GLOBALS['wpo_cache_config'])) {
		if (empty($GLOBALS['wpo_cache_config']['cache_exception_urls'])) {
			$exceptions = array();
		} else {
			$exceptions = is_array($GLOBALS['wpo_cache_config']['cache_exception_urls']) ? $GLOBALS['wpo_cache_config']['cache_exception_urls'] : preg_split('#(\n|\r)#', $GLOBALS['wpo_cache_config']['cache_exception_urls']);
		}
	} elseif (class_exists('WPO_Page_Cache')) {
		$config = WPO_Page_Cache::instance()->config->get();

		if (is_array($config) && array_key_exists('cache_exception_urls', $config)) {
			$exceptions = $config['cache_exception_urls'];
		} else {
			$exceptions = array();
		}

		$exceptions = is_array($exceptions) ? $exceptions : preg_split('#(\n|\r)#', $exceptions);
		$exceptions = array_filter($exceptions, 'trim');
	} else {
		$exceptions = array();
	}

	return apply_filters('wpo_get_url_exceptions', $exceptions);
}
endif;

/**
 * Return true of exception url matches current url
 *
 * @param  string $exception Exceptions to check URL against.
 * @param  bool   $regex	 Whether to check with regex or not.
 * @return bool   true if matched, false otherwise
 */
if (!function_exists('wpo_current_url_exception_match')) :
function wpo_current_url_exception_match($exception) {

	return wpo_url_exception_match(wpo_current_url(), $exception);
}
endif;

/**
 * Check if url in conditional tags exceptions list.
 *
 * @return string
 */
if (!function_exists('wpo_url_in_conditional_tags_exceptions')) :
function wpo_url_in_conditional_tags_exceptions() {

	$exceptions = wpo_get_conditional_tags_exceptions();
	$restricted = '';
	$allowed_functions = array('is_single', 'is_page', 'is_front_page', 'is_home', 'is_archive', 'is_tag', 'is_category', 'is_feed', 'is_search', 'is_author', 'is_woocommerce', 'is_shop', 'is_product', 'is_account_page', 'is_product_category', 'is_product_tag', 'is_wc_endpoint_url', 'is_bbpress', 'bbp_is_forum_archive', 'bbp_is_topic_archive', 'bbp_is_topic_tag', 'bbp_is_single_forum', 'bbp_is_single_topic', 'bbp_is_single_view', 'bbp_is_single_user', 'bbp_is_user_home', 'bbp_is_search');
	//Filter for add more conditional tags to whitelist in the exceptions list.
	$allowed_functions = apply_filters('wpo_allowed_conditional_tags_exceptions', $allowed_functions);
	if (!empty($exceptions)) {
		foreach ($exceptions as $exception) {
			if (false !== strpos($exception, 'is_')) {
				$exception_function = $exception;
				if ('()' == substr($exception, -2)) {
					$exception_function = substr($exception, 0, -2);
				}

				if (in_array($exception_function, $allowed_functions) && function_exists($exception_function) && call_user_func($exception_function)) {
					$restricted = sprintf(__('In the settings, caching is disabled for %s', 'wp-optimize'), $exception_function);
				}
			}
		}
	}
	return $restricted;
}
endif;


/**
 * Check if url in exceptions list.
 *
 * @param string $url
 *
 * @return bool
 */
if (!function_exists('wpo_url_in_exceptions')) :
function wpo_url_in_exceptions($url) {
	$exceptions = wpo_get_url_exceptions();

	if (!empty($exceptions)) {
		foreach ($exceptions as $exception) {

			// don't check / - front page using regexp, we handle it in wpo_restricted_cache_page_type()
			if ('/' == $exception) continue;

			if (wpo_url_exception_match($url, $exception)) {
				// Exception match.
				return true;
			}
		}
	}

	return false;
}
endif;

/**
 * Check if url string match with exception.
 *
 * @param string $url       - complete url string i.e. http(s):://domain/path
 * @param string $exception - complete url or absolute path, can consist (.*) wildcards
 *
 * @return bool
 */
if (!function_exists('wpo_url_exception_match')) :
function wpo_url_exception_match($url, $exception) {
	if (preg_match('#^[\s]*$#', $exception)) {
		return false;
	}

	$exception = str_replace('*', '.*', $exception);

	$exception = trim($exception);

	// used to test websites placed in subdirectories.
	$sub_dir = '';

	// if exception defined from root i.e. /page1 then remove domain part in url.
	if (preg_match('/^\//', $exception)) {
		// get site sub directory.
		$sub_dir = preg_replace('#^(http|https):\/\/.*\/#Ui', '', wpo_site_url());
		// add prefix slash and remove slash.
		$sub_dir = ('' == $sub_dir) ? '' : '/' . rtrim($sub_dir, '/');
		// get relative path
		$url = preg_replace('#^(http|https):\/\/.*\/#Ui', '/', $url);
	}

	$url = rtrim($url, '/') . '/';
	$exception = rtrim($exception, '/');

	// if we have no wildcat in the end of exception then add slash.
	if (!preg_match('#\(\.\*\)$#', $exception)) $exception .= '/';

	$exception = preg_quote($exception);

	// fix - unescape possible escaped mask .*
	$exception = str_replace('\\.\\*', '.*', $exception);

	return preg_match('#^'.$exception.'$#i', $url) || preg_match('#^'.$sub_dir.$exception.'$#i', $url);
}
endif;

/**
 * Checks if its a mobile device
 *
 * @see https://developer.wordpress.org/reference/functions/wp_is_mobile/
 */
if (!function_exists('wpo_is_mobile')) :
function wpo_is_mobile() {
	if (empty($_SERVER['HTTP_USER_AGENT'])) {
		$is_mobile = false;
	// many mobile devices (all iPhone, iPad, etc.)
	} elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Silk/') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false
		|| strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false
	) {
		$is_mobile = true;
	} else {
		$is_mobile = false;
	}

	return $is_mobile;
}
endif;

/**
 * Check if current browser agent is not disabled in options.
 *
 * @return bool
 */
if (!function_exists('wpo_is_accepted_user_agent')) :
function wpo_is_accepted_user_agent($user_agent) {

	$exceptions = is_array($GLOBALS['wpo_cache_config']['cache_exception_browser_agents']) ? $GLOBALS['wpo_cache_config']['cache_exception_browser_agents'] : preg_split('#(\n|\r)#', $GLOBALS['wpo_cache_config']['cache_exception_browser_agents']);

	if (!empty($exceptions)) {
		foreach ($exceptions as $exception) {
			if ('' == trim($exception)) continue;

			if (preg_match('#'.$exception.'#i', $user_agent)) return false;
		}
	}

	return true;
}
endif;

/**
 * Delete function that deals with directories recursively
 *
 * @param string  $src       Path of the folder
 * @param boolean $recursive If $src is a folder, recursively delete the inner folders. If set to false, only the files will be deleted.
 *
 * @return bool
 */
if (!function_exists('wpo_delete_files')) :
function wpo_delete_files($src, $recursive = true) {
	if (!file_exists($src) || '' == $src || '/' == $src) {
		return true;
	}

	if (is_file($src)) {
		return unlink($src);
	}

	$success = true;
	$has_dir = false;

	if ($recursive) {
		// N.B. If opendir() fails, then a false positive (i.e. true) will be returned
		if (false !== ($dir = opendir($src))) {
			$file = readdir($dir);
			while (false !== $file) {
				if ('.' == $file || '..' == $file) {
					$file = readdir($dir);
					continue;
				}
				if (is_dir($src . '/' . $file)) {
					if (!wpo_delete_files($src . '/' . $file)) {
						$success = false;
					}
				} else {
					if (!unlink($src . '/' . $file)) {
						$success = false;
					}
				}

				$file = readdir($dir);
			}
			closedir($dir);
		}
	} else {
		// Not recursive, so we only delete the files
		// scan directories recursively.
		$handle = opendir($src);

		if (false === $handle) return false;

		$file = readdir($handle);

		while (false !== $file) {

			if ('.' != $file && '..' != $file) {
				if (is_dir($src . '/' . $file)) {
					$has_dir = true;
				} elseif (!unlink($src . '/' . $file)) {
					$success = false;
				}
			}

			$file = readdir($handle);

		}
	}

	if ($success && !$has_dir) {
		// Success of this operation is not recorded; we only ultimately care about emptying, not removing entirely (empty folders in our context are harmless)
		rmdir($src);
	}

	// delete cached information about cache size.
	WP_Optimize()->get_page_cache()->delete_cache_size_information();

	return $success;
}
endif;

if (!function_exists('wpo_is_empty_dir')) :
/**
 * Check if selected directory is empty or has only index.php which we added for security reasons.
 *
 * @param string $dir
 *
 * @return bool
 */
function wpo_is_empty_dir($dir) {
	if (!file_exists($dir) || !is_dir($dir)) return false;

	$handle = opendir($dir);

	if (false === $handle) return false;

	$is_empty = true;
	$file = readdir($handle);

	while (false !== $file) {

		if ('.' != $file && '..' != $file && 'index.php' != $file) {
			$is_empty = false;
			break;
		}

		$file = readdir($handle);
	}

	closedir($handle);
	return $is_empty;
}
endif;

/**
 * Either store for later output, or output now. Only the most-recent call will be effective.
 *
 * @param String|Null $output - if not null, then the string to use when called by the shutdown action.
 */
if (!function_exists('wpo_cache_add_footer_output')) :
function wpo_cache_add_footer_output($output = null) {

	static $buffered = null;

	if (function_exists('current_filter') && 'shutdown' == current_filter()) {
		// Only add the line if it was a page, not something else (e.g. REST response)
		if (function_exists('did_action') && did_action('wp_footer')) {
			echo "\n<!-- WP Optimize page cache - https://getwpo.com - ".$buffered." -->\n";
		} elseif (defined('WPO_CACHE_DEBUG') && WPO_CACHE_DEBUG) {
			error_log('[CACHE DEBUG] '.wpo_current_url() . ' - ' . $buffered);
		}
	} else {
		if (null == $buffered && function_exists('add_action')) add_action('shutdown', 'wpo_cache_add_footer_output', 11);
		$buffered = $output;
	}

}
endif;

/**
 * Remove variable names that shouldn't influence cache.
 *
 * @param array $variables List of variable names.
 *
 * @return array
 */
if (!function_exists('wpo_cache_maybe_ignore_query_variables')) :
function wpo_cache_maybe_ignore_query_variables($variables) {

	/**
	 * Filters the current $_GET variables that will be used when caching or excluding from cache.
	 * Currently:
	 * - 'wpo_cache_debug' (Shows the reason for not being cached even when WP_DEBUG isn't set)
	 * - 'doing_wp_cron' (alternative cron)
	 * - 'aiosp_sitemap_path', 'aiosp_sitemap_page' (All in one SEO sitemap)
	 * - 'xml_sitemap', 'seopress_sitemap', 'seopress_news', 'seopress_video', 'seopress_cpt', 'seopress_paged' (SEOPress sitemap)
	 * - 'sitemap', 'sitemap_n' (YOAST SEO sitemap)
	 */
	$exclude_variables = array(
		'wpo_cache_debug',    // Shows the reason for not being cached even when WP_DEBUG isn't set
		'doing_wp_cron',      // alternative cron
		'aiosp_sitemap_path', // All in one SEO sitemap
		'aiosp_sitemap_page',
		'xml_sitemap',        // SEOPress sitemap
		'seopress_sitemap',
		'seopress_news',
		'seopress_video',
		'seopress_cpt',
		'seopress_paged',
		'sitemap',            // YOAST SEO sitemap
		'sitemap_n',
	);
	$exclude_variables = function_exists('apply_filters') ? apply_filters('wpo_cache_ignore_query_variables', $exclude_variables) : $exclude_variables;

	if (empty($exclude_variables)) return $variables;

	foreach ($exclude_variables as $variable) {
		$exclude = array_search($variable, $variables);
		if (false !== $exclude) {
			array_splice($variables, $exclude, 1);
		}
	}

	return $variables;
}
endif;

/**
 * Get cache config
 *
 * @param string $key     - The config item
 * @param mixed  $default - The default value
 *
 * @return mixed
 */
if (!function_exists('wpo_cache_config_get')) :
function wpo_cache_config_get($key, $default = false) {
	$config = $GLOBALS['wpo_cache_config'];

	if (!$config) return false;

	if (isset($config[$key])) {
		return $config[$key];
	} else {
		return $default;
	}
}
endif;

if (!function_exists('wpo_disable_cache_directories_viewing')) :
function wpo_disable_cache_directories_viewing() {
	global $is_apache, $is_IIS, $is_iis7;

	if (!is_dir(WPO_CACHE_FILES_DIR)) return;

	// Create .htaccess file for apache server.
	if ($is_apache) {
		$htaccess_filename = WPO_CACHE_FILES_DIR . '/.htaccess';

		// CS does not like heredoc
		// phpcs:disable
		$htaccess_content = <<<EOF
# Disable directory browsing 
Options -Indexes

# Disable access to any files
<FilesMatch ".*">
	Order allow,deny
	Deny from all
</FilesMatch>		
EOF;
		// phpcs:enable

		if (!is_file($htaccess_filename)) @file_put_contents($htaccess_filename, $htaccess_content); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}

	// Create web.config file for IIS servers.
	if ($is_IIS || $is_iis7) {
		$webconfig_filename = WPO_CACHE_FILES_DIR . '/web.config';
		$webconfig_content = "<configuration>\n<system.webServer>\n<authorization>\n<deny users=\"*\" />\n</authorization>\n</system.webServer>\n</configuration>\n";

		if (!is_file($webconfig_filename)) @file_put_contents($webconfig_filename, $webconfig_content); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}

	// Create empty index.php file for all servers.
	if (!is_file(WPO_CACHE_FILES_DIR . '/index.php')) @file_put_contents(WPO_CACHE_FILES_DIR . '/index.php', '');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
}
endif;

/**
 * Add the headers indicating why the page is not cached or served from cache
 *
 * @param string $message - The headers
 *
 * @return void
 */
if (!function_exists('wpo_cache_add_nocache_http_header')) :
	function wpo_cache_add_nocache_http_header($message = '') {
		static $buffered_message = null;

		if (function_exists('current_filter') && 'send_headers' === current_filter() && $buffered_message && !headers_sent()) {
			header('WPO-Cache-Status: not cached');
			header('WPO-Cache-Message: '. trim(str_replace(array("\r", "\n", ':'), ' ', strip_tags($buffered_message))));
		} else {
			if (!$buffered_message && function_exists('add_action')) add_action('send_headers', 'wpo_cache_add_nocache_http_header', 11);
			$buffered_message = $message;
		}
	}
endif;

/**
 * Check if feeds caching enabled
 *
 * @return bool
 */
if (!function_exists('wpo_feeds_caching_enabled')) :
	function wpo_feeds_caching_enabled() {
		return apply_filters('wpo_feeds_caching_enabled', true);
	}
endif;

if (!function_exists('wpo_debug_backtrace_summary')) {
	function wpo_debug_backtrace_summary($ignore_class = null, $skip_frames = 0, $pretty = true) {
		static $truncate_paths;
	 
		$trace       = debug_backtrace(false);
		$caller      = array();
		$check_class = !is_null($ignore_class);
		$skip_frames++; // Skip this function.
	 
		if (!isset($truncate_paths)) {
			$truncate_paths = array(
				wpo_normalize_path(WP_CONTENT_DIR),
				wpo_normalize_path(ABSPATH),
			);
		}
	 
		foreach ($trace as $call) {
			if ($skip_frames > 0) {
				$skip_frames--;
			} elseif (isset($call['class'])) {
				if ($check_class && $ignore_class == $call['class']) {
					continue; // Filter out calls.
				}
	 
				$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
			} else {
				if (in_array($call['function'], array('do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array'), true)) {
					$caller[] = "{$call['function']}('{$call['args'][0]}')";
				} elseif (in_array($call['function'], array('include', 'include_once', 'require', 'require_once'), true)) {
					$filename = isset($call['args'][0]) ? $call['args'][0] : '';
					$caller[] = $call['function'] . "('" . str_replace($truncate_paths, '', wpo_normalize_path($filename)) . "')";
				} else {
					$caller[] = $call['function'];
				}
			}
		}
		if ($pretty) {
			return implode(', ', array_reverse($caller));
		} else {
			return $caller;
		}
	}
}

if (!function_exists('wpo_normalize_path')) {
	function wpo_normalize_path($path) {
		// Standardise all paths to use '/'.
		$path = str_replace('\\', '/', $path);
	 
		// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
		$path = preg_replace('|(?<=.)/+|', '/', $path);
	 
		// Windows paths should uppercase the drive letter.
		if (':' === substr($path, 1, 1)) {
			$path = ucfirst($path);
		}

		return $path;
	}
}
