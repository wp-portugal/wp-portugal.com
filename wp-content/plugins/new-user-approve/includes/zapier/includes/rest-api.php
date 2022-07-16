<?php

namespace NewUserApproveZapier;

use WP_REST_Server;

class RestRoutes
{
    private static $_instance;

    /**
     * @version 1.0
     * @since 2.1
     */
    public static function get_instance()
    {
        if ( self::$_instance == null )
            self::$_instance = new self();

        return self::$_instance;
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function __construct()
    {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function register_routes()
    {
        register_rest_route( 'nua-zapier', '/v1/auth',array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => [ $this, 'authenticate' ]
		) );

        register_rest_route( 'nua-zapier', '/v1/user-approved',array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => [ $this, 'user_approved' ]
		) );

        register_rest_route( 'nua-zapier', '/v1/user-denied',array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => [ $this, 'user_denied' ]
		) );
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public static function api_key()
    {
        return get_option( 'nua_api_key' );
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function authenticate( $request )
    {
		$api_key = $request->get_param( 'api_key' );

		if( $api_key == $this->api_key() )
			return new \WP_REST_Response( true, 200 );
		
		if( $api_key == null )
			return new \WP_Error( 400, __( 'Required Parameter Missing', 'new-user-approve' ), 'api_key required' );
		
		if( $api_key != $this->api_key() )
			return new \WP_Error( 400, __( 'Invalid API Key', 'new-user-approve' ), 'invalid api_key' );
    }

    public function user_approved( $request )
    {
        $api_key = $request->get_param( 'api_key' );
		
		if( $api_key == null )
			return new \WP_Error( 400, __( 'Required Parameter Missing', 'new-user-approve' ), 'api_key required' );
		
		if( $api_key != $this->api_key() )
			return new \WP_Error( 400, __( 'Invalid API Key', 'new-user-approve' ), 'invalid api_key' );
    
        if( $api_key == $this->api_key() )
        {
            return $this->user_data( 'nua_user_approved' );
        }
    }

    public function user_denied( $request )
    {
        $api_key = $request->get_param( 'api_key' );
		
		if( $api_key == null )
			return new \WP_Error( 400, __( 'Required Parameter Missing', 'new-user-approve' ), 'api_key required' );
		
		if( $api_key != $this->api_key() )
			return new \WP_Error( 400, __( 'Invalid API Key', 'new-user-approve' ), 'invalid api_key' );
    
        if( $api_key == $this->api_key() )
        {
            return $this->user_data( 'nua_user_denied' );
        }
    }

    public function user_data( $option_name )
    {
        $user_data = get_option( $option_name );

        if( $user_data )
        {
			$data = array();

            $time_key = $option_name == 'nua_user_approved' ? 'approval_time' : 'denial_time';
			
			foreach( $user_data as $key => $value )
			{
				$user_id = $value['user_id'];

				$user = get_userdata( $user_id );
				
				$data[] = array(
                    'id'                =>  $value['id'],
                    'user_login'        =>  $user->user_login,
                    'user_nicename'     =>  $user->user_nicename,
                    'user_email'        =>  $user->user_email,
                    'user_registered'   =>  date( DATE_ISO8601, strtotime( $user->user_registered ) ),
                    $time_key           =>  date( DATE_ISO8601, $value['time'] )
                );
			}
			
			return apply_filters( "{$option_name}_zapier", $data );
        }
    }
}

\NewUserApproveZapier\RestRoutes::get_instance();