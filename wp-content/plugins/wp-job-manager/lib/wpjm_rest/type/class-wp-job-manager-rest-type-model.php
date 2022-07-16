<?php
/**
 * Model type
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Type_Model
 */
class WP_Job_Manager_REST_Type_Model extends WP_Job_Manager_REST_Type {
	/**
	 * The Class (must implement WP_Job_Manager_REST_Interfaces_Model).
	 *
	 * @var string
	 */
	private $model_class;

	/**
	 * WP_Job_Manager_REST_Type_Array constructor.
	 *
	 * @param string $model_class The model class.
	 */
	public function __construct( $model_class = 'WP_Job_Manager_REST_Model' ) {
		WP_Job_Manager_REST_Expect::implements_interface( $model_class, 'WP_Job_Manager_REST_Interfaces_Model' );
		$this->model_class = $model_class;
		parent::__construct( 'model:' . $model_class );
	}

	/**
	 * Get default WP_Job_Manager_REST_Interfaces_Model
	 *
	 * @return WP_Job_Manager_REST_Interfaces_Model
	 */
	public function default_value() {
		$klass = $this->model_class;
		return new $klass();
	}

	/**
	 * Sanitize.
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Model|mixed $value Val.
	 * @return WP_Job_Manager_REST_Interfaces_Model
	 * @throws WP_Job_Manager_REST_Exception If value not a $this->model_class.
	 */
	function sanitize( $value ) {
		if ( is_a( $value, $this->model_class ) ) {
			return $value->sanitize();
		}
		throw new WP_Job_Manager_REST_Exception( 'WP_Job_Manager_REST_Type_Model: don\'t know how to sanitize provided value' );
	}

	/**
	 * Cast to WP_Job_Manager_REST_Interfaces_Model if possible.
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Model|array $value The value. Should be either array or type class.
	 * @return WP_Job_Manager_REST_Interfaces_Model
	 * @throws WP_Job_Manager_REST_Exception If value not an array or a $this->model_class.
	 */
	public function cast( $value ) {
		if ( is_a( $value, $this->model_class ) ) {
			return $value;
		} elseif ( is_array( $value ) ) {
			$klass = $this->model_class;
			return new $klass( $value );
		}
		throw new WP_Job_Manager_REST_Exception( 'WP_Job_Manager_REST_Type_Model: don\'t know how to cast provided value' );
	}
}
