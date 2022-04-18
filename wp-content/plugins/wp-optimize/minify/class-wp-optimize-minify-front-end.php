<?php

if (!defined('ABSPATH')) die('No direct access allowed');

class WP_Optimize_Minify_Front_End {

	private $collect_preload_js = array();
	
	private $collect_preload_css = array();

	private $collect_google_fonts = array();

	private $minify_cache_incremented = false;

	private $options = array();

	/**
	 * Initialize actions and filters
	 *
	 * @return void
	 */
	public function __construct() {
		$this->include_dependencies();
		$this->options = wp_optimize_minify_config()->get();

		// Main process
		add_action('wp', array($this, 'init'));
		
		// Beaver builder
		add_action('fl_builder_after_save_layout', array('WP_Optimize_Minify_Cache_Functions', 'reset'));

		// extra_preload_headers is currently not available to users
		// add_action('send_headers', array($this, 'extra_preload_headers'));
	}

	/**
	 * Fired on wp init
	 *
	 * @return void
	 */
	public function init() {
		/**
		 * Check whether Minify is run on the current page
		 */
		if (!$this->run_on_page()) return;

		if ($this->options['emoji_removal']) {
			$this->disable_emojis();
		}

		if ($this->options['clean_header_one']) {
			$this->remove_header_meta_info();
		}

		// Headers & Preload JS/CSS/Extra
		if ($this->options['enabled_css_preload'] || $this->options['enabled_js_preload']) {
			add_action('wp_footer', array($this, 'generate_preload_headers'), PHP_INT_MAX);
		}
		
		if ($this->options['enable_js']) {
			$this->process_js();
		}

		if ($this->options['enable_css']) {
			$this->process_css();
		}

		// Preload tags
		if (trim($this->options['hpreload'])) {
			add_action('wp_head', array($this, 'add_assets_preload'), 2);
		}

		if ($this->should_process_html()) {
			add_action('template_redirect', array('WP_Optimize_Minify_Functions', 'html_compression_start'), PHP_INT_MAX);
		}

		if ($this->should_use_loadCSS()) {
			add_action('wp_footer', array('WP_Optimize_Minify_Print', 'add_load_css'), PHP_INT_MAX);
		}

		$this->remove_query_string_from_static_assets();
	}

	/**
	 * Detects whether cache preloading is running or not
	 *
	 * @return bool
	 */
	private function is_cache_preload() {
		return isset($_SERVER['HTTP_X_WP_OPTIMIZE_CACHE_PRELOAD']) && 0 === strcmp($_SERVER['HTTP_X_WP_OPTIMIZE_CACHE_PRELOAD'], 'Yes');
	}

	/**
	 * Wether to run the feature on a page or not
	 *
	 * @param string $context - Optional, The context where the check is done
	 * @return boolean
	 */
	public function run_on_page($context = 'default') {
		/**
		 * Filters wether the functionality is ran on the current page.
		 *
		 * @param boolean $run_on_page
		 * @param string  $context - Optional, The feature where the check is done
		 */
		return apply_filters(
			'wpo_minify_run_on_page',
			!is_admin()
			&& (!defined('SCRIPT_DEBUG') || !SCRIPT_DEBUG)
			&& !is_preview()
			&& (!function_exists('is_customize_preview') || !is_customize_preview())
			&& !($this->options['disable_when_logged_in'] && is_user_logged_in())
			&& !(function_exists('is_amp_endpoint') && is_amp_endpoint())
			&& !WP_Optimize_Minify_Functions::exclude_contents(),
			$context
		);
	}

	/**
	 * Inline css in place, instead of inlining the large file
	 *
	 * @param String $html
	 * @param String $handle
	 * @param String $href
	 * @param String $media
	 *
	 * @return String
	 */
	public function inline_css($html, $handle, $href, $media) {
		$exclude_css = array_map('trim', explode("\n", trim($this->options['exclude_css'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_css);
		$blacklist = WP_Optimize_Minify_Functions::get_ie_blacklist();
		$async_css = array_map('trim', explode("\n", trim($this->options['async_css'])));
		$master_ignore = array_merge($ignore_list, $blacklist);

		// make sure href is complete
		$href = WP_Optimize_Minify_Functions::get_hurl($href);
		
		if ($this->options['debug']) {
			echo "<!-- wpo_min DEBUG: Inline CSS processing start $handle / $href -->\n";
		}
		

		// skip all this, if the async css option is enabled
		if ($this->options['loadcss']) return $html;
		// remove all css?
		if ($this->options['remove_css']) return false;
		// leave conditionals alone
		if (wp_styles()->get_data($handle, 'conditional')) return $html;
		
		// mediatype fix for some plugins + remove print mediatypes
		if ('screen' == $media
			|| 'screen, print' == $media
			|| empty($media)
			|| is_null($media)
			|| false == $media
		) {
			$media = 'all';
		}
		if (!empty($this->options['remove_print_mediatypes']) && 'print' == $media) {
			return false;
		}

		// Exclude specific CSS files from PageSpeedInsights?
		if (WP_Optimize_Minify_Functions::in_arrayi($href, $async_css)) {
			WP_Optimize_Minify_Print::exclude_style($href);
			return false;
		}

		// remove wpo_min from the ignore list
		$ignore_list = array_filter($ignore_list, array($this, 'check_wpo'));
		
		// return if in any ignore or black list
		if (count($master_ignore) > 0 && WP_Optimize_Minify_Functions::in_arrayi($href, $master_ignore)) {
			return $html;
		}

		// check if working with a font awesom link
		if (WP_Optimize_Minify_Functions::is_font_awesome($href)) {
			// font awesome processing, async css
			if ('async' == $this->options['fawesome_method']) {
				WP_Optimize_Minify_Print::async_style($href, $media);
				return false;
			} elseif ('exclude' === $this->options['fawesome_method']) {
				// font awesome processing, async and exclude from PageSpeedIndex
				WP_Optimize_Minify_Print::exclude_style($href);
				return false;
			} elseif ('inline' == $this->options['fawesome_method']) {
				WP_Optimize_Minify_Print::inline_style($handle, $href);
				return false;
			}
		}

		// Check if working with google font url

		if ('fonts.googleapis.com' == parse_url($href, PHP_URL_HOST)) {
			// check if google fonts should be removed
			if ($this->options['remove_googlefonts']) return false;
			// check if google fonts should be merged
			if ($this->options['merge_google_fonts']) {
				if (WP_Optimize_Minify_Functions::is_flatsome_handle($handle)) {
					$href = WP_Optimize_Minify_Functions::fix_flatsome_google_fonts_url($href);
					$this->collect_google_fonts[$handle] = $href;
				} else {
					$this->collect_google_fonts[$handle] = $href;
				}
				return false;
			} else {
				if ('inline' === $this->options['gfonts_method']) {
					if (WP_Optimize_Minify_Functions::is_flatsome_handle($handle)) {
						$href = WP_Optimize_Minify_Functions::fix_flatsome_google_fonts_url($href);
					}
					// download, minify, cache
					$tkey = 'css-'.hash('adler32', $handle.$href).'.css';
					$json = false;
					$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
					if (false === $json) {
						$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $this->options['enable_css_minification'], 'css', $handle);
						if ($this->options['debug']) {
							echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href -->\n";
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
						echo '<style class="optimize_css_1" type="text/css" media="all">'.$res['code'].'</style>' . "\n";
						return false;
					} else {
						if ($this->options['debug']) {
							echo "<!-- wpo_min DEBUG: Google fonts request failed for $href -->\n";
						}
						return $html;
					}
				} elseif ('async' === $this->options['gfonts_method']) {
					WP_Optimize_Minify_Print::async_style($href);
					return false;
				} elseif ('exclude' === $this->options['gfonts_method']) {
					WP_Optimize_Minify_Print::exclude_style($href);
					return false;
				}
			}
		}

		// skip external scripts that are not specifically allowed
		if (false === WP_Optimize_Minify_Functions::internal_url($href, site_url()) || empty($href)) {
			if ($this->options['debug']) {
				echo "<!-- wpo_min DEBUG: Skipped the next external enqueued CSS -->\n";
			}
			return $html;
		}

		$file_size = WP_Optimize_Minify_Functions::get_file_size($href);

		// If we can't determine file size, then we still need to proceed with normal minify process
		if (apply_filters('wp_optimize_skip_inlining', false === $file_size || $file_size > 20480, $file_size, $href)) return $html;

		// download, minify, cache
		$tkey = 'css-'.hash('adler32', $handle.$href).'.css';
		$json = false;
		$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
		if (false === $json) {
			$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $this->options['enable_css_minification'], 'css', $handle);
			if ($this->options['debug']) {
				echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href -->" . "\n";
			}
			WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
		}
		
		// decode
		$res = json_decode($json, true);
		
		// inline it + other inlined children styles
		if (false != $res['status']) {
			echo '<style class="optimize_css_2" type="text/css" media="'.$media.'">'.$res['code'].'</style>' . "\n";
			
			// get inline_styles for this handle, minify and print
			$inline_styles = array();
			$inline_styles = wp_styles()->get_data($handle, 'after');
			if (false != $inline_styles) {

				// string type
				if (is_string($inline_styles)) {
					$code = WP_Optimize_Minify_Functions::get_css($href, $inline_styles, $this->options['enable_css_minification']);
					if (!empty($code) && false != $code) {
						echo '<style class="optimize_css_3" type="text/css" media="'.$media.'">'.$code.'</style>' . "\n";
					}
				}
				
				// array type
				if (is_array($inline_styles)) {
					foreach ($inline_styles as $st) {
						$code = WP_Optimize_Minify_Functions::get_css($href, $st, $this->options['enable_css_minification']);
						if (!empty($code) && false != $code) {
							echo '<style class="optimize_css_4" type="text/css" media="'.$media.'">'.$code.'</style>' . "\n";
						}
					}
				}
			}
			
			// prevent default
			return false;
		} else {
			if ($this->options['debug']) {
				echo "<!-- wpo_min DEBUG: $handle / $href returned an empty from minification -->" . "\n";
			}
			return $html;
		}
		echo "<!-- ERROR: WPO-Minify couldn't catch the CSS file below. Please report this on https://wordpress.org/support/plugin/wp-optimize/ -->\n";
		return $html;
	}
	
	/**
	 * Enable defer for JavaScript (WP 4.1 and above) and remove query strings for ignored files
	 *
	 * @param String $tag
	 * @param String $handle
	 * @param String $src
	 *
	 * @return String
	 */
	public function defer_js($tag, $handle, $src) {
		$wp_domain = trim(str_ireplace(array('http://', 'https://'), '', trim(site_url(), '/')));
		$exclude_js = array_map('trim', explode("\n", trim($this->options['exclude_js'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_js);
		// Should this defer the Poly fills for IE?
		$blacklist = WP_Optimize_Minify_Functions::get_ie_blacklist();
		// no query strings
		if (false !== stripos($src, '?ver')) {
			$srcf = stristr($src, '?ver', true); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.stristr_before_needleFound
			$tag = str_ireplace($src, $srcf, $tag);
			$src = $srcf;
		}

		// return if defer option is set to individual
		if ('individual' === $this->options['enable_defer_js']) {
			return $tag;
		}

		// return the tag if it's a polyfill
		if (count($blacklist) > 0 && WP_Optimize_Minify_Functions::in_arrayi($src, $blacklist)) {
			return $tag;
		}

		// Skip deferring the jQuery library option, if the defer jQuery option is disabled
		if (!$this->options['defer_jquery']
			&& (false !== stripos($tag, '/jquery.js')
			|| false !== stripos($tag, '/jquery.min.js')
			|| (false !== stripos($tag, '/jquery-') && false !== stripos($tag, '.js')))
		) {
			return $tag;
		}

		// return if external script url https://www.chromestatus.com/feature/5718547946799104
		if (WP_Optimize_Minify_Functions::is_local_domain($src) !== true) {
			return $tag;
		}

		// bypass if already optimized
		if (false !== stripos($tag, 'navigator.userAgent.match')) {
			return $tag;
		}

		// should we exclude defer on the login page?
		if ($this->options['exclude_defer_login']
			&& false !== stripos($_SERVER["SCRIPT_NAME"], strrchr(wp_login_url(), '/'))
		) {
			return $tag;
		}

		// add defer attribute, but only if not having async or defer already
		if (stripos($tag, 'defer') === false && stripos($tag, 'async') === false) {

			// add cdn for PageSpeedIndex
			if (!empty($this->options['cdn_url'])) {
				$cdn_url = trim(trim(str_ireplace(array('http://', 'https://'), '', trim($this->options['cdn_url'], '/'))), '/');
				$src = str_ireplace($wp_domain, $cdn_url, $src);
			}
			if ('async_using_js' === $this->options['defer_js_type']) {
				return WP_Optimize_Minify_Print::async_script($src, false);
			}

			// remove wpo_min from the ignore list
			$ignore_list = array_filter($ignore_list, array($this, 'check_wpo'));
			if (count($ignore_list) > 0 && WP_Optimize_Minify_Functions::in_arrayi($src, $ignore_list)) {
				return $tag;
			} else {
				/**
				 * Filters whether to use the defer attribute or async.
				 *
				 * @default string 'defer' (any other value will be set to async)
				 * @param string $value  - 'defer' or 'async'
				 * @param string $tag    - The <script> tag
				 * @param string $handle - The WP handle for the script
				 * @param string $src    - The SRC for the script
				 * @return string - 'defer' or 'async'
				 */
				$defer_or_async = apply_filters('wpo_minify_defer_or_async', 'defer', $tag, $handle, $src);
				if ('defer' === $defer_or_async) {
					return str_ireplace('<script ', '<script defer ', $tag);
				} else {
					return str_ireplace('<script ', '<script async ', $tag);
				}
			}
		}

		// fallback
		return $tag;
	}

	/**
	 * Add inline CSS code / Critical Path
	 *
	 * @return void
	 */
	public function add_critical_path() {
		if (is_front_page() && !empty($this->options['critical_path_css_is_front_page'])) {
			echo '<style id="critical-path-is-front-page" type="text/css" media="all">' . "\n" . $this->options['critical_path_css_is_front_page'] . "\n" . '</style>' . "\n";
		} elseif (!empty($this->options['critical_path_css'])) {
			echo '<style id="critical-path-global" type="text/css" media="all">' . "\n" . $this->options['critical_path_css'] . "\n" . '</style>' . "\n";
		}
	}

	/**
	 * Add critical assets preload
	 *
	 * @return void
	 */
	public function add_assets_preload() {
		$preload = json_decode($this->options['hpreload']);
		if (is_array($preload)) {
			foreach ($preload as $asset) {
				if (!empty($asset)) {
					echo '<link rel="preload" href="'.esc_url($asset->href).'" as="'.esc_attr($asset->type).'"'.($asset->crossorigin ? ' crossorigin' : '').'>';
				}
			}
		}
	}

	/**
	 * Process header CSS
	 *
	 * @return boolean
	 */
	public function process_header_css() {
		global $wp_styles;
		if (!is_object($wp_styles)) return false;

		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		$cache_dir_url = $cache_path['cachedirurl'];
		$exclude_css = array_map('trim', explode("\n", trim($this->options['exclude_css'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_css);
		$async_css = array_map('trim', explode("\n", trim($this->options['async_css'])));

		$minify_css = $this->options['enable_css_minification'];
		$merge_css = $this->options['enable_merging_of_css'];
		$merge_inline_extra_css_js = $this->options['merge_inline_extra_css_js'];
		$process_css = $minify_css || $merge_css;
		$styles = clone $wp_styles;
		$styles->all_deps($styles->queue);
		$done = $styles->done;
		$header = array();
		$google_fonts = array();
		$process = array();
		$inline_css = array();
		$log = '';

		// dequeue all styles
		if (isset($this->options['remove_css']) && $this->options['remove_css']) {
			foreach ($styles->to_do as $handle) {
				$done = array_merge($done, array($handle));
			}
			// remove from queue
			$wp_styles->done = $done;
			return false;
		}

		// get list of handles to process, dequeue duplicate css urls and keep empty source handles (for dependencies)
		$uniq = array();
		foreach ($styles->to_do as $handle) {
			
			// conditionals
			$conditional = null; if (isset($wp_styles->registered[$handle]->extra["conditional"])) {
				$conditional = $wp_styles->registered[$handle]->extra["conditional"]; // such as ie7, ie8, ie9, etc
			}
			
			// mediatype
			$mt = isset($wp_styles->registered[$handle]->args) ? $wp_styles->registered[$handle]->args : 'all';
			if ('screen' == $mt || 'screen, print' == $mt || empty($mt) || is_null($mt) || false == $mt) {
				$mt = 'all';
			}
			$mediatype = $mt;
			
			// full url or empty
			$href = WP_Optimize_Minify_Functions::get_hurl($wp_styles->registered[$handle]->src);
			$version = $wp_styles->registered[$handle]->ver;
			
			// inlined scripts without file
			if (empty($href)) continue;
			
			
			// mark duplicates as done and remove from the queue
			if (!empty($href)) {
				$key = hash('adler32', $href . $version);
				if (isset($uniq[$key])) {
					$done = array_merge($done, array($handle));
					continue;
				} else {
					$uniq[$key] = $handle;
				}
			}
			// Exclude specific CSS files from PageSpeedIndex?
			if (WP_Optimize_Minify_Functions::in_arrayi($href, $async_css)) {
				WP_Optimize_Minify_Print::exclude_style($href);
				$done = array_merge($done, array($handle));
				continue;
			}
			// Fonts Awesome Processing
			if (WP_Optimize_Minify_Functions::is_font_awesome($href)) {
				if ('inline' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::inline_style($handle, $href);
					$done = array_merge($done, array($handle));
					continue;
				} elseif ('async' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::async_style($href, $mediatype);
					$done = array_merge($done, array($handle));
					continue;
				} elseif ('exclude' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::exclude_style($href);
					$done = array_merge($done, array($handle));
					continue;
				}
			}

			// Exclude Print mediatype
			if (!empty($this->options['remove_print_mediatypes']) && 'print' === $mediatype) {
				$done = array_merge($done, array($handle));
				continue;
			}

			// array of info to save
			$arr = array(
				'handle' => $handle,
				'url' => $href,
				'conditional' => $conditional,
				'mediatype' => $mediatype
			);

			// google fonts to the top (collect and skip process array)
			if (WP_Optimize_Minify_Functions::is_google_font($href)) {
				if ($this->options['remove_googlefonts']) {
					$done = array_merge($done, array($handle));
					continue;
				}
				if (WP_Optimize_Minify_Functions::is_flatsome_handle($handle)) {
					$href = WP_Optimize_Minify_Functions::fix_flatsome_google_fonts_url($href);
					$google_fonts[$handle] = $href;
				} else {
					$google_fonts[$handle] = $href;
				}
			}
			$process[$handle] = $arr;
		}
		
		// Process Google fonts
		if (count($google_fonts) > 0) {
			// merge google fonts if force inlining is enabled?
			$nfonts = array();
			if ($this->options['merge_google_fonts']) {
				$nfonts[] = WP_Optimize_Minify_Fonts::concatenate_google_fonts($google_fonts);
			} else {
				foreach ($google_fonts as $h => $a) {
					if (!empty($a)) {
						$nfonts[$h] = $a;
					}
				}
			}

			// foreach google font (will be one if merged is not disabled)
			foreach ($nfonts as $handle => $href) {
				if ('inline' === $this->options['gfonts_method']) {
					// download, minify, cache
					$tkey = 'css-'.hash('adler32', $href).'.css';
					// this returns false if the cache is empty! but it doesn't check for failed
					$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
					$json = json_decode($json, true);
					// check if the cache is empty or if the cache has code
					if (false === $json || empty($json['code'])) {
						$res = WP_Optimize_Minify_Functions::download_and_minify($href, null, $minify_css, 'css', null);
						if ($this->options['debug']) {
							echo "<!-- wpo_min DEBUG: Uncached file processing now for $href -->\n";
						}
						WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $res);
						// decode
						$json = json_decode($res, true);
					}
					
					// inline css or fail
					if (!empty($json['code'])) {
						// add font-display
						// https://developers.google.com/web/updates/2016/02/font-display
						$json['code'] = str_ireplace('font-style:normal;', 'font-display:block;font-style:normal;', $json['code']);
						echo '<style type="text/css" media="all">'.$json['code'].'</style>' . "\n";
						$done = array_merge($done, array($handle));
					} else {
						echo "<!-- GOOGLE FONTS REQUEST FAILED for $href -->"  . "\n";
						// inlining failed, so enqueue again
						wp_enqueue_style($handle, $href, array(), null);
					}
				} elseif ('async' === $this->options['gfonts_method']) {
					WP_Optimize_Minify_Print::async_style($href);
					$done = array_merge($done, array($handle));
				} elseif ('exclude' === $this->options['gfonts_method']) {
					// make a stylesheet, hide from PageSpeedIndex
					WP_Optimize_Minify_Print::exclude_style($href);
					$done = array_merge($done, array($handle));
				}
			}
		}

		// get groups of handles
		foreach ($styles->to_do as $handle) {
			// skip already processed google fonts and empty dependencies
			if (isset($google_fonts[$handle]) && 'inherit' !== $this->options['gfonts_method']) {
				continue;
			}
			if (empty($wp_styles->registered[$handle]->src)) {
				continue;
			}
			if (WP_Optimize_Minify_Functions::in_arrayi($handle, $done)) {
				continue;
			}
			if (!isset($process[$handle])) {
				continue;
			}

			// get full url
			$href = $process[$handle]['url'];
			$conditional = $process[$handle]['conditional'];
			$mediatype = $process[$handle]['mediatype'];
			
			// IE only files don't increment things
			$ieonly = WP_Optimize_Minify_Functions::is_url_in_ie_blacklist($href);
			if ($ieonly) {
				continue;
			}

			$file_size = WP_Optimize_Minify_Functions::get_file_size($href);

			// If we can't determine file size, then we still need to proceed with normal minify process
			if (!apply_filters('wp_optimize_skip_inlining', false === $file_size || $file_size > 20480 || !$this->options['inline_css'], $file_size, $href)) continue;
	
			// skip ignore list, conditional css, external css, font-awesome merge
			if (($process_css && !WP_Optimize_Minify_Functions::in_arrayi($href, $ignore_list) && !isset($conditional) && WP_Optimize_Minify_Functions::internal_url($href, site_url()))
				|| empty($href)
				|| ($process_css && 'inherit' == $this->options['fawesome_method'] && WP_Optimize_Minify_Functions::is_font_awesome($href))
				|| ($process_css && 'inherit' == $this->options['gfonts_method'] && WP_Optimize_Minify_Functions::is_google_font($href))
			) {
				// colect inline css for this handle
				if (isset($wp_styles->registered[$handle]->extra['after']) && is_array($wp_styles->registered[$handle]->extra['after'])) {
					$inline_css[$handle] = WP_Optimize_Minify_Functions::minify_css_string(implode('', $wp_styles->registered[$handle]->extra['after'])); // save
					$wp_styles->registered[$handle]->extra['after'] = null; // dequeue
				}
			
				// process
				if (isset($header[count($header)-1]['handle']) || count($header) == 0 || $header[count($header)-1]['media'] != $mediatype || !$merge_css) {
					array_push($header, array('handles' => array(), 'media' => $mediatype, 'versions' => array()));
				}
			
				// push it to the array
				array_push($header[count($header)-1]['handles'], $handle);
				array_push($header[count($header)-1]['handles'], $wp_styles->registered[$handle]->ver);

				// external and ignored css
			} else {

				// normal enqueuing
				array_push($header, array('handle' => $handle));
			}
		}

		/**
		 * Filters the array of stylesheets before processing them
		 *
		 * @param array  $list     - The list of items filtered
		 * @param string $location - The location of the list (footer or header)
		 * @return array
		 */
		$header = apply_filters('wpo_minify_stylesheets', $header, 'header');

		// loop through header css and merge
		for ($i=0,$l=count($header); $i<$l; $i++) {
			if (!isset($header[$i]['handle'])) {
				if ($merge_css) {
					// get hash for the inline css in this group
					$inline_css_group = array();
					foreach ($header[$i]['handles'] as $h) {
						if (isset($inline_css[$h]) && !empty($inline_css[$h])) {
							$inline_css_group[] = $inline_css[$h];
						}
					}
					$inline_css_hash = md5(implode('', $inline_css_group));
					$hash = hash('adler32', implode('', $header[$i]['handles']).$inline_css_hash . implode('', $header[$i]['versions']));
				} else {
					$hash = implode('', $header[$i]['handles']) . implode('', $header[$i]['versions']);
				}

				// static cache file info
				$file_name = 'wpo-minify-header-'.$hash.($minify_css ? '.min' : '');

				// create cache files and urls
				$file = $cache_dir.'/'.$file_name.'.css';

				$file_url = WP_Optimize_Minify_Functions::get_protocol("$cache_dir_url/$file_name.css");
				
				// generate a new cache file
				clearstatcache();
				if (!file_exists($file)) {
					
					// code and log initialization
					$log = array(
						'header' => "PROCESSED on ".date('r')." from ".home_url(add_query_arg(null, null)),
						'files' => array()
					);
					$code = '';

					// minify and write to file
					foreach ($header[$i]['handles'] as $handle) {
						if (!empty($wp_styles->registered[$handle]->src)) {
							
							// get href per handle
							$href = WP_Optimize_Minify_Functions::get_hurl($wp_styles->registered[$handle]->src);
							$version = $wp_styles->registered[$handle]->ver;
							
							// inlined scripts without file
							if (empty($href)) continue;
							
							// download, minify, cache
							$tkey = 'css-'.hash('adler32', $handle . $href).'.css';
							$json = false;
							$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
							if (false === $json) {
								$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $this->options['enable_css_minification'], 'css', $handle, $version);
								if ($this->options['debug']) {
									echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href / $version -->" . "\n";
								}
								WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
							}
							
							// decode
							$res = json_decode($json, true);

							if (isset($res['request']['version']) && $res['request']['version'] != $version && !$this->minify_cache_incremented) {
								WP_Optimize_Minify_Cache_Functions::reset();
								$this->minify_cache_incremented = true;
							}

							// response has failed
							if (true != $res['status']) {
								$log['files'][$handle] = $res['log'];
								continue;
							}

							// Only add the $handle to $done if it was successfully downloaded
							$done[] = $handle;
							// append code to merged file
							$code .= isset($res['code']) ? $res['code'] : '';
							$log['files'][$handle] = $res['log'];
							
							// append inlined styles
							if ($merge_inline_extra_css_js && isset($inline_css[$handle]) && !empty($inline_css[$handle])) {
								$code.= $inline_css[$handle];
							}

							// consider dependencies on handles with an empty src
						} else {
							wp_dequeue_script($handle);
							wp_enqueue_script($handle);
						}
					};

					// generate cache, write log
					if (!empty($code)) {
						WP_Optimize_Minify_Print::write_combined_asset($file, $code, $log);
					}
				} else {
					$log_file = $file.'.json';
					if (file_exists($log_file)) {
						$saved_log = json_decode(file_get_contents($log_file));
						if (is_object($saved_log) && property_exists($saved_log, 'files')) {
							$files = (array) $saved_log->files;
							foreach ($header[$i]['handles'] as $handle) {
								if (isset($files[$handle]) && $files[$handle]->success) {
									$done[] = $handle;
								}
							}
						} else {
							// The merged file already exists, so add all files to $done.
							$done = array_merge($done, $header[$i]['handles']);
						}
					} else {
						// The merged file already exists, so add all files to $done.
						$done = array_merge($done, $header[$i]['handles']);
					}
				}
			
				// the developers tab, takes precedence
				// Async CSS with loadCSS ?
				if ($this->options['loadcss'] && empty($this->options['remove_css'])) {
					$mt = $header[$i]['media'];
					WP_Optimize_Minify_Print::async_style($file_url, $mt);
					// enqueue file, if not empty
				} else {
					if (file_exists($file) && filesize($file) > 0) {
						
						// inline CSS if mediatype is not of type "all" (such as mobile only), if the file is smaller than 20KB
						if (filesize($file) < 20000 && 'all' != $header[$i]['media']) {
							echo '<style id="wpo-min-header-'.$i.'" media="'.$header[$i]['media'].'">'.file_get_contents($file).'</style>' . "\n";
						} else {
							// enqueue it
							wp_enqueue_style("wpo_min-header-$i", $file_url, array(), 'mycoolversion', $header[$i]['media']);
							foreach ($header[$i]['handles'] as $h) {
								if (!$merge_inline_extra_css_js && isset($inline_css[$h]) && !empty($inline_css[$h])) {
									wp_add_inline_style("wpo_min-header-$i", $inline_css[$h]);
								}
							}
						}
					} else {
						// file could not be generated, output something meaningful
						echo "<!-- ERROR: WP-Optimize Minify was not allowed to save its cache on - ".str_replace(ABSPATH, '', $file)." -->";
						echo "<!-- Please check if the path above is correct and ensure your server has write permission there! -->";
					}
				}
			// other css need to be requeued for the order of files to be kept
			} else {
				wp_dequeue_style($header[$i]['handle']);
				wp_enqueue_style($header[$i]['handle']);
			}
		}

		// remove from queue
		$wp_styles->done = $done;
		return true;
	}
	
	/**
	 * Process JS in the footer
	 *
	 * @return void
	 */
	public function process_footer_scripts() {
		global $wp_scripts;
		if (!is_object($wp_scripts)) {
			return;
		}
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		$cache_dir_url = $cache_path['cachedirurl'];

		
		$exclude_js = array_map('trim', explode("\n", trim($this->options['exclude_js'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_js);
		$async_js = trim($this->options['async_js']) ? array_map('trim', explode("\n", trim($this->options['async_js']))) : array();
		$scripts = clone $wp_scripts;
		$scripts->all_deps($scripts->queue);
		$footer = array();
		$minify_js = $this->options['enable_js_minification'];
		$merge_js = $this->options['enable_merging_of_js'];
		$process_js = $minify_js || $merge_js;
		$merge_inline_extra_css_js = $this->options['merge_inline_extra_css_js'];

		// mark as done (as we go)
		$done = $scripts->done;

		// get groups of handles
		foreach ($scripts->to_do as $handle) :

			// get full url
			$href = WP_Optimize_Minify_Functions::get_hurl($wp_scripts->registered[$handle]->src);

			// inlined scripts without file
			if (empty($href)) {
				continue;
			}
			
			// Exclude JS files from PageSpeedIndex (Async) takes priority over the ignore list
			if (false != $async_js || is_array($async_js)) {
				
				// check for string match
				$skipjs = false;
				foreach ($async_js as $l) {
					if (stripos($href, $l) !== false) {
						// print code if there are no linebreaks, or return
						WP_Optimize_Minify_Print::async_script($href);
						$skipjs = true;
						$done = array_merge($done, array($handle));
						break;
					}
				}
				if (false != $skipjs) {
					continue;
				}
			}
			
			// IE only files don't increment things
			$ieonly = WP_Optimize_Minify_Functions::is_url_in_ie_blacklist($href);
			if ($ieonly) {
				continue;
			}

			// skip ignore list, scripts with conditionals, external scripts
			if (($process_js && !WP_Optimize_Minify_Functions::in_arrayi($href, $ignore_list) && !isset($wp_scripts->registered[$handle]->extra["conditional"]) && WP_Optimize_Minify_Functions::internal_url($href, site_url()))
				|| empty($href)
			) {
					
				// process
				if (isset($footer[count($footer)-1]['handle']) || !count($footer) || !$merge_js) {
					array_push($footer, array('handles' => array(), 'versions' => array()));
				}
				
				if (isset($wp_scripts->registered[$handle]->extra['before'])) {
					if (!empty($footer[count($footer)-1]['handles'])) {
						array_push($footer, array('handles' => array(), 'versions' => array()));
					}
				}

				// push it to the array
				array_push($footer[count($footer)-1]['handles'], $handle);
				array_push($footer[count($footer)-1]['versions'], $wp_scripts->registered[$handle]->ver);
				// external and ignored scripts
			} else {
				array_push($footer, array('handle' => $handle));
			}
		endforeach;

		// loop through footer scripts and merge
		for ($i=0,$l=count($footer); $i<$l; $i++) {
			if (!isset($footer[$i]['handle'])) {

				if ($merge_js) {
					// Change the hash based on version numbers
					$hash = hash('adler32', implode('', $footer[$i]['handles']) . implode('', $footer[$i]['versions']));
				} else {
					$hash = implode('', $footer[$i]['handles']) . implode('', $footer[$i]['versions']);
				}
								
				// static cache file info
				$file_name = 'wpo-minify-footer-'.$hash.($minify_js ? '.min' : '');
				
				// create cache files and urls
				$file = $cache_dir.'/'.$file_name.'.js';
				$file_url = WP_Optimize_Minify_Functions::get_protocol($cache_dir_url.'/'.$file_name.'.js');
			
				// generate a new cache file
				clearstatcache();
				if (!file_exists($file)) {
					
					// code and log initialization
					$log = array(
						'header' => "PROCESSED on ".date('r')." from ".home_url(add_query_arg(null, null)),
						'files' => array()
					);
					$code = '';
				
					// minify and write to file
					foreach ($footer[$i]['handles'] as $handle) :
						if (!empty($wp_scripts->registered[$handle]->src)) {
							// get href per handle
							$href = WP_Optimize_Minify_Functions::get_hurl($wp_scripts->registered[$handle]->src);
							$version = $wp_scripts->registered[$handle]->ver;
							// inlined scripts without file
							if (empty($href)) {
								continue;
							}
							// download, minify, cache
							$tkey = 'js-'.hash('adler32', $handle . $href).'.js';
							$json = false;
							$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
							if (false === $json) {
								$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $minify_js, 'js', $handle, $version);
								if ($this->options['debug']) {
									echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href / $version -->\n";
								}
								WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
							}
							
							// decode
							$res = json_decode($json, true);
							
							if (isset($res['request']['version']) && $res['request']['version'] != $version && !$this->minify_cache_incremented) {
								WP_Optimize_Minify_Cache_Functions::reset();
								$this->minify_cache_incremented = true;
							}
							
							// response has failed
							if (true != $res['status']) {
								$log['files'][$handle] = $res['log'];
								continue;
							}

							$done[] = $handle;
							// Add extra data from wp_add_inline_script before
							if (!empty($wp_scripts->registered[$handle]->extra)) {
								if (!empty($wp_scripts->registered[$handle]->extra['before']) && is_array($wp_scripts->registered[$handle]->extra['before'])) {
									if ($merge_inline_extra_css_js) {
										$code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['before'])), $href . ' - BEFORE');
									}
								}
							}

							// Add translation
							if (!empty($wp_scripts->registered[$handle]->textdomain)) {
								$code .= "\n" . $wp_scripts->print_translations($handle, false);
							}

							// append code to merged file
							$code .= isset($res['code']) ? WP_Optimize_Minify_Functions::prepare_merged_js($res['code'], $href) : '';
							$log['files'][$handle] = $res['log'];
							
							// Add extra data from wp_add_inline_script after
							if (!empty($wp_scripts->registered[$handle]->extra)) {
								if (!empty($wp_scripts->registered[$handle]->extra['after']) && is_array($wp_scripts->registered[$handle]->extra['after'])) {
									if ($merge_inline_extra_css_js) {
										$code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['after'])), $href. ' - AFTER');
									}
								}
							}
					
							// consider dependencies on handles with an empty src
						} else {
							wp_dequeue_script($handle);
							wp_enqueue_script($handle);
						}
					endforeach;

					// generate cache, write log
					if (!empty($code)) {
						WP_Optimize_Minify_Print::write_combined_asset($file, $code, $log);
					}
				} else {
					$log_file = $file.'.json';
					if (file_exists($log_file)) {
						$saved_log = json_decode(file_get_contents($log_file));
						if (is_object($saved_log) && property_exists($saved_log, 'files')) {
							$files = (array) $saved_log->files;
							foreach ($footer[$i]['handles'] as $handle) {
								if (isset($files[$handle]) && $files[$handle]->success) {
									$done[] = $handle;
								}
							}
						} else {
							// The merged file already exists, so add all files to $done.
							$done = array_merge($done, $footer[$i]['handles']);
						}
					} else {
						// The merged file already exists, so add all files to $done.
						$done = array_merge($done, $footer[$i]['handles']);
					}
				}
				
				// register minified file
				wp_register_script("wpo_min-footer-$i", $file_url, array(), null, false);
				
				// add all extra data from wp_localize_script
				$before_code = '';
				$data = array();
				$after_code = '';

				foreach ($footer[$i]['handles'] as $handle) {
					if (isset($wp_scripts->registered[$handle]->extra['data'])) {
						$data[] = $wp_scripts->registered[$handle]->extra['data'];
					}
					// Add extra data from wp_add_inline_script before
					if (!empty($wp_scripts->registered[$handle]->extra)) {
						if (!empty($wp_scripts->registered[$handle]->extra['before']) && is_array($wp_scripts->registered[$handle]->extra['before'])) {
							if (!$merge_inline_extra_css_js) {
								$before_code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['before'])), $href.' - BEFORE');
							}
						}
						if (!empty($wp_scripts->registered[$handle]->extra['after']) && is_array($wp_scripts->registered[$handle]->extra['after'])) {
							if (!$merge_inline_extra_css_js) {
								$after_code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['after'])), $href.' - AFTER');
							}
						}
					}
				}
				if (count($data) > 0) {
					$wp_scripts->registered["wpo_min-footer-$i"]->extra['data'] = implode("\n", $data);
				}
				
				// enqueue file, if not empty
				if (file_exists($file) && (filesize($file) > 0 || count($data) > 0)) {
					if (!empty($before_code)) {
						wp_add_inline_script("wpo_min-footer-$i", $before_code, 'before');
					}
					wp_enqueue_script("wpo_min-footer-$i");
					if (!empty($after_code)) {
						wp_add_inline_script("wpo_min-footer-$i", $after_code, 'after');
					}
				} else {
					// file could not be generated, output something meaningful
					echo "<!-- ERROR: WP-Optimize Minify was not allowed to save its cache on - ".str_replace(ABSPATH, '', $file)." -->";
					echo "<!-- Please check if the path above is correct and ensure your server has write permission there! -->";
				}
				
				// other scripts need to be requeued for the order of files to be kept
			} else {
				wp_dequeue_script($footer[$i]['handle']);
				wp_enqueue_script($footer[$i]['handle']);
			}
		}

		// remove from queue
		$wp_scripts->done = $done;
	}

	/**
	 * Process header JavaScript
	 * Dependant on 'enable_js' option
	 *
	 * @return void
	 */
	public function process_header_scripts() {
		global $wp_scripts;
		if (!is_object($wp_scripts)) return;
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		$cache_dir_url = $cache_path['cachedirurl'];
		$exclude_js = array_map('trim', explode("\n", trim($this->options['exclude_js'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_js);
		$async_js = trim($this->options['async_js']) ? array_map('trim', explode("\n", trim($this->options['async_js']))) : array();
		$scripts = clone $wp_scripts;
		$scripts->all_deps($scripts->queue);
		$minify_js = $this->options['enable_js_minification'];
		$merge_js = $this->options['enable_merging_of_js'];
		$process_js = $minify_js || $merge_js;
		$merge_inline_extra_css_js = $this->options['merge_inline_extra_css_js'];
		$header = array();
		// mark as done (as we go)
		$done = $scripts->done;
		$excluded_dependencies = array();

		// Prepare and separate assets (get groups of handles)
		foreach ($scripts->to_do as $handle) {
			// get full url
			$href = WP_Optimize_Minify_Functions::get_hurl($wp_scripts->registered[$handle]->src);
			// inlined scripts without file
			if (empty($href)) {
				wp_enqueue_script($handle, false);
				continue;
			}

			// Only go through items without a group
			if (!$scripts->groups[$handle]) {
				// Exclude JS files from PageSpeedIndex (Async) takes priority over the ignore list
				// check for string match
				$skipjs = false;
				foreach ($async_js as $l) {
					if (stripos($href, $l) !== false) {
						WP_Optimize_Minify_Print::async_script($href);
						$skipjs = true;
						$done = array_merge($done, array($handle));
						break;
					}
				}
				// IE only files don't increment things
				if ($skipjs
					|| WP_Optimize_Minify_Functions::is_url_in_ie_blacklist($href)
				) {
					continue;
				}

				// Skip jQuery from the merged files if defering is ENABLED and defer_jquery is DISABLED
				if ('all' === $this->options['enable_defer_js']
					&& !$this->options['defer_jquery']
					&& (false !== stripos($href, '/jquery.js')
					|| false !== stripos($href, '/jquery.min.js')
					|| (false !== stripos($href, '/jquery-') && false !== stripos($href, '.js')))
				) {
					continue;
				}

				// Group handles - skip ignore list, scripts with conditionals, external scripts
				if (($process_js && !WP_Optimize_Minify_Functions::in_arrayi($href, $ignore_list) && !isset($wp_scripts->registered[$handle]->extra["conditional"]) && WP_Optimize_Minify_Functions::internal_url($href, site_url()))
					|| empty($href)
				) {
					// process
					if (isset($header[count($header)-1]['handle']) || !count($header) || !$merge_js) {
						array_push($header, array('handles' => array(), 'versions' => array()));
					}

					// Force loading of dependencies
					foreach ($wp_scripts->registered[$handle]->deps as $dep) {
						// If the handle is not present in $done yet, or excluded, enqueue it.
						$dep_href = WP_Optimize_Minify_Functions::get_hurl($wp_scripts->registered[$dep]->src);
						if (!in_array($dep, $done) && !WP_Optimize_Minify_Functions::in_arrayi($dep_href, $ignore_list)) {
							// Include any dependency
							array_push($header[count($header)-1]['handles'], $dep);
							array_push($header[count($header)-1]['versions'], $wp_scripts->registered[$dep]->ver);
						} elseif (!in_array($dep, $done) && WP_Optimize_Minify_Functions::in_arrayi($dep_href, $ignore_list)) {
							// The dependency is in the exclude list
							array_push($header, array('handle' => $dep));
							// Record dependency to be added to the minified script as dependency array
							if (isset($excluded_dependencies[count($header)])) {
								$excluded_dependencies[count($header)][] = $dep;
							} else {
								$excluded_dependencies[count($header)] = array($dep);
							}
							// Adds the 'handles' record for the main script
							array_push($header, array('handles' => array(), 'versions' => array()));
						}
					}

					if (isset($wp_scripts->registered[$handle]->extra['before'])) {
						if (!empty($header[count($header)-1]['handles'])) {
							array_push($header, array('handles' => array(), 'versions' => array()));
						}
					}

					// push it to the array
					array_push($header[count($header)-1]['handles'], $handle);
					array_push($header[count($header)-1]['versions'], $scripts->registered[$handle]->ver);

					// external and ignored scripts
				} else {
					// add the ignored assets
					array_push($header, array('handle' => $handle));
				}

				// make sure that the scripts skipped here, show up in the footer
			} else {
				wp_enqueue_script($handle, $href, array(), null, true);
			}
		}

		// loop through header scripts and merge
		for ($i=0,$l=count($header); $i < $l; $i++) {
			if (!isset($header[$i]['handle'])) {
				
				if ($merge_js) {
					$hash = hash('adler32', implode('', $header[$i]['handles']) . implode('', $header[$i]['versions']));
				} else {
					$hash = implode('', $header[$i]['handles']) . implode('', $header[$i]['versions']);
				}

				// static cache file info
				$file_name = 'wpo-minify-header-'.$hash.($minify_js ? '.min' : '');
				// create cache files and urls
				$file = $cache_dir.'/'.$file_name.'.js';
				$file_url = WP_Optimize_Minify_Functions::get_protocol($cache_dir_url.'/'.$file_name.'.js');
				
				// generate a new cache file
				clearstatcache();
				if (!file_exists($file)) {
					
					// code and log initialization
					$log = array(
						'header' => "PROCESSED on ".date('r')." from ".home_url(add_query_arg(null, null)),
						'files' => array()
					);
					$code = '';

					// minify and write to file
					foreach ($header[$i]['handles'] as $handle) {
						if (!empty($wp_scripts->registered[$handle]->src)) {

							// get href per handle
							$href = WP_Optimize_Minify_Functions::get_hurl($wp_scripts->registered[$handle]->src);
							$version = $wp_scripts->registered[$handle]->ver;
							if (empty($href)) continue;
							// download, minify, cache
							$tkey = 'js-'.hash('adler32', $handle . $href).'.js';
							$json = false;
							$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
							if (false === $json) {
								$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $minify_js, 'js', $handle, $version);
								if ($this->options['debug']) {
									echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href / $version -->" . "\n";
								}
								WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
							}
							
							// decode
							$res = json_decode($json, true);
							
							if (isset($res['request']['version']) && $res['request']['version'] != $version && !$this->minify_cache_incremented) {
								WP_Optimize_Minify_Cache_Functions::reset();
								$this->minify_cache_incremented = true;
							}

							// response has failed
							if (true != $res['status']) {
								$log['files'][$handle] = $res['log'];
								continue;
							}

							// Add extra data from wp_add_inline_script before
							if (!empty($wp_scripts->registered[$handle]->extra)) {
								if (!empty($wp_scripts->registered[$handle]->extra['before']) && is_array($wp_scripts->registered[$handle]->extra['before'])) {
									if ($merge_inline_extra_css_js) {
										$code .= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['before'])), $href.' - BEFORE');
									}
								}
							}

							// Only add the $handle to $done if it was successfully downloaded
							$done[] = $handle;

							// Add translations
							if (!empty($wp_scripts->registered[$handle]->textdomain)) {
								$code .= "\n" . $wp_scripts->print_translations($handle, false);
							}

							// append code to merged file
							$code .= isset($res['code']) ? WP_Optimize_Minify_Functions::prepare_merged_js($res['code'], $href) : '';
							$log['files'][$handle] = $res['log'];
							
							// Add extra data from wp_add_inline_script after
							if (!empty($wp_scripts->registered[$handle]->extra)) {
								if (!empty($wp_scripts->registered[$handle]->extra['after']) && is_array($wp_scripts->registered[$handle]->extra['after'])) {
									if ($merge_inline_extra_css_js) {
										$code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['after'])), $href.' - AFTER');
									}
								}
							}

							// consider dependencies on handles with an empty src
						} else {
							wp_dequeue_script($handle);
							wp_enqueue_script($handle);
						}
					}

					// generate cache, write log
					if (!empty($code)) {
						WP_Optimize_Minify_Print::write_combined_asset($file, $code, $log);
					}
				} else {
					$log_file = $file.'.json';
					if (file_exists($log_file)) {
						$saved_log = json_decode(file_get_contents($log_file));
						if (is_object($saved_log) && property_exists($saved_log, 'files')) {
							$files = (array) $saved_log->files;
							foreach ($header[$i]['handles'] as $handle) {
								if (isset($files[$handle]) && $files[$handle]->success) {
									$done[] = $handle;
								}
							}
						} else {
							// The merged file already exists, so add all files to $done.
							$done = array_merge($done, $header[$i]['handles']);
						}
					} else {
						// The merged file already exists, so add all files to $done.
						$done = array_merge($done, $header[$i]['handles']);
					}
				}

				// register minified file
				$dependencies = isset($excluded_dependencies[$i]) ? $excluded_dependencies[$i] : array();
				wp_register_script("wpo_min-header-$i", $file_url, $dependencies, null, false);

				// add all extra data from wp_localize_script
				$before_code = '';
				$data = array();
				$after_code = '';

				foreach ($header[$i]['handles'] as $handle) {
					if (isset($wp_scripts->registered[$handle]->extra['data'])) {
						$data[] = $wp_scripts->registered[$handle]->extra['data'];
					}

					// Add extra data from wp_add_inline_script before
					if (!empty($wp_scripts->registered[$handle]->extra)) {
						if (!empty($wp_scripts->registered[$handle]->extra['before']) && is_array($wp_scripts->registered[$handle]->extra['before'])) {
							if (!$merge_inline_extra_css_js) {
								$before_code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['before'])), $href.' - BEFORE');
							}
						}
						if (!empty($wp_scripts->registered[$handle]->extra['after']) && is_array($wp_scripts->registered[$handle]->extra['after'])) {
							if (!$merge_inline_extra_css_js) {
								$after_code.= "\n" . WP_Optimize_Minify_Functions::prepare_merged_js(implode("\n", array_filter($wp_scripts->registered[$handle]->extra['after'])), $href.' - AFTER');
							}
						}
					}
				}
				if (count($data) > 0) {
					$wp_scripts->registered["wpo_min-header-$i"]->extra['data'] = implode("\n", $data);
				}
				
				// enqueue file, if not empty
				if (file_exists($file) && (filesize($file) > 0 || count($data) > 0)) {
					if (!empty($before_code)) {
						wp_add_inline_script("wpo_min-header-$i", $before_code, 'before');
					}
					wp_enqueue_script("wpo_min-header-$i");
					if (!empty($after_code)) {
						wp_add_inline_script("wpo_min-header-$i", $after_code, 'after');
					}
				} else {
					// file could not be generated, output something meaningful
					echo "<!-- ERROR: WP-Optimize minify was not allowed to save its cache on - ".str_replace(ABSPATH, '', $file)." -->";
					echo "<!-- Please check if the path above is correct and ensure your server has write permission there! -->";
					echo "<!-- If you found a bug, please report this on https://wordpress.org/support/plugin/wp-optimize/ -->";
				}
			
				// other scripts need to be requeued for the order of files to be kept
			} else {
				wp_dequeue_script($header[$i]['handle']);
				wp_enqueue_script($header[$i]['handle']);
			}
		}

		// remove from queue
		$wp_scripts->done = $done;
	}
	
	/**
	 * Process CSS in the footer
	 *
	 * @return void
	 */
	public function process_footer_css() {
		global $wp_styles;
		if (!is_object($wp_styles)) return;
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		$cache_dir_url = $cache_path['cachedirurl'];
		$exclude_css = array_map('trim', explode("\n", trim($this->options['exclude_css'])));
		$ignore_list = WP_Optimize_Minify_Functions::compile_ignore_list($exclude_css);
		$async_css = array_map('trim', explode("\n", trim($this->options['async_css'])));
		$minify_css = $this->options['enable_css_minification'];
		$merge_css = $this->options['enable_merging_of_css'];
		$process_css = $minify_css || $merge_css;
		$log = "";
		$code = "";
		$styles = clone $wp_styles;
		$styles->all_deps($styles->queue);
		$done = $styles->done;
		$footer = array();
		$google_fonts = array();
		$inline_css = array();

		// dequeue all styles
		if (isset($this->options['remove_css']) && $this->options['remove_css']) {
			foreach ($styles->to_do as $handle) :
				$done = array_merge($done, array($handle));
			endforeach;
			
			// remove from queue
			$wp_styles->done = $done;
			return;
		}

		// dequeue and get a list of google fonts, or requeue external
		foreach ($styles->to_do as $handle) {
			$href = WP_Optimize_Minify_Functions::get_hurl($wp_styles->registered[$handle]->src);
			// inlined scripts without file
			if (empty($href)) continue;
			
			if (WP_Optimize_Minify_Functions::is_google_font($href)) {
				wp_dequeue_style($handle);
				if ($this->options['remove_googlefonts']) {
					$done = array_merge($done, array($handle));
					continue;
				}
				// mark as done if to be removed
				if ($this->options['merge_google_fonts']
					|| 'inline' === $this->options['gfonts_method']
				) {
					if (WP_Optimize_Minify_Functions::is_flatsome_handle($handle)) {
						$href = WP_Optimize_Minify_Functions::fix_flatsome_google_fonts_url($href);
						$google_fonts[$handle] = $href;
					} else {
						$google_fonts[$handle] = $href;
					}
				} else {
					// skip google fonts optimization?
					wp_enqueue_style($handle);
				}
			} else {
				// failsafe
				wp_dequeue_style($handle);
				wp_enqueue_style($handle);
			}
			
		}

		// concat google fonts, if enabled
		if ($this->options['merge_google_fonts']
			&& count($google_fonts) > 0
			|| ('inline' === $this->options['gfonts_method'] && count($google_fonts) > 0)
		) {
		
			// merge google fonts if force inlining is enabled?
			$nfonts = array();
			if ($this->options['merge_google_fonts']) {
				$nfonts[] = WP_Optimize_Minify_Fonts::concatenate_google_fonts($google_fonts);
			} else {
				foreach ($google_fonts as $h => $a) {
					if (!empty($a)) {
						$nfonts[$h] = $a;
					}
				}
			}

			// foreach google font (will be one if merged is not disabled)
			if (count($nfonts) > 0) {
				foreach ($nfonts as $handle => $href) {
					// hide from PageSpeedIndex, async, inline, or default
					if ('exclude' === $this->options['gfonts_method']) {
						WP_Optimize_Minify_Print::exclude_style($href);
						$done = array_merge($done, array($handle));
					} elseif ('async' === $this->options['gfonts_method']) {
						// async CSS
						WP_Optimize_Minify_Print::async_style($href);
						$done = array_merge($done, array($handle));
					} elseif ('inline' === $this->options['gfonts_method']) {
						// inline css
						WP_Optimize_Minify_Print::inline_style($handle, $href);
						$done = array_merge($done, array($handle));
					} else {
						// fallback, enqueue google fonts
						wp_enqueue_style($handle, $href, array(), null, 'all');
					}
				}
			}
		}


		// get groups of handles
		$uniq = array();
		foreach ($styles->to_do as $handle) {

			// skip already processed google fonts
			if (isset($google_fonts[$handle])) {
				continue;
			}
			
			// conditionals
			$conditional = null; if (isset($wp_styles->registered[$handle]->extra["conditional"])) {
				$conditional = $wp_styles->registered[$handle]->extra["conditional"]; // such as ie7, ie8, ie9, etc
			}
			
			// mediatype
			$mt = isset($wp_styles->registered[$handle]->args) ? $wp_styles->registered[$handle]->args : 'all';
			if ('screen' == $mt || 'screen, print' == $mt || empty($mt) || is_null($mt) || false == $mt) {
				$mt = 'all';
			}
			$mediatype = $mt;
			
			// get full url
			$href = WP_Optimize_Minify_Functions::get_hurl($wp_styles->registered[$handle]->src);
			$version = $wp_styles->registered[$handle]->ver;
			
			// inlined scripts without file
			if (empty($href)) {
				continue;
			}
			
			// mark duplicates as done and remove from the queue
			if (!empty($href)) {
				$key = hash('adler32', $href . $version);
				if (isset($uniq[$key])) {
					$done = array_merge($done, array($handle));
					continue;
				} else {
					$uniq[$key] = $handle;
				}
			}
			
			// IE only files don't increment things
			$ieonly = WP_Optimize_Minify_Functions::is_url_in_ie_blacklist($href);
			if ($ieonly) {
				continue;
			}

			// Exclude specific CSS files from PageSpeedIndex?
			if (false != $async_css && is_array($async_css) && WP_Optimize_Minify_Functions::in_arrayi($href, $async_css)) {
				WP_Optimize_Minify_Print::exclude_style($href);
				$done = array_merge($done, array($handle));
				continue;
			}

			if (WP_Optimize_Minify_Functions::is_font_awesome($href)) {
				if ('inline' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::inline_style($handle, $href);
					$done = array_merge($done, array($handle));
					continue;
				} elseif ('async' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::async_style($href, $mediatype);
					$done = array_merge($done, array($handle));
					continue;
				} elseif ('exclude' === $this->options['fawesome_method']) {
					WP_Optimize_Minify_Print::exclude_style($href);
					$done = array_merge($done, array($handle));
					continue;
				}
			}

			// Exclude Print mediatype
			if ($this->options['remove_print_mediatypes'] && 'print' === $mediatype) {
				$done = array_merge($done, array($handle));
				continue;
			}
			
			// skip ignore list, conditional css, external css, font-awesome merge
			if (($process_css && !WP_Optimize_Minify_Functions::in_arrayi($href, $ignore_list) && !isset($conditional) && WP_Optimize_Minify_Functions::internal_url($href, site_url()))
				|| empty($href)
			) {
					
				// colect inline css for this handle
				if (isset($wp_styles->registered[$handle]->extra['after']) && is_array($wp_styles->registered[$handle]->extra['after'])) {
					$inline_css[$handle] = WP_Optimize_Minify_Functions::minify_css_string(implode('', $wp_styles->registered[$handle]->extra['after'])); // save
					$wp_styles->registered[$handle]->extra['after'] = null; // dequeue
				}

				// process
				if (isset($footer[count($footer)-1]['handle']) || !count($footer) || $footer[count($footer)-1]['media'] != $wp_styles->registered[$handle]->args || !$merge_css) {
					array_push($footer, array('handles' => array(), 'media' => $mediatype, 'versions' => array()));
				}
			
				// push it to the array get latest modified time
				array_push($footer[count($footer)-1]['handles'], $handle);
				array_push($footer[count($footer)-1]['handles'], $version);
				
				// external and ignored css
			} else {
				
				// normal enqueueing
				array_push($footer, array('handle' => $handle));
			}
		}

		/**
		 * Filters the array of stylesheets before processing them
		 *
		 * @param array  $list     - The list of items filtered
		 * @param string $location - The location of the list (footer or header)
		 * @return array
		 */
		$footer = apply_filters('wpo_minify_stylesheets', $footer, 'footer');

		// loop through footer css and merge
		for ($i=0,$l=count($footer); $i<$l; $i++) {
			if (!isset($footer[$i]['handle'])) {
				if ($merge_css) {
					// get hash for the inline css in this group
					$inline_css_group = array();
					foreach ($footer[$i]['handles'] as $h) {
						if (isset($inline_css[$h]) && !empty($inline_css[$h])) {
							$inline_css_group[] = $inline_css[$h];
						}
					}
					$inline_css_hash = md5(implode('', $inline_css_group));
					$hash = hash('adler32', implode('', $footer[$i]['handles']) . $inline_css_hash . implode('', $footer[$i]['versions']));
				} else {
					$hash = implode('', $footer[$i]['handles']) . implode('', $footer[$i]['versions']);
				}

				// static cache file info
				$file_name = 'wpo-minify-footer-'.$hash.($minify_css ? '.min' : '');

				// create cache files and urls
				$file = $cache_dir.'/'.$file_name.'.css';
				$file_url = WP_Optimize_Minify_Functions::get_protocol($cache_dir_url.'/'.$file_name.'.css');
				
				// generate a new cache file
				clearstatcache();
				if (!file_exists($file)) {

					// code and log initialization
					$log = array(
						'header' => "PROCESSED on ".date('r')." from ".home_url(add_query_arg(null, null)),
						'files' => array()
					);
					$code = '';

					// minify and write to file
					foreach ($footer[$i]['handles'] as $handle) {
						if (!empty($wp_styles->registered[$handle]->src)) {

							// get href per handle
							$href = WP_Optimize_Minify_Functions::get_hurl($wp_styles->registered[$handle]->src);
							$version = $wp_styles->registered[$handle]->ver;
							// inlined scripts without hreffile
							if (empty($href)) continue;

							// download, minify, cache
							$tkey = 'css-'.hash('adler32', $handle . $href).'.css';
							$json = false;
							$json = WP_Optimize_Minify_Cache_Functions::get_transient($tkey);
							if (false === $json) {
								$json = WP_Optimize_Minify_Functions::download_and_minify($href, null, $minify_css, 'css', $handle, $version);
								if ($this->options['debug']) {
									echo "<!-- wpo_min DEBUG: Uncached file processing now for $handle / $href / $version -->" . "\n";
								}
								WP_Optimize_Minify_Cache_Functions::set_transient($tkey, $json);
							}
							
							// decode
							$res = json_decode($json, true);

							if (isset($res['request']['version']) && $res['request']['version'] != $version && !$this->minify_cache_incremented) {
								WP_Optimize_Minify_Cache_Functions::reset();
								$this->minify_cache_incremented = true;
							}
							
							// response has failed
							if (true != $res['status']) {
								$log['files'][$handle] = $res['log'];
								continue;
							}

							$done[] = $handle;

							// append code to merged file
							$code .= isset($res['code']) ? $res['code'] : '';
							$log['files'][$handle] = $res['log'];

							// append inlined styles
							if (isset($inline_css[$handle]) && !empty($inline_css[$handle])) {
								$code.= $inline_css[$handle];
							}

							// consider dependencies on handles with an empty src
						} else {
							wp_dequeue_script($handle);
							wp_enqueue_script($handle);
						}
					};

					// generate cache, add inline css, write log
					if (!empty($code)) {
						WP_Optimize_Minify_Print::write_combined_asset($file, $code, $log);
					}
				} else {
					$log_file = $file.'.json';
					if (file_exists($log_file)) {
						$saved_log = json_decode(file_get_contents($log_file));
						if (is_object($saved_log) && property_exists($saved_log, 'files')) {
							$files = (array) $saved_log->files;
							foreach ($footer[$i]['handles'] as $handle) {
								if (isset($files[$handle]) && $files[$handle]->success) {
									$done[] = $handle;
								}
							}
						} else {
							// The merged file already exists, so add all files to $done.
							$done = array_merge($done, $footer[$i]['handles']);
						}
					} else {
						// The merged file already exists, so add all files to $done.
						$done = array_merge($done, $footer[$i]['handles']);
					}
				}

				// Async CSS with loadCSS ?
				if ($this->options['loadcss'] && !$this->options['remove_css']) {
					$mt = $footer[$i]['media'];
					WP_Optimize_Minify_Print::async_style($file_url, $mt);
					// enqueue file, if not empty
				} else {
					if (file_exists($file) && filesize($file) > 0) {

						// inline if the file is smaller than 20KB or option has been enabled
						if (filesize($file) < 20000 && $this->options['inline_css']) {
							$this->inline_css(file_get_contents($file), $handle, $file_url, $footer[$i]['media']);
						} else {
							// enqueue it
							wp_enqueue_style("wpo_min-footer-$i", $file_url, array(), null, $footer[$i]['media']);
						}
					} else {
						// file could not be generated, output something meaningful
						echo "<!-- ERROR: WP-Optimize Minify was not allowed to save its cache on - ".str_replace(ABSPATH, '', $file)." -->";
						echo "<!-- Please check if the path above is correct and ensure your server has write permission there! -->";
					}
				}

				// other css need to be requeued for the order of files to be kept
			} else {
				wp_dequeue_style($footer[$i]['handle']);
				wp_enqueue_style($footer[$i]['handle']);
			}
		}

		// remove from queue
		$wp_styles->done = $done;
	}

	/**
	 * Orders the CSS per media type
	 *
	 * @param array $list - The list of assets
	 * @return array
	 */
	public function order_stylesheets_per_media_type($list) {
		// get unique mediatypes
		$allmedia = array();
		foreach ($list as $array) {
			if (isset($array['media'])) {
				$allmedia[$array['media']] = '';
			}
		}

		// extract handles by mediatype
		$grouphandles = array();
		foreach ($allmedia as $md => $var) {
			foreach ($list as $array) {
				if (isset($array['media']) && $array['media'] === $md) {
					foreach ($array['handles'] as $h) {
						$grouphandles[$md][] = $h;
					}
				}
			}
		}

		// reset and reorder list by mediatypes
		$newlist = array();
		foreach ($allmedia as $md => $var) {
			$newlist[] = array('handles' => $grouphandles[$md], 'media' => $md);
		}
		if (count($newlist) > 0) {
			$list = $newlist;
		}

		return $list;
	}

	/**
	 * Merged google font
	 *
	 * @return void;
	 */
	public function add_google_fonts_merged() {
		// must have something to do
		if (count($this->collect_google_fonts) == 0) return;

		// merge google fonts
		$href = WP_Optimize_Minify_Fonts::concatenate_google_fonts($this->collect_google_fonts);
		if (empty($href)) {
			return;
		}
		
		// hide google fonts from PageSpeedIndex
		if ('exclude' === $this->options['gfonts_method']) {
			// make a stylesheet, hide from PageSpeedIndex
			WP_Optimize_Minify_Print::exclude_style($href);
		} elseif ('async' === $this->options['gfonts_method']) {
			// load google fonts async
			WP_Optimize_Minify_Print::async_style($href);
		} else {
			// fallback to normal inline
			WP_Optimize_Minify_Print::style($href);
		}

		// unset per hook
		foreach ($this->collect_google_fonts as $k => $v) {
			unset($this->collect_google_fonts[$k]);
		}
	}

	/**
	 *  Check if the string given contains 'wpo-minify'
	 *
	 * @param string $var
	 *
	 * @return boolean
	 */
	public function check_wpo($var) {
		return (false === strpos($var, 'assets/wpo-minify'));
	}

	/**
	 * Generate preload headers file
	 *
	 * @return boolean
	 */
	public function generate_preload_headers() {

		// always false for admin pages
		if (is_admin()) return false;

		// get host with multisite support and query strings
		$host = htmlentities($_SERVER['SERVER_NAME']);
		if (empty($host)) {
			$host = htmlentities($_SERVER['HTTP_HOST']);
		}
		$request_query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		
		// initialize headers
		$headers = array();

		// css headers
		if ($this->options['enabled_css_preload']
			&& count($this->collect_preload_css) > 0
		) {
			foreach ($this->collect_preload_css as $u) {
				// filter out footer footer files, because they are not in the critical path
				if (strpos($u, '/assets/wpo-minify-footer-') !== false) {
					continue;
				}
				
				// add headers
				$headers[] = "Link: <$u>; rel=preload; as=style";
			}
		}
		
		// js headers
		if ($this->options['enabled_js_preload']
			&& count($this->collect_preload_js) > 0
		) {
			foreach ($this->collect_preload_js as $u) {

				// filter out footer footer files, because they are not in the critical path
				if (false !== strpos($u, '/assets/wpo-minify-footer-')) {
					continue;
				}
				
				// add headers
				$headers[] = "Link: <$u>; rel=preload; as=script";
			}
		}
		
		// must have something
		if (count($headers) == 0) {
			return false;
		} else {
			$headers = implode("\n", $headers);
		}
		
		// get cache path
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$header_dir = $cache_path['headerdir'];
		
		// possible cache file locations
		$b = $header_dir . '/' . md5($host.'-'.$request_uri) . '.header';
		$a = $header_dir . '/' . md5($host.'-'.$request_uri . $request_query).'.header';

		// reset file cache
		clearstatcache();
		
		// if there are no query strings
		if ($b == $a) {
			if (!file_exists($a)) {
				WP_Optimize_Minify_Print::write_header($a, $headers);
			}
			return false;
		} elseif (!file_exists($b)) {
			WP_Optimize_Minify_Print::write_header($b, $headers);
		}
		
		return false;
	}

	/**
	 * Collect all wpo_min JS files and save them to a headers file
	 *
	 * @param String $html
	 * @param String $handle
	 * @param String $src
	 *
	 * @return String
	 */
	public function collect_js_preload_headers($html, $handle, $src) {
		if (false !== strpos($src, 'assets/wpo-minify')) {
			if (!in_array($src, $this->collect_preload_js)) {
				$this->collect_preload_js[] = $src;
			}
		}
		return $html . "\n";
	}

	/**
	 * Collect all wpo_min CSS files and save them to a headers file
	 * this function intercepts the styles that are enqueued
	 * take note that if the css is loaded ASYNC, it's not enqueued
	 *
	 * @param string $html   - Html string
	 * @param string $handle - Handle
	 * @param string $src    - The CSS file path
	 *
	 * @return string
	 */
	public function collect_css_preload_headers($html, $handle, $src) {
		if (false !== strpos($src, 'assets/wpo-minify')) {
			if (!in_array($src, $this->collect_preload_css)) {
				$this->collect_preload_css[] = $src;
			}
		}
		return $html . "\n";
	}

	/**
	 * Get current headers file for the url
	 *
	 * @return bool|string
	 */
	private function get_preload_headers() {

		// always false for admin pages
		if (is_admin()) return false;

		$enabled_css_preload = $this->options['enabled_css_preload'];
		$enabled_js_preload = $this->options['enabled_js_preload'];

		if (!$enabled_css_preload && !$enabled_js_preload) {
			return false;
		}

		// get host with multisite support and query strings
		$host = htmlentities($_SERVER['SERVER_NAME']);
		if (empty($host)) {
			$host = htmlentities($_SERVER['HTTP_HOST']);
		}
		$request_query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		
		// get cache path
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$header_dir = $cache_path['headerdir'];
		$cache_file_base = $header_dir . '/';
		
		// possible cache file locations
		$b = $cache_file_base . md5("$host-$request_uri").'.header';
		$a = $cache_file_base . md5("$host-$request_uri$request_query").'.header';
		
		// reset file cache
		clearstatcache();
		
		// return header files or fallback
		if ($b == $a && file_exists($a)) {
			return file_get_contents($a);
		}
		if ($b != $a && file_exists($b)) {
			return file_get_contents($b);
		}
		
		return false;
	}
	
	/**
	 * Add pre-connect and pre-load headers
	 *
	 * @return void
	 */
	public function extra_preload_headers() {
		if (!$this->run_on_page('extra_preload_headers')) return;

		// fetch headers
		$pre_connect = array_map('trim', explode("\n", trim($this->options['hpreconnect'])));

		// preload
		if (is_array($pre_connect) && count($pre_connect) > 0) {
			foreach ($pre_connect as $url) {
				if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
					header("Link: <$url>; rel=preconnect", false);
				}
			}
		}

		$headers = $this->get_preload_headers();
		if (false != $headers) {
			$nh = array_map('trim', explode("\n", $headers));
			foreach ($nh as $h) {
				if (!empty($h)) {
					header($h, false);
				}
			}
		}
	}

	/**
	 * Includes dependency files
	 */
	private function include_dependencies() {
		if (!class_exists('WP_Optimize_Minify_Functions')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-functions.php';
		}
		
		if (!class_exists('WP_Optimize_Minify_Print')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-print.php';
		}
		
		if (!class_exists('WP_Optimize_Minify_Fonts')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-fonts.php';
		}
	}

	/**
	 * Handles emoji removal
	 */
	private function disable_emojis() {
		WP_Optimize_Minify_Functions::disable_wp_emojicons();
		add_filter('tiny_mce_plugins', array('WP_Optimize_Minify_Functions', 'disable_emojis_tinymce' ));
	}

	/**
	 * Handles header meta information removal
	 */
	private function remove_header_meta_info() {
		// no resource hints, generator tag, shortlinks, manifest link, etc
		remove_action('wp_head', 'wp_resource_hints', 2);
		remove_action('wp_head', 'wp_generator');
		remove_action('template_redirect', 'wp_shortlink_header', 11);
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
		remove_action('wp_head', 'feed_links', 2);
		remove_action('wp_head', 'feed_links_extra', 3);
		WP_Optimize_Minify_Functions::remove_redundant_shortlink();
	}

	/**
	 * Handles javascript processing
	 */
	private function process_js() {
		add_action('wp_print_scripts', array($this, 'process_header_scripts'), PHP_INT_MAX);
		add_action('wp_print_footer_scripts', array($this, 'process_footer_scripts'), 9);
		
		// Defer JS
		add_filter('script_loader_tag', array($this, 'defer_js'), 10, 3);

		// Preloading
		if ($this->options['enabled_js_preload']) {
			add_filter('script_loader_tag', array($this, 'collect_js_preload_headers'), PHP_INT_MAX, 3);
		}

		// add the LoadAsync JavaScript function
		$async_js = trim($this->options['async_js']) ? array_map('trim', explode("\n", trim($this->options['async_js']))) : array();
		if (count($async_js) > 0
			|| ( 'all' === $this->options['enable_defer_js'] && 'async_using_js' === $this->options['defer_js_type'] )
		) {
			add_action('wp_head', array('WP_Optimize_Minify_Print', 'add_load_async'), 0);
		}
	}

	/**
	 * Handles css processing
	 */
	private function process_css() {
		add_action('wp_head', array($this, 'add_critical_path'), 2);

		// merge, if inline is selected but prevent optimization for these locations
		if ($this->options['inline_css']) {
			// this prints the styles (not fonts) and checks the 'colllect google fonts'
			add_filter('style_loader_tag', array($this, 'inline_css'), PHP_INT_MAX, 4);
			// this prints the google fonts
			add_action('wp_print_styles', array($this, 'add_google_fonts_merged'), PHP_INT_MAX);
			add_action('wp_print_footer_scripts', array($this, 'add_google_fonts_merged'), PHP_INT_MAX);
		}
		// Preloading
		if ($this->options['enabled_css_preload']) {
			add_filter('style_loader_tag', array($this, 'collect_css_preload_headers'), PHP_INT_MAX, 3);
		}
		// Optimize the css and collect the google fonts for merging
		add_action('wp_print_styles', array($this, 'process_header_css'), PHP_INT_MAX);
		add_action('wp_print_footer_scripts', array($this, 'process_footer_css'), 9);

		/**
		 * Filters whether or not to ignore the order of the CSS files, and group them by media type
		 *
		 * @param boolean $maintain_css_order
		 * @return boolean
		 * @default true
		 */
		if (!apply_filters('wpo_minify_maintain_css_order', true)) {
			// Reorder stylesheets
			add_filter('wpo_minify_stylesheets', array($this, 'order_stylesheets_per_media_type'), 10);
		}
	}

	/**
	 * Determines whether we should minify html or not
	 */
	private function should_process_html() {
		return $this->options['html_minification'] && !is_admin() && $this->is_cache_preload();
	}

	/**
	 * Determines whether to use loadCSS polyfill or not
	 */
	private function should_use_loadCSS() {
		return $this->options['loadcss']
			|| 'async' === $this->options['fawesome_method']
			|| 'async' === $this->options['gfonts_method'];
	}

	/**
	 * Removes query string from static assets
	 */
	private function remove_query_string_from_static_assets() {
		add_filter('style_loader_src', array('WP_Optimize_Minify_Functions', 'remove_cssjs_ver'), 10, 2);
	}
}
