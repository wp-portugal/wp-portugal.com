<?php

if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('Updraft_Task_Manager_1_3')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-manager.php');

abstract class WP_Optimize_Preloader extends Updraft_Task_Manager_1_3 {

	protected $options;

	/**
	 * WP_Optimize_Page_Cache_Preloader constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->options = WP_Optimize()->get_options();
		// setup loggers
		$this->set_loggers(WP_Optimize()->wpo_loggers());

		add_action('wpo_' . $this->preload_type . '_preload_continue', array($this, 'process_tasks_queue'));
		add_filter('updraft_interrupt_tasks_queue_'.$this->task_type, array($this, 'maybe_interrupt_queue'), 20);
	}

	/**
	 * Get a schedule interval
	 *
	 * @param string $schedule_key The schedule to check
	 * @return integer
	 */
	protected function get_schedule_interval($schedule_key) {
		$schedules = wp_get_schedules();
		if (!isset($schedules[$schedule_key])) {
			$this->log('Could not get interval for event of type '.$schedule_key);
			return 0;
		}
		return isset($schedules[$schedule_key]['interval']) ? $schedules[$schedule_key]['interval'] : 0;
	}

	/**
	 * Get the interval to continuing a preload task
	 *
	 * @return integer
	 */
	protected function get_continue_preload_cron_interval() {
		/**
		 * Filters the interval between each preload attempt, in seconds.
		 */
		return (int) apply_filters('wpo_' . $this->preload_type . '_preload_continue_interval', 600);
	}

	/**
	 * Schedule action for continuously preload.
	 */
	public function schedule_preload_continue_action() {
		$continue_in = wp_next_scheduled('wpo_' . $this->preload_type .'_preload_continue');

		// Action is still scheduled
		if ($continue_in && $continue_in > 0) return;
		// Action is overdue, delete it and re schedule it
		if ($continue_in && $continue_in < 0) $this->delete_preload_continue_action();

		wp_schedule_event(time() + $this->get_schedule_interval('wpo_' . $this->preload_type . '_preload_continue_interval'), 'wpo_' . $this->preload_type . '_preload_continue_interval', 'wpo_' . $this->preload_type . '_preload_continue');
	}

	/**
	 * Delete scheduled action for continuously preload.
	 */
	public function delete_preload_continue_action() {
		wp_clear_scheduled_hook('wpo_' . $this->preload_type . '_preload_continue');
	}

	/**
	 * Run preload. If task queue is empty it creates tasks for site urls.
	 *
	 * @param string $type     - The preload type (schedule | manual)
	 * @param array  $response - Specific response for echo into output thread when browser connection closing.
	 * @return array|void - Void when closing the browser connection
	 */
	public function run($type = 'scheduled', $response = null) {
		if (!$this->is_option_active()) {
			return $this->get_option_disabled_error();
		}

		do_action('wpo_before_' . $this->preload_type . '_preload');

		if (empty($response)) {
			$response = array('success' => true);
		}

		$this->delete_cancel_flag();

		// trying to lock semaphore.

		$creating_tasks_semaphore = new Updraft_Semaphore_3_0('wpo_' . $this->preload_type . '_preloader_creating_tasks');
		$lock = $creating_tasks_semaphore->lock();

		// if semaphore haven't locked then just return response.
		if (!$lock) {
			return $this->get_preload_already_running_error();
		}

		$is_wp_cli = defined('WP_CLI') && WP_CLI;

		// close browser connection and continue work.
		// don't close connection for WP-CLI
		if (false == $is_wp_cli) {
			WP_Optimize()->close_browser_connection(json_encode($response));
		}

		// trying to change time limit.
		WP_Optimize()->change_time_limit();

		$status = $this->get_status($this->task_type);

		if (0 == $status['all_tasks'] && $lock) {
			if (is_multisite()) {
				$sites = WP_Optimize()->get_sites();

				foreach ($sites as $site) {
					switch_to_blog($site->blog_id);
					$this->create_tasks_for_preload_site_urls($type);
					restore_current_blog();
				}
			} else {
				$this->create_tasks_for_preload_site_urls($type);
			}
		}

		if ($lock) $creating_tasks_semaphore->release();

		$this->process_tasks_queue();

		// return $response in WP-CLI mode
		if ($is_wp_cli) {
			return $response;
		}
	}

	/**
	 * Process tasks queue.
	 */
	public function process_tasks_queue() {
		// schedule continue preload action.
		$this->schedule_preload_continue_action();

		if (!$this->process_queue($this->task_type)) {
			return;
		}

		// delete scheduled continue preload action.
		$this->delete_preload_continue_action();

		// update last preload time only if processing any tasks, else process was cancelled.
		if ($this->is_running()) {
			$this->options->update_option('wpo_last_' . $this->preload_type . '_preload', time());
		}

		$this->clean_up_old_tasks($this->task_type);
	}

	/**
	 * Find out if the current queue should be interrupted
	 *
	 * @param boolean $interrupt
	 * @return boolean
	 */
	public function maybe_interrupt_queue($interrupt) {

		if ($interrupt) return $interrupt;

		static $memory_threshold = null;
		if (null == $memory_threshold) {
			/**
			 * Filters the minimum memory required before stopping a queue. Default: 10MB
			 */
			$memory_threshold = apply_filters('wpo_' . $this->preload_type . '_preload_memory_threshold', 10485760);
		}

		return WP_Optimize()->get_free_memory() < $memory_threshold;
	}

	/**
	 * Delete all preload tasks from queue.
	 */
	public function cancel_preload() {
		$this->set_cancel_flag();
		$this->delete_tasks($this->task_type);
		$this->delete_preload_continue_action();
	}

	/**
	 * Set 'cancel' option to true.
	 */
	public function set_cancel_flag() {
		$this->options->update_option("last_{$this->preload_type}_preload_cancel", true);
	}

	/**
	 * Delete 'cancel' option.
	 */
	public function delete_cancel_flag() {
		$this->options->delete_option('last_' . $this->preload_type . '_preload_cancel');
	}

	/**
	 * Check if the last preload is cancelled.
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		return $this->options->get_option("last_{$this->preload_type}_preload_cancel", false);
	}

	/**
	 * Check if preloading queue is processing.
	 *
	 * @return bool
	 */
	public function is_busy() {
		return $this->is_semaphore_locked($this->task_type) || $this->is_semaphore_locked('wpo_' . $this->preload_type . '_preloader_creating_tasks');
	}

	/**
	 * Get current status of preloading urls.
	 *
	 * @return array
	 */
	public function get_status_info() {

		$status = $this->get_status($this->task_type);
		$preload_data = $this->get_preload_data();

		if ($this->is_semaphore_locked('wpo_' . $this->preload_type . '_preloader_creating_tasks') && !$this->is_cancelled()) {
			// we are still creating tasks.
			return $this->get_preloading_message($preload_data);
		} elseif ($status['complete_tasks'] == $status['all_tasks']) {
			$gmt_offset = (int) (3600 * get_option('gmt_offset'));

			$last_preload_time = $this->options->get_option('wpo_last_' . $this->preload_type . '_preload');

			if ($last_preload_time) {
				$last_preload_time_str = date_i18n(get_option('time_format').', '.get_option('date_format'), $last_preload_time + $gmt_offset);
				return $this->get_last_preload_message($preload_data, $last_preload_time_str);
			} else {
				return $this->get_preload_success_message($preload_data);
			}
		} else {
			$preload_resuming_time = wp_next_scheduled('wpo_' . $this->preload_type . '_preload_continue');
			$preload_resuming_in = $preload_resuming_time ? $preload_resuming_time - time() : 0;
			$preloaded_message = sprintf(_n('%1$s out of %2$s URL preloaded', '%1$s out of %2$s URLs preloaded', $status['all_tasks'], 'wp-optimize'), $status['complete_tasks'], $status['all_tasks']);
			if ('sitemap' == $this->options->get_option('wpo_last_' . $this->preload_type . '_preload_type', '')) {
				$preloaded_message = __('Preloading posts found in sitemap:', 'wp-optimize') .' '. $preloaded_message;
			}
			$return = $this->get_preload_progress_message($preload_data, $preloaded_message, $preload_resuming_in);
			if (defined('DOING_AJAX') && DOING_AJAX) {
				// if no cron was found or cron is overdue more than 20s, trigger it
				if (!$preload_resuming_time || $preload_resuming_in < -20) {
					$this->run($return);
				}
			}
			return $return;
		}
	}

	/**
	 * Check if preload action in process.
	 *
	 * @return bool
	 */
	public function is_running() {
		$status = $this->get_status($this->task_type);

		if ($status['all_tasks'] > 0) return true;
	}

	/**
	 * Preload desktop version from url.
	 *
	 * @param string $url
	 *
	 * @return void
	 */
	public function preload_desktop($url) {
		$desktop_args = array(
			'httpversion' => '1.1',
			'user-agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Safari/537.36',
			'timeout'     => 10,
			'headers'     => apply_filters('wpo_preload_headers', array()),
		);

		$desktop_args = apply_filters('wpo_' . $this->preload_type . '_preloader_desktop_args', $desktop_args, $url);

		$this->log('preload_desktop - '. $url);

		wp_remote_get($url, $desktop_args);
	}

	/**
	 * Preload amp version from $url.
	 *
	 * @param string $url
	 *
	 * @return void
	 */
	public function preload_amp($url) {
		if (!apply_filters('wpo_should_preload_amp', false, $url)) return;

		$amp_args = array(
			'httpversion' => '1.1',
			'user-agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Safari/537.36',
			'timeout'     => 10,
			'headers'     => apply_filters('wpo_preload_headers', array()),
		);

		$url = untrailingslashit($url) . '/amp/';

		$amp_args = apply_filters('wpo_' . $this->preload_type . '_preloader_amp_args', $amp_args, $url);

		$this->log('preload_amp - ' . $url);

		wp_remote_get($url, $amp_args);
	}

	/**
	 * Get sitemap filename.
	 *
	 * @return string
	 */
	protected function get_sitemap_filename() {
		/**
		 * Filter the sitemap file used to collect the URLs to preload
		 *
		 * @param string $filename - The sitemap name
		 * @default sitemap.xml
		 */
		return apply_filters('wpo_cache_preload_sitemap_filename', 'sitemap.xml');
	}

	/**
	 * Check if sitemap exists then returns list of urls from sitemap file otherwise returns all posts urls.
	 *
	 * @return array
	 */
	public function get_site_urls() {

		$urls = $this->get_sitemap_urls();

		if (!empty($urls)) {
			$this->options->update_option('wpo_last_' . $this->preload_type . '_preload_type', 'sitemap');
		} else {
			$urls = $this->get_post_urls();
			$this->options->update_option('wpo_last_' . $this->preload_type . '_preload_type', 'posts');
		}

		$this->log(sprintf(_n('%d url found.', '%d urls found.', count($urls), 'wp-optimize'), count($urls)));

		/**
		 * Filter the URLs which will be preloaded
		 *
		 * @param array $urls
		 * @return array
		 */
		return apply_filters('wpo_preload_get_site_urls', $urls);
	}

	/**
	 * Loads sitemap file and returns list of urls.
	 *
	 * @param string $sitemap_url
	 *
	 * @return array|bool
	 */
	public function get_sitemap_urls($sitemap_url = '') {

		$urls = array();

		// if sitemap url is empty then use main sitemap file name.
		$sitemap_url = ('' === $sitemap_url) ? site_url('/'.$this->get_sitemap_filename()) : $sitemap_url;

		// if simplexml_load_string not available then we don't load sitemap.
		if (!function_exists('simplexml_load_string')) {
			return $urls;
		}

		// load sitemap file.
		$response = wp_remote_get($sitemap_url, array('timeout' => 30));

		// if we get error then
		if (is_wp_error($response)) {
			$response = file_get_contents($sitemap_url);

			// if response is empty then try load from file.
			if (empty($response) && '' == $sitemap_url) {
				$sitemap_file = $this->get_local_sitemap_file();

				$response = file_get_contents($sitemap_file);
			}

			if (empty($response)) return $urls;

			$xml = @simplexml_load_string($response); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		} else {
			// parse xml answer.
			$xml = @simplexml_load_string(wp_remote_retrieve_body($response)); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		// xml file has not valid xml content then return false.
		if (false === $xml) return false;

		// if exists urls then return them.
		if (isset($xml->url)) {
			foreach ($xml->url as $element) {
				if (!isset($element->loc)) continue;
				$urls[] = (string) $element->loc;
			}
		} elseif (isset($xml->sitemap)) {
			// if has links to other sitemap files then get urls from them.
			foreach ($xml->sitemap as $element) {
				if (!isset($element->loc)) continue;

				$sitemap_urls = $this->get_sitemap_urls($element->loc);

				if (is_array($sitemap_urls)) {
					$urls = array_merge($urls, $sitemap_urls);
				}
			}
		}

		return $urls;
	}

	/**
	 * Get the path to a local sitemap file
	 *
	 * @return string
	 */
	private function get_local_sitemap_file() {
		if (!function_exists('get_home_path')) {
			include_once ABSPATH . '/wp-admin/includes/file.php';
		}
		return trailingslashit(get_home_path()) . $this->get_sitemap_filename();
	}

	/**
	 * Get all posts of any post type and returns urls for them.
	 *
	 * @return array
	 */
	public function get_post_urls() {
		global $post;

		$offset = 0;
		$posts_per_page = 1000;
		$urls = array();

		$urls[] = site_url('/');

		do {
			$query = new WP_Query(array(
				'post_type'         => 'any',
				'post_status'       => 'publish',
				'posts_per_page'    => $posts_per_page,
				'offset'            => $offset,
				'orderby'           => 'ID',
				'order'             => 'ASC',
				'cache_results'     => false, // disable cache to avoid memory error.
			));

			$posts_loaded = $query->post_count;

			while ($query->have_posts()) {
				$query->the_post();
				$permalink = get_permalink();
				$urls[] = $permalink;

				// check page separators in the post content
				preg_match_all('/\<\!--nextpage--\>/', $post->post_content, $matches);
				// if there any separators add urls for each page
				if (count($matches[0])) {
					$prefix = strpos($permalink, '?') ? '&page=' : '';
					for ($page = 0; $page < count($matches[0]); $page++) {
						if ('' != $prefix) {
							$urls[] = $permalink . $prefix . ($page+2);
						} else {
							$urls[] = trailingslashit($permalink) . ($page+2);
						}
					}
				}
			}

			$offset += $posts_loaded;
		} while ($posts_loaded > 0);

		/**
		 * If domain mapping enabled then replace domains in urls.
		 */
		if ($this->is_domain_mapping_enabled()) {
			$blog_id = get_current_blog_id();

			$mapped_domain = $this->get_mapped_domain($blog_id);
			$blog_details = get_blog_details($blog_id);

			if ($mapped_domain) {
				foreach ($urls as $i => $url) {
					$urls[$i] = preg_replace('/'.$blog_details->domain.'/i', $mapped_domain, $url, 1);
				}
			}
		}

		wp_reset_postdata();

		return $urls;
	}

	/**
	 * Check if domain mapping enabled.
	 *
	 * @return bool
	 */
	public function is_domain_mapping_enabled() {
		// SUNRISE constant is defined with installation WordPress MU Domain Mapping plugin.
		$enabled = is_multisite() && defined('SUNRISE') && 'on' == strtolower(SUNRISE);

		/**
		 * Filters if Multisite Domain mapping is enabled.
		 * Currently, we can only detect if the WordPress MU Domain Mapping plugin is in use.
		 * Using the WP Core functionality should not require this, unless if the domain name is set somewhere else but in the site url option.
		 */
		return apply_filters('wpo_is_domain_mapping_enabled', $enabled);
	}

	/**
	 * Return mapped domain by $blog_id.
	 *
	 * @param int $blog_id
	 *
	 * @return string
	 */
	public function get_mapped_domain($blog_id) {
		global $wpdb;

		$domain = '';
		$multisite_plugin_table_name = $wpdb->base_prefix.'domain_mapping';
		// Check if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$multisite_plugin_table_name'") != $multisite_plugin_table_name) {
			// This table created in WordPress MU Domain Mapping plugin.
			$row = $wpdb->get_row("SELECT `domain` FROM {$multisite_plugin_table_name} WHERE `blog_id` = {$blog_id} AND `active` = 1", ARRAY_A);
			if (!empty($row)) {
				$domain = $row['domain'];
			}
		} else {
			// When using the WP Core method, the site url option contains the mapped domain.
			$domain = get_site_url($blog_id);
		}

		/**
		 * Filters the mapped domain name
		 *
		 * @param string  $domain  The domain name
		 * @param integer $blog_id The blog ID
		 */
		return apply_filters('wpo_get_mapped_domain', $domain, $blog_id);
	}

	/**
	 * Captures and logs any interesting messages
	 *
	 * @param String $message    - the error message
	 * @param String $error_type - the error type
	 */
	public function log($message, $error_type = 'info') {

		if (isset($this->loggers)) {
			foreach ($this->loggers as $logger) {
				$logger->log($message, $error_type);
			}
		}
	}

	/**
	 * Check if semaphore is locked.
	 *
	 * @param string $semaphore
	 * @return bool
	 */
	protected function is_semaphore_locked($semaphore) {
		$semaphore = new Updraft_Semaphore_3_0($semaphore);
		if ($semaphore->lock()) {
			$semaphore->release();
			return false;
		}
		return true;
	}

	abstract protected function is_option_active();

	abstract protected function get_option_disabled_error();

	abstract protected function get_preload_already_running_error();

	abstract protected function create_tasks_for_preload_site_urls($type);

	abstract protected function get_preload_data();

	abstract protected function get_preloading_message($data);

	abstract protected function get_last_preload_message($data, $last_preload_time_str);

	abstract protected function get_preload_success_message($data);

	abstract protected function get_preload_progress_message($data, $preloaded_message, $preload_resuming_in);
}
