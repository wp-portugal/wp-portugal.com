<?php

function p2_likes_like_check() {

	global $current_user;

	if ( isset($_POST['type']) && isset($_POST['id']))  :
		
		$type = absint($_POST['type']);
		$id = absint($_POST['id']);
		
		switch ( $type ) {
			
			case '0': // Post
				p2_likes_process_post( $current_user->ID, $id );
				$users = get_post_meta( $id, '_p2_likes', true );
				break;
			
			case '1': // Comment
				p2_likes_process_comment( $current_user->ID, $id );
				$users = get_comment_meta( $id, '_p2_likes', true );
				break;
			
		} 
		
		echo json_encode( array( count($users), p2_likes_generate_users_html($users) ) );
	
	endif;
	
	exit;
	
}
add_action( 'wp_ajax_p2_likes_like', 'p2_likes_like_check' );
// add_action( 'wp_ajax_nopriv_p2_likes_like', 'p2_likes_like_check' );

function p2_likes_process_post( $user_id, $id ) {
	if ( $likes = get_post_meta( $id, '_p2_likes', true ) ) {
		if ( in_array( $user_id, $likes ) )
			$likes = array_merge( array_diff( $likes, array($user_id) ) );
		else
			$likes[] = $user_id;
		update_post_meta( $id, '_p2_likes', $likes );
	} else {
		update_post_meta( $id, '_p2_likes', array($user_id) );
	}
}

function p2_likes_process_comment( $user_id, $id ) {
	if ( $likes = get_comment_meta( $id, '_p2_likes', true ) ) {
		if ( in_array( $user_id, $likes ) )
			$likes = array_merge( array_diff( $likes, array($user_id) ) );
		else
			$likes[] = $user_id;
		update_comment_meta( $id, '_p2_likes', $likes );
	} else {
		update_comment_meta( $id, '_p2_likes', array($user_id) );
	}
}