<?php
/**
 * Build a Bundle
 *
 * @package WP_Job_Manager_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Controller_Bundle_Builder
 */
class WP_Job_Manager_REST_Controller_Bundle_Builder implements WP_Job_Manager_REST_Interfaces_Builder {

	/**
	 * Prefix.
	 *
	 * @var string
	 */
	private $bundle_prefix;
	/**
	 * Env.
	 *
	 * @var WP_Job_Manager_REST_Environment
	 */
	private $environment;
	/**
	 * Endpoint Builders.
	 *
	 * @var array
	 */
	private $endpoint_builders = array();
	/**
	 * Bundle.
	 *
	 * @var WP_Job_Manager_REST_Controller_Bundle|null
	 */
	private $bundle = null;

	/**
	 * WP_Job_Manager_REST_Controller_Bundle_Builder constructor.
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Controller_Bundle|null $bundle Bundle.
	 */
	function __construct( $bundle = null ) {
		$this->bundle = $bundle;
	}

	/**
	 * Build it
	 *
	 * @return WP_Job_Manager_REST_Interfaces_Controller_Bundle
	 */
	public function build() {
		if ( is_a( $this->bundle, 'WP_Job_Manager_REST_Interfaces_Controller_Bundle' ) ) {
			return $this->bundle;
		}
		return new WP_Job_Manager_REST_Controller_Bundle( $this->bundle_prefix, $this->endpoint_builders );
	}

	/**
	 * Prefix.
	 *
	 * @param string $bundle_prefix Prefix.
	 * @return WP_Job_Manager_REST_Controller_Bundle_Builder $this
	 */
	public function with_prefix( $bundle_prefix ) {
		$this->bundle_prefix = $bundle_prefix;
		return $this;
	}

	/**
	 * Env.
	 *
	 * @param WP_Job_Manager_REST_Environment $env Env.
	 * @return WP_Job_Manager_REST_Controller_Bundle_Builder $this
	 */
	public function with_environment( $env ) {
		$this->environment = $env;
		return $this;
	}

	/**
	 * Endpoint.
	 *
	 * Adds a new WP_Job_Manager_REST_Controller_Builder to our builders and returns it for further setup.
	 *
	 * @param null|WP_Job_Manager_REST_Interfaces_Controller $controller_object The (optional) controller object.
	 * @return WP_Job_Manager_REST_Controller_Bundle_Builder $this
	 */
	public function add_endpoint( $controller_object = null ) {
		WP_Job_Manager_REST_Expect::is_a( $controller_object, 'WP_Job_Manager_REST_Interfaces_Controller' );
		$this->endpoint_builders[] = $controller_object;
		return $this;
	}
}
