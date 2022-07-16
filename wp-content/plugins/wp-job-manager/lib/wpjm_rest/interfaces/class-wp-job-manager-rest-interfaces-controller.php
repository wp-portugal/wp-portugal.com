<?php
/**
 * Our controller Interface
 *
 * @package Mixtape/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WP_Job_Manager_REST_Interfaces_Controller
 */
interface WP_Job_Manager_REST_Interfaces_Controller {
	/**
	 * Register This Controller
	 *
	 * @param WP_Job_Manager_REST_Controller_Bundle $bundle The bundle to register with.
	 * @param WP_Job_Manager_REST_Environment       $environment The Environment to use.
	 * @throws WP_Job_Manager_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	function register( $bundle, $environment );
}
