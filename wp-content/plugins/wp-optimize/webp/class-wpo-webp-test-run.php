<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

use \WebPConvert\Convert\ConverterFactory;

require_once WPO_PLUGIN_MAIN_PATH . 'vendor/autoload.php';

if (!class_exists('WPO_WebP_Test_Run')) :
	/**
	 * Test run
	 */
class WPO_WebP_Test_Run {

	/**
	 * Get a test result object OR false, if tests cannot be made.
	 *
	 * @return object|false
	 */
	public static function get_converter_status() {
		$source = WPO_PLUGIN_MAIN_PATH . 'images/logo/wpo_logo_small.png';
		$upload_dir = wp_upload_dir();
		$destination =  $upload_dir['basedir']. '/wpo/images/wpo_logo_small.webp';

		$converters = array(
			// 'cwebp',
			'vips',
			'imagemagick',
			'graphicsmagick',
			'ffmpeg',
			'wpc',
			'ewww',
			'imagick',
			'gmagick',
			'gd',
		);
		$working_converters = array();
		$errors = array();

		foreach ($converters as $converter) {
			$converter_id = $converter;
			try {
				$converter_instance = ConverterFactory::makeConverter(
					$converter_id,
					$source,
					$destination
				);
				$converter_instance->doConvert();
				$working_converters[] = $converter_id;
			} catch (\Exception $e) {
				$errors[$converter_id] = $e->getMessage();
			}
		}

		return array(
			'working_converters' => $working_converters,
			'errors' => $errors,
		);
	}
}

endif;
