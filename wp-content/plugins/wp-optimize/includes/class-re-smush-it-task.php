<?php
/**
 *  A sample implementation using the Resmush.it API and our tasks library
 */

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Re_Smush_It_Task')) :

class Re_Smush_It_Task extends Updraft_Smush_Task {

	public $label = 're-smush-it';

	const MAX_FILESIZE = 5242880;

	const API_URL = 'http://api.resmush.it/';

	/**
	 * Checks if the server is online
	 *
	 * @return boolean - true if yes, false otherwise
	 */
	public static function is_server_online() {
		
		global $wp_version;
		$test_image = WPO_PLUGIN_MAIN_PATH . 'images/icon/wpo.png';
		$boundary = wp_generate_password(12);
		$file_name = basename($test_image);

		$body = "--$boundary";
		$body .= "\r\n";
		$body .= "Content-Disposition: form-data; name=\"files\"; filename=\"$file_name\"\r\n";
		$body .= "\r\n";
		$body .= file_get_contents($test_image);
		$body .= "\r\n";
		$body .= "--$boundary";

		$request = array(
			'headers' => array( "content-type" => "multipart/form-data; boundary=$boundary" ),
			'user-agent' => "WordPress $wp_version/WP-Optimize ".WPO_VERSION.' - anonymous', // Anonymous until Resmushit has a clear privacy statement that we can link to
			'timeout' => 10,
			'body' => $body,
		);

		$response = wp_remote_post(self::API_URL, $request);

		if (is_wp_error($response)) {
			update_option(__CLASS__, $response->get_error_message());
			return false;
		}

		$data = json_decode(wp_remote_retrieve_body($response));


		if (empty($data)) {
			update_option(__CLASS__, "Empty data returned by server");
			return false;
		}

		if (isset($data->error)) {
			update_option(__CLASS__, $data->error_long);
			return false;
		}

		return true;
	}
	
	/**
	 * Prepares the image as part of the post data for the specific implementation
	 *
	 * @param String $local_file - The image to e optimised
	 * @param array  $options    - Eventual options
	 */
	public function prepare_post_request($local_file, $options) {
		global $wp_version;
		
		$boundary = wp_generate_password(12);
		$headers  = array( "content-type" => "multipart/form-data; boundary=$boundary" );

		$lossy = $this->get_option('lossy_compression');

		if ($lossy) {
			$quality = $this->get_option('image_quality');
		} else {
			$quality = 100;
		}

		if (isset($options['quality']) && is_int($options['quality']) && 0 < $options['quality']) $quality = $options['quality'];

		$this->log($quality);
		$post_fields = array(
			'qlty' => $quality,
			'exif' => $this->get_option('preserve_exif', false)
		);
		$payload = '';
		$file_name = basename($local_file);
		
		foreach ($post_fields as $name => $value) {
			$payload .= "--$boundary";
			$payload .= "\r\n";
			$payload .= "Content-Disposition: form-data; name='$name' \r\n\r\n $value";
			$payload .= "\r\n";
		}

		$payload .= "--$boundary";
		$payload .= "\r\n";
		$payload .= "Content-Disposition: form-data; name=\"files\"; filename=\"$file_name\"\r\n";
		$payload .= "\r\n";
		$payload .= file_get_contents($local_file);
		$payload .= "\r\n";
		$payload .= "--$boundary";

		return array(
			'headers' => $headers,
			'timeout' => $this->get_option('request_timeout'),
			'user-agent' => "WordPress $wp_version/WP-Optimize ".WPO_VERSION.' - anonymous', // Anonymous until Resmushit has a clear privacy statement that we can link to
			'body' => $payload,
		);
	}

	/**
	 * Processes the response recieved from the remote server
	 *
	 * @param String $response - The response object
	 */
	public function process_server_response($response) {
		global $http_response_header;

		$response = parent::process_server_response($response);
		$data = json_decode(wp_remote_retrieve_body($response));

		if (!$data) {
			$this->log("Cannot establish connection with reSmush.it webservice. Please try later");
			return false;
		}

		if (isset($data->error)) {
			$this->fail($data->error, $data->error_long);
			return false;
		}

		if (!property_exists($data, 'dest')) {
			$this->fail("invalid_response", "The response does not contain the compressed file URL");
			$this->log("data: ".json_encode($data));
			return false;
		}

		$compressed_image_response = wp_remote_get($data->dest);

		if (!is_wp_error($compressed_image_response)) {
			$image_contents = wp_remote_retrieve_body($compressed_image_response);
			if ($this->is_downloaded_image_buffer_mime_type_valid($image_contents)) {
				return $image_contents;
			} else {
				$this->log("The downloaded resource does not have a matching mime type.");
				return false;
			}
		} else {
			$this->fail("invalid_response", "The compression apparently succeeded, but WP-Optimize could not retrieve the compressed image from the remote server.");
			$this->log("data: ".json_encode($data));
			if (!empty($http_response_header) && is_array($http_response_header)) {
				$this->log("headers: ".implode("\n", $http_response_header));
			}
			return false;
		}
	}

	/**
	 * Retrieve features for this service
	 *
	 * @return Array - an array of options
	 */
	public static function get_features() {
		return array(
			'max_filesize' => self::MAX_FILESIZE,
			'lossy_compression' => false,
			'preserve_exif' => true,
		);
	}

	/**
	 * Retrieve default options for this task type.
	 *
	 * @return Array - an array of options
	 */
	public function get_default_options() {
		return array(
			'allowed_file_types' => array('gif', 'png', 'jpg', 'tif', 'jpeg'),
			'request_timeout' => 30,
			'keep_original' => true,
			'preserve_exif' => false,
			'image_quality' => 98,
			'api_endpoint' => self::API_URL,
			'max_filesize' => self::MAX_FILESIZE,
			'version' => '0.1.13',
			'backup_prefix' => '-updraft-pre-smush-original.'
		);
	}
}
endif;
