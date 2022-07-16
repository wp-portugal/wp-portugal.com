<?php
/**
 * A Collection of Mixtape_Interfaces_Model
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WP_Job_Manager_REST_Interfaces_Model_Collection
 */
interface WP_Job_Manager_REST_Interfaces_Model_Collection {
	/**
	 * Get all the collection's Items
	 *
	 * @return Iterator
	 */
	function get_items();
}
