<?php

if (!defined('ABSPATH')) die('No direct access allowed');


if (!class_exists('WP_Optimize_Minify_Load_Url_Task')) require_once(dirname(__FILE__) . '/class-wpo-minify-load-url-task.php');

if (!class_exists('WP_Optimize_Preloader')) require_once(WPO_PLUGIN_MAIN_PATH . 'includes/class-wpo-preloader.php');

class WP_Optimize_Minify_Preloader extends WP_Optimize_Preloader {

	protected $preload_type = 'minify';
	
	protected $task_type = 'minify-load-url-task';

	static protected $_instance = null;

	/**
	 * WP_Optimize_Page_Cache_Preloader constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_filter('cron_schedules', array($this, 'cron_add_intervals'));
	}

	/**
	 * Check if minify is active.
	 *
	 * @return bool
	 */
	public function is_option_active() {
		if (!function_exists('wp_optimize_minify_config')) {
			include_once WPO_PLUGIN_MAIN_PATH . '/minify/class-wp-optimize-minify-config.php';
		}
		return wp_optimize_minify_config()->get('enabled');
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
		$schedules['wpo_minify_preload_continue_interval'] = array(
			'interval' => $interval,
			'display' => round($interval / 60, 1).' minutes'
		);

		return $schedules;
	}

	/**
	 * Create tasks (WP_Optimize_Load_Url_Task) for preload all urls from site.
	 *
	 * @param string $type The preload type (currently: scheduled, manual)
	 * @return void
	 */
	public function create_tasks_for_preload_site_urls($type = 'manual') {
		$urls = $this->get_site_urls();

		if (!empty($urls)) {

			$this->log(__('Minify: Creating tasks for preload site urls.', 'wp-optimize'));

			foreach ($urls as $url) {
				if (wpo_url_in_exceptions($url)) continue;

				// this description is being used for internal purposes.
				$description = 'Preload - '.$url;
				$options = array('url' => $url, 'preload_type' => $type, 'anonymous_user_allowed' => (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI));

				WP_Optimize_Minify_Load_Url_Task::create_task($this->task_type, $description, $options, 'WP_Optimize_Minify_Load_Url_Task');
			}

			$this->log(__('Minify: Tasks for preload site urls created.', 'wp-optimize'));
		}
	}

	/**
	 * Instance of WP_Optimize_Minify_Preloader.
	 *
	 * @return WP_Optimize_Minify_Preloader
	 */
	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new WP_Optimize_Minify_Preloader();
		}

		return self::$_instance;
	}

	/**
	 * Option disabled error message
	 *
	 * @return array
	 */
	protected function get_option_disabled_error() {
		return array(
			'success' => false,
			'error' => __('Minify is disabled.', 'wp-optimize')
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
			'error' => __('Probably minify preload is running already.', 'wp-optimize')
		);
	}

	protected function get_preload_data() {
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir = $cache_path['cachedir'];
		$minify_cache_data = array();
		$minify_cache_data['size'] = esc_html(WP_Optimize_Minify_Cache_Functions::get_cachestats($cache_dir));
		$minify_cache_data['total_size'] = esc_html(WP_Optimize_Minify_Cache_Functions::get_cachestats(WPO_CACHE_MIN_FILES_DIR));
		return $minify_cache_data;
	}

	protected function get_preloading_message($minify_cache_data) {
		return array(
			'done' => false,
			'message' => __('Loading URLs...', 'wp-optimize'),
			'size' => WP_Optimize()->format_size($minify_cache_data['size']),
			'total_size' => $minify_cache_data['total_size']
		);
	}

	protected function get_last_preload_message($minify_cache_data, $last_preload_time_str) {
		return array(
			'done' => true,
			'message' => sprintf(__('Last preload finished at %s', 'wp-optimize'), $last_preload_time_str),
			'size' => WP_Optimize()->format_size($minify_cache_data['size']),
			'total_size' => $minify_cache_data['total_size']
		);
	}

	protected function get_preload_success_message($minify_cache_data) {
		return array(
			'done' => true,
			'size' => WP_Optimize()->format_size($minify_cache_data['size']),
			'total_size' => $minify_cache_data['total_size']
		);
	}

	protected function get_preload_progress_message($minify_cache_data, $preloaded_message, $preload_resuming_in) {
		return array(
			'done' => false,
			'message' => $preloaded_message,
			'size' => WP_Optimize()->format_size($minify_cache_data['size']),
			'total_size' => $minify_cache_data['total_size'],
			'resume_in' => $preload_resuming_in
		);
	}
}

WP_Optimize_Minify_Preloader::instance();
