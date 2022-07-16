<?php
/**
 * Something that can be registered with an environment
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WP_Job_Manager_REST_Interfaces_Registrable
 */

interface WP_Job_Manager_REST_Interfaces_Registrable {
	/**
	 * Register This with an environment
	 *
	 * @param WP_Job_Manager_REST_Environment $environment The Environment to use.
	 * @return void
	 */
	function register( $environment );
}
