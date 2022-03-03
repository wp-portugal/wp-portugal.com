<?php

/**
 * wcb_session_query()
 *
 * Creates and fetches the session query.
 *
 * @return WP_Query The session query.
 */
function wcb_session_query( $args = '' ) {
	global $wcb_session_query;

	if ( isset( $wcb_session_query ) )
		return $wcb_session_query;

	$wcb_session_query = new WP_Query( array(
		'post_type'         => WCB_SESSION_POST_TYPE,
		'orderby'           => 'title',
		'order'             => 'DESC',
		'posts_per_page'    => -1,
	) );

	return $wcb_session_query;
}

/**
 * wcb_have_sessions()
 *
 * Whether there are more sessions available in the loop.
 *
 * @return object WordCamp information
 */
function wcb_have_sessions() {
	$query = wcb_session_query();
	return $query->have_posts();
}

/**
 * wcb_rewind_sessions()
 *
 * Rewind the sessions loop.
 */
function wcb_rewind_sessions() {
	$query = wcb_session_query();
	return $query->rewind_posts();
}

/**
 * wcb_the_session()
 *
 * Loads up the current session in the loop.
 *
 * @return object WordCamp information
 */
function wcb_the_session() {
	$query = wcb_session_query();
	return $query->the_post();
}

/**
 * wcb_get_session_speakers()
 *
 * Gets the speakers for the current session.
 *
 * @return object WordCamp information
 */
function wcb_get_session_speakers() {
	$sessions = wcb_get('sessions');
	return $sessions->meta_manager->get( get_the_ID(), 'speakers' );
}


/**
 * wcb_get_session_track()
 *
 * Gets the track for the current session.
 *
 * @return object WordCamp information
 */
function wcb_get_session_track() {
	$track = get_the_terms( get_the_ID(), WCB_TRACK_TAXONOMY );

	if ( empty( $track ) )
		return '';

	$track = array_values( $track );
	return $track[0]->name;
}


function wcb_session_entry_meta( $meta ) {
	if ( get_post_type() == WCB_SESSION_POST_TYPE ) {
		$track  = wcb_get_session_track();

		$meta['track']      = '<a href="' . get_term_link( $track, WCB_TRACK_TAXONOMY ) . '">';
		$meta['track']     .= sprintf( __('%s Track'), $track ) . '</a>';

		$meta['speakers']   = sprintf( __('Presented by %s'), wcb_get_session_speakers() );
		$meta['order']      = array('speakers', 'sep', 'track', 'edit');
	}

	return $meta;
}
add_filter( 'wcb_entry_meta', 'wcb_session_entry_meta' );

?>