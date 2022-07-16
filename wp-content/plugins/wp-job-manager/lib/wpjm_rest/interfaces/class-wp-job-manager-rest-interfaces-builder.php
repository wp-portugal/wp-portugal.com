<?php
/**
 * Build Stuff
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WP_Job_Manager_REST_Interfaces_Builder
 */
interface WP_Job_Manager_REST_Interfaces_Builder {
	/**
	 * Build something
	 *
	 * @return mixed
	 */
	function build();
}
