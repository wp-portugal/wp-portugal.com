<?php
/**
 * A Route that is part of a controller.
 *
 * @package WP_Job_Manager_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Controller_Route
 */
class WP_Job_Manager_REST_Controller_Route {

	/**
	 * Our pattern
	 *
	 * @var string
	 */
	private $pattern;

	/**
	 * Our Handlers
	 *
	 * @var array
	 */
	private $actions;

	/**
	 * Our Controller
	 *
	 * @var WP_Job_Manager_REST_Controller
	 */
	private $controller;

	/**
	 * HTTP Methods
	 *
	 * @var array
	 */
	private $http_methods;

	/**
	 * WP_Job_Manager_REST_Controller_Route constructor.
	 *
	 * @param WP_Job_Manager_REST_Controller $controller A Controller.
	 * @param string        $pattern Pattern.
	 */
	public function __construct( $controller, $pattern ) {
		$this->controller = $controller;
		$this->pattern = $pattern;
		$this->actions = array();
		$this->http_methods = explode( ', ', WP_REST_Server::ALLMETHODS );
	}

	/**
	 * Add/Get an action
	 *
	 * @param WP_Job_Manager_REST_Controller_Action $action Action.
	 *
	 * @return WP_Job_Manager_REST_Controller_Route
	 */
	public function add_action( $action ) {
		$this->actions[ $action->name() ] = $action;
		return $this;
	}

	/**
	 * Gets Route info to use in Register rest route.
	 *
	 * @throws WP_Job_Manager_REST_Exception If invalid callable.
	 * @return array
	 */
	public function as_array() {
		$result = array();
		$result['pattern'] = $this->pattern;
		$result['actions'] = array();
		foreach ( $this->actions as $action => $route_action ) {
			/**
			 * The route action.
			 *
			 * @var WP_Job_Manager_REST_Controller_Action $route_action
			 */
			$result['actions'][] = $route_action->as_array();
		}
		return $result;
	}
}
