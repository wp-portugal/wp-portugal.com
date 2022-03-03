<?php
if (!defined('ABSPATH')) die('No direct access allowed');

define('WP_OPTIMIZE_MINIFY_VERSION', '2.6.5');
define('WP_OPTIMIZE_MINIFY_DIR', dirname(__FILE__));
if (!defined('WP_OPTIMIZE_SHOW_MINIFY_ADVANCED')) define('WP_OPTIMIZE_SHOW_MINIFY_ADVANCED', false);

class WP_Optimize_Minify {
	/**
	 * Constructor - Initialize actions and filters
	 *
	 * @return void
	 */
	public function __construct() {

		if (!class_exists('WP_Optimize_Minify_Commands')) include_once(WPO_PLUGIN_MAIN_PATH . 'minify/class-wp-optimize-minify-commands.php');
		$this->minify_commands = new WP_Optimize_Minify_Commands();

		if (!class_exists('WP_Optimize_Minify_Config')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-config.php';
		}

		$this->enabled = wp_optimize_minify_config()->is_enabled();

		$this->load_admin();

		// Don't run the rest if PHP requirement isn't met
		if (!WPO_MINIFY_PHP_VERSION_MET) return;

		add_filter('wpo_cache_admin_bar_menu_items', array($this, 'admin_bar_menu'), 30, 1);
		
		if (WP_Optimize::is_premium()) {
			$this->load_premium();
		}

		/**
		 * Directory that stores the cache, including gzipped files and mobile specifc cache
		 */
		if (!defined('WPO_CACHE_MIN_FILES_DIR')) define('WPO_CACHE_MIN_FILES_DIR', untrailingslashit(WP_CONTENT_DIR).'/cache/wpo-minify');
		if (!defined('WPO_CACHE_MIN_FILES_URL')) define('WPO_CACHE_MIN_FILES_URL', untrailingslashit(WP_CONTENT_URL).'/cache/wpo-minify');

		if (!class_exists('WP_Optimize_Minify_Cache_Functions')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-cache-functions.php';
		}

		$this->load_frontend();

		// cron job to delete old wpo_min cache
		add_action('wpo_minify_purge_old_cache', array('WP_Optimize_Minify_Cache_Functions', 'purge_old'));
		// front-end actions; skip on certain post_types or if there are specific keys on the url or if editor or admin

		// Handle minify cache purging.
		add_action('wp_loaded', array($this, 'handle_purge_minify_cache'));

	}

	/**
	 * Admin toolbar processing
	 *
	 * @param array        $menu_items
	 * @return array
	 */
	public function admin_bar_menu($menu_items) {
		$wpo_minify_options = wp_optimize_minify_config()->get();

		if (!$wpo_minify_options['enabled'] || !current_user_can('manage_options') || !($wpo_minify_options['enable_css'] || $wpo_minify_options['enable_js'])) return $menu_items;
		
		$act_url = remove_query_arg('wpo_minify_cache_purged');
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_size_info = '<h4>'.__('Minify cache', 'wp-optimize').'</h4><span><span class="label">'.__('Cache size:', 'wp-optimize').'</span> <span class="stats">'.esc_html(WP_Optimize_Minify_Cache_Functions::get_cachestats($cache_path['cachedir'])).'</span></span>';

		$menu_items[] = array(
			'id'    => 'wpo_minify_cache_stats',
			'title' => $cache_size_info,
			'meta'  => array(
				'class' => 'wpo-cache-stats',
			),
			'parent' => 'wpo_purge_cache',
		);

		$menu_items[] = array(
			'parent' => 'wpo_purge_cache',
			'id' => 'purge_minify_cache',
			'title' => __('Purge minify cache', 'wp-optimize'),
			'href' => add_query_arg('_wpo_purge_minify_cache', wp_create_nonce('wpo_purge_minify_cache'), $act_url),
		);
		return $menu_items;
	}

	/**
	 * Check if purge single page action sent and purge cache.
	 */
	public function handle_purge_minify_cache() {
		$wpo_minify_options = wp_optimize_minify_config()->get();
		if (!$wpo_minify_options['enabled'] || !current_user_can('manage_options')) return;

		if (isset($_GET['wpo_minify_cache_purged'])) {
			if (is_admin()) {
				add_action('admin_notices', array($this, 'notice_purge_minify_cache_success'));
				return;
			} else {
				$message = __('Minify cache purged', 'wp-optmize');
				printf('<script>alert("%s");</script>', $message);
				return;
			}
		}

		if (!isset($_GET['_wpo_purge_minify_cache'])) return;
		
		if (wp_verify_nonce($_GET['_wpo_purge_minify_cache'], 'wpo_purge_minify_cache')) {
			$success = false;

			// Purge minify
			$results = $this->minify_commands->purge_minify_cache();
			if ("caches cleared" == $results['result']) $success = true;

			// remove nonce from url and reload page.
			wp_redirect(add_query_arg('wpo_minify_cache_purged', $success, remove_query_arg('_wpo_purge_minify_cache')));
			exit;

		}
	}

	/**
	 * Load the admin class
	 *
	 * @return void
	 */
	private function load_admin() {
		if (!is_admin()) return;

		if (!class_exists('WP_Optimize_Minify_Admin')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-admin.php';
		}
		new WP_Optimize_Minify_Admin();
	}

	/**
	 * Load the frontend class
	 *
	 * @return void
	 */
	private function load_frontend() {
		if ($this->enabled) {
			if (!class_exists('WP_Optimize_Minify_Front_End')) {
				include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-front-end.php';
			}
			new WP_Optimize_Minify_Front_End();
		}
	}

	/**
	 * Load the premium class
	 *
	 * @return void
	 */
	private function load_premium() {
		if (!class_exists('WP_Optimize_Minify_Premium')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-premium.php';
		}
		$this->premium = new WP_Optimize_Minify_Premium();
	}

	/**
	 * Run during activation
	 * Increment cache first as it will save files to that dir
	 *
	 * @return void
	 */
	public function plugin_activate() {
		// increment cache time
		if (class_exists('WP_Optimize_Minify_Cache_Functions')) {
			WP_Optimize_Minify_Cache_Functions::cache_increment();
		}
		
		// old cache purge event cron
		wp_clear_scheduled_hook('wpo_minify_purge_old_cache');
		if (!wp_next_scheduled('wpo_minify_purge_old_cache')) {
			wp_schedule_event(time() + 86400, 'daily', 'wpo_minify_purge_old_cache');
		}
	}

	/**
	 * Run during plugin deactivation
	 *
	 * @return void
	 */
	public function plugin_deactivate() {
		if (defined('WPO_MINIFY_PHP_VERSION_MET') && !WPO_MINIFY_PHP_VERSION_MET) return;
		if (class_exists('WP_Optimize_Minify_Cache_Functions')) {
			WP_Optimize_Minify_Cache_Functions::purge_temp_files();
			WP_Optimize_Minify_Cache_Functions::purge_old();
			WP_Optimize_Minify_Cache_Functions::purge_others();
		}

		// old cache purge event cron
		wp_clear_scheduled_hook('wpo_minify_purge_old_cache');
	}

	/**
	 * Run during plugin uninstall
	 *
	 * @return void
	 */
	public function plugin_uninstall() {
		if (defined('WPO_MINIFY_PHP_VERSION_MET') && !WPO_MINIFY_PHP_VERSION_MET) return;
		// remove options from DB
		if (!function_exists('wp_optimize_minify_config')) {
			include WP_OPTIMIZE_MINIFY_DIR.'/class-wp-optimize-minify-config.php';
		}
		wp_optimize_minify_config()->purge();
		// remove minified files
		if (class_exists('WP_Optimize_Minify_Cache_Functions')) {
			WP_Optimize_Minify_Cache_Functions::purge();
			WP_Optimize_Minify_Cache_Functions::purge_others();
		}
	}

	/**
	 * Shows success notice for purge minify cache
	 */
	public function notice_purge_minify_cache_success() {
		$this->show_notice(__('The minify cache was successfully purged.', 'wp-optimize'), 'success');
	}

	/**
	 * Show notification in WordPress admin.
	 *
	 * @param string $message HTML (no further escaping is performed)
	 * @param string $type    error, warning, success, or info
	 */
	public function show_notice($message, $type) {
		global $current_screen;
		
		if ($current_screen && is_callable(array($current_screen, 'is_block_editor')) && $current_screen->is_block_editor()) :
		?>
			<script>
				window.addEventListener('load', function() {
					(function(wp) {
						if (window.wp && wp.hasOwnProperty('data') && 'function' == typeof wp.data.dispatch) {
							wp.data.dispatch('core/notices').createNotice(
								'<?php echo $type; ?>',
								'<?php echo $message; ?>',
								{
									isDismissible: true,
								}
							);
						}
					})(window.wp);
				});
			</script>
		<?php else : ?>
			<div class="notice wpo-notice notice-<?php echo $type; ?> is-dismissible">
				<p><?php echo $message; ?></p>
			</div>
		<?php
		endif;
	}
}
