<?php
/*
Plugin Name: Theme Tester
Plugin URI: http://ocaoimh.ie/theme-tester/
Description: Allow an admin to test new themes without showing your blog visitors
Version: 0.3
License: GPL
Author: Donncha O Caoimh
Author URI: http://ocaoimh.ie/
*/

if ( !function_exists('wp_nonce_field') ) {
	function themetester_nonce_field($action = -1) { return; }
	$themetester_nonce = -1;
} else {
	function themetester_nonce_field($action = -1) { return wp_nonce_field($action); }
	$themetester_nonce = 'themetester-update-key';
}

function themetester_init() {
	add_action('admin_menu', 'themetester_config_page');
}
add_action('init', 'themetester_init');

function themetester_warning() {
	if( get_option( 'themetester' ) )
		echo "<div id='themetester-warning' class='updated fade'><p><strong>Theme Tester Active!</strong> Theme changes will not be visible to normal users until deactivated on <a href='themes.php?page=themetester-config'>the admin page</a>.</p></div>";
}
add_action('admin_notices', 'themetester_warning');

function themetester_config_page() {
	if ( function_exists('add_submenu_page') )
		add_submenu_page('themes.php', __('Theme Tester'), __('Theme Tester'), 'switch_themes', 'themetester-config', 'themetester_conf');
}

function themetester_deactivated() {
	if( get_option( 'tt_orig_template' ) ) { // change back to original settings
		update_option( 'template', get_option( 'tt_orig_template' ) );
		update_option( 'stylesheet', get_option( 'tt_orig_stylesheet' ) );
		update_option( 'current_theme', get_option( 'tt_orig_current_theme' ) );
	}
	delete_option( 'tt_orig_template' );
	delete_option( 'tt_orig_stylesheet' );
	delete_option( 'tt_orig_current_theme' );
	delete_option( 'themetester' );
}
add_action( 'deactivate_theme-tester/themetester.php', 'themetester_deactivated' );

function themetester_conf() {
	global $themetester_nonce;

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('switch_themes') )
			die(__('Cheatin&#8217; uh?'));

		check_admin_referer( $themetester_nonce );
		if( !get_option( 'themetester' ) ) {
			update_option( 'tt_orig_template', get_option( 'template' ) );
			update_option( 'tt_orig_stylesheet', get_option( 'stylesheet' ) );
			update_option( 'tt_orig_current_theme', get_option( 'current_theme' ) );
		} else {
			if( get_option( 'tt_orig_template' ) ) { // change back to original settings
				update_option( 'template', get_option( 'tt_orig_template' ) );
				update_option( 'stylesheet', get_option( 'tt_orig_stylesheet' ) );
				update_option( 'current_theme', get_option( 'tt_orig_current_theme' ) );
			}
		}
		update_option( 'themetester', intval( $_POST[ 'themetester_active' ] ) );
	}
?>
<?php if ( !empty($_POST ) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
<h2><?php _e('Theme Tester'); ?></h2>
<div class="narrow">
<form action="" method="post" id="themetester-conf" style="margin: auto; width: 400px; ">
<p><?php _e('This plugin allows you the administrator to test out new themes without changing the theme visible to his blog visitors.'); ?></p>

<?php themetester_nonce_field($themetester_nonce) ?>
<p><label><input id="key" name="themetester_active" type="checkbox" value="1" <?php if( get_option( 'themetester' ) ) { echo 'checked'; } ?>> Activate Theme Tester</label></p>
<?php if( get_option( 'themetester' ) ) {
	echo '<p>' . __( 'Visitors will continue to see this theme: ' ) . get_option( 'tt_orig_template' ) . '</p>';
} ?>
<p>Until you deactivate and select your new theme from the Design page your visitors will <em>not</em> see the new theme. This is to avoid accidentally activating an incomplete theme that was being tested.</p>
	<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
</form>
</div>
</div>
<?php
}

function get_admin_template( $value ) {
	global $current_user;
	if( !get_option( 'themetester' ) )
		return $value;
	wp_get_current_user();
	if( !$current_user->ID || ( $current_user->ID && !current_user_can( 'switch_themes' ) ) ) {
		$orig_template = get_option( 'tt_orig_template' );
		if( $orig_template ) {
			return $orig_template;
		} else {
			return $value;
		}
	}
	return $value;
}
add_filter( 'option_template', 'get_admin_template' );

function get_admin_stylesheet( $value ) {
	global $current_user;
	if( !get_option( 'themetester' ) )
		return $value;
	wp_get_current_user();
	if( !$current_user->ID || ( $current_user->ID && !current_user_can( 'switch_themes' ) ) ) {
		$orig_stylesheet = get_option( 'tt_orig_stylesheet' );
		if( $orig_stylesheet ) {
			return $orig_stylesheet;
		} else {
			return $value;
		}
	}
	return $value;
}
add_filter( 'option_stylesheet', 'get_admin_stylesheet' );

?>
