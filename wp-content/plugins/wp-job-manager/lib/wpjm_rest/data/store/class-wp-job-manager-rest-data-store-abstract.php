<?php
/**
 * Data Store Abstract
 *
 * @package WP_Job_Manager_REST/Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Data_Store_Abstract
 * An abstract Data_Store class that contains a model factory
 */
abstract class WP_Job_Manager_REST_Data_Store_Abstract implements WP_Job_Manager_REST_Interfaces_Data_Store {

	/**
	 * Definition
	 *
	 * @var WP_Job_Manager_REST_Model
	 */
	protected $model_prototype;

	/**
	 * Type Serializers
	 *
	 * @var array
	 */
	private $type_serializers;

	/**
	 * WP_Job_Manager_REST_Data_Store_Abstract constructor.
	 *
	 * @param null|WP_Job_Manager_REST_Model $model_prototype Def.
	 * @param array         $args Args.
	 */
	public function __construct( $model_prototype = null, $args = array() ) {
		$this->type_serializers = array();
		$this->args = $args;
		WP_Job_Manager_REST_Expect::is_a( $model_prototype, 'WP_Job_Manager_REST_Interfaces_Model' );
		$this->set_model_factory( $model_prototype );
	}

	/**
	 * Set Definition
	 *
	 * @param WP_Job_Manager_REST_Model $factory Def.
	 *
	 * @return WP_Job_Manager_REST_Interfaces_Data_Store $this
	 */
	private function set_model_factory( $factory ) {
		$this->model_prototype = $factory;
		$this->configure();
		return $this;
	}

	/**
	 * Configure
	 */
	protected function configure() {
	}

	/**
	 * Get Definition
	 *
	 * @return WP_Job_Manager_REST_Model
	 */
	public function get_model_prototype() {
		return $this->model_prototype;
	}
}
