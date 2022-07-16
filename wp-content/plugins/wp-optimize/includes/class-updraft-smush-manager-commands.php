<?php
/**
 *  A Smush Task manager class
 */

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Updraft_Task_Manager_Commands_1_0')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-manager-commands.php');

if (!class_exists('Updraft_Smush_Manager_Commands')) :

class Updraft_Smush_Manager_Commands extends Updraft_Task_Manager_Commands_1_0 {

	/**
	 * The commands constructor
	 *
	 * @param mixed $task_manager - A task manager instance
	 */
	public function __construct($task_manager) {
		parent::__construct($task_manager);
	}

	/**
	 * Returns a list of commands available for smush related operations
	 */
	public static function get_allowed_ajax_commands() {

		$commands = apply_filters('updraft_task_manager_allowed_ajax_commands', array());

		$smush_commands = array(
			'compress_single_image',
			'restore_single_image',
			'process_bulk_smush',
			'update_smush_options',
			'get_ui_update',
			'process_pending_images',
			'clear_pending_images',
			'clear_smush_stats',
			'check_server_status',
			'get_smush_logs',
			'mark_as_compressed',
			'mark_all_as_uncompressed',
			'clean_all_backup_images',
			'reset_webp_serving_method',
		);

		return array_merge($commands, $smush_commands);
	}

	/**
	 * Process the compression of a single image
	 *
	 * @param mixed $data - sent in via AJAX
	 * @return WP_Error|array - information about the operation or WP_Error object on failure
	 */
	public function compress_single_image($data) {

		$options = !empty($data['smush_options']) ? $data['smush_options'] : $this->task_manager->get_smush_options();
		$image = isset($data['selected_image']) ? filter_var($data['selected_image']['attachment_id'], FILTER_SANITIZE_NUMBER_INT) : false;
		$blog = isset($data['selected_image']) ? filter_var($data['selected_image']['blog_id'], FILTER_SANITIZE_NUMBER_INT) : false;

		// A subsite administrator can only compress their own image. If the blog ID isn't theirs, return an error.
		if ($blog && is_multisite() && get_current_blog_id() != $blog && !current_user_can('manage_network_options')) {
			return new WP_Error('compression_not_permitted', __('The blog ID provided does not match the current blog.', 'wp-optimize'));
		}

		$server = sanitize_text_field($options['compression_server']);

		$lossy = filter_var($options['lossy_compression'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$backup = filter_var($options['back_up_original'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$exif = filter_var($options['preserve_exif'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$quality = filter_var($options['image_quality'], FILTER_SANITIZE_NUMBER_INT);

		$options = array(
			'attachment_id' 	=> $image,
			'blog_id'		   => $blog,
			'image_quality' 	=> $quality,
			'keep_original'		=> $backup,
			'lossy_compression' => $lossy,
			'preserve_exif'	 => $exif
		);

		if (filesize(get_attached_file($image)) > 5242880) {
			$options['request_timeout'] = 180;
		}

		$success = $this->task_manager->compress_single_image($image, $options, $server);

		if (!$success) {
			return new WP_Error('compress_failed', get_post_meta($image, 'smush-info', true));
		}

		$response = array();
		$response['status'] = true;
		$response['operation'] = 'compress';
		$response['options'] = $options;
		$response['server'] = $server;
		$response['success'] = $success;
		$response['restore_possible'] = $backup;
		$response['summary'] = get_post_meta($image, 'smush-info', true);

		$smush_stats = get_post_meta($image, 'smush-stats', true);
		if (isset($smush_stats['sizes-info'])) {
			$response['sizes-info'] = WP_Optimize()->include_template('images/smush-details.php', true, array('sizes_info' => $smush_stats['sizes-info']));
		}

		return $response;
	}

	/**
	 * Restores a single image, if backup is available
	 *
	 * @param mixed $data - Sent in via AJAX
	 * @return WP_Error|array - information about the operation or a WP_Error object on failure
	 */
	public function restore_single_image($data) {

		$blog_id = isset($data['blog_id']) ? $data['blog_id'] : false;
		$image_id   = isset($data['selected_image']) ? $data['selected_image'] : false;

		$success = $this->task_manager->restore_single_image($image_id, $blog_id);

		if (is_wp_error($success)) {
			return $success;
		}

		$response = array();
		$response['status'] = true;
		$response['operation'] = 'restore';
		$response['blog_id'] = $blog_id;
		$response['image']	 = $image_id;
		$response['success'] = $success;
		$response['summary'] = __('The image was restored successfully', 'wp-optimize');
		
		return $response;
	}

	/**
	 * Process the compression of multiple images
	 *
	 * @param mixed $data - Sent in via AJAX
	 */
	public function process_bulk_smush($data = array()) {
		$images = isset($data['selected_images']) ? $data['selected_images'] : array();

		$ui_update = $this->get_ui_update($images);
		$this->close_browser_connection(json_encode($ui_update));
		$this->task_manager->process_bulk_smush($images);
		// Since we already sent back data and closed the browser connection, we must not return (that would result in further sending back of JSON).
		die();
	}

	/**
	 * Returns useful information for the UI and closes the connection
	 *
	 * @param mixed $data - Sent in via AJAX
	 *
	 * @return mixed - Information for the UI
	 */
	public function get_ui_update($data) {
		$ui_update = array();
		$ui_update['status'] = true;
		$ui_update['is_multisite'] = is_multisite() ? 1 : 0;
		$pending_tasks = $this->task_manager->get_pending_tasks();
		
		$ui_update['pending_tasks'] = is_array($pending_tasks) ? count($this->task_manager->get_pending_tasks()) : 0;
		$ui_update['unsmushed_images'] = $this->task_manager->get_uncompressed_images();
		$ui_update['admin_urls'] = $this->task_manager->get_admin_urls();
		$ui_update['completed_task_count'] = $this->task_manager->options->get_option('completed_task_count', 0);
		$ui_update['bytes_saved'] = WP_Optimize()->format_size($this->task_manager->options->get_option('total_bytes_saved', 0));
		$ui_update['percent_saved'] = number_format($this->task_manager->options->get_option('total_percent_saved', 1), 2).'%';
		$ui_update['failed_task_count'] = $this->task_manager->get_failed_task_count();

		$ui_update['summary'] = sprintf(__("Since your compression statistics were last reset, a total of %d image(s) were compressed on this site, saving approximately %s of space at an average of %02d percent per image.", 'wp-optimize'), $ui_update['completed_task_count'], $ui_update['bytes_saved'], $ui_update['percent_saved']);
		$ui_update['failed'] = sprintf(__("%d image(s) could not be compressed. Please see the logs for more information, or try again later.", 'wp-optimize'), $ui_update['failed_task_count']);
		$ui_update['pending'] = sprintf(__("%d image(s) images were selected for compressing previously, but were not all processed. You can either complete them now or cancel and retry later.", 'wp-optimize'), $ui_update['pending_tasks']);
		$ui_update['smush_complete'] = $this->task_manager->is_queue_processed();
		
		if (isset($data['image_list'])) {
			$images = $data['image_list'];
			$stats = $this->task_manager->get_session_stats($images);
			$ui_update['session_stats'] = "";

			if (!empty($stats['success'])) {
			$ui_update['session_stats'] .= sprintf(__("A total of %d image(s) were successfully compressed in this iteration. ", 'wp-optimize'), $stats['success']);
			}

			if (!empty($stats['fail'])) {
				$ui_update['session_stats'] .= sprintf(__("%d selected image(s) could not be compressed. Please see the logs for more information, you may try again later.", 'wp-optimize'), $stats['fail']);
			}
		}
		
		return $ui_update;

	}

	/**
	 * Updates smush related options
	 *
	 * @param mixed $data - Sent in via AJAX
	 * @return WP_Error|array - information about the operation or WP_Error object on failure
	 */
	public function update_smush_options($data) {
		$options = array();
		$options['compression_server'] = sanitize_text_field($data['compression_server']);
		$options['lossy_compression'] = filter_var($data['lossy_compression'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$options['back_up_original'] = filter_var($data['back_up_original'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$options['back_up_delete_after'] = filter_var($data['back_up_delete_after'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$options['back_up_delete_after_days'] = filter_var($data['back_up_delete_after_days'], FILTER_SANITIZE_NUMBER_INT);
		$options['preserve_exif'] = filter_var($data['preserve_exif'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$options['autosmush'] = filter_var($data['autosmush'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$options['image_quality'] = filter_var($data['image_quality'], FILTER_SANITIZE_NUMBER_INT);
		$options['show_smush_metabox'] = filter_var($data['show_smush_metabox'], FILTER_VALIDATE_BOOLEAN) ? 'show' : 'hide';
		$options['webp_conversion'] = filter_var($data['webp_conversion'], FILTER_VALIDATE_BOOLEAN) ? true : false;
		$is_webp_conversion_enabled = $options['webp_conversion'] ? 'true' : 'false';
		WP_Optimize()->log("WebP conversion is enabled? $is_webp_conversion_enabled");
		$options['webp_converters'] = false;

		$success = $this->task_manager->update_smush_options($options);

		if (!$success) {
			return new WP_Error('update_failed', __('Options could not be updated', 'wp-optimize'));
		}

		do_action('wpo_save_images_settings');

		$response = array();
		$response['status'] = true;
		$response['saved'] = $success;
		$response['summary'] = __('Options updated successfully', 'wp-optimize');
		
		return $response;
	}

	/**
	 * Clears any smush related stats
	 *
	 * @return WP_Error|array - information about the operation or WP_Error object on failure
	 */
	public function clear_smush_stats() {

		$success = $this->task_manager->clear_smush_stats();

		if (!$success) {
			return new WP_Error('update_failed', __('Stats could not be cleared', 'wp-optimize'));
		}

		$response = array();
		$response['status'] = true;
		$response['summary'] = __('Stats cleared successfully', 'wp-optimize');

		return $response;
	}

	/**
	 * Checks if the selected server is online
	 *
	 * @param mixed $data - Sent in via AJAX
	 */
	public function check_server_status($data) {
		$server = sanitize_text_field($data['server']);
		$response = array();
		$response['status'] = true;
		$response['online'] = $this->task_manager->check_server_online($server);
		
		if (!$response['online']) {
			$response['error'] = get_option($this->task_manager->get_associated_task($server));
		}

		return $response;
	}

	/**
	 * Completes any pending tasks
	 */
	public function process_pending_images() {
		$this->process_bulk_smush();
	}

	/**
	 * Deletes and removes any pending tasks from queue
	 *
	 * @return WP_Error|array - information about the operation or WP_Error object on failure
	 */
	public function clear_pending_images() {

		$success = $this->task_manager->clear_pending_images();

		if (!$success) {
			return new WP_Error('error_deleting_tasks', __('Pending tasks could not be cleared', 'wp-optimize'));
		}

		$response = array();
		$response['status'] = true;
		$response['summary'] = __('Pending tasks cleared successfully', 'wp-optimize');
		
		return $response;
	}

	/**
	 * Mark selected images as already compressed.
	 *
	 * @param array $data
	 * @return array
	 */
	public function mark_as_compressed($data) {
		$response = array();
		$selected_images = array();

		$unmark = isset($data['unmark']) && $data['unmark'];

		foreach ($data['selected_images'] as $image) {
			if (!array_key_exists($image['blog_id'], $selected_images)) $selected_images[$image['blog_id']] = array();

			$selected_images[$image['blog_id']][] = $image['attachment_id'];
		}

		$info = __('This image is marked as already compressed by another tool.', 'wp-optimize');

		foreach (array_keys($selected_images) as $blog_id) {
			if (is_multisite()) switch_to_blog($blog_id);

			foreach ($selected_images[$blog_id] as $attachment_id) {
				if ($unmark) {
					delete_post_meta($attachment_id, 'smush-complete');
					delete_post_meta($attachment_id, 'smush-marked');
					delete_post_meta($attachment_id, 'smush-info');
				} else {
					update_post_meta($attachment_id, 'smush-complete', true);
					update_post_meta($attachment_id, 'smush-marked', true);
					update_post_meta($attachment_id, 'smush-info', $info);
				}
			}

			if (is_multisite()) restore_current_blog();
		}

		$response['status'] = true;

		if ($unmark) {
			$response['summary'] = _n('The selected image was successfully marked as uncompressed', 'The selected images were successfully marked as uncompressed', count($data['selected_images']), 'wp-optimize');
		} else {
			$response['summary'] = _n('The selected image was successfully marked as compressed', 'The selected images were successfully marked as compressed', count($data['selected_images']), 'wp-optimize');
		}

		$response['info'] = $info;

		return $response;
	}

	/**
	 * Mark all images as uncompressed and if posted restore_backup argument
	 * then try to restore images form backup.
	 *
	 * @param array $data
	 * @return array
	 */
	public function mark_all_as_uncompressed($data) {

		$restore_backup = isset($data['restore_backup']) && $data['restore_backup'];
		$images_per_request = apply_filters('mark_all_as_uncompressed_images_per_request', 100);
		$delete_only_backups_meta = isset($data['delete_only_backups_meta']) && $data['delete_only_backups_meta'];

		if (is_multisite()) {
			// option where we store last completed blog id
			$option_name = 'mark_as_uncompressed_last_blog_id';
			// set default value for response
			$response = array(
				'completed' => true,
				'message' => __('All the compressed images were successfully restored.', 'wp-optimize'),
			);

			// get all blogs ids
			$blogs = WP_Optimize()->get_sites();
			$blogs_ids = wp_list_pluck($blogs, 'blog_id');
			sort($blogs_ids);

			// select the blog for processing
			$last_completed_blog_id = $this->task_manager->options->get_option($option_name, false);
			$index = $last_completed_blog_id ? array_search($last_completed_blog_id, $blogs_ids) + 1 : 0;

			if ($index < count($blogs_ids)) {
				$blog_id = $blogs_ids[$index];
				$response = $this->task_manager->bulk_restore_compressed_images($restore_backup, $blog_id, $images_per_request, $delete_only_backups_meta);

				// if we get completed the current blog then update last completed blog option value
				// and if we have other blogs for processing then set complete to false as we have not
				// processed all blogs
				if ($response['completed']) {
					if ($index + 1 < count($blogs_ids)) {
						$response['completed'] = false;
					} else {
						if ($delete_only_backups_meta) {
							$response['message'] = __('All the compressed images were successfully restored.', 'wp-optimize');
						} else {
							$response['message'] = __('All the compressed images were successfully marked as uncompressed.', 'wp-optimize');
						}
					}
					$this->task_manager->options->update_option($option_name, $blog_id);
				}
			}

			// if we get an error or completed the work then delete option with last completed blog id.
			if ($response['completed'] || isset($response['error'])) {
				$this->task_manager->options->delete_option($option_name);
			}
		} else {
			$response = $this->task_manager->bulk_restore_compressed_images($restore_backup, 0, $images_per_request, $delete_only_backups_meta);
		}

		return $response;
	}

	/**
	 * Returns the log file
	 *
	 * @return WP_Error|file - logfile or WP_Error object on failure
	 */
	public function get_smush_logs() {

		$logfile = $this->task_manager->get_logfile_path();

		if (!file_exists($logfile)) {
			 $this->task_manager->write_log_header();
		}

		if (is_file($logfile)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename($logfile).'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($logfile));
			readfile($logfile);
			exit;
		} else {
			return new WP_Error('log_file_error', __('Log file does not exist or could not be read', 'wp-optimize'));
		}
	}

	/**
	 * Clean all backup images command.
	 *
	 * @return array
	 */
	public function clean_all_backup_images() {
		$upload_dir = wp_upload_dir(null, false);
		$base_dir = $upload_dir['basedir'];

		$this->task_manager->clear_backup_images_directory($base_dir, 0);

		return array(
			'status' => true,
		);
	}

	/**
	 * Close browser connection so that it can resume AJAX polling
	 *
	 * @param array $txt Response to browser; this must be JSON (or if not, alter the Content-Type header handling below)
	 * @return void
	 */
	public function close_browser_connection($txt = '') {
		header('Content-Length: '.((!empty($txt)) ? 4+strlen($txt) : '0'));
		header('Content-Type: application/json');
		header('Connection: close');
		header('Content-Encoding: none');
		if (session_id()) session_write_close();
		echo "\r\n\r\n";
		echo $txt;

		$levels = ob_get_level();
		
		for ($i = 0; $i < $levels; $i++) {
			ob_end_flush();
		}

		flush();
		
		if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
	}

	/**
	 * Resets webp serving method
	 *
	 * @return array
	 */
	public function reset_webp_serving_method() {
		$success = $this->task_manager->reset_webp_serving_method();
		return array(
			'success' => $success,
		);
	}
}

endif;
