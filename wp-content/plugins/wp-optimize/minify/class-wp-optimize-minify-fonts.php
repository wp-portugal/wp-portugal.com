<?php
if (!defined('ABSPATH')) die('No direct access allowed');

class WP_Optimize_Minify_Fonts {

	private static $fonts = array();

	private static $subsets = array();

	/**
	 * Get a list of Google fonts
	 *
	 * @return Array
	 */
	public static function get_google_fonts() {
		// https://www.googleapis.com/webfonts/v1/webfonts?sort=alpha
		$google_fonts_file = WPO_PLUGIN_MAIN_PATH.'google-fonts.json';
		if (is_file($google_fonts_file) && is_readable($google_fonts_file)) {
			return json_decode(file_get_contents($google_fonts_file), true);
		}
		return array();
	}

	/**
	 * Check if the google font exist or not
	 *
	 * @param string $font
	 * @return boolean
	 */
	public static function concatenate_google_fonts_allowed($font) {
		$gfonts_whitelist = self::get_google_fonts();

		// normalize
		$font = str_ireplace('+', ' ', strtolower($font));
		
		return in_array($font, $gfonts_whitelist);
	}

	/**
	 * Concatenates Google Fonts tags (http://fonts.googleapis.com/css?...)
	 *
	 * @param array $gfonts_array
	 * @return string|boolean
	 */
	public static function concatenate_google_fonts($gfonts_array) {
		// Loop through fonts array
		foreach ($gfonts_array as $font) {
			self::parse_font_url($font);
		}
		self::convert_v1_font_specs_to_v2();
		$merge = self::build();
		$config = wp_optimize_minify_config();
		/**
		 * Filters wether to add display=swap to Google fonts urls
		 *
		 * @param boolean $display - Default to true
		 */
		if (apply_filters('wpo_minify_gfont_display_swap', $config->get('enable_display_swap'))) {
			/**
			 * Filters the value of the display parameter.
			 *
			 * @param string $display_value - Default to 'swap'. https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/font-display
			 */
			$merge.= '&display='.apply_filters('wpo_minify_gfont_display_type', 'swap');
		}

		if (!empty($merge)) return 'https://fonts.googleapis.com/css2?' . $merge;

		return false;
	}

	/**
	 * Parses font url based on whether it is API version 1 or 2
	 */
	private static function parse_font_url($font) {
		if (false !== strpos($font, 'css?')) {
			self::parse_font_api1_url($font);
		} else {
			self::parse_font_api2_url($font);
		}
	}

	/**
	 * Parses google font api version 1 url
	 */
	private static function parse_font_api1_url($font) {
		parse_str(parse_url(rtrim($font, '|'), PHP_URL_QUERY), $font_elements);
		// Process each font family
		foreach (explode('|', $font_elements['family']) as $font_family) {
			// Separate font and sizes
			$font_family = explode(':', $font_family);
			// if the family wasn't added yet
			if (!in_array($font_family[0], array_keys(self::$fonts))) {
				self::$fonts[$font_family[0]]['specs'] = isset($font_family[1]) ? explode(',', rtrim($font_family[1], ',')) : array();
			} else {
				// if the family was already added, and this new one has weights, merge with previous
				if (isset($font_family[1])) {
					if (isset(self::$fonts[$font_family[0]]['version']) && 'V2' == self::$fonts[$font_family[0]]['version']) {
						self::$fonts[$font_family[0]]['specs'] = explode(',', rtrim($font_family[1], ','));
					} else {
						self::$fonts[$font_family[0]]['specs'] = array_merge(self::$fonts[$font_family[0]]['specs'], explode(',', rtrim($font_family[1], ',')));
					}
				}
			}
			self::$fonts[$font_family[0]]['version'] = 'V1';
		}

		// Add subsets
		if (isset($font_elements['subset'])) {
			self::$subsets = array_merge(self::$subsets, explode(',', $font_elements['subset']));
		}
	}

	/**
	 * Parses google font api version 2 url
	 */
	private static function parse_font_api2_url($font) {
		$parsed_url = parse_url($font, PHP_URL_QUERY);
		$query_elements = explode('&', $parsed_url);
		foreach ($query_elements as $element) {
			$family_str = str_replace('family=', '', $element);
			$family = explode(':', $family_str);
			if (!empty($family)) {
				$font_name = $family[0];
				$font_elements = isset($family[1]) ? explode('@', $family[1]) : '';
				if (!empty($font_elements) && !empty($font_elements[0]) && !empty($font_elements[1])) {
					$font_styles = $font_elements[0];
					$font_units = explode(',', $font_elements[1]);
				}
			} else {
				$font_name = $family_str;
				continue;
			}
	
			if (!isset(self::$fonts[$font_name])) {
				self::$fonts[$font_name]['specs'] = array(
					'wght' => array(),
					'ital' => array(),
					'ital,wght' => array(),
				);
			}

			if (!isset(self::$fonts[$font_name]['version'])) {
				self::$fonts[$font_name]['version'] = 'V2';
			}
			if (isset($font_styles) && isset($font_units) && isset($font_elements[1])) {
				$font_units = explode(';', $font_elements[1]);
				switch ($font_styles) {
					case 'wght':
						foreach ($font_units as $font_unit) {
							if (!in_array($font_unit, self::$fonts[$font_name]['specs']['wght'])) {
								array_push(self::$fonts[$font_name]['specs']['wght'], $font_unit);
							}
						}
						break;
					case 'ital':
						foreach ($font_units as $font_unit) {
							if (!in_array($font_unit, self::$fonts[$font_name]['specs']['ital'])) {
								array_push(self::$fonts[$font_name]['specs']['ital'], $font_unit);
							}
						}
						break;
					case 'ital,wght':
						foreach ($font_units as $font_unit) {
							if (!in_array($font_unit, self::$fonts[$font_name]['specs']['ital,wght'])) {
								array_push(self::$fonts[$font_name]['specs']['ital,wght'], $font_unit);
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Converts google font api version 1 font specification into API V2
	 */
	private static function convert_v1_font_specs_to_v2() {
		foreach (self::$fonts as $font_name => $font_details) {
			if ('V2' == $font_details['version']) continue;
			if (0 == count($font_details['specs'])) {
				self::$fonts[$font_name]['specs'] = array(
					'wght' => array(),
					'ital' => array(),
					'ital,wght' => array(),
				);
			} else {
				foreach ($font_details['specs'] as $key => $detail) {
					if (is_array($detail)) $detail = implode('', $detail);
					switch ($detail) {
						case 'i':
							unset(self::$fonts[$font_name]['specs'][$key]);
							self::$fonts[$font_name]['specs']['ital'] = array(1);
							break;
						case 'b':
							unset(self::$fonts[$font_name]['specs'][$key]);
							self::$fonts[$font_name]['specs']['wght'] = array();
							break;
						case 'bi':
							unset(self::$fonts[$font_name]['specs'][$key]);
							self::$fonts[$font_name]['specs']['ital'] = array('0;1');
							break;
						default:
							unset(self::$fonts[$font_name]['specs'][$key]);
							if (!isset(self::$fonts[$font_name]['specs']['ital,wght'])) {
								self::$fonts[$font_name]['specs']['ital,wght'] = array();
							}
							if (false !== strpos($detail, 'i')) {
								$detail = str_replace(array('italic', 'i'), '', $detail);
								$detail = '' === $detail ? 400 : $detail;
								array_push(self::$fonts[$font_name]['specs']['ital,wght'], '1,' . $detail);
							} else {
								$detail = 'regular' === $detail ? 400 : $detail;
								array_push(self::$fonts[$font_name]['specs']['ital,wght'], '0,' . $detail);
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * Build valid Google font api 2 url string
	 *
	 * @return string $result Url string
	 */
	private static function build() {
		$result = '';
		foreach (self::$fonts as $font_name => $font_details) {
			if ('display=swap' == $font_name) continue;
			if ('' != $result) {
				$result .= '&';
			}
			$result .= 'family=' . str_replace(' ', '+', $font_name);
			$result .= self::specs_to_string($font_details['specs']);
		}
		return $result;
	}

	/**
	 * Converts font specifications into a valid google font api2 url string
	 *
	 * @param array $font_specs Font style and weight specifications
	 *
	 * @return string
	 */
	private static function specs_to_string($font_specs) {
		$result = array();
		$weights = isset($font_specs['wght']) && count($font_specs['wght']);
		$italic_weights = isset($font_specs['ital']) && count($font_specs['ital']);
		$all_weights = isset($font_specs['ital,wght']) && count($font_specs['ital,wght']);

		// Nothing is set, return
		if (!$weights && !$italic_weights && !$all_weights) {
			return '';
		}

		// Italic only
		if ($italic_weights && !$weights && !$all_weights) {
			if ('1' == $font_specs['ital'][0]) {
				return ':ital@1';
			} elseif ('0;1' == $font_specs['ital'][0]) {
				return ':ital@0;1';
			}
		}

		foreach ($font_specs as $style => $units) {
			switch ($style) {
				case 'wght':
					foreach ($units as $unit) {
						$multiple_units = explode(',', $unit);
						if (count($multiple_units) > 0) {
							foreach ($multiple_units as $single_unit) {
								array_push($result, '0,' . $single_unit);
							}
						} else {
							array_push($result, '0,' . $unit);
						}
					}
					break;
				case 'ital':
					foreach ($units as $unit) {
						array_push($result, 1 == $unit ? '1,400' : $unit);
					}
					break;
				case 'ital,wght':
					foreach ($units as $unit) {
						array_push($result, $unit);
					}
					break;
			}
		}

		sort($result);
		return ':ital,wght@' . implode(';', array_unique($result));
	}
}
