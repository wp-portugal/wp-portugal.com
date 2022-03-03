<?php
/**
 *  A sample implementation using the Resmush.it API and our tasks library
 */

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Updraft_Task_1_2')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task.php');

if (!class_exists('Updraft_Smush_Task')) :

abstract class Updraft_Smush_Task extends Updraft_Task_1_2 {

	/**
	 * A flag indicating if the operation was succesful
	 *
	 * @var bool
	 */
	protected $success = false;

	/**
	 * A text descriptor describing the stage of the task
	 *
	 * @var string
	 */
	protected $stage;

	/**
	 * Initialise the task
	 *
	 * @param Array $options - options to use
	 */
	public function initialise($options = array()) {
		parent::initialise($options);
		$this->set_current_stage('initialised');
		do_action('ud_task_initialised', $this);
	}

	/**
	 * Runs the task
	 *
	 * @return bool - true if complete, false otherwise
	 */
	public function run() {

		$this->set_status('active');
		
		do_action('ud_task_started', $this);

		$attachment_id	= $this->get_option('attachment_id');

		if (is_multisite()) {
			switch_to_blog($this->get_option('blog_id', 1));
			$file_path = get_attached_file($attachment_id);
			restore_current_blog();
		} else {
			$file_path = get_attached_file($attachment_id);
		}

		if (!$this->validate_file($file_path)) return false;

		$api_endpoint = $this->get_option('api_endpoint');

		if (false === filter_var($api_endpoint, FILTER_VALIDATE_URL)) {
			$this->fail('invalid_api_url', "The API endpoint supplied {$api_endpoint} is invalid");
			return false;
		}

		$original_image = $file_path;
		$backup_original_image = $this->get_option('keep_original', true);

		// add possibility to exclude certain image sizes from smush.
		$dont_smush_sizes = apply_filters('wpo_dont_smush_sizes', array());

		$this->update_option('original_filesize', filesize($file_path));

		// build list of files for smush.
		$files = array_merge(array('full' => $file_path), $this->get_attachment_files($attachment_id));

		$sizes_info = array();

		foreach ($files as $size => $file_path) {

			if (in_array($size, $dont_smush_sizes)) continue;

			$file_size = filesize($file_path);

			if ($file_size > 5242880) {
				$this->update_option('request_timeout', 180);
			}

			$this->log($this->get_description());

			if (defined('WPO_USE_WEBP_CONVERSION') && true === WPO_USE_WEBP_CONVERSION) {
				$this->maybe_do_webp_conversion($file_path);
			}
			
			/**
			 * Filters the options for a single image to compress.
			 * Currently supports:
			 * - 'quality': Will use the image quality set in this filter, instead of the one defined in the settings.
			 *
			 * @param array   $options       - The options (default: empty array)
			 * @param integer $attachment_id - The attachment post ID
			 * @param string  $file_path     - The path to the file being compressed
			 * @param string  $size          - The size name (e.g. 'thumbnail')
			 */
			$options = apply_filters('wpo_image_compression_single_image_options', array(), $attachment_id, $file_path, $size);

			$post_data = $this->prepare_post_request($file_path, $options);

			$response = $this->post_to_remote_server($api_endpoint, $post_data);
			$optimised_image = $this->process_server_response($response);

			if ($optimised_image) {
				$backup_image = ($original_image == $file_path) ? $backup_original_image : false;
				$this->save_optimised_image($file_path, $optimised_image, $backup_image);

				clearstatcache($file_path);

				$sizes_info[$size] = array(
					'original' => $file_size,
					'compressed' => filesize($file_path),
				);
			}
			
		}

		$this->update_option('smush-sizes-info', $sizes_info);

		return $this->success;
	}

	/**
	 * Converts to WebP format, if possible
	 *
	 * @param string $source Source image file path
	 */
	public function maybe_do_webp_conversion($source) {
		$webp_conversion = WP_Optimize()->get_options()->get_option('webp_conversion', false);
		if (!empty($webp_conversion)) {
			if (!class_exists('WPO_WebP_Convert')) include_once(WPO_PLUGIN_MAIN_PATH . 'webp/class-wpo-webp-convert.php');
			$webp_converter = new WPO_WebP_Convert();
			$webp_converter->convert($source);
		} else {
			$this->log('There were no WebP conversion tools found on your server.');
		}
	}

	/**
	 * Posts the supplied data to the API url and returns a response
	 *
	 * @param String $api_endpoint - the url to post the form to
	 * @param String $post_data	   - the post data as specified by the server
	 * @return mixed - the response
	 */
	public function post_to_remote_server($api_endpoint, $post_data) {

		$this->set_current_stage('connecting');
		$response = wp_remote_post($api_endpoint, $post_data);
		
		if (is_wp_error($response)) {
			$this->fail($response->get_error_code(), $response->get_error_message());
			return false;
		}

		return $response;
	}

	/**
	 * Processes the response recieved from the remote server
	 *
	 * @param mixed $response - the response object
	 * @return mixed - the response
	 */
	public function process_server_response($response) {
		$this->set_current_stage('processing_response');
		return $response;
	}

	/**
	 * Checks if a file is valid and capable of being smushed
	 *
	 * @param String $file_path - the path of the original image
	 * @return bool - true on success, false otherwise
	 */
	public function validate_file($file_path) {

		$allowed_file_types = $this->get_option('allowed_file_types');

		if (!file_exists($file_path)) {
			$this->fail("invalid_file_path", "The linked attachment ID does not have a valid file path");
			return false;
		}

		if (filesize($file_path) > $this->get_option('max_filesize')) {
			$this->fail("exceeded_max_filesize", "$file_path - cannot be optimized, file size is above service provider limit");
			return false;
		}

		if (!in_array(strtolower(pathinfo($file_path, PATHINFO_EXTENSION)), $allowed_file_types)) {
			$this->fail("invalid_file_type", "$file_path - cannot be optimized, it has an invalid file type");
			return false;
		}

		return true;
	}

	/**
	 * Creates a backup of the original image
	 *
	 * @param String $file_path - the path of the original image
	 * @return bool - true on success, false otherwise
	 */
	public function backup_original_image($file_path) {
				
		$this->set_current_stage('backup_original');

		if (is_multisite()) {
			switch_to_blog($this->get_option('blog_id', 1));
		}

		$file = pathinfo($file_path);
		$back_up = wp_normalize_path($file['dirname'].'/'.basename($file['filename'].$this->get_option('backup_prefix').$file['extension']));
		$uploads_dir = wp_upload_dir();

		// Make path relative and safe for migrations
		$back_up_relative_path = preg_replace('#^'.wp_normalize_path($uploads_dir['basedir'].'/').'#', '', $back_up);

		update_post_meta($this->get_option('attachment_id'), 'original-file', $back_up_relative_path);

		if (is_multisite()) {
			restore_current_blog();
		}

		$this->log("Backing up the original image - {$back_up_relative_path}");

		return copy($file_path, $back_up);
	}

	/**
	 * Creates a backup of the original image
	 *
	 * @param String $file_path 	  - the path of the original image
	 * @param Mixes  $optimised_image - the contents of the image
	 * @param bool   $backup_original - backup original image
	 *
	 * @return bool - true on success, false otherwise
	 */
	private function save_optimised_image($file_path, $optimised_image, $backup_original) {
		
		$this->set_current_stage('saving_image');

		if ($backup_original)
			$this->backup_original_image($file_path);

		if (false !== file_put_contents($file_path, $optimised_image)) {
			$this->success = true;
		} else {
			$this->success = false;
		}

		return $this->success;
	}

	/**
	 * Fires if the task succeds, any clean up code and logging goes here
	 */
	public function complete() {

		$attachment_id	= $this->get_option('attachment_id');

		if (is_multisite()) {
			switch_to_blog($this->get_option('blog_id', 1));
			$file_path = get_attached_file($attachment_id);
			restore_current_blog();
		} else {
			$file_path = get_attached_file($attachment_id);
		}

		$original_size = $this->get_option('original_filesize');
		$this->set_current_stage('completed');

		clearstatcache(true, $file_path); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.clearstatcache_clear_realpath_cacheFound,PHPCompatibility.FunctionUse.NewFunctionParameters.clearstatcache_filenameFound
		if (0 == $original_size) {
			$saved = '';
			$info = sprintf(__("The file was compressed to %s using WP-Optimize", 'wp-optimize'), WP_Optimize()->format_size(filesize($file_path)));
		} else {
			$saved = round((($original_size - filesize($file_path)) / $original_size * 100), 2);
			$info = sprintf(__("The file was compressed from %s to %s saving %s percent using WP-Optimize", 'wp-optimize'), WP_Optimize()->format_size($original_size), WP_Optimize()->format_size(filesize($file_path)), $saved);
		}

		$stats = array(
			'smushed-with'  	=> $this->label,
			'original-size' 	=> $original_size,
			'smushed-size'		=> filesize($file_path),
			'savings-percent' 	=> $saved,
			'sizes-info'		=> $this->get_option('smush-sizes-info'),
		);

		if (is_multisite()) {
			switch_to_blog($this->get_option('blog_id', 1));
			update_post_meta($attachment_id, 'smush-complete', true);
			update_post_meta($attachment_id, 'smush-info', $info);
			update_post_meta($attachment_id, 'smush-stats', $stats);
			restore_current_blog();
		} else {
			update_post_meta($attachment_id, 'smush-complete', true);
			update_post_meta($attachment_id, 'smush-info', $info);
			update_post_meta($attachment_id, 'smush-stats', $stats);
		}

		$this->log("Successfully optimized the image - {$file_path}." . $info);
		$this->set_status('complete');

		return parent::complete();
	}

	/**
	 * Fires if the task fails, any clean up code and logging goes here
	 *
	 * @param String $error_code	- A code for the failure
	 * @param String $error_message - A description for the failure
	 */
	public function fail($error_code = "Unknown", $error_message = "Unknown") {

		$attachment_id = $this->get_option('attachment_id');

		$info = sprintf(__("Failed with error code %s - %s", 'wp-optimize'), $error_code, $error_message);

		if (is_multisite()) {
			switch_to_blog($this->get_option('blog_id', 1));
			update_post_meta($attachment_id, 'smush-info', $info);
			update_post_meta($attachment_id, 'smush-complete', false);
			restore_current_blog();
		} else {
			update_post_meta($attachment_id, 'smush-info', $info);
			update_post_meta($attachment_id, 'smush-complete', false);
		}


		do_action('ud_smush_task_failed', $this, $error_code, $error_message);

		return parent::fail($error_code, $error_message);
	}
	
	/**
	 * Get all the supported task stages.
	 *
	 * @return array - list of task stages.
	 */
	public function get_allowed_stages() {
		
		$stages = array(
			'initialised' => __('Initialised', 'wp-optimize'),
			'connecting'   => __('Connecting to API server', 'wp-optimize'),
			'processing_response' => __('Processing response', 'wp-optimize'),
			'backup_original' => __('Backing up original image', 'wp-optimize'),
			'saving_image' => __('Saving optimized image', 'wp-optimize'),
			'completed' => __('Successful', 'wp-optimize'),
		);

		return apply_filters('allowed_task_stages', $stages);
	}

	/**
	 * Get features available with this service
	 *
	 * @return Array - an array of features
	 */
	public static function get_features() {
		return array(
			'max_filesize' => self::MAX_FILESIZE,
			'lossy_compression' => true,
			'preserve_exif' => true,
		);
	}

	/**
	 * Retrieve default options for this task.
	 * This method should normally be over-ridden by the child.
	 *
	 * @return Array - an array of options
	 */
	public function get_default_options() {

		return array(
			'allowed_file_types' => array('gif', 'png', 'jpg', 'tif', 'jpeg'),
			'request_timeout' => 15,
			'image_quality' => 90,
			'backup_prefix' => '-updraft-pre-smush-original.'
		);
	}

	/**
	 * Sets the task stage.
	 *
	 * @param String $stage - the current stage of the task
	 * @return bool - the result of the  update
	 */
	public function set_current_stage($stage) {
		
		if (array_key_exists($stage, self::get_allowed_stages())) {
			$this->stage = $stage;
			return $this->update_option('current_stage', $this->stage);
		}
		
		return false;
	}

	/**
	 * Gets the task stage
	 *
	 * @return String $stage - the current stage of the task
	 */
	public function get_current_stage() {
		if (isset($this->stage))
			return $this->stage;
		else return $this->get_option('current_stage');
	}

	/**
	 * Get image paths to resized attachment images.
	 *
	 * @param int $attachment_id
	 * @return array
	 */
	private function get_attachment_files($attachment_id) {
		$attachment_images = array();
		$upload_dir = function_exists('wp_get_upload_dir') ? wp_get_upload_dir() : wp_upload_dir(null, false);

		// get sizes info from attachment meta data.
		$meta = wp_get_attachment_metadata($attachment_id);
		if (!is_array($meta) || !array_key_exists('sizes', $meta)) return $attachment_images;

		$image_sizes = array_keys($meta['sizes']);

		// build list of resized images.
		foreach ($image_sizes as $size) {
			$image = image_get_intermediate_size($attachment_id, $size);

			if (is_array($image)) {
				$file = trailingslashit($upload_dir['basedir']) . $image['path'];
				if (is_file($file) && !in_array($file, $attachment_images)) {
					$attachment_images[$size] = $file;
				}
			}
		}

		return $attachment_images;
	}

	/**
	 * Check the mime type of a downloaded file, returns true if it is a valid image mime type.
	 *
	 * @param string $file_buffer The buffer string downloaded from the compression service
	 * @return boolean
	 */
	protected function is_downloaded_image_buffer_mime_type_valid($file_buffer) {
		// If the required class does not exist, return true to avoid breaking the functionality
		if (!class_exists('finfo')) return true;
		$accepted_types = apply_filters('wpo_image_compression_accepted_mime_types', array('image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'));
		// The ignore rule below is added because "finfo" doesn't exist in PHP5.2.
		$finfo = new finfo(FILEINFO_MIME_TYPE); // phpcs:ignore PHPCompatibility.Classes.NewClasses.finfoFound, PHPCompatibility.Constants.NewConstants.fileinfo_mime_typeFound
		$mime_type = $finfo->buffer($file_buffer);
		return in_array($mime_type, $accepted_types);
	}
}
endif;
