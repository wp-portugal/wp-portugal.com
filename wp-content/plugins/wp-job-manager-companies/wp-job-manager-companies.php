<?php
/**
 * Plugin Name: WP Job Manager - Company Profiles
 * Plugin URI:  https://github.com/astoundify/wp-job-manager-companies
 * Description: Output a list of all companies that have posted a job, with a link to a company profile.
 * Author:      Astoundify
 * Author URI:  http://astoundify.com
 * Version:     1.4
 * Text Domain: wp-job-manager-companies
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Astoundify_Job_Manager_Companies {

	/**
	 * @var $instance
	 */
	private static $instance;

	/**
	 * @var slug
	 */
	private $slug;

	/**
	 * Make sure only one instance is only running.
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Start things up.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @return void
	 */
	private function setup_globals() {
		$this->file         = __FILE__;

		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );

		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		$this->domain       = 'wp-job-manager-companies';

		/**
		 * The slug for creating permalinks
		 */
		$this->slug = apply_filters( 'wp_job_manager_companies_company_slug', __( 'company',
		'wp-job-manager-companies' ) );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @return void
	 */
	private function setup_actions() {
		add_shortcode( 'job_manager_companies', array( $this, 'shortcode' ) );

		add_filter( 'pre_get_document_title', array( $this, 'page_title' ), 20 );

		add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'pre_get_posts', array( $this, 'posts_filter' ) );
		add_action( 'template_redirect', array( $this, 'template_loader' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Define "company" as a valid query variable.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param array $vars The array of existing query variables.
	 * @return array $vars The modified array of query variables.
	 */
	public function query_vars( $vars ) {
		$vars[] = $this->slug;

		return $vars;
	}

	/**
	 * Create the custom rewrite tag, then add it as a custom structure.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @return obj $wp_rewrite
	 */
	public function add_rewrite_rule() {
		global $wp_rewrite;

		$wp_rewrite->add_rewrite_tag( '%company%', '(.+?)', $this->slug . '=' );

		$rewrite_keywords_structure = $wp_rewrite->root . $this->slug ."/%company%/";

		$new_rule = $wp_rewrite->generate_rewrite_rules( $rewrite_keywords_structure );

		$wp_rewrite->rules = $new_rule + $wp_rewrite->rules;

		return $wp_rewrite->rules;
	}

	/**
	 * If we detect the "company" query variable, load our custom template
	 * file. This will check a child theme so it can be overwritten as well.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @return void
	 */
	public function template_loader() {
		global $wp_query;

		if ( ! get_query_var( $this->slug ) )
			return;

		if ( 0 == $wp_query->found_posts )
			locate_template( apply_filters( 'wp_job_manager_companies_404', array( '404.php' ) ), true );
		else
			locate_template( apply_filters( 'wp_job_manager_companies_templates', array( 'single-company.php', 'taxonomy-job_listing_category.php' ) ), true );

		exit();
	}

	/**
	 * Potentialy filter the query. If we detect the "company" query variable
	 * then filter the results to show job listsing for that company.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param object $query
	 * @return void
	 */
	public function posts_filter( $query ) {
		if ( ! ( get_query_var( $this->slug ) && $query->is_main_query() && ! is_admin() ) )
			return;

		$meta_query = array(
			array(
				'key'   => '_company_name',
				'value' => urldecode( get_query_var( $this->slug ) )
			)
		);

		if ( get_option( 'job_manager_hide_filled_positions' ) == 1 ) {
			$meta_query[] = array(
				'key'     => '_filled',
				'value'   => '1',
				'compare' => '!='
			);
		}

		$query->set( 'post_type', 'job_listing' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Register the `[job_manager_companies]` shortcode.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param array $atts
	 * @return string The shortcode HTML output
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'show_letters' => true
		), $atts );

		wp_enqueue_script( 'jquery-masonry' );
	?>

		<script type="text/javascript">
		jQuery(function($) {
			$('.companies-overview').masonry({
				itemSelector : '.company-group',
				isFitWidth   : true
			});
		});
		</script>
	<?php

		return $this->build_company_archive( $atts );
	}

	/**
	 * Build the shortcode.
	 *
	 * Not very flexible at the moment. Only can deal with english letters.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param array $atts
	 * @return string The shortcode HTML output
	 */
	public function build_company_archive( $atts ) {
		global $wpdb;

		$output      = '';
		$companies   = $wpdb->get_col(
			"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_company_name'
			 AND p.post_status = 'publish'
			 AND p.post_type = 'job_listing'
			 GROUP BY pm.meta_value
			 ORDER BY pm.meta_value"
		);
		$_companies = array();

		foreach ( $companies as $company ) {
			$_companies[ strtoupper( $company[0] ) ][] = $company;
		}

		if ( $atts[ 'show_letters' ] ) {
			$output .= '<div class="company-letters">';

			foreach ( range( 'A', 'Z' ) as $letter ) {
				$output .= '<a href="#' . $letter . '">' . $letter . '</a>';
			}

			$output .= '</div>';
		}

		$output .= '<ul class="companies-overview">';

		foreach ( range( 'A', 'Z' ) as $letter ) {
			if ( ! isset( $_companies[ $letter ] ) )
				continue;

			$output .= '<li class="company-group"><div id="' . $letter . '" class="company-letter">' . $letter . '</div>';
			$output .= '<ul>';

			foreach ( $_companies[ $letter ] as $company_name ) {
				$count = count( get_posts( array( 'post_type' => 'job_listing', 'meta_key' => '_company_name', 'meta_value' => $company_name, 'nopaging' => true ) ) );

				$output .= '<li class="company-name"><a href="' . $this->company_url( $company_name ) . '">' . esc_attr( $company_name ) . ' (' . $count . ')</a></li>';
			}

			$output .= '</ul>';
			$output .= '</li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Company profile URL. Depending on our permalink structure we might
	 * not output a pretty URL.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param string $company_name
	 * @return string $url The company profile URL.
	 */
	public function company_url( $company_name ) {
		global $wp_rewrite;

		$company_name = rawurlencode( $company_name );

		if ( $wp_rewrite->permalink_structure == '' ) {
			$url = home_url( 'index.php?'. $this->slug . '=' . $company_name );
		} else {
			$url = home_url( '/' . $this->slug . '/' . trailingslashit( $company_name ) );
		}

		return esc_url( $url );
	}

	/**
	 * Set a page title when viewing an individual company.
	 *
	 * @since WP Job Manager - Company Profiles 1.2
	 *
	 * @param string $title Default title text for current view.
	 * @param string $sep Optional separator.
	 * @return string Filtered title.
	 */
	function page_title($title) {
		global $paged, $page;
		$sep = apply_filters( 'document_title_separator', '-' );
		if ( ! get_query_var( $this->slug ) )
			return $title;

		$company = urldecode( get_query_var( $this->slug ) );

		$title = get_bloginfo( 'name' );

		$site_description = get_bloginfo( 'description', 'display' );

		if ( $site_description && ( is_home() || is_front_page() ) )
			$title = "$title $sep $site_description";

		$title = sprintf( __( 'Jobs at %s', 'wp-job-manager-companies' ), $company ) . " $sep $title";

		return $title;
	}

	/**
	 * Localisation
	 *
	 * @access private
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager-companies' );

		load_textdomain( 'wp-job-manager-companies', WP_LANG_DIR . "/wp-job-manager-companies/wp-job-manager-companies-$locale.mo" );
		load_plugin_textdomain( 'wp-job-manager-companies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}
add_action( 'plugins_loaded', array( 'Astoundify_Job_Manager_Companies', 'instance' ) );
