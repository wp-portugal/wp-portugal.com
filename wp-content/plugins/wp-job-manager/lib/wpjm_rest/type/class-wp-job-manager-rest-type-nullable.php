<?php
/**
 * The Nullable Type (a type that can be null)
 *
 * @package WP_Job_Manager_REST/Types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Type_Nullable
 */
class WP_Job_Manager_REST_Type_Nullable extends WP_Job_Manager_REST_Type {
	/**
	 * The type
	 *
	 * @var WP_Job_Manager_REST_Interfaces_Type
	 */
	private $item_type_definition;

	/**
	 * Construct
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Type $item_type_definition Def.
	 */
	function __construct( $item_type_definition ) {
		parent::__construct( 'nullable:' . $item_type_definition->name() );
		$this->item_type_definition = $item_type_definition;
	}

	/**
	 * Default value as always null.
	 *
	 * @return null
	 */
	public function default_value() {
		return null;
	}

	/**
	 * Cast
	 *
	 * @param mixed $value Value.
	 * @return mixed|null
	 */
	public function cast( $value ) {
		if ( null === $value ) {
			return null;
		}
		return $this->item_type_definition->cast( $value );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $value Value.
	 * @return mixed|null
	 */
	public function sanitize( $value ) {
		if ( null === $value ) {
			return null;
		}
		return $this->item_type_definition->sanitize( $value );
	}

	/**
	 * Schema
	 */
	function schema() {
		$schema = parent::schema();
		$schema['type'] = array_unique( array_merge( $schema['type'], array( 'null' ) ) );
	}
}
