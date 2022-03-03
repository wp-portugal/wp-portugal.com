<?php
/*
Plugin Name: WordCamp Lisboa Ribbon
Plugin URI: http://2012.lisboa.wordcamp.org
Description: WordCamp Lisboa 2012 Ribbon. Core foundation from David Gwyer's Plugin Options Starter Kit.
Version: 1.2.1
Author: Filipe Varela
Author URI: http://wp-portugal.com
*/

function requires_wordpress_version() {
	global $wp_version;
	$plugin = plugin_basename( __FILE__ );
	$plugin_data = get_plugin_data( __FILE__, false );

	if ( version_compare($wp_version, "3.0", "<" ) ) {
		if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "'".$plugin_data['Name']."' requires WordPress 3.0 or higher, and has been deactivated. Please upgrade WordPress and try again.<br /><br />Go back to <a href='".admin_url()."'>WordPress admin</a>." );
		}
	}
}
add_action( 'admin_init', 'requires_wordpress_version' );


function myplugin_init() {
	load_plugin_textdomain('wclxribbon', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}
add_action('plugins_loaded', 'myplugin_init');


register_activation_hook(__FILE__, 'wclxribbon_defaults');
register_uninstall_hook(__FILE__, 'wclxribbon_delete_plugin_options');
add_action('admin_init', 'wclxribbon_init' );
add_action('admin_menu', 'wclxribbon_options_page');
add_filter( 'plugin_action_links', 'wclxribbon_plugin_action_links', 10, 2 );


function wclxribbon_delete_plugin_options() {
	delete_option('wclxribbon_options');
}


function wclxribbon_defaults() {
	$tmp = get_option('wclxribbon_options');
    if(($tmp['chk_default_options_db']=='1')||(!is_array($tmp))) {
		delete_option('wclxribbon_options');
		$arr = array("wclxribbon_selection" => "brightleft");
		update_option('wclxribbon_options', $arr);
	}
}


function wclxribbon_init(){
	register_setting('wclxribbon_plugin_options', 'wclxribbon_options');
}


function wclxribbon_options_page() {
	add_options_page('WordCamp Lisboa Ribbon Options', 'WordCamp Lisboa Ribbon', 'manage_options', __FILE__, 'wclxribbon_form');
}


function wclxribbon_form() {
	?>
	<div class="wrap">
		<div class="icon32" id="icon-options-general"><br></div>
		<h2><?php _e('WordCamp Lisboa Ribbon') ?></h2>
		<p><?php _e('Use the options below to choose the Ribbon and its placement on your site.') ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('wclxribbon_plugin_options'); ?>
			<?php $options = get_option('wclxribbon_options'); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><strong><?php _e('Bright Yellow Ribbon') ?></strong></th>
					<td>
						<label><input name="wclxribbon_options[wclxribbon_selection]" type="radio" value="brightleft" <?php checked('brightleft', $options['wclxribbon_selection']); ?> /> <?php _e('Left Corner') ?></label><br />
						<label><input name="wclxribbon_options[wclxribbon_selection]" type="radio" value="brightright" <?php checked('brightright', $options['wclxribbon_selection']); ?> /> <?php _e('Right Corner') ?></label><br />
					</td>
				</tr>
				<tr>
					<th scope="row"><strong><?php _e('Dark Grey Ribbon') ?></strong></th>
					<td>
						<label><input name="wclxribbon_options[wclxribbon_selection]" type="radio" value="darkleft" <?php checked('darkleft', $options['wclxribbon_selection']); ?> /> <?php _e('Left Corner') ?></label><br />
						<label><input name="wclxribbon_options[wclxribbon_selection]" type="radio" value="darkright" <?php checked('darkright', $options['wclxribbon_selection']); ?> /> <?php _e('Right Corner') ?></label><br />
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php	
}


function wclxribbon_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( __FILE__ ) ) {
		$posk_links = '<a href="'.get_admin_url().'options-general.php?page=wordcamp-lisbon-ribbon/wclxribbon.php">'.__('Settings').'</a>';
		array_unshift( $links, $posk_links );
	}
	return $links;
}


add_action( 'wp_enqueue_scripts', 'prefix_stylesheet' );
function prefix_stylesheet() {
	wp_register_style( 'wclx2012-ribbon-style', plugins_url('/css/wclx2012-ribbon.css', __FILE__) );
	wp_enqueue_style( 'wclx2012-ribbon-style' );
}


function wclxribbon_ribbon() {
	$options = get_option('wclxribbon_options');
	$select = $options['wclxribbon_selection'];
	
	if(function_exists('is_admin_bar_showing')) :
		$top = is_admin_bar_showing() ? 28 : 0;
	else:
		$top = 0;
	endif;
	
	if($select === "brightright"):
	    $image = plugins_url('images/wclx-ribbon-bright-right.png', __FILE__ );
		$side = "right";
	elseif($select === "darkright"):
	    $image = plugins_url('images/wclx-ribbon-dark-right.png', __FILE__ );
		$side = "right";
	elseif($select === "darkleft"):
	    $image = plugins_url('images/wclx-ribbon-dark-left.png', __FILE__ );
		$side = "left";
	else:
		$image = plugins_url('images/wclx-ribbon-bright-left.png', __FILE__ );
	    $side = "left";
	endif;

	echo "<a target='_blank' href='http://2012.lisboa.wordcamp.org' class='wclx-ribbon wclx-ribbon-$side' style='top: ".$top ."px;'><img src='{$image}' alt='WordCamp Lisboa 2012' class='wclx-ribbon-image' /></a>";
}
add_action('wp_footer', 'wclxribbon_ribbon');