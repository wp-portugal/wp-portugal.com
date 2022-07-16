<?php

if (!defined('ABSPATH')) die('No direct access allowed');


class WP_Optimize_Page_Cache_Preloader extends WP_Optimize_Preloader {

	protected $preload_type = 'page_cache';

	protected $task_type = 'load-url-task';

	static protected $_instance = null;

	/**
	 * WP_Optimize_Page_Cache_Preloader constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_filter('cron_schedules', array($this, 'cron_add_intervals'));
		add_action('wpo_page_cache_schedule_preload', array($this, 'run_scheduled_cache_preload'));
		add_filter('wpo_preload_headers', array($this, 'preload_headers'));
	}

	/**
	 * Check if cache is active.
	 *
	 * @return bool
	 */
	public function is_option_active() {
		return WP_Optimize()->get_page_cache()->is_enabled();
	}

	/**
	 * Schedule or delete automatic preload action on cache settings update.
	 *
	 * @param array $new_settings      The new settings
	 * @param array $previous_settings Settings before saving
	 */
	public function cache_settings_updated($new_settings, $previous_settings) {
		if (!$new_settings['enable_page_caching']) {
			wp_clear_scheduled_hook('wpo_page_cache_schedule_preload');
			$this->delete_preload_continue_action();
			return;
		}

		if (!empty($new_settings['enable_schedule_preload'])) {

			$last_schedule_type = $previous_settings['preload_schedule_type'];

			if (wp_next_scheduled('wpo_page_cache_schedule_preload')) {
				// if already scheduled this schedule type
				if ($new_settings['preload_schedule_type'] == $last_schedule_type) {
					// If the schedule type is cache lifespan, check if the cache lifespan changed.
					if ('wpo_use_cache_lifespan' == $new_settings['preload_schedule_type']) {
						// Else, if the settings cache lifespan settings haven't changed, returns
						if ($new_settings['page_cache_length_value'] == $previous_settings['page_cache_length_value'] && $new_settings['page_cache_length_unit'] == $previous_settings['page_cache_length_unit']) {
							return;
						}
					} else {
						return;
					}
				}
				// clear currently scheduled preload action.
				wp_clear_scheduled_hook('wpo_page_cache_schedule_preload');
			}
			// schedule preload action.
			wp_schedule_event((time() + $this->get_schedule_interval($new_settings['preload_schedule_type'])), $new_settings['preload_schedule_type'], 'wpo_page_cache_schedule_preload');
		} else {
			wp_clear_scheduled_hook('wpo_page_cache_schedule_preload');
		}

	}

	/**
	 * Clear active preload tasks, reschedule preload action.
	 */
	public function reschedule_preload() {
		// clear scheduled action.
		if (wp_next_scheduled('wpo_page_cache_schedule_preload')) {
			wp_clear_scheduled_hook('wpo_page_cache_schedule_preload');
		}

		// schedule preload action if need.
		if ($this->is_scheduled_preload_enabled()) {
			$preload_schedule_type = $this->get_cache_config('preload_schedule_type');
			wp_schedule_event(time() + $this->get_schedule_interval($preload_schedule_type), $preload_schedule_type, 'wpo_page_cache_schedule_preload');
		}
	}

	/**
	 * Check if scheduled preload enabled.
	 *
	 * @return bool
	 */
	public function is_scheduled_preload_enabled() {
		$enable_schedule_preload = $this->get_cache_config('enable_schedule_preload');
		return !empty($enable_schedule_preload);
	}

	/**
	 * Add intervals to cron schedules.
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function cron_add_intervals($schedules) {
		$interval = $this->get_continue_preload_cron_interval();
		$schedules['wpo_page_cache_preload_continue_interval'] = array(
			'interval' => $interval,
			'display' => round($interval / 60, 1).' minutes'
		);

		$schedules['wpo_use_cache_lifespan'] = array(
			'interval' => WPO_Cache_Config::instance()->get_option('page_cache_length'),
			'display' => 'Same as cache lifespan: '.WPO_Cache_Config::instance()->get_option('page_cache_length_value').' '.WPO_Cache_Config::instance()->get_option('page_cache_length_unit')
		);

		return $schedules;
	}


	/**
	 * Check if we need run cache preload and run it.
	 */
	public function run_scheduled_cache_preload() {

		$schedule_type = WPO_Cache_Config::instance()->get_option('preload_schedule_type');
		if (!$schedule_type) return;

		// Don't run preload if cache lifespan option enabled and cache not expired yet.
		if ('wpo_use_cache_lifespan' == $schedule_type) {

			/**
			 * Filters the allowed time difference between the cache exiry and the current time, in seconds.
			 * If the cache expires in less than $allowed_time_difference, preload. Otherwise leave it.
			 *
			 * @param integer $allowed_time_difference The time difference, in seconds (default = 600)
			 */
			$allowed_time_difference = apply_filters('wpo_preload_allowed_time_difference', 600);
			$page_cache_lifespan = WPO_Cache_Config::instance()->get_option('page_cache_length', 0);
			$last_preload_time = $this->options->get_option('wpo_last_page_cache_preload', 0);
			$time_since_last_preload = time() - $last_preload_time;
			$minimum_time_to_next_schedule_preload = $page_cache_lifespan - $allowed_time_difference;
			// Skip this if the last preload is not as old as the cache lifespan minus $allowed_time_difference
			if ($page_cache_lifespan > 0 && $time_since_last_preload < $minimum_time_to_next_schedule_preload) return;
		}

		$this->run();
	}


	/**
	 * Get cache config option value.
	 *
	 * @return mixed
	 */
	public function get_cache_config($option) {
		static $config = null;

		if (null === $config) $config = WPO_Page_Cache::instance()->config->get();

		if (is_array($config) && array_key_exists($option, $config)) {
			return $config[$option];
		}

		return false;
	}

	/**
	 * Create tasks (WP_Optimize_Load_Url_Task) for preload all urls from site.
	 *
	 * @param string $type The preload type (currently: scheduled, manual)
	 * @return void
	 */
	public function create_tasks_for_preload_site_urls($type) {
		$urls = $this->get_site_urls();

		if (!empty($urls)) {

			$this->log(__('Creating tasks for preload site urls.', 'wp-optimize'));

			foreach ($urls as $url) {
				if (wpo_url_in_exceptions($url)) continue;

				if ($this->url_is_already_cached($url, $type)) {
					continue;
				}

				// this description is being used for internal purposes.
				$description = 'Preload - '.$url;
				$options = array('url' => $url, 'preload_type' => $type, 'anonymous_user_allowed' => (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI));

				if (!class_exists('WP_Optimize_Load_Url_Task')) {
					require_once WPO_PLUGIN_MAIN_PATH . 'cache/class-wpo-load-url-task.php';
				}
				WP_Optimize_Load_Url_Task::create_task($this->task_type, $description, $options, 'WP_Optimize_Load_Url_Task');
			}

			$this->log(__('Tasks for preload site urls created.', 'wp-optimize'));
		}
	}

	/**
	 * Preload mobile version from $url.
	 *
	 * @param string $url
	 *
	 * @return void
	 */
	public function preload_mobile($url) {
		static $is_mobile_caching_enabled;
		if (!isset($is_mobile_caching_enabled)) {
			$is_mobile_caching_enabled = $this->get_cache_config('enable_mobile_caching');
		}

		// Only run if option is active
		if (!$is_mobile_caching_enabled) return;

		$mobile_args = array(
			'httpversion' => '1.1',
			'user-agent'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1',
			'timeout'     => 10,
			'headers'     => apply_filters('wpo_preload_headers', array()),
		);

		$mobile_args = apply_filters('wpo_page_cache_preloader_mobile_args', $mobile_args, $url);

		$this->log('preload_mobile - ' . $url);

		wp_remote_get($url, $mobile_args);
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
			'headers'     => array(
				'X-WP-Optimize-Cache-Preload' => 'Yes',
			),
		);

		$url = untrailingslashit($url) . '/amp/';

		$amp_args = apply_filters('wpo_page_cache_preloader_amp_args', $amp_args, $url);

		$this->log('preload_amp - ' . $url);

		wp_remote_get($url, $amp_args);
	}

	/**
	 * Check if sitemap exists then returns list of urls from sitemap file otherwise returns all posts urls.
	 *
	 * @return array
	 */
	public function get_site_urls() {

		$urls = $this->get_sitemap_urls();

		if (!empty($urls)) {
			$this->options->update_option('wpo_last_page_cache_preload_type', 'sitemap');
		} else {
			$urls = $this->get_post_urls();
			$this->options->update_option('wpo_last_page_cache_preload_type', 'posts');
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
	 * Instance of WP_Optimize_Page_Cache_Preloader.
	 *
	 * @return WP_Optimize_Page_Cache_Preloader
	 */
	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new WP_Optimize_Page_Cache_Preloader();
		}

		return self::$_instance;
	}

	/**
	 * Check if the URL is already cached, or needs to be preloaded
	 *
	 * @param string $url          The preloaded url
	 * @param string $preload_type The preload type (manual | scheduled)
	 * @return boolean
	 */
	private function url_is_already_cached($url, $preload_type) {
		static $files = array();
		$regenerate_count = 0;
		$folder = trailingslashit(WPO_CACHE_FILES_DIR) . wpo_get_url_path($url);
		// If the folder does not exist, consider the URL as cleared
		if (!is_dir($folder)) return false;

		if (empty($files)) {
			// Check only the base files
			$files[] = 'index.html';

			if (WPO_Cache_Config::instance()->get_option('enable_mobile_caching')) {
				$files[] = 'mobile.index.html';
			}
			$files = apply_filters('wpo_maybe_clear_files_list', $files);
		}

		foreach ($files as $file) {
			$file_path = trailingslashit($folder).$file;
			if (!file_exists($file_path)) {
				// The file does not exist, count it as "deleted"
				$regenerate_count++;
				continue;
			}

			if ($this->should_regenerate_file($file_path, $preload_type)) {
				// delefe the expired cache file
				unlink($file_path);
				$regenerate_count++;
			}
		}

		// if 0 == $regenerate_count, nothing all the expected files exist, and none were deleted.
		return 0 == $regenerate_count;
	}

	/**
	 * Determine if a file should be regenerated
	 *
	 * @param string $path         The file to check
	 * @param string $preload_type The preload type (manual | scheduled)
	 *
	 * @return boolean
	 */
	private function should_regenerate_file($path, $preload_type) {
		// Store the variables, as they'll be used for each file and each file
		static $is_preloader_scheduled = null;
		static $lifespan = null;
		static $schedule_type = null;
		static $schedule_interval = null;
		static $lifespan_expiry_threshold = null;
		static $always_regenerate_file_if_preload_is_manual = null;
		static $always_regenerate_file_if_preload_is_scheduled = null;
		static $regenerate_file_when_no_expiry_date = null;

		// Sets the variables once per request:
		if (null === $is_preloader_scheduled) {
			$is_preloader_scheduled = WPO_Cache_Config::instance()->get_option('enable_schedule_preload');
			$schedule_type = WPO_Cache_Config::instance()->get_option('preload_schedule_type');
			$lifespan = WPO_Cache_Config::instance()->get_option('page_cache_length');
			$schedule_interval = $this->get_schedule_interval($schedule_type);

			/**
			 * Expiry threshold: the current file will be considered stale if within the threshold. Default: 600s (10min)
			 */
			$lifespan_expiry_threshold = apply_filters('wpo_lifespan_expiry_threshold', 600);

			/**
			 * Filters if a cache should systematically be regenerated when running a manual preload. Default: false
			 */
			$always_regenerate_file_if_preload_is_manual = apply_filters('wpo_always_regenerate_file_if_preload_is_manual', false);

			/**
			 * Filters if a cache should systematically be regenerated when running a scheduled preload. Default: false
			 */
			$always_regenerate_file_if_preload_is_scheduled = apply_filters('wpo_always_regenerate_file_if_preload_is_scheduled', false);

			/**
			 * Filters if a cache should systematically be regenerated when running a preload and no schedule is set, and cache does not expire. Default: true
			 */
			$regenerate_file_when_no_expiry_date = apply_filters('wpo_regenerate_file_when_no_expiry_date', true);
		}

		if (($always_regenerate_file_if_preload_is_manual && 'manual' == $preload_type) || ($always_regenerate_file_if_preload_is_scheduled && 'scheduled' == $preload_type)) {
			$result = true;
		} else {

			$modified_time = (int) filemtime($path);

			// cache lifespan is set.
			if (0 != $lifespan) {
				$expiry_time = $modified_time + $lifespan - $lifespan_expiry_threshold;
				$result = time() > $expiry_time;
			} elseif ($is_preloader_scheduled) {
				$expiry_time = $modified_time + $schedule_interval - $lifespan_expiry_threshold;
				$result = time() > $expiry_time;
			} else {
				$result = $regenerate_file_when_no_expiry_date;
			}
			
		}
		
		return apply_filters('wpo_preloader_should_regenerate_file', $result, $path, $preload_type);
	}

	/**
	 * Add preloader headers
	 */
	public function preload_headers($headers) {
		$headers['X-WP-Optimize-Cache-Preload'] = 'Yes';
		return $headers;
	}

	/**
	 * Option disabled error message
	 *
	 * @return array
	 */
	protected function get_option_disabled_error() {
		return array(
			'success' => false,
			'error' => __('Page cache is disabled.', 'wp-optimize')
		);
	}

	/**
	 * Get preload already running error message
	 *
	 * @return array
	 */
	protected function get_preload_already_running_error() {
		return array(
			'success' => false,
			'error' => __('Probably page cache preload is running already.', 'wp-optimize')
		);
	}

	protected function get_preload_data() {
		return WP_Optimize()->get_page_cache()->get_cache_size();
	}

	protected function get_preloading_message($cache_size) {
		return array(
			'done' => false,
			'message' => __('Loading URLs...', 'wp-optimize'),
			'size' => WP_Optimize()->format_size($cache_size['size']),
			'file_count' => $cache_size['file_count']
		);
	}

	protected function get_last_preload_message($cache_size, $last_preload_time_str) {
		return array(
			'done' => true,
			'message' => sprintf(__('Last preload finished at %s', 'wp-optimize'), $last_preload_time_str),
			'size' => WP_Optimize()->format_size($cache_size['size']),
			'file_count' => $cache_size['file_count']
		);
	}

	protected function get_preload_success_message($cache_size) {
		return array(
			'done' => true,
			'size' => WP_Optimize()->format_size($cache_size['size']),
			'file_count' => $cache_size['file_count']
		);
	}

	protected function get_preload_progress_message($cache_size, $preloaded_message, $preload_resuming_in) {
		return array(
			'done' => false,
			'message' => $preloaded_message,
			'size' => WP_Optimize()->format_size($cache_size['size']),
			'file_count' => $cache_size['file_count'],
			'resume_in' => $preload_resuming_in
		);
	}
}

WP_Optimize_Page_Cache_Preloader::instance();
