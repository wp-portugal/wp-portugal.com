<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

if (!class_exists('WPO_WebP_Self_Test')) :

class WPO_WebP_Self_Test {

	/**
	 * Decided whether we can get a webp image or not
	 *
	 * @return bool
	 */
	public function get_webp_image() {
		$args = array(
			'headers' => array(
				'accept' => 'image/webp'
			)
		);

		$upload_dir = wp_upload_dir();
		$url =  $upload_dir['baseurl']. '/wpo/images/wpo_logo_small.png.webp';

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) return false;
		if (200 != $response['response']['code']) return false;

		$headers = wp_remote_retrieve_headers($response);
		if (is_a($headers, 'Requests_Utility_CaseInsensitiveDictionary')) {
			$headers = $headers->getAll();
			if (isset($headers['content-type']) && 'image/webp' == $headers['content-type']) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Decided whether webp version is served or not
	 *
	 * @return bool
	 */
	public function is_webp_served() {
		$args = array(
			'headers' => array(
				'accept' => 'image/webp'
			)
		);

		$upload_dir = wp_upload_dir();
		$url =  $upload_dir['baseurl']. '/wpo/images/wpo_logo_small.png';

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) return false;
		if (200 != $response['response']['code']) return false;

		$headers = wp_remote_retrieve_headers($response);
		if (is_a($headers, 'Requests_Utility_CaseInsensitiveDictionary')) {
			$headers = $headers->getAll();
			if (isset($headers['content-type']) && 'image/webp' == $headers['content-type']) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Returns singleton instance
	 *
	 * @return WPO_WebP_Self_Test
	 */
	public static function get_instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}
}

endif;
