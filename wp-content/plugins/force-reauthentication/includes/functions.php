<?php

if( !function_exists( 'shrkey_has_usermeta_oncer') ) {
	function shrkey_has_usermeta_oncer( $user_id, $meta ) {

		$value = get_user_meta( $user_id, $meta, true );
		if(!empty($value)) {
			return true;
		} else {
			return false;
		}

	}
}

if( !function_exists( 'shrkey_get_usermeta_oncer') ) {
	function shrkey_get_usermeta_oncer( $user_id, $meta ) {

		$value = get_user_meta( $user_id, $meta, true );
		if(!empty($value)) {
			// remove it as we only want it readable once
			delete_user_meta( $user_id, $meta );
		}

		return $value;

	}
}

if( !function_exists( 'shrkey_set_usermeta_oncer') ) {
	function shrkey_set_usermeta_oncer( $user_id, $meta, $value ) {

		update_user_meta( $user_id, $meta, $value );

	}
}

if( !function_exists( 'shrkey_delete_usermeta_oncer') ) {
	function shrkey_delete_usermeta_oncer( $user_id, $meta ) {

		delete_user_meta( $user_id, $meta );

	}
}