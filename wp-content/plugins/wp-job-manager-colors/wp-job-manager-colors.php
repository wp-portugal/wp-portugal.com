<?php
/**
 * Plugin Name: WP Job Manager - Job Type Colors
 * Plugin URI:  https://github.com/astoundify/wp-job-manager-colors
 * Description: Assign custom colors for each existing job type.
 * Author:      Astoundify
 * Author URI:  http://astoundify.com
 * Version:     1.0.2
 * Text Domain: job_manager_colors
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_Job_Manager_Colors {

	private static $instance;

	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		$this->setup_actions();
	}

	private function setup_actions() {
		add_filter( 'job_manager_settings', array( $this, 'job_manager_settings' ) );
		add_action( 'wp_head', array( $this, 'output_colors' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'colorpickers' ) );
			add_action( 'admin_footer', array( $this, 'colorpickersjs' ) );
		}
	}

	public function job_manager_settings( $settings ) {
		$settings[ 'job_colors' ] = array(
			__( 'Job Colors', 'job_manager_colors' ),
			$this->create_options()
		);

		return $settings;
	}

	private function create_options() {
		$terms   = get_terms( 'job_listing_type', array( 'hide_empty' => false ) );
		$options = array();

		$options[] = array(
			'name' 		  => 'job_manager_job_type_what_color',
			'std' 		  => 'background',
			'placeholder' => '',
			'label' 	  => __( 'What', 'job_manager_colors' ),
			'desc'        => __( 'Should these colors be applied to the text color, or background color?', 'job_manager_colors' ),
			'type'        => 'select',
			'options'     => array(
				'background' => __( 'Background', 'job_manager_colors' ),
				'text'       => __( 'Text', 'job_manager_colors' )
			)
		);

		foreach ( $terms as $term ) {
			$options[] = array(
				'name' 		  => 'job_manager_job_type_' . $term->slug . '_color',
				'std' 		  => '',
				'placeholder' => '#',
				'label' 	  => '<strong>' . $term->name . '</strong>',
				'desc'		  => __( 'Hex value for the color of this job type.', 'job_manager_colors' ),
				'attributes'  => array(
					'data-default-color' => '#fff',
					'data-type'          => 'colorpicker'
				)
			);
		}

		return $options;
	}

	public function output_colors() {
		$terms   = get_terms( 'job_listing_type', array( 'hide_empty' => false ) );

		echo "<style id='job_manager_colors'>\n";

		foreach ( $terms as $term ) {
			$what = 'background' == get_option( 'job_manager_job_type_what_color' ) ? 'background-color' : 'color';

			printf( ".job-type.term-%s, .job-type.%s { %s: %s; } \n", $term->term_id, $term->slug, $what, get_option( 'job_manager_job_type_' . $term->slug . '_color', '#fff' ) );
		}

		echo "</style>\n";
	}

	public function colorpickers( $hook ) {
		$screen = get_current_screen();

		if ( 'job_listing_page_job-manager-settings' != $screen->id )
			return;

		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );
	}

	public function colorpickersjs() {
		$screen = get_current_screen();

		if ( 'job_listing_page_job-manager-settings' != $screen->id )
			return;
		?>
			<script>
				jQuery(document).ready(function($){
					$( 'input[data-type="colorpicker"]' ).wpColorPicker();
				});
			</script>
		<?php
	}
}

add_action( 'init', array( 'WP_Job_Manager_Colors', 'instance' ) );