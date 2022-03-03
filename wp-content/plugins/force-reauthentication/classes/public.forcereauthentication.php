<?php

if( !class_exists( 'forcereauthenticationpublic') ) {

	class forcereauthenticationpublic {

		function __construct() {

			add_action( 'init', array( &$this, 'check_for_oncer' ), 1 );

		}

		function forcereauthenticationpublic() {
			$this->__construct();
		}

		function check_for_oncer() {

			if( is_user_logged_in() ) {
				// If the user is logged in then grab the current user
				$user = wp_get_current_user();
				// See if we have a oncer
				$reauth = shrkey_get_usermeta_oncer( $user->ID, '_shrkey_force_reauthentication' );
				if(!empty($reauth)) {
					// We have a oncer so force remove the logged in cookies
					@wp_logout();
					if(is_admin()) {
						wp_safe_redirect( wp_login_url( wp_get_referer() ) );
					} else {
						wp_safe_redirect( stripslashes( $_SERVER['REQUEST_URI'] ) );
					}
					exit;
				}
			}

		}

	}

}

$forcereauthenticationpublic = new forcereauthenticationpublic();