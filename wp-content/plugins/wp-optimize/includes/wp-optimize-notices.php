<?php

if (!defined('WPO_PLUGIN_MAIN_PATH')) die('No direct access allowed');

if (!class_exists('Updraft_Notices_1_1')) require_once(WPO_PLUGIN_MAIN_PATH.'includes/updraft-notices.php');

class WP_Optimize_Notices extends Updraft_Notices_1_1 {

	protected static $_instance = null;

	private $initialized = false;

	protected $self_affiliate_id = 216;

	protected $notices_content = array();

	/**
	 * Creates and returns the only notice instance
	 *
	 * @return object WP_Optimize_Notices instance
	 */
	public static function instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * This method gets any parent notices and adds its own notices to the notice array
	 *
	 * @return Array returns an array of notices
	 */
	protected function populate_notices_content() {
		
		$parent_notice_content = parent::populate_notices_content();

		$child_notice_content = array(
			'updraftplus' => array(
				'prefix' => '',
				'title' => __('Make sure you backup before you optimize your database', 'wp-optimize'),
				'text' => __("UpdraftPlus is the world's most trusted backup plugin from the owners of WP-Optimize", 'wp-optimize'),
				'image' => 'notices/updraft_logo.png',
				'button_link' => 'https://wordpress.org/plugins/updraftplus/',
				'button_meta' => 'updraftplus',
				'dismiss_time' => 'dismiss_page_notice_until',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_updraftplus_installed',
			),
			'updraftcentral' => array(
				'prefix' => '',
				'title' => __('Save Time and Money. Manage multiple WordPress sites from one location.', 'wp-optimize'),
				'text' => __('UpdraftCentral is a highly efficient way to take backup, update and manage multiple WP sites from one location.', 'wp-optimize'),
				'image' => 'notices/updraft_logo.png',
				'button_link' => 'https://updraftcentral.com',
				'button_meta' => 'updraftcentral',
				'dismiss_time' => 'dismiss_page_notice_until',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_updraftcentral_installed',
			),
			'rate_plugin' => array(
				'text' => __("Hey - We noticed WP-Optimize has kept your site running fast for a while.  If you like us, please consider leaving a positive review to spread the word.  Or if you have any issues or questions please leave us a support message", 'wp-optimize') . ' <a href="https://wordpress.org/support/plugin/wp-optimize/" target="_blank">' . __('here', 'wp-optimize') . '.</a><br>' . __('Thank you so much!', 'wp-optimize') . ' - <b>WP-Optimize</b><br>',
				'image' => 'notices/ud_smile.png',
				'button_link' => 'https://wordpress.org/support/plugin/wp-optimize/reviews/?rate=5#new-post',
				'button_meta' => 'review',
				'dismiss_time' => 'dismiss_review_notice',
				'supported_positions' => $this->dashboard_top,
				'validity_function' => 'show_rate_notice'
			),
			'translation_needed' => array(
				'prefix' => '',
				'title' => 'Can you translate? Want to improve WP-Optimize for speakers of your language?',
				'text' => $this->url_start(true, 'translate.wordpress.org/projects/wp-plugins/wp-optimize')."Please go here for instructions - it is easy.".$this->url_end(true, 'translate.wordpress.org/projects/wp-plugins/wp-optimize'),
				'text_plain' => $this->url_start(false, 'translate.wordpress.org/projects/wp-plugins/wp-optimize')."Please go here for instructions - it is easy.".$this->url_end(false, 'translate.wordpress.org/projects/wp-plugins/wp-optimize'),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => false,
				'dismiss_time' => false,
				'supported_positions' => $this->anywhere,
				'validity_function' => 'translation_needed',
			),
			'wpo-premium' => array(
				'prefix' => '',
				'title' => __("Perform optimizations while your visitors sleep", "wp-optimize"),
				'text' => __("WP-Optimize Premium features an advanced scheduling system that allows you to run optimizations at the quietest time of day (or night).", "wp-optimize"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wpo-premium',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'wpo-premium-multisite' => array(
				'prefix' => '',
				'title' => __("Manage a multisite installation? Need extra control?", "wp-optimize"),
				'text' => __("WP-Optimize Premium's multisite feature includes a locking system that restricts optimization commands to users with the right permissions.", "wp-optimize"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wpo-premium',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'wpo-premium2' => array(
				'prefix' => '',
				'title' => __("WP-Optimize Premium offers unparalleled choice and flexibility", "wp-optimize"),
				'text' => __("Upgrade today to combine over a dozen optimization options.", "wp-optimize"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wpo-premium',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'wpo-premium3' => array(
				'prefix' => '',
				'title' => __("Remove unwanted images for better site performance.", "wp-optimize"),
				'text' => __("WP-Optimize Premium comes with a feature to easily remove orphaned images, or images that exceed a certain size from your website.", "wp-optimize"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wpo-premium',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'wpo-power-tweaks' => array(
				'prefix' => '',
				'title' => __("Power tweaks for advanced users and better site performance.", "wp-optimize"),
				'text' => __("WP-Optimize Premium comes with the power tweaks that will enable you to improve performance by targeting specific weak points, either in WordPress Core, or in popular plugins.", "wp-optimize"),
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://getwpo.com/faqs/#Power-tweaks-2-',
				'button_meta' => 'wpo-premium',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'subscriben' => array(
				'prefix' => '',
				'title' => 'Subscriben ' .__('by', 'wp-optimize'). ' UpdraftPlus',
				'text' => __("The WordPress subscription extension for WooCommerce store owners.", "wp-optimize"),
				'image' => 'notices/subscriben.png',
				'button_link' => 'https://subscribenplugin.com',
				'button_meta' => 'read_more',
				'dismiss_time' => 'dismiss_notice',
				'supported_positions' => $this->anywhere,
			),

			// The sale adverts content starts here
			'blackfriday' => array(
				'prefix' => '',
				'title' => __('Black Friday - 20% off WP-Optimize Premium until November 30th', 'wp-optimize'),
				'text' => __('To benefit, use this discount code:', 'wp-optimize').' ',
				'image' => 'notices/black_friday.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wp-optimize',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'blackfridaysale2022',
				'valid_from' => '2022-11-20 00:00:00',
				'valid_to' => '2022-11-30 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'newyear' => array(
				'prefix' => '',
				'title' => __('Happy New Year - 20% off WP-Optimize Premium until January 14th', 'wp-optimize'),
				'text' => __('To benefit, use this discount code:', 'wp-optimize').' ',
				'image' => 'notices/new_year.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wp-optimize',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'newyearsale2023',
				'valid_from' => '2022-12-26 00:00:00',
				'valid_to' => '2023-01-14 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'spring' => array(
				'prefix' => '',
				'title' => __('Spring sale - 20% off WP-Optimize Premium until May 31st', 'wp-optimize'),
				'text' => __('To benefit, use this discount code:', 'wp-optimize').' ',
				'image' => 'notices/spring.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wp-optimize',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'springsale2022',
				'valid_from' => '2022-05-01 00:00:00',
				'valid_to' => '2022-05-31 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'summer' => array(
				'prefix' => '',
				'title' => __('Summer sale - 20% off WP-Optimize Premium until July 31st', 'wp-optimize'),
				'text' => __('To benefit, use this discount code:', 'wp-optimize').' ',
				'image' => 'notices/summer.png',
				'button_link' => 'https://getwpo.com',
				'button_meta' => 'wp-optimize',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'summersale2022',
				'valid_from' => '2022-07-01 00:00:00',
				'valid_to' => '2022-07-31 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
				'validity_function' => 'is_wpo_premium_installed',
			),
			'collection' => array(
				'prefix' => '',
				'title' => __('The Updraft Plugin Collection Sale', 'wp-optimize'),
				'text' => __('Get 20% off any of our plugins. But hurry - offer ends 30th September, use this discount code:', 'wp-optimize').' ',
				'image' => 'notices/wp_optimize_logo.png',
				'button_link' => 'https://teamupdraft.com',
				'campaign' => 'collection',
				'button_meta' => 'collection',
				'dismiss_time' => 'dismiss_season',
				'discount_code' => 'WPO2022',
				'valid_from' => '2022-09-01 00:00:00',
				'valid_to' => '2022-09-30 23:59:59',
				'supported_positions' => $this->dashboard_top_or_report,
			)
		);

		return array_merge($parent_notice_content, $child_notice_content);
	}
	
	/**
	 * Call this method to setup the notices
	 */
	public function notices_init() {
		if ($this->initialized) return;
		$this->initialized = true;
		$this->notices_content = (defined('WP_OPTIMIZE_NOADS_B') && WP_OPTIMIZE_NOADS_B) ? array() : $this->populate_notices_content();
	}

	/**
	 * This method will call the parent is_plugin_installed and pass in the product updraftplus to check if that plugin is installed if it is then we shouldn't display the notice
	 *
	 * @param  string  $product             the plugin slug
	 * @param  boolean $also_require_active a bool to indicate if the plugin should also be active
	 * @return boolean                      a bool to indicate if the notice should be displayed or not
	 */
	protected function is_updraftplus_installed($product = 'updraftplus', $also_require_active = false) {
		return parent::is_plugin_installed($product, $also_require_active);
	}

	/**
	 * This method will call the parent is_plugin_installed and pass in the product updraftcentral to check if that plugin is installed if it is then we shouldn't display the notice
	 *
	 * @param  string  $product             the plugin slug
	 * @param  boolean $also_require_active a bool to indicate if the plugin should also be active
	 * @return boolean                      a bool to indicate if the notice should be displayed or not
	 */
	protected function is_updraftcentral_installed($product = 'updraftcentral', $also_require_active = false) {
		return parent::is_plugin_installed($product, $also_require_active);
	}

	/**
	 * This method will call the is premium function in the WPO object to check if this install is premium and if it is we won't display the notice
	 *
	 * @return boolean a bool to indicate if we should display the notice or not
	 */
	protected function is_wpo_premium_installed() {
		if (WP_Optimize::is_premium()) {
			return false;
		}

		return true;
	}

	/**
	 * This method will check to see if a number of different backup plugins are installed and if they are we won't display the notice
	 *
	 * @param  String  $product             the plugin slug
	 * @param  boolean $also_require_active a bool to indicate if the plugin should be active or not
	 * @return boolean                      a bool to indicate if the notice should be displayed or not
	 */
	public function is_backup_plugin_installed($product = null, $also_require_active = false) {
		$backup_plugins = array('updraftplus' => 'UpdraftPlus', 'backwpup' => 'BackWPup', 'backupwordpress' => 'BackupWordPress', 'vaultpress' => 'VaultPress', 'wp-db-backup' => 'WP-DB-Backup', 'backupbuddy' => 'BackupBuddy');

		foreach ($backup_plugins as $slug => $title) {
			if (!parent::is_plugin_installed($slug, $also_require_active)) {
				return $title;
			}
		}

		return apply_filters('wp_optimize_is_backup_plugin_installed', false);
	}

	/**
	 * This function will check if we should display the rate notice or not
	 *
	 * @return boolean - to indicate if we should show the notice or not
	 */
	protected function show_rate_notice() {
		
		$options = WP_Optimize()->get_options();
		$installed = $options->get_option('installed-for', 0);
		$installed_for = time() - $installed;
		
		if ($installed && $installed_for > 28*86400) {
			return true;
		}

		return false;
	}

	/**
	 * This method calls the parent verson and will work out if the user is using a non english language and if so returns true so that they can see the translation advert.
	 *
	 * @param  String $plugin_base_dir the plugin base directory
	 * @param  String $product_name    the name of the plugin
	 * @return Boolean                 returns true if the user is using a non english language and could translate otherwise false
	 */
	protected function translation_needed($plugin_base_dir = null, $product_name = null) {// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return parent::translation_needed(WPO_PLUGIN_MAIN_PATH, 'wp-optimize');
	}
	
	/**
	 * This method is used to generate the correct URL output for the start of the URL
	 *
	 * @param  Boolean $html_allowed a boolean value to indicate if HTML can be used or not
	 * @param  String  $url          the url to use
	 * @param  Boolean $https        a boolean value to indicate if https should be used or not
	 * @param  String  $website_home a string to be displayed
	 * @return String                returns a string of the completed url
	 */
	protected function url_start($html_allowed, $url, $https = false, $website_home = 'getwpo.com') {
		return parent::url_start($html_allowed, $url, $https, $website_home);
	}

	/**
	 * This method checks to see if the notices dismiss_time parameter has been dismissed
	 *
	 * @param  String $dismiss_time a string containing the dimiss time ID
	 * @return Boolean returns true if the notice has been dismissed and shouldn't be shown otherwise display it
	 */
	protected function check_notice_dismissed($dismiss_time) {

		$time_now = (defined('WP_OPTIMIZE_NOTICES_FORCE_TIME') ? WP_OPTIMIZE_NOTICES_FORCE_TIME : time());
	
		$options = WP_Optimize()->get_options();

		$notice_dismiss = ($time_now < $options->get_option($dismiss_time, 0));

		return $notice_dismiss;
	}

	/**
	 * Check notice data for seasonal info and return true if we should display this notice.
	 *
	 * @param array $notice_data
	 * @return bool
	 */
	protected function skip_seasonal_notices($notice_data) {
		$time_now = defined('WPO_NOTICES_FORCE_TIME') ? WPO_NOTICES_FORCE_TIME : time();
		// Do not show seasonal notices in Premium version.
		if (false === WP_Optimize::is_premium()) {
			$valid_from = strtotime($notice_data['valid_from']);
			$valid_to = strtotime($notice_data['valid_to']);
			$dismiss = $this->check_notice_dismissed($notice_data['dismiss_time']);
			if (($time_now >= $valid_from && $time_now <= $valid_to) && !$dismiss) {
				// return true so that we return this notice to be displayed
				return true;
			}
		}

		return false;
	}

	/**
	 * This method will create the chosen notice and the template to use and depending on the parameters either echo it to the page or return it
	 *
	 * @param  Array   $advert_information     an array with the notice information in
	 * @param  Boolean $return_instead_of_echo a bool value to indicate if the notice should be printed to page or returned
	 * @param  String  $position               a string to indicate what template should be used
	 * @return String                          a notice to display
	 */
	protected function render_specified_notice($advert_information, $return_instead_of_echo = false, $position = 'top') {
	
		if ('bottom' == $position) {
			$template_file = 'bottom-notice.php';
		} elseif ('report' == $position) {
			$template_file = 'report.php';
		} elseif ('report-plain' == $position) {
			$template_file = 'report-plain.php';
		} else {
			$template_file = 'horizontal-notice.php';
		}
		
		$extract_variables = array_merge($advert_information, array('wp_optimize_notices' => $this));

		return WP_Optimize()->include_template('notices/'.$template_file, $return_instead_of_echo, $extract_variables);
	}
}

$GLOBALS['wp_optimize_notices'] = WP_Optimize_Notices::instance();
