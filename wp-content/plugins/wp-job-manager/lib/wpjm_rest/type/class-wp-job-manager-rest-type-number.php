<?php
/**
 * The Number Type (a floating point type)
 *
 * @package WP_Job_Manager_REST/Types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Type_Number
 */
class WP_Job_Manager_REST_Type_Number extends WP_Job_Manager_REST_Type {

	/**
	 * WP_Job_Manager_REST_Type_Number constructor.
	 */
	function __construct() {
		parent::__construct( 'number' );
	}

	/**
	 * The default value
	 *
	 * @return float
	 */
	function default_value() {
		return 0.0;
	}

	/**
	 * Cast
	 *
	 * @param mixed $value The thing to cast.
	 * @return float
	 */
	function cast( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}
		return floatval( $value );
	}

	/**
	 * Sanitize
	 *
	 * @param mixed $value The value to sanitize.
	 * @return float
	 */
	function sanitize( $value ) {
		return $this->cast( $value );
	}
}
