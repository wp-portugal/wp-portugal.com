<?php
if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('WP_Optimize_Minify_Functions')) {
	include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-functions.php';
}

class WP_Optimize_Minify_Print {

	/**
	 * Load the script using loadAsync JavaScript
	 *
	 * @param string  $href
	 * @param boolean $print
	 * @return string|void
	 */
	public static function async_script($href, $print = true) {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		$tag = '<script>if (!navigator.userAgent.match(/'.implode('|', $wpo_minify_options['ualist']).'/i)){' . "\n";
		$tag .= "    loadAsync('$href', null);" . "\n";
		$tag .= '}</script>' . "\n";

		if ($print) {
			echo $tag;
		} else {
			return $tag;
		}
	}

	/**
	 * Print a style that will be loaded async using 'preload'
	 *
	 * @param string $href
	 * @param string $media
	 * @return void
	 */
	public static function async_style($href, $media = 'all') {
		echo '<link rel="preload" href="'.$href.'" as="style" media="'.$media.'" onload="this.onload=null;this.rel=\'stylesheet\'" />' . "\n";
		// fix for firefox not supporting preload
		echo '<link rel="stylesheet" href="'.$href.'" media="'.$media.'" />' . "\n";
		echo '<noscript><link rel="stylesheet" href="'.$href.'" media="'.$media.'" /></noscript>' . "\n";
		echo '<!--[if IE]><link rel="stylesheet" href="'.$href.'" media="'.$media.'" /><![endif]-->' . "\n";
	}

	/**
	 * Print a style that will be loaded by JS
	 *
	 * @param string $href
	 * @return void
	 */
	public static function exclude_style($href) {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		// make a stylesheet, hide from PageSpeedIndex
		$cssguid = 'wpo_min'.hash('adler32', $href);
		echo '<script>if (!navigator.userAgent.match(/'.implode('|', $wpo_minify_options['ualist']).'/i)){' . "\n";
		echo '    var '.$cssguid.'=document.createElement("link");'.$cssguid.'.rel="stylesheet",'.$cssguid.'.type="text/css",'.$cssguid.'.media="async",'.$cssguid.'.href="'.$href.'",'.$cssguid.'.onload=function() {'.$cssguid.'.media="all"},document.getElementsByTagName("head")[0].appendChild('.$cssguid.');' . "\n";
		echo '}</script>' . "\n";
	}

	/**
	 * Inline a single style
	 *
	 * @param string $handle
	 * @param string $href
	 * @return boolean
	 */
	public static function inline_style($handle, $href) {
		$wpo_minify_options = wp_optimize_minify_config()->get();

		// font awesome processing, inline
		// download, minify, cache
		$tkey = 'css-'.hash('adler32', $href).'.css';
		$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
		if (false === $json) {
			$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $wpo_minify_options['enable_css_minification'], 'css', $handle);
			if ($wpo_minify_options['debug']) {
				echo "<!-- wpo_min DEBUG: Uncached file processing now for $href -->" . "\n";
			}
			WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
		}
	
		// decode
		$res = json_decode($json, true);
		
		// add font-display
		// https://developers.google.com/web/updates/2016/02/font-display
		$res['code'] = str_ireplace('font-style:normal;', 'font-display:block;font-style:normal;', $res['code']);
		
		// inline css or fail
		if (false != $res['status']) {
			echo '<style type="text/css" media="all">' . "\n";
			echo $res['code'] . "\n";
			echo '</style>' . "\n";
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Print a normal style tag pointing the href
	 *
	 * @param string $href
	 * @return void
	 */
	public static function style($href) {
		echo '<link rel="stylesheet" href="'.$href.'" media="all">' . "\n";
	}

	/**
	 * Write header
	 *
	 * @param string $file
	 * @param string $headers
	 * @return void
	 */
	public static function write_header($file, $headers) {
		file_put_contents($file, $headers);
		WP_Optimize_Minify_Cache_Functions::fix_permission_bits($file);
	}

	/**
	 * Write CSS
	 *
	 * @param string $file
	 * @param string $code
	 * @param string $log
	 * @return void
	 */
	public static function write_combined_asset($file, $code, $log) {
		file_put_contents($file.'.json', json_encode($log));
		file_put_contents($file, $code);
		// permissions
		WP_Optimize_Minify_Cache_Functions::fix_permission_bits($file.'.json');
		WP_Optimize_Minify_Cache_Functions::fix_permission_bits($file);

		if (function_exists('gzencode')) {
			file_put_contents($file.'.gz', gzencode(file_get_contents($file), 9));
			WP_Optimize_Minify_Cache_Functions::fix_permission_bits($file.'.gz');
		}
		
		// brotli static support
		if (function_exists('brotli_compress')) {
			file_put_contents($file.'.br', brotli_compress(file_get_contents($file), 11));
			WP_Optimize_Minify_Cache_Functions::fix_permission_bits($file.'.br');
		}
	}
	/**
	 * Load async scripts with callback
	 *
	 * @return void
	 */
	public static function add_load_async() {
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';
		$contents = file_get_contents(trailingslashit(WPO_PLUGIN_MAIN_PATH) . "js/loadAsync$min_or_not_internal.js");
		echo "<script>$contents</script>\n";
	}

	/**
	 * Defer CSS globally from the header (order matters)
	 * Dev: https://www.filamentgroup.com/lab/async-css.html
	 *
	 * @return void
	 */
	public static function add_load_css() {
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';
		$contents = file_get_contents(trailingslashit(WPO_PLUGIN_MAIN_PATH) . "js/loadCSS$min_or_not_internal.js");
		echo "<script>$contents</script>" . "\n";
	}
}
