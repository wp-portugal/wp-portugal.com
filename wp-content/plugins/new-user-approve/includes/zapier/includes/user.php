<?php 

namespace NewUserApproveZapier;

class User {

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
        add_action( 'new_user_approve_user_approved', array( $this, 'user_approved' ) ); 
        add_action( 'new_user_approve_user_denied', array( $this, 'user_denied' ) ); 
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_approved( $user )
    {
        $this->update_user( 'nua_user_approved', $user->ID );
    }
    
    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_denied( $user )
    {
        $this->update_user( 'nua_user_denied', $user->ID );
    }

    public function update_user( $option_name, $user_id )
    {
        $user_data = get_option( $option_name );

        if( $user_data )
        {
            $user_data[] = array(
				'id'		=>	count( $user_data ) + 1,
				'user_id'	=>	$user_id,
				'time'		=>	time()
			);

            update_option( $option_name, $user_data );
        }
        else
        {
			$user_data = array();
			
            $user_data[] = array(
                'id'        =>  1,
                'user_id'   =>  $user_id,
                'time'      =>  time()
            );

            update_option( $option_name, $user_data );
        }
    }
}

\NewUserApproveZapier\User::get_instance();