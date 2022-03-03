<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Page caching rules and exceptions
 */

if (!class_exists('WPO_Cache_Config')) require_once('class-wpo-cache-config.php');

require_once dirname(__FILE__) . '/file-based-page-cache-functions.php';

if (!class_exists('WPO_Cache_Rules')) :

class WPO_Cache_Rules {

	/**
	 * Cache config object
	 *
	 * @var mixed
	 */
	public $config;

	/**
	 * Instance of this class
	 *
	 * @var mixed
	 */
	public static $instance;

	public function __construct() {
		$this->config = WPO_Cache_Config::instance()->get();
		$this->setup_hooks();
	}

	/**
	 * Setup hooks/filters
	 */
	public function setup_hooks() {
		add_action('save_post', array($this, 'purge_post_on_update'), 10, 1);
		add_action('save_post', array($this, 'purge_archive_pages_on_post_update'), 10, 1);
		add_action('wp_trash_post', array($this, 'purge_post_on_update'), 10, 1);
		add_action('comment_post', array($this, 'purge_post_on_comment'), 10, 3);
		add_action('wp_set_comment_status', array($this, 'purge_post_on_comment_status_change'), 10, 1);
		add_action('edit_terms', array($this, 'purge_related_elements_on_term_updated'), 10, 2);
		add_action('set_object_terms', array($this, 'purge_related_elements_on_post_terms_change'), 10, 6);
		add_action('wpo_cache_config_updated', array($this, 'cache_config_updated'), 10, 1);
		add_action('wp_insert_comment', array($this, 'comment_inserted'), 10, 2);

		add_action('woocommerce_variation_set_stock', array($this, 'purge_product_page'), 10, 1);
		add_action('woocommerce_product_set_stock', array($this, 'purge_product_page'), 10, 1);

		/**
		 * List of hooks for which when executed, the cache will be purged
		 *
		 * @param array $actions The actions
		 */
		$purge_on_action = apply_filters('wpo_purge_cache_hooks', array('after_switch_theme', 'wp_update_nav_menu', 'customize_save_after', array('wp_ajax_save-widget', 0), array('wp_ajax_update-widget', 0), 'autoptimize_action_cachepurged', 'upgrader_overwrote_package', 'wpo_active_plugin_or_theme_updated', 'fusion_cache_reset_after'));
		foreach ($purge_on_action as $action) {
			if (is_array($action)) {
				add_action($action[0], array($this, 'purge_cache'), $action[1]);
			} else {
				add_action($action, array($this, 'purge_cache'));
			}
		}

		add_filter('wpo_cache_cookies', array($this, 'wpo_cache_cookies'), 9);
	}

	/**
	 * Purge post cache when there is a new approved comment
	 *
	 * @param  int        $comment_id  Comment ID.
	 * @param  int|string $approved    Comment approved status. can be 0, 1 or 'spam'.
	 * @param  array      $commentdata Comment data array. Always sent be WP core, but a plugin was found that does not send it - https://wordpress.org/support/topic/critical-problems-with-version-3-0-10/
	 */
	public function purge_post_on_comment($comment_id, $approved, $commentdata = array()) {
		if (1 !== $approved) {
			return;
		}

		if (!empty($this->config['enable_page_caching']) && !empty($commentdata['comment_post_ID'])) {
			$post_id = $commentdata['comment_post_ID'];

			WPO_Page_Cache::delete_single_post_cache($post_id);
			WPO_Page_Cache::delete_comments_feed();
		}
	}

	/**
	 * Every time a comment's status changes, purge it's parent posts cache
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function purge_post_on_comment_status_change($comment_id) {
		if (!empty($this->config['enable_page_caching'])) {
			$comment = get_comment($comment_id);
			if (is_object($comment) && !empty($comment->comment_post_ID)) {
				WPO_Page_Cache::delete_single_post_cache($comment->comment_post_ID);
				WPO_Page_Cache::delete_comments_feed();
			}
		}
	}

	/**
	 * Action when a comment is inserted
	 *
	 * @param integer            $comment_id - The comment ID
	 * @param boolean|WP_Comment $comment    - The comment object (from WP 4.4)
	 * @return void
	 */
	public function comment_inserted($comment_id, $comment = false) {
		if ($comment && is_a($comment, 'WP_Comment')) {
			/**
			 * Filters whether to add a cookie when a comment is posted, in order to exclude the page from caching.
			 * Regular comments have the property comment_type set to ''  or 'comment'. So by default, only add the cookie in those cases.
			 *
			 * @param boolean    $add_cookie
			 * @param WP_Comment $comment
			 * @return boolean
			 */
			$add_cookie = apply_filters('wpo_add_commented_post_cookie', '' == $comment->comment_type || 'comment' == $comment->comment_type, $comment);
			if (!$add_cookie) return;

			$url = get_permalink($comment->comment_post_ID);
			$url_info = parse_url($url);
			setcookie('wpo_commented_post', 1, time() + WEEK_IN_SECONDS, isset($url_info['path']) ? $url_info['path'] : '/');
		}
	}

	/**
	 * Automatically purge all file based page cache on post changes
	 * We want the whole cache purged here as different parts
	 * of the site could potentially change on post updates
	 *
	 * @param Integer $post_id - WP post id
	 */
	public function purge_post_on_update($post_id) {
		$post_type = get_post_type($post_id);
		$post_type_object = get_post_type_object($post_type);

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 'revision' === $post_type || !$post_type_object->public) {
			return;
		}

		/**
		 * Purge the whole cache if set to true, only the edited post otherwise. Default is false.
		 *
		 * @param boolean $purge_all_cache The default filter value
		 * @param integer $post_id         The saved post ID
		 */
		if (apply_filters('wpo_purge_all_cache_on_update', false, $post_id)) {
			$this->purge_cache();
			return;
		} else {
			if (apply_filters('wpo_delete_cached_homepage_on_post_update', true, $post_id)) WPO_Page_Cache::delete_homepage_cache();
			WPO_Page_Cache::delete_feed_cache();
			WPO_Page_Cache::delete_single_post_cache($post_id);
			WPO_Page_Cache::delete_sitemap_cache();
			WPO_Page_Cache::delete_post_feed_cache($post_id);
		}
	}

	/**
	 * Purge archive pages on post update.
	 *
	 * @param integer $post_id
	 */
	public function purge_archive_pages_on_post_update($post_id) {
		$post_type = get_post_type($post_id);

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 'revision' === $post_type) {
			return;
		}

		$post_obj = get_post_type_object($post_type);

		if ('post' == $post_type) {
			// delete blog page cache
			$blog_post_id = get_option('page_for_posts');
			if ($blog_post_id) {
				WPO_Page_Cache::delete_cache_by_url(get_permalink($blog_post_id), true);
			}
		
			// delete next and previus posts cache.
			$globals_post = isset($GLOBALS['post']) ? $GLOBALS['post'] : false;
			$GLOBALS['post'] = get_post($post_id);
			$previous_post = function_exists('get_previous_post') ? get_previous_post() : false;
			$next_post = function_exists('get_next_post') ? get_next_post() : false;
			if ($globals_post) $GLOBALS['post'] = $globals_post;
			
			if ($previous_post) WPO_Page_Cache::delete_cache_by_url(get_permalink($previous_post), true);
			if ($next_post) WPO_Page_Cache::delete_cache_by_url(get_permalink($next_post), true);
			
			// delete all archive pages for post.
			$post_date = get_post_time('Y-m-j', false, $post_id);
			list($year, $month, $day) = $post_date;

			$archive_links = array(
				get_year_link($year),
				get_month_link($year, $month),
				get_day_link($year, $month, $day),
			);

			foreach ($archive_links as $link) {
				WPO_Page_Cache::delete_cache_by_url($link, true);
			}
		} elseif ($post_obj->has_archive) {
			// delete archive page for custom post type.
			WPO_Page_Cache::delete_cache_by_url(get_post_type_archive_link($post_type), true);
		}

	}

	/**
	 * We use it with edit_terms action filter to purge cached elements related
	 * to updated term when term updated.
	 *
	 * @param int    $term_id  Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function purge_related_elements_on_term_updated($term_id, $taxonomy) {
		// purge cached page for term.
		$term = get_term($term_id, $taxonomy, ARRAY_A);
		if (is_array($term)) {
			$term_permalink = get_term_link($term['term_id']);
			if (!is_wp_error($term_permalink)) {
				WPO_Page_Cache::delete_cache_by_url($term_permalink, true);
			}
		}

		// get posts which belongs to updated term.
		$posts = get_posts(array(
			'numberposts'      => -1,
			'post_type'        => 'any',
			'fields'           => 'ids',
			'tax_query' => array(
				'relation' => 'OR',
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				)
			),
		));

		if (!empty($posts)) {
			foreach ($posts as $post_id) {
				WPO_Page_Cache::delete_single_post_cache($post_id);
			}
		}
	}

	/**
	 * Triggered by set_object_terms action. Used to clear all the terms archives a post belongs to or belonged to before being saved.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 */
	public function purge_related_elements_on_post_terms_change($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {

		$post_type = get_post_type($object_id);

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 'revision' === $post_type || 'product_type' === $taxonomy || 'action-group' === $taxonomy) {
			return;
		}

		/**
		 * Adds a way to exit the purge of terms permalink using the provided parameters.
		 *
		 * @param bool   $purge      The value filtered, whether or not to purge the related elements
		 * @param int    $object_id  Object ID.
		 * @param array  $terms      An array of object terms.
		 * @param array  $tt_ids     An array of term taxonomy IDs.
		 * @param string $taxonomy   Taxonomy slug.
		 * @param bool   $append     Whether to append new terms to the old terms.
		 * @param array  $old_tt_ids Old array of term taxonomy IDs.
		 * @default true
		 * @return boolean
		 */
		if (!apply_filters('wpo_cache_purge_related_elements_on_post_terms_change', true, $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)) return;

		// get all affected terms.
		$affected_terms_ids = array_unique(array_merge($tt_ids, $old_tt_ids));

		if (!empty($affected_terms_ids)) {
			// walk through all changed terms and purge cached pages for them.
			foreach ($affected_terms_ids as $tt_id) {
				$term = get_term($tt_id, $taxonomy, ARRAY_A);
				if (!is_array($term)) continue;

				$term_permalink = get_term_link($term['term_id']);
				if (!is_wp_error($term_permalink)) {
					$url = parse_url($term_permalink);
					// Check if the permalink contains a valid path, to avoid deleting the whole cache.
					if (!isset($url['path']) || '/' === $url['path']) return;
					WPO_Page_Cache::delete_cache_by_url($term_permalink, true);
				}
			}
		}
	}

	/**
	 * Purge product page upon stock update
	 */
	public function purge_product_page($product_with_stock) {
		if (!empty($product_with_stock->get_id())) {
			WPO_Page_Cache::delete_single_post_cache($product_with_stock->get_id());
		}
	}

	/**
	 * Clears the cache.
	 */
	public function purge_cache() {
		if (!empty($this->config['enable_page_caching'])) {
			wpo_cache_flush();
		}
	}

	/**
	 * Triggered by wpo_cache_config_updated.
	 *
	 * @param array $config
	 */
	public function cache_config_updated($config) {
		// delete front page form cache if defined in the settings
		if (is_array($config['cache_exception_urls']) && in_array('/', $config['cache_exception_urls'])) {
			WPO_Page_Cache::delete_cache_by_url(home_url());
		}
	}

	/**
	 * Add cookie names that are need separate caching
	 */
	public function wpo_cache_cookies($cookies) {
		$cookies[] = 'cookie_notice_accepted';
		$cookies[] = 'cookielawinfo-checkbox-necessary';
		$cookies[] = 'cookielawinfo-checkbox-functional';
		$cookies[] = 'cookielawinfo-checkbox-advertisement';
		$cookies[] = 'cookielawinfo-checkbox-others';
		$cookies[] = 'cookielawinfo-checkbox-analytics';
		$cookies[] = 'cookielawinfo-checkbox-performance';
		return $cookies;
	}

	/**
	 * Returns an instance of the current class, creates one if it doesn't exist
	 *
	 * @return object
	 */
	public static function instance() {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

endif;
