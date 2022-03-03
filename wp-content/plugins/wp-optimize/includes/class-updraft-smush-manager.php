<?php
/**
 *  Extends the generic task manager to manage smush related queues
 */

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Updraft_Task_Manager_1_3')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-manager.php');

if (!class_exists('Updraft_Smush_Manager')) :

class Updraft_Smush_Manager extends Updraft_Task_Manager_1_3 {

	static protected $_instance = null;

	/**
	 * Options used for smush jobs
	 *
	 * @var array
	 */
	public $options;

	/**
	 * The service provider to use
	 *
	 * @var string
	 */
	public $webservice;

	/**
	 * The logger for this instance
	 *
	 * @var mixed
	 */
	public $logger;

	/**
	 * Require task-level locking
	 *
	 * @var integer
	 */
	protected $use_per_task_lock = 60;
	
	/**
	 * The Task Manager constructor
	 */
	public function __construct() {
		parent::__construct();

		if (!class_exists('Updraft_Smush_Manager_Commands')) include_once('class-updraft-smush-manager-commands.php');
		if (!class_exists('Updraft_Smush_Task')) include_once('class-updraft-smush-task.php');
		if (!class_exists('Re_Smush_It_Task')) include_once('class-updraft-resmushit-task.php');
		if (!class_exists('Updraft_Logger_Interface')) include_once('class-updraft-logger-interface.php');
		if (!class_exists('Updraft_Abstract_Logger')) include_once('class-updraft-abstract-logger.php');
		if (!class_exists('Updraft_File_Logger')) include_once('class-updraft-file-logger.php');
		if (!class_exists('WP_Optimize_Transients_Cache')) include_once('class-wp-optimize-transients-cache.php');

		$this->commands = new Updraft_Smush_Manager_Commands($this);
		$this->options = WP_Optimize()->get_options();

		if (!isset($this->options)) {
			$this->set_default_options();
		}

		$this->webservice = $this->options->get_option('compression_server', 'resmushit');

		// Ensure the saved service is valid
		if (!in_array($this->webservice, $this->get_allowed_services())) {
			$this->webservice = $this->get_default_webservice();
		}
		$this->logger = new Updraft_File_Logger($this->get_logfile_path());
		$this->add_logger($this->logger);

		add_action('wp_ajax_updraft_smush_ajax', array($this, 'updraft_smush_ajax'));
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 9);
		add_action('elementor/editor/before_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
		add_action('add_attachment', array($this, 'autosmush_create_task'));
		add_action('ud_task_initialised', array($this, 'set_task_logger'));
		add_action('ud_task_started', array($this, 'set_task_logger'));
		add_action('ud_task_completed', array($this, 'record_stats'));
		add_action('ud_task_failed', array($this, 'record_stats'));
		add_action('prune_smush_logs', array($this, 'prune_smush_logs'));
		add_action('autosmush_process_queue', array($this, 'autosmush_process_queue'));
		if ('show' == $this->options->get_option('show_smush_metabox', 'show')) {
			add_action('add_meta_boxes_attachment', array($this, 'add_smush_metabox'), 10, 2);
			add_filter('attachment_fields_to_edit', array($this, 'add_compress_button_to_media_modal' ), 10, 2);
		}
		add_action('delete_attachment', array($this, 'unscheduled_original_file_deletion'));

		add_filter('manage_media_columns', array($this, 'manage_media_columns'));
		add_action('manage_media_custom_column', array($this, 'manage_media_custom_column'), 10, 2);

		// clean backup images cron action.
		add_action('wpo_smush_clear_backup_images', array($this, 'clear_backup_images'));

		// add filter for already compressed images by EWWW Image Optimizer.
		add_filter('wpo_get_uncompressed_images_args', array($this, 'ewww_image_optimizer_compressed_images_args'));

		if (!wp_next_scheduled('wpo_smush_clear_backup_images')) {
			wp_schedule_event(time(), 'daily', 'wpo_smush_clear_backup_images');
		}
	}

	/**
	 * Add custom column to Media Library.
	 *
	 * @param array $columns
	 * @return mixed
	 */
	public function manage_media_columns($columns) {
		$columns['wpo_smush'] = 'WP-Optimize';

		return $columns;
	}

	/**
	 * Display content in the custom column.
	 *
	 * @param string $column
	 * @param int    $attachment_id
	 */
	public function manage_media_custom_column($column, $attachment_id) {
		if ('wpo_smush' !== $column) return;

		if (!$this->is_compressed($attachment_id)) return;

		$smush_stats = get_post_meta($attachment_id, 'smush-stats', true);

		if (empty($smush_stats)) {
			_e('The file was either compressed using another tool or marked as compressed', 'wp-optimize');
			return;
		}

		$original_size = $smush_stats['original-size'];
		$smushed_size = $smush_stats['smushed-size'];

		if (0 == $original_size) {
			$info = sprintf(__('The file was compressed to %s using WP-Optimize', 'wp-optimize'), WP_Optimize()->format_size($smushed_size));
		} else {
			$saved = round((($original_size - $smushed_size) / $original_size * 100), 2);
			$info = sprintf(__('The file was compressed from %s to %s, saving %s percent, using WP-Optimize', 'wp-optimize'), WP_Optimize()->format_size($original_size), WP_Optimize()->format_size($smushed_size), $saved);
		}

		echo htmlentities($info);

		// Display additional information about resized images.
		if (!empty($smush_stats['sizes-info'])) {
			WP_Optimize()->include_template('images/smush-details.php', false, array('sizes_info' => $smush_stats['sizes-info']));
		}
	}

	/**
	 * The Task Manager AJAX handler
	 */
	public function updraft_smush_ajax() {

		$nonce = empty($_REQUEST['nonce']) ? '' : $_REQUEST['nonce'];

		if (!wp_verify_nonce($nonce, 'updraft-task-manager-ajax-nonce') || empty($_REQUEST['subaction']))
			die('Security check failed');

		$subaction = $_REQUEST['subaction'];

		$allowed_commands = Updraft_Smush_Manager_Commands::get_allowed_ajax_commands();
		
		if (in_array($subaction, $allowed_commands)) {

			if (isset($_REQUEST['data'])) {
				$data = $_REQUEST['data'];
				$results = call_user_func(array($this->commands, $subaction), $data);
			} else {
				$results = call_user_func(array($this->commands, $subaction));
			}
			
			if (is_wp_error($results)) {
				$results = array(
					'status' => true,
					'result' => false,
					'error_code' => $results->get_error_code(),
					'error_message' => $results->get_error_message(),
					'error_data' => $results->get_error_data(),
				);
			}
			
			echo json_encode($results);
		} else {
			echo json_encode(array('error' => 'No such command found'));
		}
		die;
	}

	/**
	 * Creates a task to auto compress an image on  upload
	 *
	 * @param int $post_id - id of the post
	 */
	public function autosmush_create_task($post_id) {

		$post = get_post($post_id);

		if (!$this->options->get_option('autosmush', false))
			return;

		if (!'image' == substr($post->post_mime_type, 0, 5))
			return;

		if ($this->task_exists($post_id))
			return;
		
		$options = array(
			'attachment_id' => $post_id,
			'blog_id'	   => get_current_blog_id(),
			'image_quality' => $this->options->get_option('image_quality', 96),
			'keep_original' => $this->options->get_option('back_up_original', true),
			'preserve_exif' => $this->options->get_option('preserve_exif', true),
			'lossy_compression' => $this->options->get_option('lossy_compression', false)
		);

		if (filesize(get_attached_file($post_id)) > 5242880) {
			$options['request_timeout'] = 180;
		}

		$server = $this->options->get_option('compression_server', $this->webservice);
		$task_name = $this->get_associated_task($server);

		$description = "$task_name with attachment ID : ".$post_id.", autocreated on : ".date("F d, Y h:i:s", time());
		$task = call_user_func(array($task_name, 'create_task'), 'smush', $description, $options, $task_name);
		
		if ($task) $task->add_logger($this->logger);
		$this->log($description);

		if (!wp_next_scheduled('autosmush_process_queue')) {
			wp_schedule_single_event(time() + 300, 'autosmush_process_queue');
		}
	}

	/**
	 * Process the autosmush queue and sets up a cron job if needed
	 * for future processing
	 */
	public function autosmush_process_queue() {
		
		if (!wp_next_scheduled('autosmush_process_queue') && !$this->is_queue_processed()) {
			wp_schedule_single_event(time() + 600, 'autosmush_process_queue');
		}

		$this->write_log_header();
		$this->clear_cached_data();
		$this->process_queue('smush');

		if ($this->is_queue_processed()) {
			$this->clean_up_old_tasks('smush');
		}

	}


	/**
	 * Process the compression of a single image
	 *
	 * @param int	 $image   - ID of image
	 * @param array  $options - options to use
	 * @param string $server  - the server to process with
	 *
	 * @return boolean - Status of the task
	 */
	public function compress_single_image($image, $options, $server) {
		$task_name = $this->get_associated_task($server);
		$description = "$task_name - attachment ID : ". $image. ", started on : ". date("F d, Y h:i:s", time());

		$task = call_user_func(array($task_name, 'create_task'), 'smush', $description, $options, $task_name);
		$task->add_logger($this->logger);
		$this->clear_cached_data();

		if (!wp_next_scheduled('prune_smush_logs')) {
			wp_schedule_single_event(time() + 7200, 'prune_smush_logs');
		}

		return $this->process_task($task);
	}

	/**
	 * Restores a single image if a backup is available
	 *
	 * @param int $image_id - The id of the image
	 * @param int $blog_id  - The id of the blog
	 *
	 * @return bool|WP_Error - success or failure
	 */
	public function restore_single_image($image_id, $blog_id) {

		$switched_blog = false;
		if (is_multisite() && current_user_can('manage_network_options')) {
			switch_to_blog($blog_id);
			$switched_blog = true;
		} elseif (is_multisite() && get_current_blog_id() != $blog_id) {
			return new WP_Error('restore_backup_wrong_blog_id', __('The blog ID provided does not match the current blog.', 'wp-optimize'));
		}

		$error = false;

		$image_path = get_attached_file($image_id);
		$backup_path = get_post_meta($image_id, 'original-file', true);
		
		// If the file doesn't exist, check if it's relative
		if (!is_file($backup_path)) {
			$uploads_dir = wp_upload_dir();
			$uploads_basedir = trailingslashit($uploads_dir['basedir']);

			if (is_file($uploads_basedir . $backup_path)) {
				$backup_path = $uploads_basedir . $backup_path;
			}
		}

		// If the file still doesn't exist, the record could be an absolute path from a migrated site
		// Use the current Uploads path
		if (!is_file($backup_path)) {
			$current_uploads_dir_folder = trailingslashit(substr($uploads_basedir, strlen(ABSPATH)));
			// A strict operator (!==) needs to be used, as 0 is a positive result.
			if (false !== strpos($backup_path, $current_uploads_dir_folder)) {
				$temp_relative_backup_path = substr($backup_path, strpos($backup_path, $current_uploads_dir_folder) + strlen($current_uploads_dir_folder));
				if (is_file($uploads_basedir . $temp_relative_backup_path)) {
					$backup_path = $uploads_basedir . $temp_relative_backup_path;
				}
			}


		}

		// If the file still doesn't exist, the record could be an absolute path from a migrated site
		// The current Uploads path failed, so try with the default uploads directory value
		if (!is_file($backup_path)) {
			// A strict operator (!==) needs to be used, as 0 is a positive result.
			if (false !== strpos($backup_path, 'wp-content/uploads/')) {
				$backup_path = substr($backup_path, strpos($backup_path, 'wp-content/uploads/') + strlen('wp-content/uploads/'));
				$backup_path = $uploads_basedir . $backup_path;
			}
		}

		if (!is_file($backup_path)) {
			// Delete information about backup.
			delete_post_meta($image_id, 'original-file');
			$error = new WP_Error('restore_backup_not_found', __('The backup was not found; it may have been deleted or was already restored', 'wp-optimize'));
		} elseif (!is_writable($image_path)) {
			$error =  new WP_Error('restore_failed', __('The destination could not be written to.', 'wp-optimize').' '.__("Please check your folder's permissions", 'wp-optimize'));
		} elseif (!copy($backup_path, $image_path)) {
			$error =  new WP_Error('restore_failed', __('The file could not be copied; check your PHP error logs for details', 'wp-optimize'));
		} elseif (!unlink($backup_path)) {
			$error =  new WP_Error('restore_failed', sprintf(__('The backup file %s could not be deleted.', 'wp-optimize'), $backup_path));
		}

		if (!$error) {
			// if backup image deleted successfully
			// then delete from attachment meta associated smush data
			delete_post_meta($image_id, 'smush-complete');
			delete_post_meta($image_id, 'smush-stats');
			delete_post_meta($image_id, 'original-file');
			delete_post_meta($image_id, 'smush-info');
		}

		if ($switched_blog) {
			restore_current_blog();
		}

		if (is_wp_error($error)) return $error;

		$this->delete_from_cache('uncompressed_images');

		if (!wp_next_scheduled('prune_smush_logs')) {
			wp_schedule_single_event(time() + 7200, 'prune_smush_logs');
		}

		return true;
	}

	/**
	 * Restore compressed images for selected blog.
	 *
	 * @param bool $restore_backup 			 if true then restore images from backup otherwise just delete meta.
	 * @param int  $blog_id        			 blog id.
	 * @param int  $images_limit   			 how many images process per time.
	 * @param bool $delete_only_backups_meta meta fields will deleted only for images those will restored from backup.
	 *
	 * @return array ['completed' => (bool), 'message' => (string), 'error' => (string)]
	 */
	public function bulk_restore_compressed_images($restore_backup, $blog_id = 1, $images_limit = 100, $delete_only_backups_meta = false) {
		global $wpdb;

		if (is_multisite()) {
			switch_to_blog($blog_id);
		}

		$result = array(
			'completed' => false,
			'message' => '',
		);

		$processed = 0;

		if ($restore_backup) {
			// get post ids those have backup meta field.
			$image_ids = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'original-file' LIMIT %d;", $images_limit), ARRAY_A);

			if (!empty($image_ids)) {
				// run restore function for each found image.
				foreach ($image_ids as $image) {
					$restore_result = $this->restore_single_image($image['post_id'], $blog_id);

					// if we get an error then we stop the work, except situation when "backup already restored'.
					if (is_wp_error($restore_result) && 'restore_backup_not_found' != $restore_result->get_error_code()) {
						// we need to stop the work as we haven't restored the backup.
						$result['error'] = $restore_result->get_error_message();
						$this->options->delete_option('smush_images_restored');
						break;
					}

					$processed++;
				}
			}

			$images_count = count($image_ids);

			// if all images processed then set flag completed to true.
			if ($processed == $images_count && $images_count < $images_limit) {
				$this->options->delete_option('smush_images_restored');
				$result['completed'] = true;
			} else {
				// save into options total processed count.
				$processed += $this->options->get_option('smush_images_restored', 0);
				$this->options->update_option('smush_images_restored', $processed);

				if (is_multisite()) {
					$result['message'] = sprintf(__('%s compressed images were restored from their backup for the site %s', 'wp-optimize'), $processed, get_site_url($blog_id));
				} else {
					$result['message'] = sprintf(__('%s compressed images were restored from their backup', 'wp-optimize'), $processed);
				}
			}

		} else {
			// if we just delete compressed images meta then set complete flag to true.
			$result['completed'] = true;
		}

		if ($result['completed']) {
			if ($delete_only_backups_meta) {
				if (is_multisite()) {
					$result['message'] = sprintf(__('All the compressed images for the site %s were successfully restored.', 'wp-optimize'), get_site_url($blog_id));
				} else {
					$result['message'] = __('All the compressed images were successfully restored.', 'wp-optimize');
				}
			} else {
				if (is_multisite()) {
					$result['message'] = sprintf(__('All the compressed images for the site %s were successfully marked as uncompressed.', 'wp-optimize'), get_site_url($blog_id));
				} else {
					$result['message'] = __('All the compressed images were successfully marked as uncompressed.', 'wp-optimize');
				}
			}

			// clear all metas for smushed images after work completed.
			// if $delete_only_backup_meta set to true then all meta fields was deleted in restore_single_image()
			// and we don't need delete metas for other images.
			if (!$delete_only_backups_meta) {
				$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('smush-complete', 'smush-stats', 'original-file', 'smush-info');");
			}
		}

		if (is_multisite()) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Process bulk smushing operation
	 *
	 * @param array $images - the array of images to process
	 * @return bool - true if processing complete
	 */
	public function process_bulk_smush($images = array()) {
		
		// Get a list of pending tasks so we can exclude those
		$pending_tasks = $this->get_pending_tasks();
		$queued_images = array();

		$this->write_log_header();

		if (!empty($pending_tasks)) {
			foreach ($pending_tasks as $task) {
				$queued_images[] = array(
					'attachment_id' => $task->get_option('attachment_id'),
					'blog_id' => $task->get_option('blog_id')
				);
			}
		}

		foreach ($images as $image) {
			// Skip if already in the queue
			if (in_array($image, $queued_images)) continue;

			$options = array(
				'attachment_id' => intval($image['attachment_id']),
				'blog_id'	   => intval($image['blog_id']),
				'image_quality' => $this->options->get_option('image_quality', 85),
				'keep_original' => $this->options->get_option('back_up_original', true),
				'preserve_exif' => $this->options->get_option('preserve_exif', true),
				'lossy_compression' => $this->options->get_option('lossy_compression', false)
			);

			$server = $this->options->get_option('compression_server', $this->webservice);
			$task_name = $this->get_associated_task($server);

			$description = "$task_name - Attachment ID : ". intval($image['attachment_id']) . ", Started on : ". date("F d, Y h:i:s", time());
			$task = call_user_func(array($task_name, 'create_task'), 'smush', $description, $options, $task_name);
			$task->add_logger($this->logger);
		}

		$this->clear_cached_data();
		$this->process_queue('smush');

		if ($this->is_queue_processed()) {
			$this->clean_up_old_tasks('smush');
		}

		if (!wp_next_scheduled('prune_smush_logs')) {
			wp_schedule_single_event(time() + 7200, 'prune_smush_logs');
		}

		return true;
	}


	/**
	 * Check if a specified server online
	 *
	 * @param string $server - the server to test
	 * @return bool - true if yes, false otherwise
	 */
	public function check_server_online($server = 'resmushit') {
		$task = $this->get_associated_task($server);
		$online = call_user_func(array($task, 'is_server_online'));
			
		if ($online) {
			update_option($task, strtotime('now'));
		} else {
			$this->log(get_option($task));
		}

		$this->log(sprintf('%s server status: %s', $task, $online ? 'Online' : 'Offline'));
		return $online;
	}
	
	/**
	 * Checks if the queue for smushing is compleete
	 *
	 * @return bool - true if processed, false otherwise
	 */
	public function is_queue_processed() {

		$active = $this->get_pending_tasks();
		if ($active && 0 != count($active))
			return false;

		if (false !== get_option('updraft_semaphore_smush'))
			return false;

		return true;
	}

	/**
	 * Logs useful data once a smush task completes or if it fails
	 *
	 * @param mixed $task - A task object
	 */
	public function record_stats($task) {

		$attachment_id	= $task->get_option('attachment_id');
		$completed_task_count = $this->options->get_option('completed_task_count', false);
		$failed_task_count = $this->options->get_option('failed_task_count', 0);
		$total_bytes_saved = $this->options->get_option('total_bytes_saved', false);
		$total_percent_saved = $this->options->get_option('total_percent_saved', 0);

		if ('ud_task_failed' == current_action()) {
			$this->options->update_option('failed_task_count', ++$failed_task_count);
			return;
		}

		if (false === $completed_task_count) {
			$completed_task_count = $total_bytes_saved = 0;
		}

		if (!$total_bytes_saved) {
			$total_bytes_saved = 0;
		}

		if (is_multisite()) {
			switch_to_blog($task->get_option('blog_id', 1));
			$stats = get_post_meta($attachment_id, 'smush-stats', true);
			restore_current_blog();
		} else {
			$stats = get_post_meta($attachment_id, 'smush-stats', true);
		}

		if (isset($stats['sizes-info'])) {
		
			$original_size = 0;
			$compressed_size = 0;

			foreach ($stats['sizes-info'] as $info) {
				$original_size += $info['original'];
				$compressed_size += $info['compressed'];
			}

			$percent = round((($original_size - $compressed_size) / $original_size * 100), 2);
		} else {
			$original_size = isset($stats['original-size']) ? $stats['original-size'] : 0;
			$compressed_size = isset($stats['smushed-size']) ? $stats['smushed-size'] : 0;
			$percent = isset($stats['savings-percent']) ? $stats['savings-percent'] : 0;
		}

		$saved = $original_size - $compressed_size;
		$completed_task_count++;

		$total_bytes_saved += $saved;
		$total_percent_saved = (($total_percent_saved * ($completed_task_count - 1)) + $percent) / $completed_task_count;

		$this->options->update_option('completed_task_count', $completed_task_count);
		$this->options->update_option('total_bytes_saved', $total_bytes_saved);
		$this->options->update_option('total_percent_saved', $total_percent_saved);
	}

	/**
	 * Cleans out all complete + failed tasks from the DB.
	 *
	 * @param String $type type of the task
	 * @return bool - true if processing complete
	 */
	public function clean_up_old_tasks($type) {
		$completed_tasks = $this->get_tasks('all', $type);

		if (!$completed_tasks) return false;

		$this->log(sprintf('Cleaning up tasks of type (%s). A total of %d tasks will be deleted.', $type, count($completed_tasks)));

		foreach ($completed_tasks as $task) {
			$task->delete_meta();
			$task->delete();
		}

		return true;
	}

	/**
	 * Get current smush options.
	 *
	 * @return array
	 */
	public function get_smush_options() {
		static $smush_options = array();
		if (empty($smush_options)) {
			$smush_options = array(
				'compression_server' => $this->options->get_option('compression_server', $this->get_default_webservice()),
				'image_quality' => $this->options->get_option('image_quality', 'very_good'),
				'lossy_compression' => $this->options->get_option('lossy_compression', false),
				'back_up_original' => $this->options->get_option('back_up_original', true),
				'back_up_delete_after' => $this->options->get_option('back_up_delete_after', true),
				'back_up_delete_after_days' => $this->options->get_option('back_up_delete_after_days', 50),
				'preserve_exif' => $this->options->get_option('preserve_exif', false),
				'autosmush' => $this->options->get_option('autosmush', false),
				'show_smush_metabox' => $this->options->get_option('show_smush_metabox', 'show') == 'show' ? true : false,
				'webp_conversion' => $this->options->get_option('webp_conversion', false)
			);
		}
		return $smush_options;
	}

	/**
	 * Updates global smush options
	 *
	 * @param array $options - sent in via AJAX
	 * @return bool - status of the update
	 */
	public function update_smush_options($options) {
		
		foreach ($options as $option => $value) {
			$this->options->update_option($option, $value);
		}

		return true;
	}

	/**
	 * Clears smush related stats
	 *
	 * @return bool - status of the update
	 */
	public function clear_smush_stats() {
		$this->options->update_option('failed_task_count', 0);
		$this->options->update_option('completed_task_count', false);
		$this->options->update_option('total_bytes_saved', false);
		$this->options->update_option('total_percent_saved', 0);

		return true;
	}

	/**
	 * Returns array of translations used in javascript code.
	 *
	 * @return array - translations used in JS
	 */
	public function smush_js_translations() {
		return apply_filters('updraft_smush_js_translations', array(
			'all_images_compressed' 		  => __('No uncompressed images were found.', 'wp-optimize'),
			'error_unexpected_response' 	  => __('An unexpected response was received from the server. More information has been logged in the browser console.', 'wp-optimize'),
			'compress_single_image_dialog'	  => __('Please wait: compressing the selected image.', 'wp-optimize'),
			'error_try_again_later'			  => __('Please try again later.', 'wp-optimize'),
			'server_check'					  => __('Connecting to the Smush API server, please wait', 'wp-optimize'),
			'please_wait'					  => __('Please wait while the request is being processed', 'wp-optimize'),
			'server_error'					  => __('There was an error connecting to the image compression server. This could mean either the server is temporarily unavailable or there are connectivity issues with your internet connection. Please try later.', 'wp-optimize'),
			'please_select_images'		  	  => __('Please select the images you want compressed from the "Uncompressed images" panel first', 'wp-optimize'),
			'please_updating_images_info'	  => __('Please wait: updating information about the selected image.', 'wp-optimize'),
			'please_select_compressed_images' => __('Please select the images you want to mark as already compressed from the "Uncompressed images" panel first', 'wp-optimize'),
			'view_image'					  => __('View Image', 'wp-optimize'),
			'delete_image_backup_confirm'	=> __('Do you really want to delete all backup images now? This action is irreversible.', 'wp-optimize'),
			'mark_all_images_uncompressed'	=> __('Do you really want to mark all the images as uncompressed? This action is irreversible.', 'wp-optimize'),
			'restore_images_from_backup'	=> __('Do you want to restore the original images from the backup (where they exist?)', 'wp-optimize'),
			'restore_all_compressed_images'	=> __('Do you really want to restore all the compressed images?', 'wp-optimize'),
			'more' => __('More', 'wp-optimize'),
			'less' => __('Less', 'wp-optimize'),
		));
	}

	/**
	 * Adds a smush metabox on the post edit screen for images
	 *
	 * @param WP_Post $post - a post object
	 */
	public function add_smush_metabox($post) {

		if (!wp_attachment_is_image($post->ID)) return;

		if (!file_exists(get_attached_file($post->ID))) {
			return;
		}

		add_meta_box('smush-metabox', __('Compress Image', 'wp-optimize'), array($this, 'render_smush_metabox'), 'attachment', 'side');
	}

	/**
	 * Renders a metabox on the post edit screen for images
	 *
	 * @param WP_Post $post - a post object
	 */
	public function render_smush_metabox($post) {

		$compressed = get_post_meta($post->ID, 'smush-complete', true) ? true : false;
		$has_backup = get_post_meta($post->ID, 'original-file', true) ? true : false;

		$smush_info = get_post_meta($post->ID, 'smush-info', true);
		$smush_stats = get_post_meta($post->ID, 'smush-stats', true);
		$marked = get_post_meta($post->ID, 'smush-marked', false);
		
		$options = Updraft_Smush_Manager()->get_smush_options();

		$file = get_attached_file($post->ID);
		$file_size = ($file && is_file($file)) ? filesize($file) : 0;

		$extract = array(
			'post_id' 			=> $post->ID,
			'smush_display'		=> $compressed ? "style='display:none;'" : "style='display:block;'",
			'restore_display' 	=> $compressed ? "style='display:block;'" : "style='display:none;'",
			'restore_action'	=> $has_backup ? "style='display:block;'" : "style='display:none;'",
			'smush_mark'		=> !$compressed && !$marked ? "style='display:block;'" : "style='display:none;'",
			'smush_unmark'      => $marked ? "style='display:block;'" : "style='display:none;'",
			'smush_info'		=> $smush_info ? $smush_info : ' ',
			'file_size'			=> $file_size,
			'smush_options'     => $options,
			'custom'            => 100 == $options['image_quality'] || 90 == $options['image_quality'] ? false : true,
			'smush_details'		=> '',
		);

		if (!empty($smush_stats['sizes-info'])) {
			$extract['smush_details'] = WP_Optimize()->include_template('images/smush-details.php', true, array('sizes_info' => $smush_stats['sizes-info']));
		}

		$extract['compressed_by_another_plugin'] = $this->is_image_compressed_by_another_plugin($post->ID);

		WP_Optimize()->include_template('admin-metabox-smush.php', false, $extract);
	}

	/**
	 * Check if a single image compressed by another plugin.
	 *
	 * @param int $image_id
	 * @return bool
	 */
	private function is_image_compressed_by_another_plugin($image_id) {
		global $wpdb;

		$meta = $wpdb->get_results("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE `post_id`={$image_id}", ARRAY_A);

		if (is_array($meta)) {
			foreach ($meta as $row) {
				// Smush, Imagify, Compress JPEG & PNG images by TinyPNG.
				if (in_array($row['meta_key'], array('wp-smpro-smush-data', '_imagify_optimization_level', 'tiny_compress_images'))) return true;
				// ShortPixel Image Optimizer
				if ('_shortpixel_status' == $row['meta_key'] && 2 <= $row['meta_key'] && 3 > $row['meta_key']) return true;
			}
		}

		if (WP_Optimize()->get_db_info()->table_exists('ewwwio_images')) {
			$old_show_errors = $wpdb->show_errors(false);
			// EWWW Image Optimizer.
			$ewww_image = $wpdb->get_col("SELECT attachment_id FROM {$wpdb->prefix}ewwwio_images WHERE attachment_id={$image_id} AND gallery='media' LIMIT 1");
			if (!empty($ewww_image)) return true;
			$wpdb->show_errors($old_show_errors);
		}

		return apply_filters('wpo_image_compressed_by_another_plugin', false);
	}

	/**
	 * Get attachment ids for images those already compressed with EWWW Image Optimizer.
	 * Used with filter `wpo_get_uncompressed_images_args`.
	 *
	 * @param array $args WP_Query arguments.
	 * @return array
	 */
	public function ewww_image_optimizer_compressed_images_args($args) {
		global $wpdb;

		if (!WP_Optimize()->get_db_info()->table_exists('ewwwio_images')) return $args;

		$old_show_errors = $wpdb->show_errors(false);
		$compressed_images = $wpdb->get_col("SELECT DISTINCT(attachment_id) FROM {$wpdb->prefix}ewwwio_images WHERE gallery='media'");
		$wpdb->show_errors($old_show_errors);

		if (isset($args['post__not_in'])) {
			$args['post__not_in'] = array_merge($args['post__not_in'], $compressed_images);
		} else {
			$args['post__not_in'] = $compressed_images;
		}

		return $args;
	}

	/**
	 * Returns a list of images for smush (from cache if available)
	 *
	 * @return array - uncompressed images
	 */
	public function get_uncompressed_images() {
		
		$uncompressed_images = $this->get_from_cache('uncompressed_images');

		if ($uncompressed_images) return $uncompressed_images;

		$uncompressed_images = array();

		$args = array(
			'post_type'		=> 'attachment',
			'post_mime_type' => 'image',
			'post_status'	=> 'inherit',
			'posts_per_page' => apply_filters('updraft_smush_posts_per_page', 1000),
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'	 => 'smush-complete',
						'compare' => '!=',
						'value'   => '1',
					),
					array(
						'key'	 => 'smush-complete',
						'compare' => 'NOT EXISTS',
						'value'   => '',
					),
				),
				// ShortPixel Image Optimizer plugin
				array(
					'relation' => 'OR',
					array(
						'key'    => '_shortpixel_status',
						'compare' => '<',
						'value'   => '2',
					),
					array(
						'key'    => '_shortpixel_status',
						'compare' => '>=',
						'value'   => '3',
					),
					array(
						'key'	 => '_shortpixel_status',
						'compare' => 'NOT EXISTS',
						'value'   => '',
					),
				),
				// Smush plugin
				array(
					'key'	 => 'wp-smpro-smush-data',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				// Imagify
				array(
					'key'	 => '_imagify_optimization_level',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				// Compress JPEG & PNG images by TinyPNG
				array(
					'key'	 => 'tiny_compress_images',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
			)
		);

		if (is_multisite()) {

			$sites = WP_Optimize()->get_sites();

			foreach ($sites as $site) {
				
				switch_to_blog($site->blog_id);

				$args = apply_filters('wpo_get_uncompressed_images_args', $args);
				$images = new WP_Query($args);

				foreach ($images->posts as $image) {
					if (file_exists(get_attached_file($image->ID))) {
						$uncompressed_images[$site->blog_id][] = array(
							'id' => $image->ID,
							'thumb_url' => wp_get_attachment_thumb_url($image->ID),
							'filesize'  => filesize(get_attached_file($image->ID))
						);
					} else {
						$this->log("Could not find file for image: blog_id={$site->blog_id}, ID={$image->ID}, file=".get_attached_file($image->ID));
					}
				}

				restore_current_blog();
			}

		} else {
			$args = apply_filters('wpo_get_uncompressed_images_args', $args);
			$images = new WP_Query($args);
			foreach ($images->posts as $image) {
				if (file_exists(get_attached_file($image->ID))) {
					$uncompressed_images[1][] = array(
						'id' => $image->ID,
						'thumb_url' => wp_get_attachment_thumb_url($image->ID),
						'filesize'  => filesize(get_attached_file($image->ID))
					);
				} else {
						$this->log("Could not find file for image: ID={$image->ID}, file=".get_attached_file($image->ID));
				}
			}
		}

		$this->save_to_cache('uncompressed_images', $uncompressed_images);
		return $uncompressed_images;
	}

	/**
	 * Returns a list of admin URLs. This is to prevent unnecessary bloat in the output of get_uncompressed_images() (and thus better performance over the network on sites with huge numbers of images)
	 *
	 * @return array - list of admin URLs
	 */
	public function get_admin_urls() {
		
		$admin_urls = $this->get_from_cache('admin_urls');

		if ($admin_urls) return $admin_urls;

		$admin_urls = array();

		if (is_multisite()) {
		
			$sites = WP_Optimize()->get_sites();

			foreach ($sites as $site) {
				switch_to_blog($site->blog_id);
				$admin_urls[$site->blog_id] = admin_url();
				restore_current_blog();
			}

		} else {
			// The pseudo-blog_id here (1) matches (and must match) what is used in get_uncompressed_images
			$admin_urls[1] = admin_url();
		}

		$this->save_to_cache('admin_urls', $admin_urls);
		return $admin_urls;
	}
	
	/**
	 * Check if a task exists for a given image
	 *
	 * @param string $image - The attachment ID of the image
	 * @return bool - true if yes, false otherwise
	 */
	public function task_exists($image) {
		
		$pending_tasks = $this->get_active_tasks('smush');
		$queued_images = array();

		if (!empty($pending_tasks)) {
			foreach ($pending_tasks as $task) {
				$queued_images[] = $task->get_option('attachment_id');
			}
		}
		return in_array($image, $queued_images);
	}

	/**
	 * Returns the status of images compressed in this iteration of the bulk compress
	 *
	 * @param array $images - List of images in the current session
	 *
	 * @return array - status of the operation
	 */
	public function get_session_stats($images) {
		$stats = array();

		foreach ($images as $image) {
			if (is_multisite()) {
				switch_to_blog($image['blog_id'], 1);
				$stats[] = get_post_meta($image['attachment_id'], 'smush-complete', true) ? 'success' : 'fail';
				restore_current_blog();
			} else {
				$stats[] = get_post_meta($image['attachment_id'], 'smush-complete', true) ? 'success' : 'fail';
			}
		}

		return array_count_values($stats);
	}

	/**
	 * Returns a list of images for smush (from cache if available)
	 *
	 * @return array - List of task objects with uncompressed images
	 */
	public function get_pending_tasks() {
		return $this->get_active_tasks('smush');
	}

	/**
	 * Deletes and removes any pending tasks from queue
	 */
	public function clear_pending_images() {

		$pending_tasks = $this->get_active_tasks('smush');

		foreach ($pending_tasks as $task) {
			$task->delete_meta();
			$task->delete();
		}
		
		return true;
	}


	/**
	 * Returns a count of failed tasks
	 *
	 * @return int -  failed tasks
	 */
	public function get_failed_task_count() {
		return $this->options->get_option('failed_task_count', 0);
	}

	/**
	 * Adds the required scripts and styles
	 */
	public function admin_enqueue_scripts() {
		$current_screen = get_current_screen();
		// load scripts and styles only on WP-Optimize pages or if show_smush_metabox option enabled.
		if (!preg_match('/wp\-optimize/i', $current_screen->id) && 'show' != $this->options->get_option('show_smush_metabox', 'show')) return;

		$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? WPO_VERSION.'.'.time() : WPO_VERSION;
		$min_or_not = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
		$min_or_not_internal = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '-'. str_replace('.', '-', WPO_VERSION). '.min';
		
		$js_variables = $this->smush_js_translations();
		$js_variables['ajaxurl'] = admin_url('admin-ajax.php');
		$js_variables['features'] = $this->get_features();

		$js_variables['smush_ajax_nonce'] = wp_create_nonce('updraft-task-manager-ajax-nonce');

		wp_enqueue_script('block-ui-js', WPO_PLUGIN_URL.'js/jquery.blockUI'.$min_or_not.'.js', array('jquery'), $enqueue_version);
		wp_enqueue_script('smush-js', WPO_PLUGIN_URL.'js/wposmush'.$min_or_not_internal.'.js', array('jquery', 'block-ui-js'), $enqueue_version);
		wp_enqueue_style('smush-css', WPO_PLUGIN_URL.'css/smush'.$min_or_not_internal.'.css', array(), $enqueue_version);
		wp_localize_script('smush-js', 'wposmush', $js_variables);
	}

	/**
	 * Gets default service provider for smush
	 *
	 * @return string - service name
	 */
	public function get_default_webservice() {
		return 'resmushit';
	}

	/**
	 * Sets default options for smush
	 */
	public function set_default_options() {

		$options = array(
			'compression_server' => $this->get_default_webservice(),
			'image_quality'		 => 'very_good',
			'lossy_compression'	 => false,
			'back_up_original'	 => true,
			'preserve_exif'		 => false,
			'autosmush'			 => false,
			'back_up_delete_after' => $this->options->get_option('back_up_delete_after', true),
			'back_up_delete_after_days' => $this->options->get_option('back_up_delete_after_days', 50),
		);
		
		$this->update_smush_options($options);
	}

	/**
	 * Gets default service provider for smush
	 *
	 * @param string $server - The name of the server
	 * @return string - associated task type, default if none found
	 */
	public function get_associated_task($server) {
		$allowed = $this->get_allowed_services();

		if (key_exists($server, $allowed))
			return $allowed[$server];

		$default = $this->get_default_webservice();
		return $allowed[$default];
	}

	/**
	 * Gets allowed service providers for smush
	 *
	 * @return array - key value pair of service name => task name
	 */
	public function get_allowed_services() {
		return array(
			'resmushit'  => 'Re_Smush_It_Task',
		);
	}

	/**
	 * Gets allowed service provider features smush
	 *
	 * @return array - key value pair of service name => features exposed
	 */
	public function get_features() {
		$features = array();
		foreach ($this->get_allowed_services() as $service => $class_name) {
			$features[$service] = call_user_func(array($class_name, 'get_features'));
		}
		return $features;
	}

	/**
	 * Returns the path to the logfile
	 *
	 * @return string - file path
	 */
	public function get_logfile_path() {
		$upload_dir = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];
		return $upload_base . '/smush-' . substr(md5(wp_salt()), 0, 20) . '.log';
	}

	/**
	 * Adds a logger to the task
	 *
	 * @param Mixed $task - a task object
	 */
	public function set_task_logger($task) {
		if (!$this->logger) {
			$this->logger = new Updraft_File_Logger($this->get_logfile_path());
		}
		
		if (!$task->get_loggers()) {
			$task->add_logger($this->logger);
		}
	}

	/**
	 * Writes a standardised header to the log file
	 */
	public function write_log_header() {
		
		global $wpdb;
		
		// phpcs:disable
		$wp_version = $this->get_wordpress_version();
		$mysql_version = $wpdb->db_version();
		$safe_mode = $this->detect_safe_mode();
		$max_execution_time = (int) @ini_get("max_execution_time");

		$memory_limit = ini_get('memory_limit');
		$memory_usage = round(@memory_get_usage(false)/1048576, 1);
		$total_memory_usage = round(@memory_get_usage(true)/1048576, 1);

		// Attempt to raise limit
		@set_time_limit(330);

		$log_header = array();

		// phpcs:enable
		$log_header[] = "\n";
		$log_header[] = "Header for logs at time:  ".date('r')." on ".network_site_url();
		$log_header[] = "WP: ".$wp_version;
		$log_header[] = "PHP: ".phpversion()." (".PHP_SAPI.", ".@php_uname().")";// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$log_header[] = "MySQL: $mysql_version";
		$log_header[] = "WPLANG: ".get_locale();
		$log_header[] = "Server: ".$_SERVER["SERVER_SOFTWARE"];
		$log_header[] = "Outbound connections: ".(defined('WP_HTTP_BLOCK_EXTERNAL') ? 'Y' : 'N');
		$log_header[] = "safe_mode: $safe_mode";
		$log_header[] = "max_execution_time: $max_execution_time";
		$log_header[] = "memory_limit: $memory_limit (used: ${memory_usage}M | ${total_memory_usage}M)";
		$log_header[] = "multisite: ".(is_multisite() ? 'Y' : 'N');
		$log_header[] = "openssl: ".(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N');


		foreach ($log_header as $log_entry) {
			$this->log($log_entry);
		}

		$memlim = $this->memory_check_current();

		if ($memlim<65 && $memlim>0) {
			$this->log(sprintf('The amount of memory (RAM) allowed for PHP is very low (%s Mb) - you should increase it to avoid failures due to insufficient memory (consult your web hosting company for more help)', round($memlim, 1)), 'warning');
		}

		if ($max_execution_time>0 && $max_execution_time<20) {
			$this->log(sprintf('The amount of time allowed for WordPress plugins to run is very low (%s seconds) - you should increase it to avoid failures due to time-outs (consult your web hosting company for more help - it is the max_execution_time PHP setting; the recommended value is %s seconds or more)', $max_execution_time, 90), 'warning');
		}
	}

	/**
	 * Prunes the log file
	 */
	public function prune_smush_logs() {
		$this->log("Pruning the smush log file");
		$this->logger->prune_logs();
	}

	/**
	 * Get the WordPress version
	 *
	 * @return String - the version
	 */
	public function get_wordpress_version() {
		static $got_wp_version = false;
		
		if (!$got_wp_version) {
			global $wp_version;
			@include(ABSPATH.WPINC.'/version.php');// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$got_wp_version = $wp_version;
		}

		return $got_wp_version;
	}

	/**
	 * Get the current memory limit
	 *
	 * @return String - memory limit in megabytes
	 */
	public function memory_check_current($memory_limit = false) {
		// Returns in megabytes
		if (false == $memory_limit) $memory_limit = ini_get('memory_limit');
		$memory_limit = rtrim($memory_limit);
		$memory_unit = $memory_limit[strlen($memory_limit)-1];
		if (0 == (int) $memory_unit && '0' !== $memory_unit) {
			$memory_limit = substr($memory_limit, 0, strlen($memory_limit)-1);
		} else {
			$memory_unit = '';
		}
		switch ($memory_unit) {
			case '':
			$memory_limit = floor($memory_limit/1048576);
				break;
			case 'K':
			case 'k':
			$memory_limit = floor($memory_limit/1024);
				break;
			case 'G':
			$memory_limit = $memory_limit*1024;
				break;
			case 'M':
			// assumed size, no change needed
				break;
		}
		return $memory_limit;
	}

	/**
	 * Detect if safe_mode is on
	 *
	 * @return Integer - 1 or 0
	 */
	public function detect_safe_mode() {
		return (@ini_get('safe_mode') && strtolower(@ini_get('safe_mode')) != "off") ? 1 : 0;// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Saves a value to the cache.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param int	$blog_id
	 */
	public function save_to_cache($key, $value, $blog_id = 1) {
		$transient_limit = 3600 * 48;
		$key = 'wpo_smush_cache_' . $blog_id . '_'. $key;

		return WP_Optimize_Transients_Cache::get_instance()->set($key, $value, $transient_limit);
	}

	/**
	 * Gets value from the cache.
	 *
	 * @param string $key
	 * @param int	$blog_id
	 * @return mixed
	 */
	public function get_from_cache($key, $blog_id = 1) {
		$key = 'wpo_smush_cache_' . $blog_id . '_'. $key;

		$value = WP_Optimize_Transients_Cache::get_instance()->get($key);

		return $value;
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key
	 * @param int	$blog_id
	 */
	public function delete_from_cache($key, $blog_id = 1) {
		$key = 'wpo_smush_cache_' . $blog_id . '_'. $key;

		WP_Optimize_Transients_Cache::get_instance()->delete($key);

		$this->delete_transient($key);
	}

	/**
	 * Wrapper for deleting a transient
	 *
	 * @param string $key
	 */
	public function delete_transient($key) {
		if ($this->is_multisite_mode()) {
			delete_site_transient($key);
		} else {
			delete_transient($key);
		}
	}

	/**
	 * Removes all cached data
	 */
	public function clear_cached_data() {
		global $wpdb;

		// get list of cached data by optimization.
		if ($this->is_multisite_mode()) {
			$keys = $wpdb->get_col("SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%wpo_smush_cache_%'");
		} else {
			$keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%wpo_smush_cache_%'");
		}

		if (!empty($keys)) {
			$transient_keys = array();
			foreach ($keys as $key) {
				preg_match('/wpo_smush_cache_.+/', $key, $option_name);
				$option_name = $option_name[0];
				$transient_keys[] = $option_name;
			}

			// get unique keys.
			$transient_keys = array_unique($transient_keys);

			// delete transients.
			foreach ($transient_keys as $key) {
				$this->delete_transient($key);
			}
		}
	}

	/**
	 * Delete recursively all smush backup files created more that $days_ago days.
	 *
	 * @param string $directory upload directory
	 * @param int    $days_ago
	 */
	public function clear_backup_images_directory($directory, $days_ago = 30) {

		$directory = trailingslashit($directory);
		$current_time = time();

		if (preg_match('/(\d{4})\/(\d{2})\/$/', $directory, $match)) {

			$check_date = false;

			if ($days_ago > 0) {
				// check if it is end directory then scan for backup images.
				$year = (int) $match[1];
				$month = (int) $match[2];

				$limit = strtotime('-'.$days_ago.' '.(($days_ago > 1) ? 'days' : 'day'));
				$year_limit = (int) date('Y', $limit);
				$month_limit = (int) date('m', $limit);
				$day_limit = (int) date('j', $limit);

				// if current directory is newer than needed then we skip it.
				if ($year_limit < $year || ($year_limit == $year && $month_limit < $month)) {
					return;
				}

				// we will check dates only in directory that contain limit date.
				$check_date = ($year_limit == $year && $month_limit == $month);
			}

			// GLOB_BRACE isn't defined on some systems (Solaris, SunOS and more) > https://www.php.net/manual/en/function.glob.php
			$files = glob($directory . '*-updraft-pre-smush-original.*', (defined('GLOB_BRACE') ? GLOB_BRACE : 0));

			foreach ($files as $file) {
				if ($check_date) {
					$filedate_day = (int) date('j', filectime($file));
					if ($filedate_day >= $day_limit) continue;
				}

				unlink($file);
			}

		} else {
			// scan directories recursively.
			$handle = opendir($directory);

			if (false === $handle) return;

			$file = readdir($handle);

			while (false !== $file) {

				if ('.' == $file || '..' == $file) {
					$file = readdir($handle);
					continue;
				}

				if (is_dir($directory . $file)) {
					$this->clear_backup_images_directory($directory . $file, $days_ago);
				} elseif (is_file($directory . $file) && preg_match('/^.+-updraft-pre-smush-original\.\S{3,4}/i', $file)) {
					// check the file time and compare with $days_ago.
					$filedate_day = (int) filectime($directory . $file);
					if ($filedate_day > 0 && ($current_time - $filedate_day) / 86400 >= $days_ago) unlink($directory . $file);
				}

				$file = readdir($handle);
			}
		}

	}

	/**
	 * Clean backup smush images according to saved options.
	 */
	public function clear_backup_images() {
		$back_up_delete_after = $this->options->get_option('back_up_delete_after', false);

		if (!$back_up_delete_after) return;

		$back_up_delete_after_days = $this->options->get_option('back_up_delete_after_days', 50);

		$upload_dir = wp_upload_dir(null, false);
		$base_dir = $upload_dir['basedir'];

		$this->clear_backup_images_directory($base_dir, $back_up_delete_after_days);
	}

	/**
	 * Check if attachment already compressed.
	 *
	 * @param int $attachment_id
	 *
	 * @return bool
	 */
	public function is_compressed($attachment_id) {
		return (true == get_post_meta($attachment_id, 'smush-complete', true));
	}

	/**
	 * @param array   $form_fields
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function add_compress_button_to_media_modal($form_fields, $post) {

		if (!is_admin() || !function_exists('get_current_screen')) return $form_fields;
	
		/**
		 * In media modal get_current_screen() return null or id = 'async-upload' We don't need add smush fields elsewhere.
		 */
		$current_screen = get_current_screen();
		if (null !== $current_screen && 'async-upload' != $current_screen->id) return $form_fields;

		/**
		 * Don't show additional fields for non-image attachments.
		 */
		if (!wp_attachment_is_image($post->ID)) return $form_fields;

		ob_start();
		$this->render_smush_metabox($post);
		$smush_metabox = ob_get_contents();
		ob_end_clean();

		$form_fields['wpo_compress_image'] = array(
			'value'			=> '',
			'label'         => __('Compress image', 'wp-optimize'),
			'input'         => 'html',
			'html'          => $smush_metabox,
		);

		return $form_fields;
	}

	/**
	 * Returns true if multisite
	 *
	 * @return bool
	 */
	public function is_multisite_mode() {
		return WP_Optimize()->is_multisite_mode();
	}

	/**
	 * This callback function is triggered due to delete_attachment action (wp-includes/post.php) and is executed prior to deletion of post-type attachment
	 *
	 * @param int $post_id - WordPress Post ID
	 */
	public function unscheduled_original_file_deletion($post_id) {
		$the_original_file = get_post_meta($post_id, 'original-file', true);
		if ('' != $the_original_file && file_exists($the_original_file)) {
			@unlink($the_original_file);// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Instance of WP_Optimize_Page_Cache_Preloader.
	 *
	 * @return self
	 */
	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

/**
 * Returns a Updraft_Smush_Manager instance
 */
function Updraft_Smush_Manager() {
	return Updraft_Smush_Manager::instance();
}

endif;
