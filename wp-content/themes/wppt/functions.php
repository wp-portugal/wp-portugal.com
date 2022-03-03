<?php
/**
 * Hide Jetpack for non admin users
 */
function wppt16_remove_jetpack() {

	if ( class_exists( 'Jetpack' ) && ! current_user_can( 'manage_options' ) ) {

		remove_menu_page( 'jetpack' );

	}

}


add_action( 'admin_init', 'wppt16_remove_jetpack' );
