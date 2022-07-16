<?php
/**
 * Controller Bundle
 *
 * A collection of WP_Job_Manager_REST_Rest_Api_Controller, sharing a common prefix.
 *
 * @package Mixtape/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WP_Job_Manager_REST_Interfaces_Rest_Api_Controller_Bundle
 */
interface WP_Job_Manager_REST_Interfaces_Controller_Bundle extends WP_Job_Manager_REST_Interfaces_Registrable {

	/**
	 * Get the Prefix
	 *
	 * @return string
	 */
	public function get_prefix();
}
