<?php
global $wp_version;
use Login_With_AJAX\Admin_Notices;
use Login_With_AJAX\Admin_Notice;

$lwa_data = get_option('lwa_data');
if( !is_array($lwa_data) ) $lwa_data = array();
//no DB changes necessary

include_once('admin/notices/admin-notices.php');
Admin_Notices::$option_name = 'lwa_data';
Admin_Notices::$option_notices_name = 'lwa_admin_notices';

//add notices and upgrade logic
if( !get_option('lwa_version') ){
	// add welcome message
	$message = esc_html__("You have installed Login With AJAX 4.0! Check out our % for options and documentation links, also look out for the new Login With AJAX blocks on the widget and page builders.", 'login-with-ajax');
	$settings_url = '<a href="'. admin_url('options-general.php?page=login-with-ajax') .'">'. esc_html__("settings page", 'login-with-ajax') .'</a>';
	$message = sprintf($message, $settings_url);
	
	$Admin_Notice = new Admin_Notice('v4-upgrade', 'info', $message, 'all' );
	Admin_Notices::add( $Admin_Notice );
	add_option('lwa_data', array('ajaxify' => array('wp_login' => true)));
}

if( get_option('lwa_version') && version_compare($wp_version, '8.3', '>=') ){
    //upgrading from old WP version and LWA
    $message = esc_html__("Since WordPress 4.3 passwords are not emailed to users anymore, they're replaced with a link to create a new password.", 'login-with-ajax') .
               '<a href="'. admin_url('options-general.php?page=login-with-ajax') .'">'. esc_html__("Check your registration email template.", 'login-with-ajax') .'</a>';
    $Admin_Notice = new Admin_Notice('password-link', 'info', $message, 'all' );
    Admin_Notices::add( $Admin_Notice );
}

if( version_compare( get_option('lwa_version',0), '4.0', '<' ) ){
	// 4.0 Upgrade
	$lwa_data['legacy'] = true;
	$lwa_data['rememberme'] = 1;
	$lwa_data['template_color'] = array('H'=>220, 'S' => 87, 'L' => 59);
	$lwa_data['notification_subject'] = str_replace('%PASSWORD%', '%PASSWORDURL%', $lwa_data['notification_subject']);
	$lwa_data['notification_message'] = str_replace('%PASSWORD%', '%PASSWORDURL%', $lwa_data['notification_message']);
	$lwa_data['ajaxify'] = array('wp_login' => true);
	update_option('lwa_data', $lwa_data);
	
	$message = '<strong>' .esc_html__('You have upgraded to Login With AJAX 4.0!', 'login-with-ajax'). '</strong></p><p>';
	$message .= esc_html__('We have completely revamped our templates as well as adding Gutenberg support. You are currently on a backwards-compatible legacy mode which you can disable from the %s and upgrade to our new templates.', 'login-with-ajax') .'</p><p>';
	$message .= esc_html__('Check out our %s and also look out for the new Login With AJAX blocks on the widget and page builders.', 'login-with-ajax');
	$settings_url = '<a href="'. admin_url('options-general.php?page=login-with-ajax') .'">'. esc_html__("settings page", 'login-with-ajax') .'</a>';
	$message = sprintf($message, $settings_url, $settings_url);
	
	$Admin_Notice = new Admin_Notice('v4-upgrade', 'info', $message, 'all' );
	Admin_Notices::add( $Admin_Notice );
	
	
	$message = '<strong>' .esc_html__('Welcome to the redesigned Login With AJAX settings page!', 'login-with-ajax'). '</strong></p><p>';
	$message .= esc_html__('You are currently on a backwards-compatible legacy mode. You can disable via the checkbox below and save your settings, you will then be able to choose from our new templates.', 'login-with-ajax') .'</p><p>';
	$message = sprintf($message, $settings_url, $settings_url);
	
	$Admin_Notice = new Admin_Notice('v4-legacy', 'info', $message, 'settings' );
	Admin_Notices::add( $Admin_Notice );
}

update_option('lwa_version', LOGIN_WITH_AJAX_VERSION);