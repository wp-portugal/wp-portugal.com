<?php
/**
 * Model
 *
 * This is the model.
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Mixtape_Interfaces_Model
 */
interface WP_Job_Manager_REST_Interfaces_Model {
	/**
	 * Get this model's unique identifier
	 *
	 * @return mixed a unique identifier
	 */
	function get_id();


	/**
	 * Set this model's unique identifier
	 *
	 * @param mixed $new_id The new Id.
	 * @return WP_Job_Manager_REST_Interfaces_Model $model This model.
	 */
	function set_id( $new_id );

	/**
	 * Get a field for this model
	 *
	 * @param string $field_name The field name.
	 * @param array  $args The args.
	 *
	 * @return mixed|null
	 */
	function get( $field_name, $args = array() );

	/**
	 * Set a field for this model
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The value.
	 *
	 * @return WP_Job_Manager_REST_Interfaces_Model $this;
	 */
	function set( $field, $value );

	/**
	 * Check if this model has a field
	 *
	 * @param string $field The field name.
	 *
	 * @return bool
	 */
	function has( $field );

	/**
	 * Validate this Model instance.
	 *
	 * @throws WP_Job_Manager_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	function validate();

	/**
	 * Sanitize this Model's field values
	 *
	 * @throws WP_Job_Manager_REST_Exception Throws.
	 *
	 * @return WP_Job_Manager_REST_Interfaces_Model
	 */
	function sanitize();

	/**
	 * Get this model class fields
	 *
	 * @param null|string $filter_by_type The field type.
	 * @return array
	 */
	public function get_fields( $filter_by_type = null );

	/**
	 * Get this model's data store
	 *
	 * @return array
	 */
	public function get_data_store();

	/**
	 * Set this model's data store (statically, all models of that class get the same one)
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Data_Store $data_store A builder or a Data store.
	 * @throws WP_Job_Manager_REST_Exception Throws when Data Store Invalid.
	 */
	public function with_data_store( $data_store );

	/**
	 * Get this model's environment
	 *
	 * @return array
	 */
	public function get_environment();

	/**
	 * Set this model's environment
	 *
	 * @param WP_Job_Manager_REST_Environment $environment The Environment.
	 * @throws WP_Job_Manager_REST_Exception If an WP_Job_Manager_REST_Environment is not provided.
	 */
	public function with_environment( $environment );

	/**
	 * Declare the fields of our Model.
	 *
	 * @return array list of WP_Job_Manager_REST_Field_Declaration
	 */
	public function declare_fields();

	/**
	 * Prepare this for data transfer
	 *
	 * @return mixed
	 */
	public function to_dto();

	/**
	 * Update from array
	 *
	 * @param array $data The Data.
	 * @param bool  $updating Is this an update.
	 *
	 * @return mixed
	 */
	function update_from_array( $data, $updating = false );

	/**
	 * Transform Model to raw data array
	 *
	 * @param null|string $field_type Type.
	 *
	 * @return array
	 */
	function serialize( $field_type = null );
}
