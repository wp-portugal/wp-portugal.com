<?php
/*
Plugin Name: Login With Ajax
Plugin URI: http://wordpress.org/extend/plugins/login-with-ajax/
Description: Ajax driven login widget. Customisable from within your template folder, and advanced settings from the admin area.
Author: Marcus Sykes
Version: 3.1.11
Author URI: http://msyk.es/?utm_source=login-with-ajax&utm_medium=plugin-header&utm_campaign=plugins
Tags: Login, Ajax, Redirect, BuddyPress, MU, WPMU, sidebar, admin, widget
Text Domain: login-with-ajax

Copyright (C) 2021 Pixelite SL

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
define('LOGIN_WITH_AJAX_VERSION', '3.1.11');
class LoginWithAjax {

	/**
	 * If logged in upon instantiation, it is a user object.
	 * @var WP_User
	 */
	public static  $current_user;
	/**
	 * List of templates available in the plugin dir and theme (populated in init())
	 * @var array
	 */
	public static $templates = array();
	/**
	 * Name of selected template (if selected)
	 * @var string
	 */
	public static $template;
	/**
	 * lwa_data option
	 * @var array
	 */
	public static $data;
	/**
	 * Location of footer file if one is found when generating a widget, for use in loading template footers.
	 * @var string
	 */
	public static $footer_loc;
	/**
	 * URL for the AJAX Login procedure in templates (including callback and template parameters)
	 * @var string
	 */
	public static $url_login;
	/**
	 * URL for the AJAX Remember Password procedure in templates (including callback and template parameters)
	 * @var string
	 */
	public static $url_remember;
	/**
	 * URL for the AJAX Registration procedure in templates (including callback and template parameters)
	 * @var string
	 */
	public static $url_register;

	// Actions to take upon initial action hook
	public static function init(){
		//Load LWA options
		self::$data = get_option('lwa_data');
		//Remember the current user, in case there is a logout
		self::$current_user = wp_get_current_user();

		//Get Templates from theme and default by checking for folders - we assume a template works if a folder exists!
		//Note that duplicate template names are overwritten in this order of precedence (highest to lowest) - Child Theme > Parent Theme > Plugin Defaults
		//First are the defaults in the plugin directory
		self::find_templates( path_join( WP_PLUGIN_DIR , basename( dirname( __FILE__ ) ). "/widget/") );
		//Now, the parent theme (if exists)
		if( get_stylesheet_directory() != get_template_directory() ){
			self::find_templates( get_template_directory().'/plugins/login-with-ajax/' );
		}
		//Finally, the child theme
		self::find_templates( get_stylesheet_directory().'/plugins/login-with-ajax/' );

		//Generate URLs for login, remember, and register
		self::$url_login = self::template_link(wp_login_url());
		self::$url_register = self::template_link(self::getRegisterLink());
		self::$url_remember = self::template_link(add_query_arg('action', 'lostpassword', wp_login_url()));

		//Make decision on what to display
		if ( !empty($_REQUEST["lwa"]) ) { //AJAX Request
		    self::ajax();
		}elseif ( isset($_REQUEST["login-with-ajax-widget"]) ) { //Widget Request via AJAX
			$instance = ( !empty($_REQUEST["template"]) ) ? array('template' => $_REQUEST["template"]) : array();
			$instance['profile_link'] = ( !empty($_REQUEST["lwa_profile_link"]) ) ? $_REQUEST['lwa_profile_link']:0;
			self::widget( $instance );
			exit();
		}else{
			//Enqueue scripts - Only one script enqueued here.... theme JS takes priority, then default JS
			if( !is_admin() ) {
			    $js_url = defined('WP_DEBUG') && WP_DEBUG ? 'login-with-ajax.source.js':'login-with-ajax.js';
				wp_enqueue_script( "login-with-ajax", self::locate_template_url($js_url), array( 'jquery' ), LOGIN_WITH_AJAX_VERSION );
				wp_enqueue_style( "login-with-ajax", self::locate_template_url('widget.css'), array(), LOGIN_WITH_AJAX_VERSION );
        		$schema = is_ssl() ? 'https':'http';
        		$js_vars = array('ajaxurl' => admin_url('admin-ajax.php', $schema));
        		//calendar translations
        		wp_localize_script('login-with-ajax', 'LWA', apply_filters('lwa_js_vars', $js_vars));
			}

			//Add logout/in redirection
			add_action('wp_logout', 'LoginWithAjax::logoutRedirect');
			add_filter('logout_url', 'LoginWithAjax::logoutUrl');
			add_filter('login_redirect', 'LoginWithAjax::loginRedirect', 1, 3);
			add_shortcode('login-with-ajax', 'LoginWithAjax::shortcode');
			add_shortcode('lwa', 'LoginWithAjax::shortcode');
		}
	}
	
	public static function widgets_init(){
		//Include and register widget
		include_once('login-with-ajax-widget.php');
		register_widget("LoginWithAjaxWidget");
	}

	/*
	 * LOGIN OPERATIONS
	 */

	// Decides what action to take from the ajax request
	public static function ajax(){
		$return = array('result'=>false, 'error'=>'Unknown command requested');
		switch ( $_REQUEST["login-with-ajax"] ) {
			case 'login': //A login has been requested
				//remove known interferences
				add_filter('ws_plugin__s2member_login_redirect', '__return_false');
			    $return = self::login();
				break;
			case 'remember': //Remember the password
				$return = self::remember();
				break;
			case 'register': //Remember the password
			default: // backwards-compatible with templates where lwa = registration
			    $return = self::register();
			    break;
		}
		@header( 'Content-Type: application/javascript; charset=UTF-8', true ); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
		echo self::json_encode(apply_filters('lwa_ajax_'.$_REQUEST["login-with-ajax"], $return));
		exit();
	}

	// Reads ajax login creds via POSt, calls the login script and interprets the result
	public static function login(){
		$return = array(); //What we send back
		if( !empty($_REQUEST['log']) && !empty($_REQUEST['pwd']) && trim($_REQUEST['log']) != '' && trim($_REQUEST['pwd'] != '') ){
			$loginResult = wp_signon();
			if ( strtolower(get_class($loginResult)) == 'wp_user' ) {
				//User login successful
				self::$current_user = $loginResult;
				/* @var $loginResult WP_User */
				$return['result'] = true;
				$return['message'] = __("Login Successful, redirecting...",'login-with-ajax');
				//Do a redirect if necessary
				$redirect = self::getLoginRedirect(self::$current_user);
				if( !empty($_REQUEST['redirect_to']) ) $redirect= wp_sanitize_redirect($_REQUEST['redirect_to']);
				if( $redirect != '' ){
					$return['redirect'] = $redirect;
				}
				//If the widget should just update with ajax, then supply the URL here.
				if( !empty(self::$data['no_login_refresh']) && self::$data['no_login_refresh'] == 1 ){
					//Is this coming from a template?
					$query_vars = ( !empty($_REQUEST['template']) ) ? "&template=".esc_attr($_REQUEST['template']) : '';
					$query_vars .= ( !empty($_REQUEST['lwa_profile_link']) ) ? "&lwa_profile_link=1" : '';
					$return['widget'] = get_bloginfo('wpurl')."?login-with-ajax-widget=1$query_vars";
					$return['message'] = __("Login successful, updating...",'login-with-ajax');
				}
			} elseif ( strtolower(get_class($loginResult)) == 'wp_error' ) {
				//User login failed
				/* @var WP_Error $loginResult */
				$return['result'] = false;
				$return['error'] = $loginResult->get_error_message();
			} else {
				//Undefined Error
				$return['result'] = false;
				$return['error'] = __('An undefined error has ocurred', 'login-with-ajax');
			}
		}else{
			$return['result'] = false;
			$return['error'] = __('Please supply your username and password.', 'login-with-ajax');
		}
		$return['action'] = 'login';
		//Return the result array with errors etc.
		return $return;
	}

	/**
	 * Checks post data and registers user, then exits
	 * @return string
	 */
	public static function register(){
	    $return = array();
	    if( get_option('users_can_register') ){
			$errors = register_new_user($_REQUEST['user_login'], $_REQUEST['user_email']);
			if ( !is_wp_error($errors) ) {
				//Success
				$return['result'] = true;
				$return['message'] = __('Registration complete. Please check your e-mail.','login-with-ajax');
				//add user to blog if multisite
				if( is_multisite() ){
				    add_user_to_blog(get_current_blog_id(), $errors, get_option('default_role'));
				}
			}else{
				//Something's wrong
				$return['result'] = false;
				$return['error'] = $errors->get_error_message();
			}
			$return['action'] = 'register';
	    }else{
	    	$return['result'] = false;
			$return['error'] = __('Registration has been disabled.','login-with-ajax');
	    }
		return $return;
	}

	// Reads ajax login creds via POST, calls the login script and interprets the result
	public static function remember(){
		$return = array(); //What we send back
		//if we're not on wp-login.php, we need to load it since retrieve_password() is located there
		if( !function_exists('retrieve_password') ){
		    ob_start();
		    include_once(ABSPATH.'wp-login.php');
		    ob_clean();
		}
		$result = retrieve_password();
		if ( $result === true ) {
			//Password correctly remembered
			$return['result'] = true;
			$return['message'] = __("We have sent you an email", 'login-with-ajax');
		} elseif ( strtolower(get_class($result)) == 'wp_error' ) {
			//Something went wrong
			/* @var $result WP_Error */
			$return['result'] = false;
			$return['error'] = $result->get_error_message();
		} else {
			//Undefined Error
			$return['result'] = false;
			$return['error'] = __('An undefined error has ocurred', 'login-with-ajax');
		}
		$return['action'] = 'remember';
		//Return the result array with errors etc.
		return $return;
	}

	//Added fix for WPML
	public static function logoutUrl( $logout_url ){
		//Add ICL if necessary
		if( defined('ICL_LANGUAGE_CODE') ){
			$logout_url .= ( strstr($logout_url,'?') !== false ) ? '&amp;':'?';
			$logout_url .= 'lang='.ICL_LANGUAGE_CODE;
		}
		return $logout_url;
	}
	
	public static function getRegisterLink(){
	    $register_link = false;
	    if ( function_exists('bp_get_signup_page') && (empty($_REQUEST['action']) || ($_REQUEST['action'] != 'deactivate' && $_REQUEST['action'] != 'deactivate-selected')) ) { //Buddypress
	    	$register_link = bp_get_signup_page();
	    }elseif ( is_multisite() ) { //MS
	    	$register_link = apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) );
	    } else {
	    	$register_link = wp_registration_url();
	    }
	    return $register_link;
	}

	/*
	 * Redirect Functions
	 */

	public static function logoutRedirect(){
		$redirect = self::getLogoutRedirect();
		if($redirect != ''){
			wp_safe_redirect($redirect);
			exit();
		}
	}

	public static function getLogoutRedirect(){
		$data = self::$data;
		//Global redirect
		$redirect = '';
		if( !empty($data['logout_redirect']) ){
			$redirect = $data['logout_redirect'];
		}
		//WPML global redirect
		$lang = !empty($_REQUEST['lang']) ? sanitize_text_field($_REQUEST['lang']):'';
		$lang = apply_filters('lwa_lang', $lang);
		if( !empty($lang) ){
			if( isset($data["logout_redirect_".$lang]) ){
				$redirect = $data["logout_redirect_".$lang];
			}
		}
		//Role based redirect
		if( strtolower(get_class(self::$current_user)) == "wp_user" ){
			//Do a redirect if necessary
			$data = self::$data;
			$user_role = array_shift(self::$current_user->roles); //Checking for role-based redirects
			if( !empty($data["role_logout"]) && is_array($data["role_logout"]) && isset($data["role_logout"][$user_role]) ){
				$redirect = $data["role_logout"][$user_role];
			}
			//Check for language redirects based on roles
			if( !empty($lang) ){
				if( isset($data["role_logout"][$user_role."_".$lang]) ){
					$redirect = $data["role_logout"][$user_role."_".$lang];
				}
			}
		}
		//final replaces
		if( !empty($redirect) ){
			$redirect = str_replace("%LASTURL%", $_SERVER['HTTP_REFERER'], $redirect);
			if( !empty($lang) ){
				$redirect = str_replace("%LANG%", $lang.'/', $redirect);
			}
		}
		return esc_url_raw($redirect);
	}

	public static function loginRedirect( $redirect, $redirect_notsurewhatthisis, $user ){
		$data = self::$data;
		if( is_object($user) ){
			$lwa_redirect = self::getLoginRedirect($user);
			if( $lwa_redirect != '' ){
				$redirect = $lwa_redirect;
			}
		}
		return $redirect;
	}

	public static function getLoginRedirect($user){
		$data = self::$data;
		//Global redirect
		$redirect = false;
		if( !empty($data['login_redirect']) ){
			$redirect = $data["login_redirect"];
		}
		//WPML global redirect
		$lang = !empty($_REQUEST['lang']) ? sanitize_text_field($_REQUEST['lang']):'';
		$lang = apply_filters('lwa_lang', $lang);
		if( !empty($lang) && isset($data["login_redirect_".$lang]) ){
			$redirect = $data["login_redirect_".$lang];
		}
		//Role based redirects
		if( strtolower(get_class($user)) == "wp_user" ){
			$user_role = array_shift($user->roles); //Checking for role-based redirects
			if( isset($data["role_login"][$user_role]) ){
				$redirect = $data["role_login"][$user_role];
			}
			//Check for language redirects based on roles
			if( !empty($lang) && isset($data["role_login"][$user_role."_".$lang]) ){
				$redirect = $data["role_login"][$user_role."_".$lang];
			}
			//Do user string replacements
			$redirect = str_replace('%USERNAME%', $user->user_login, $redirect);
			$redirect = str_replace('%USERNICENAME%', $user->user_nicename, $redirect);
		}
		//Do string replacements
		if( !strstr( wp_get_referer(), wp_login_url()) ){
			$redirect = str_replace("%LASTURL%", wp_get_referer(), $redirect);
		}
		if( !empty($lang) ){
			$redirect = str_replace("%LANG%", $lang.'/', $redirect);
		}
		return esc_url_raw($redirect);
	}

	/*
	 * WIDGET OPERATIONS
	 */

	public static function widget($instance = array() ){
		//Extract widget arguments
		//Merge instance options with global default options
		$lwa_data = wp_parse_args($instance, self::$data);
		//Deal with specific variables
		$is_widget = false; //backwards-comatibility for overriden themes, this is now done within the WP_Widget class
		$lwa_data['profile_link'] = ( !empty($lwa_data['profile_link']) && $lwa_data['profile_link'] != "false" );
		//Add template logic
		self::$template = ( !empty($lwa_data['template']) && array_key_exists($lwa_data['template'], self::$templates) ) ? $lwa_data['template']:'default';
		//Choose the widget content to display.
		if(is_user_logged_in()){
			//Firstly check for template in theme with no template folder (legacy)
			$template_loc = locate_template( array('plugins/login-with-ajax/widget_in.php') );
			//Then check for custom templates or theme template default
			$template_loc = ($template_loc == '' && self::$template) ? self::$templates[self::$template].'/widget_in.php':$template_loc;
			include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'widget/default/widget_in.php';
		}else{
		    //quick/easy WPML fix, should eventually go into a seperate file
		    if(  defined('ICL_LANGUAGE_CODE') ){
		        if( !function_exists('lwa_wpml_input_var') ){
                    function lwa_wpml_input_var(){ echo '<input type="hidden" name="lang" id="lang" value="'.esc_attr(ICL_LANGUAGE_CODE).'" />'; }
		        }
		        foreach( array('login_form','lwa_register_form', 'lostpassword_form') as $action ) add_action($action, 'lwa_wpml_input_var');
		    }
			//Firstly check for template in theme with no template folder (legacy)
			$template_loc = locate_template( array('plugins/login-with-ajax/widget_out.php') );
			//First check for custom templates or theme template default
			$template_loc = ($template_loc == '' && self::$template) ? self::$templates[self::$template].'/widget_out.php' : $template_loc;
			include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'widget/default/widget_out.php';
			//quick/easy WPML fix, should eventually go into a seperate file
			if(  defined('ICL_LANGUAGE_CODE') ){
			    foreach( array('login_form','lwa_register_form', 'lostpassword_form') as $action ) remove_action($action, 'lwa_wpml_input_var');
			}
		}
	}

	public static function shortcode($atts){
		ob_start();
		$defaults = array(
			'profile_link' => true,
			'template' => 'default',
			'registration' => true,
			'redirect' => false,
			'remember' => true
		);
		self::widget(shortcode_atts($defaults, $atts));
		return ob_get_clean();
	}

	public static function new_user_notification($user_login, $login_link, $user_email, $blogname){
		//Copied out of /wp-includes/pluggable.php
		$message = self::$data['notification_message'];
		$message = str_replace('%USERNAME%', $user_login, $message);
		$message = str_replace('%PASSWORD%', $login_link, $message);
		$message = str_replace('%BLOGNAME%', $blogname, $message);
		$message = str_replace('%BLOGURL%', get_bloginfo('wpurl'), $message);

		$subject = self::$data['notification_subject'];
		$subject = str_replace('%BLOGNAME%', $blogname, $subject);
		$subject = str_replace('%BLOGURL%', get_bloginfo('wpurl'), $subject);

		wp_mail($user_email, $subject, $message);
	}

	/*
	 * Auxillary Functions
	 */
	
	/**
	 * Returns the URL for a relative filepath which would be located in either a child, parent or plugin folder in order of priority.
	 * 
	 * This would search for $template_path within:
	 * /wp-content/themes/your-child-theme/plugins/login-with-ajax/...
	 * /wp-content/themes/your-parent-theme/plugins/login-with-ajax/...
	 * /wp-content/plugins/login-with-ajax/widget/...
	 * 
	 * It is assumed that the file always exists within the core plugin folder if the others aren't found.
	 * 
	 * @param string $template_path
	 * @return string
	 */
	public static function locate_template_url($template_path){
	    if( file_exists(get_stylesheet_directory().'/plugins/login-with-ajax/'.$template_path) ){ //Child Theme (or just theme)
	    	return trailingslashit(get_stylesheet_directory_uri())."plugins/login-with-ajax/$template_path";
	    }else if( file_exists(get_template_directory().'/plugins/login-with-ajax/'.$template_path) ){ //Parent Theme (if parent exists)
	    	return trailingslashit(get_template_directory_uri())."plugins/login-with-ajax/$template_path";
	    }
	    //Default file in plugin folder
	    return trailingslashit(plugin_dir_url(__FILE__))."widget/$template_path";
	}

	//Checks a directory for folders and populates the template file
	public static function find_templates($dir){
		if (is_dir($dir)) {
		    if ($dh = opendir($dir)) {
		        while (($file = readdir($dh)) !== false) {
		            if(is_dir($dir . $file) && $file != '.' && $file != '..' && $file != '.svn'){
		            	//Template dir found, add it to the template array
		            	self::$templates[$file] = path_join($dir, $file);
		            }
		        }
		        closedir($dh);
		    }
		}
	}
	
	/**
	 * Add template link and JSON callback var to the URL
	 * @param string $content
	 * @return string
	 */
	public static function template_link( $content ){
		return add_query_arg(array('template'=>self::$template), $content);
	}
	
	/**
	 * Returns a sanitized JSONP response from an array
	 * @param array $array
	 * @return string
	 */
	public static function json_encode($array){
		$return = json_encode($array);
		if( isset($_REQUEST['callback']) && preg_match("/^jQuery[_a-zA-Z0-9]+$/", $_REQUEST['callback']) ){
			$return = $_REQUEST['callback']."($return)";
		}
		return $return;
	}	
}
//Set when to init this class
add_action( 'init', 'LoginWithAjax::init' );
add_action( 'widgets_init', 'LoginWithAjax::widgets_init' );

//Installation and Updates
$lwa_data = get_option('lwa_data');
if( version_compare( get_option('lwa_version',0), LOGIN_WITH_AJAX_VERSION, '<' ) ){
    include_once('lwa-install.php');
}

//Include admin file if needed
if(is_admin()){
	include_once('login-with-ajax-admin.php');
}

//Include pluggable functions file if user specifies in settings
if( !empty($lwa_data['notification_override']) ){
	include_once('pluggable.php');
}

//Template Tag
function login_with_ajax($atts = ''){
    if( !array($atts) ) $atts = shortcode_parse_atts($atts);
	echo LoginWithAjax::shortcode($atts);
}