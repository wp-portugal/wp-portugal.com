<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * File based page cache drop in
 */
require_once(dirname(__FILE__) . '/file-based-page-cache-functions.php');

if (!defined('WPO_CACHE_DIR')) define('WPO_CACHE_DIR', untrailingslashit(WP_CONTENT_DIR) . '/wpo-cache');

/**
 * Load extensions.
 */
wpo_cache_load_extensions();

/**
 * Action triggered when the cache extensions are all loaded. Allows to execute code depending on an other extension, without knowing the order in which the files are loaded.
 */
if (function_exists('do_action')) {
	do_action('wpo_cache_extensions_loaded');
}

$no_cache_because = array();

// check if we want to cache current page.
if (function_exists('add_filter') && function_exists('apply_filters')) {
	add_filter('wpo_restricted_cache_page_type', 'wpo_restricted_cache_page_type');
	$restricted_cache_page_type = apply_filters('wpo_restricted_cache_page_type', false);
} else {
	// On old WP versions, you can't filter the result
	$restricted_cache_page_type = wpo_restricted_cache_page_type(false);
}

if ($restricted_cache_page_type) {
	$no_cache_because[] = $restricted_cache_page_type;
}

// Don't cache non-GET requests.
if (!isset($_SERVER['REQUEST_METHOD']) || 'GET' !== $_SERVER['REQUEST_METHOD']) {
	$no_cache_because[] = 'The request method was not GET ('.(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '-').')';
}

$file_extension = $_SERVER['REQUEST_URI'];
$file_extension = preg_replace('#^(.*?)\?.*$#', '$1', $file_extension);
$file_extension = trim(preg_replace('#^.*\.(.*)$#', '$1', $file_extension));

// Don't cache disallowed extensions. Prevents wp-cron.php, xmlrpc.php, etc.
if (!preg_match('#index\.php$#i', $_SERVER['REQUEST_URI']) && !preg_match('#sitemap([a-zA-Z0-9_-]+)?\.xml$#i', $_SERVER['REQUEST_URI']) && in_array($file_extension, array('php', 'xml', 'xsl'))) {
	$no_cache_because[] = 'The request extension is not suitable for caching';
}

// Don't cache if logged in.
if (!empty($_COOKIE)) {
	$wp_cookies = array('wordpressuser_', 'wordpresspass_', 'wordpress_sec_', 'wordpress_logged_in_');

	if (!wpo_cache_loggedin_users()) {
		foreach ($_COOKIE as $key => $value) {
			foreach ($wp_cookies as $cookie) {
				if (false !== strpos($key, $cookie)) {
					$no_cache_because[] = 'WordPress login cookies were detected';
					break(2);
				}
			}
		}
	}

	if (!empty($_COOKIE['wpo_commented_post'])) {
		$no_cache_because[] = 'The user has commented on a post (comment cookie set)';
	}

	// get cookie exceptions from options.
	$cache_exception_cookies = !empty($GLOBALS['wpo_cache_config']['cache_exception_cookies']) ? $GLOBALS['wpo_cache_config']['cache_exception_cookies'] : array();
	// filter cookie exceptions, since WP 4.6
	$cache_exception_cookies = function_exists('apply_filters') ? apply_filters('wpo_cache_exception_cookies', $cache_exception_cookies) : $cache_exception_cookies;

	// check if any cookie exists from exception list.
	if (!empty($cache_exception_cookies)) {
		foreach ($_COOKIE as $key => $value) {
			foreach ($cache_exception_cookies as $cookie) {
				if ('' != trim($cookie) && false !== strpos($key, $cookie)) {
					$no_cache_because[] = 'An excepted cookie was set ('.$key.')';
					break 2;
				}
			}
		}
	}
}

// check in not disabled current user agent
if (!empty($_SERVER['HTTP_USER_AGENT']) && false === wpo_is_accepted_user_agent($_SERVER['HTTP_USER_AGENT'])) {
	$no_cache_because[] = "In the settings, caching is disabled for matches for this request's user agent";
}

// Deal with optional cache exceptions.
if (wpo_url_in_exceptions(wpo_current_url())) {
	$no_cache_because[] = 'In the settings, caching is disabled for matches for the current URL';
}

if (!empty($_GET)) {
	// get variables used for building filename.
	$get_variable_names = wpo_cache_query_variables();

	$get_variables = wpo_cache_maybe_ignore_query_variables(array_keys($_GET));

	// if GET variables include one or more undefined variable names then we don't cache.
	$get_variables_diff = array_diff($get_variables, $get_variable_names);
	if (!empty($get_variables_diff)) {
		$no_cache_because[] = "In the settings, caching is disabled for matches for one of the current request's GET parameters";
	}
}

if (!empty($no_cache_because)) {
	$no_cache_because_message = implode(', ', $no_cache_because);

	// Add http header
	if (!defined('DOING_CRON') || !DOING_CRON) {
		wpo_cache_add_nocache_http_header($no_cache_because_message);
	}

	// Only output if the user has turned on debugging output
	if (((defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['wpo_cache_debug'])) && (!defined('DOING_CRON') || !DOING_CRON)) {
		wpo_cache_add_footer_output("Page not served from cache because: ".htmlspecialchars($no_cache_because_message));
	}
	return;
}

wpo_serve_cache();

ob_start('wpo_cache');
