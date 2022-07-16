<?php
/**
 * Data Store Nil (empty)
 *
 * @package WP_Job_Manager_REST/Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Data_Store_Nil
 * Null object for datastores
 */
class WP_Job_Manager_REST_Data_Store_Nil implements WP_Job_Manager_REST_Interfaces_Data_Store {

	/**
	 * Get Entities
	 *
	 * @param null $filter F.
	 * @return WP_Job_Manager_REST_Model_Collection
	 */
	public function get_entities( $filter = null ) {
		return new WP_Job_Manager_REST_Model_Collection( array() );
	}

	/**
	 * Get Entity
	 *
	 * @param int $id Id.
	 * @return null
	 */
	public function get_entity( $id ) {
		return null;
	}

	/**
	 * Delete
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Model $model Model.
	 * @param array               $args Args.
	 * @return bool
	 */
	public function delete( $model, $args = array() ) {
		return true;
	}

	/**
	 * Upsert
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Model $model Model.
	 * @return int
	 */
	public function upsert( $model ) {
		return 0;
	}

	/**
	 * Def
	 *
	 * @param mixed $definition Def.
	 */
	public function set_definition( $definition ) {
	}
}
