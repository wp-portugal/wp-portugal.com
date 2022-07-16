<?php
/**
 * Type Registry
 *
 * @package WP_Job_Manager_REST/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_Type_Registry
 *
 * We support some primitive types, as well as container types:
 * 'any'
 * 'string'
 * 'integer'
 * 'int'
 * 'uint'
 * 'number'
 * 'float'
 * 'boolean
 * 'array' (untyped array)
 * 'array:<type>' (typed array)
 * 'nullable:<type>' (nullable type)
 * 'model:<class>' (a model type)
 */
class WP_Job_Manager_REST_Type_Registry {
	/**
	 * Container Types (types that contain other types)
	 *
	 * @var array
	 */
	private $container_types = array(
		'array',
		'nullable',
		'model',
	);

	/**
	 * Our registered types
	 *
	 * @var null|array
	 */
	private $types = null;

	/**
	 * Environment
	 *
	 * @var null|WP_Job_Manager_REST_Environment
	 */
	private $environment = null;

	/**
	 * Define a new type
	 *
	 * @param string             $identifier The Identifier.
	 * @param WP_Job_Manager_REST_Interfaces_Type $instance The type instance.
	 *
	 * @return WP_Job_Manager_REST_Type_Registry $this
	 *
	 * @throws WP_Job_Manager_REST_Exception When $instance not a WP_Job_Manager_REST_Interfaces_Type.
	 */
	public function define( $identifier, $instance ) {
		WP_Job_Manager_REST_Expect::is_a( $instance, 'WP_Job_Manager_REST_Interfaces_Type' );
		$this->types[ $identifier ] = $instance;
		return $this;
	}

	/**
	 * Get a type definition
	 *
	 * @param string $type The type name.
	 * @return WP_Job_Manager_REST_Interfaces_Type
	 *
	 * @throws WP_Job_Manager_REST_Exception In case of type name not conforming to syntax.
	 */
	function definition( $type ) {
		$types = $this->get_types();

		if ( ! isset( $types[ $type ] ) ) {
			// maybe lazy-register missing compound type.
			$parts = explode( ':', $type );
			if ( count( $parts ) > 1 ) {

				$container_type = $parts[0];
				WP_Job_Manager_REST_Expect::that( in_array( $container_type, $this->container_types, true ), $container_type . ' is not a known container type' );

				$item_type = $parts[1];

				if ( empty( $item_type ) ) {
					throw new WP_Job_Manager_REST_Exception( $type . ': invalid syntax' );
				}

				if ( 'array' === $container_type ) {
					$item_type_definition = $this->definition( $item_type );
					$this->define( $type, new WP_Job_Manager_REST_Type_TypedArray( $item_type_definition ) );
					$types = $this->get_types();
				}

				if ( 'nullable' === $container_type ) {
					$item_type_definition = $this->definition( $item_type );
					$this->define( $type, new WP_Job_Manager_REST_Type_Nullable( $item_type_definition ) );
					$types = $this->get_types();
				}

				if ( 'model' === $container_type ) {
					$this->define( $type, new WP_Job_Manager_REST_Type_Model( $item_type ) );
					$types = $this->get_types();
				}
			}
		}

		WP_Job_Manager_REST_Expect::that( isset( $types[ $type ] ), 'invalid type ' . $type );

		return $types[ $type ];
	}

	/**
	 * Get Types
	 *
	 * @return array
	 */
	private function get_types() {
		return (array) $this->environment
			->get_event_dispatcher()
			->apply_filters( 'type_registry_get_types', $this->types, $this );
	}

	/**
	 * Initialize the type registry
	 *
	 * @param WP_Job_Manager_REST_Environment $environment The Environment.
	 */
	public function initialize( $environment ) {
		if ( null !== $this->types ) {
			return;
		}

		$this->environment = $environment;
		$this->types = (array) $this->environment->get_event_dispatcher()->apply_filters( 'type_registry_register_types', array(
			'any'           => new WP_Job_Manager_REST_Type( 'any' ),
			'string'        => new WP_Job_Manager_REST_Type_String(),
			'integer'       => new WP_Job_Manager_REST_Type_Integer(),
			'int'           => new WP_Job_Manager_REST_Type_Integer(),
			'uint'          => new WP_Job_Manager_REST_Type_Integer( true ),
			'number'        => new WP_Job_Manager_REST_Type_Number(),
			'float'         => new WP_Job_Manager_REST_Type_Number(),
			'boolean'       => new WP_Job_Manager_REST_Type_Boolean(),
			'array'         => new WP_Job_Manager_REST_Type_Array(),
		), $this, $environment );
	}
}
