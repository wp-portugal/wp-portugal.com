<?php
/*
Plugin Name: P2 Likes
Plugin URI: http://scottbasgaard.com/
Description: "P2 Likes" is a way to give positive feedback on threads you care about on P2.
Version: 1.0.7
Author: Scott Basgaard
Author URI: http://scottbasgaard.com/
License: GPL3 or later
*/

/*
Copyright 2012  Scott Basgaard  (email : mail@scottbasgaard.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'P2LIKES_URL', plugin_dir_url( __FILE__ ) );
define( 'P2LIKES_DIR', plugin_dir_path( __FILE__ ) );

function p2_likes_init() {

	// For 3.4
	// if ( function_exists( 'wp_get_theme' ) ) {
	// 	$current_theme = wp_get_theme();
	// 	$theme_name = $current_theme->name;
	// 	$theme_parent = $current_theme->parent_theme;
	// 	if ( $theme_name == "P2" || $theme_parent == "P2" ) {
	// 	}
	// }

	if ( is_user_logged_in() ) {
		add_action( 'p2_action_links', 'p2_likes_action_links' );
		add_filter( 'comment_reply_link', 'p2_likes_comment_reply_link', 99, 4 );
		add_action( 'wp_enqueue_scripts', 'p2_likes_enqueue_scripts' );
		add_action( 'wp_print_styles', 'p2_likes_enqueue_styles' );
	}

	// L10n
	load_plugin_textdomain( 'p2-likes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

}
add_action( 'plugins_loaded', 'p2_likes_init' );

function p2_likes_action_links() {
	global $post;
	global $current_user;
	$postmeta = get_post_meta( $post->ID, '_p2_likes', true );
	$users = p2_likes_generate_users_html($postmeta);
	$like_count = ( $postmeta ? count($postmeta) : 0 );
	$like_text = ( $postmeta && in_array( $current_user->ID, $postmeta ) ? __( 'Unlike', 'p2-likes' ) : __( 'Like', 'p2-likes' ) );
	echo "<div class='p2-likes-link'> | <a rel='nofollow' class='p2-likes-post p2-likes-post-".$post->ID."' href='". get_permalink($post). "' title='".$like_text."' onclick='p2Likes(0,".$post->ID."); return false;'><span class='p2-likes-like'>".$like_text."</span> (<span class='p2-likes-count'>".$like_count."</span>)</a><div class='p2-likes-box'>".$users."</div></div>";
}

function p2_likes_comment_reply_link( $link, $args, $comment, $post ) {
	global $current_user;
	$commentmeta = get_comment_meta( $comment->comment_ID, '_p2_likes', true );
	$users = p2_likes_generate_users_html($commentmeta);
	$like_count = ( $commentmeta ? count($commentmeta) : 0 );
	$like_text = ( $commentmeta && in_array( $current_user->ID, $commentmeta ) ? __( 'Unlike', 'p2-likes' ) : __( 'Like', 'p2-likes' ) );
	$output = "<div class='p2-likes-link'> | <a rel='nofollow' class='p2-likes-link p2-likes-comment p2-likes-comment-".$comment->comment_ID."' href='". get_permalink($post). "' title='".$like_text."' onclick='p2Likes(1,".$comment->comment_ID."); return false;'><span class='p2-likes-like'>".$like_text."</span> (<span class='p2-likes-count'>".$like_count."</span>)</a><div class='p2-likes-box'>".$users."</div></div>";
	return $link . $output;
}

function p2_likes_enqueue_scripts() {
	$p2_likes_data = array(
		'ajaxURL' => admin_url( 'admin-ajax.php' ),
		'unlike' => __( 'Unlike', 'p2-likes' ),
		'like'   => __( 'Like', 'p2-likes' )
	);

	wp_enqueue_script( 'p2-likes', P2LIKES_URL . '/js/p2-likes.js', array('jquery') );
	wp_localize_script( 'p2-likes', 'p2_likes', $p2_likes_data );
}

function p2_likes_enqueue_styles() {
	wp_enqueue_style( 'p2-likes', P2LIKES_URL . '/css/p2-likes.css' );
}

function p2_likes_generate_users_html($users) {
	$output = '';
	if ( !empty($users) ) :
		ob_start(); ?>
		<div class="p2-likes-box-inner">
		<?php foreach ( $users as $user_id ) : ?>
		<div id="p2-likes-user-<?php echo $user_id; ?>" class="p2-likes-user">
			<a href="<?php echo get_author_posts_url( $user_id ); ?>"><?php echo get_avatar( $user_id, 32 ); ?></a>
		</div>
		<?php endforeach; ?>
		</div>
		<?php
		$output = trim(ob_get_clean());
	endif;
	return $output;
}

if ( is_admin() && defined('DOING_AJAX') && DOING_AJAX ) {
	require_once( P2LIKES_DIR . '/ajax.php' );
}
