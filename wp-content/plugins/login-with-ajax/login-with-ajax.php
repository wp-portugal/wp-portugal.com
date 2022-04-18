<?php
/*
Plugin Name: Login With Ajax
Plugin URI: http://wordpress.org/extend/plugins/login-with-ajax/
Description: Ajax driven login widget. Customisable from within your template folder, and advanced settings from the admin area.
Author: Marcus Sykes
Version: 4.0.1
Author URI: http://msyk.es/?utm_source=login-with-ajax&utm_medium=plugin-header&utm_campaign=plugins
Tags: Login, Ajax, Redirect, BuddyPress, MU, MultiSite, security, sidebar, admin, widget
Text Domain: login-with-ajax

Copyright (C) 2022 Marcus Sykes c/o Pixelite SL

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
define('LOGIN_WITH_AJAX_VERSION', '4.0.1');
define('LOGIN_WITH_AJAX_PATH', dirname(__FILE__));
define('LOGIN_WITH_AJAX_URL', trailingslashit(plugin_dir_url(__FILE__)));
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
	 * Name of the default template or the currently loaded template. Best not to mess with this and only use for reference.
	 * @var string
	 * @deprecated This will be converted to a private variable in future versions, use LoginWithAjax::get_template() instead.
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
	 * @deprecated Use LoginWithAjax::get_login_url() instead.
	 */
	public static $url_login;
	/**
	 * URL for the AJAX Remember Password procedure in templates (including callback and template parameters)
	 * @var string
	 * @deprecated Use LoginWithAjax::get_remember_url() instead.
	 */
	public static $url_remember;
	/**
	 * URL for the AJAX Registration procedure in templates (including callback and template parameters)
	 * @var string
	 * @deprecated Use LoginWithAjax::get_register_url() instead.
	 */
	public static $url_register;

	// Actions to take upon initial action hook
	public static function init(){
		//Load LWA options
		self::$data = get_option('lwa_data');
		self::$template = !empty(self::$data['template']) ? self::$data['template'] : 'default';
		//Remember the current user, in case there is a logout
		self::$current_user = wp_get_current_user();

		//Generate URLs for login, remember, and register - backward compatibility
		self::$url_login = static::get_login_url();
		self::$url_register = self::get_register_url();
		self::$url_remember = static::get_remember_url();

		// load dependent files
		include_once('blocks/login/login-block.php');
		include_once('login-with-ajax-ajaxify.php');
		
		// add authentication filter for other LWA features/add-ons to hook into instead of directly to WP
		add_filter('authenticate', 'LoginWithAjax::authenticate', 9999, 1);
		
		//Make decision on what to display
		if ( !empty($_REQUEST["lwa"]) ) { //AJAX Request
			do_action('lwa_loaded'); // loaded, will exit after this function
		    self::ajax();
		}elseif ( isset($_REQUEST["login-with-ajax-widget"]) ) { //Widget Request via AJAX
			do_action('lwa_loaded'); // loaded, will exit inside this clause
			$instance = ( !empty($_REQUEST["template"]) ) ? array('template' => $_REQUEST["template"]) : array();
			$instance['profile_link'] = ( !empty($_REQUEST["lwa_profile_link"]) ) ? $_REQUEST['lwa_profile_link']:0;
			self::widget( $instance );
			exit();
		}else{
			//Add logout/in redirection
			add_action('wp_logout', 'LoginWithAjax::logoutRedirect');
			add_filter('logout_url', 'LoginWithAjax::logoutUrl');
			add_filter('login_redirect', 'LoginWithAjax::loginRedirect', 1, 3);
			add_shortcode('login-with-ajax', 'LoginWithAjax::shortcode');
			add_shortcode('lwa', 'LoginWithAjax::shortcode');
		}
		self::register_scripts_and_styles();
		do_action('lwa_loaded');
	}
	
	public static function __callStatic($name, $arguments) {
		if( $name == 'getRegisterLink' ){
			return static::get_register_url( $arguments );
		}
	}
	
	public static function register_scripts_and_styles(){
		//Enqueue scripts - Only one script enqueued here.... theme CSS takes priority, then default JS
		if( !empty(self::$data['legacy']) ){
			wp_register_style( "login-with-ajax", self::locate_template_url('widget.css'), array(), LOGIN_WITH_AJAX_VERSION );
		}else {
			$css_url = defined('WP_DEBUG') && WP_DEBUG ? 'login-with-ajax.css' : 'login-with-ajax.min.css';
			wp_register_style("login-with-ajax", self::locate_template_url($css_url), array(), LOGIN_WITH_AJAX_VERSION);
		}
		//Enqueue scripts - Only one script enqueued here.... theme JS takes priority, then default JS
		if( !empty(self::$data['legacy']) ){
			// if in legacy mode, we check first to see if theme overrides with old filenames, if not then use legacy filename extensions that ship with plugin update
			if( defined('WP_DEBUG') && WP_DEBUG ) {
				$js_url = self::locate_legacy_template('login-with-ajax.source.js') ? 'login-with-ajax.source.js':'login-with-ajax.legacy.js';
			}else {
				$js_url = self::locate_legacy_template('login-with-ajax.js') ? 'login-with-ajax.js':'login-with-ajax.legacy.min.js';
			}
		}else{
			$js_url = defined('WP_DEBUG') && WP_DEBUG ? 'login-with-ajax.js':'login-with-ajax.min.js';
		}
		wp_register_script( "login-with-ajax", self::locate_template_url($js_url), array( 'jquery' ), LOGIN_WITH_AJAX_VERSION );
		add_action('wp_enqueue_scripts', 'LoginWithAjax::enqueue_scripts_and_styles');
	}
	
	public static function enqueue_scripts_and_styles( $force_load = null ){
		if( $force_load || !is_admin() || (!empty($_REQUEST['legacy-widget-preview']['idBase']) && $_REQUEST['legacy-widget-preview']['idBase'] == 'loginwithajaxwidget') ) {
			wp_enqueue_script( 'login-with-ajax' );
			wp_enqueue_style( 'login-with-ajax' );
			$schema = is_ssl() ? 'https':'http';
			$js_vars = array('ajaxurl' => admin_url('admin-ajax.php', $schema), 'off' => false );
			//calendar translations
			wp_localize_script('login-with-ajax', 'LWA', apply_filters('lwa_js_vars', $js_vars));
		}
	}
	
	public static 	function is_rest() {
		if (defined('REST_REQUEST') && REST_REQUEST // (#1)
			|| isset($_GET['rest_route']) // (#2)
			&& strpos( $_GET['rest_route'] , '/', 0 ) === 0)
			return true;
		
		// (#3)
		global $wp_rewrite;
		if ($wp_rewrite === null) $wp_rewrite = new WP_Rewrite();
		
		// (#4)
		$rest_url = wp_parse_url( trailingslashit( rest_url( ) ) );
		$current_url = wp_parse_url( add_query_arg( array( ) ) );
		return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
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
			case 'register': //Register
			case 'registration':
				$return = self::register();
				break;
			default: // backwards-compatible with templates where lwa = registration
			    do_action('lwa_ajax_action_'.$_REQUEST["login-with-ajax"], $return);
			    break;
		}
		@header( 'Content-Type: application/javascript; charset=UTF-8', true ); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
		echo self::json_encode(apply_filters('lwa_ajax_'.$_REQUEST["login-with-ajax"], $return));
		exit();
	}

	// Reads ajax login creds via POSt, calls the login script and interprets the result
	public static function login(){
		$return = array(); //What we send back
		$loginResult = false;
		if( !empty($_REQUEST['log']) && !empty($_REQUEST['pwd']) && trim($_REQUEST['log']) != '' && trim($_REQUEST['pwd'] != '') ){
			$loginResult = wp_signon();
			if ( $loginResult instanceof WP_User ) {
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
				if( isset($_REQUEST['refresh']) || !empty(self::$data['no_login_refresh']) && self::$data['no_login_refresh'] == 1 ){
					if( isset($_REQUEST['refresh']) && empty($_REQUEST['refresh']) || !isset($_REQUEST['refresh']) ){
						//Is this coming from a template?
						$query_vars = ( !empty($_REQUEST['template']) ) ? "&template=".esc_attr($_REQUEST['template']) : '';
						$query_vars .= ( !empty($_REQUEST['lwa_profile_link']) ) ? "&lwa_profile_link=1" : '';
						$return['widget'] = get_bloginfo('wpurl')."?login-with-ajax-widget=1$query_vars";
						$return['message'] = __("Login successful, updating...",'login-with-ajax');
					}
				}
			} elseif ( is_wp_error($loginResult) ) {
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
		return apply_filters('lwa_login', $return, $loginResult);
	}

	/**
	 * Checks post data and registers user, then exits
	 * @return array
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
	
	// links to register, sign in or remember
	
	/**
	 * Returns registration url (escaped) with template querystring if supplied
	 * @param string $template
	 * @return string
	 * @see LoginWithAjax::template_link()
	 */
	public static function get_register_url( $template = null ){
	    if ( function_exists('bp_get_signup_page') && (empty($_REQUEST['action']) || ($_REQUEST['action'] != 'deactivate' && $_REQUEST['action'] != 'deactivate-selected')) ) { //Buddypress
	    	$register_link = bp_get_signup_page();
	    }elseif ( is_multisite() ) { //MS
	    	$register_link = apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) );
	    } else {
	    	$register_link = wp_registration_url();
	    }
	    return static::template_link( $template, $register_link );
	}
	
	/**
	 * Returns forgotten password url (escaped) with template querystring if supplied
	 * @param string $template
	 * @return string
	 * @see LoginWithAjax::template_link()
	 */
	public static function get_remember_url( $template = null ){
		return static::template_link( $template, add_query_arg('action', 'lostpassword', wp_login_url()) );
	}
	
	/**
	 * Returns login url (escaped) with template querystring if supplied.
	 * @param string $template
	 * @return string
	 * @see LoginWithAjax::template_link()
	 */
	public static function get_login_url( $template = null ){
		return static::template_link( $template, wp_login_url() );
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
		if( !empty($_REQUEST['redirect']) ){
			$redirect = esc_url_raw($_REQUEST['redirect']);
		}elseif( strtolower(get_class(self::$current_user)) == "wp_user" ){
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
		}else{
			$redirect = str_replace('%LASTURL%', wp_login_url(), $redirect);
		}
		if( !empty($lang) ){
			$redirect = str_replace("%LANG%", $lang.'/', $redirect);
		}
		return esc_url_raw($redirect);
	}

	/*
	 * WIDGET OPERATIONS
	 */

	public static function output( $instance = array() ){
		//Extract widget arguments
		//Merge instance options with global default options
		$lwa = wp_parse_args($instance, self::$data);
		$lwa['id'] = rand(0, 100000); // for html id fields to be unique
		$lwa['css_vars'] = array();
		$lwa['css_style'] = '';
		//Create HSL CSS for barebones color customization
		if( !empty($lwa['template_color']) ){
			if( !is_array($lwa['template_color']) ){
				$hsl = explode(',', str_replace(' ', '', $lwa['template_color']));
				if( count($hsl) === 3 ) {
					$lwa['template_color']['H'] = $hsl[0];
					$lwa['template_color']['S'] = $hsl[1];
					$lwa['template_color']['L'] = $hsl[2];
				}elseif( preg_match('/^#?[0-9A-Za-z]{3,6}$/', $lwa['template_color']) ){
					// we assume it's hex, convert it to HSL
					require_once('assets/php/color.php');
					try {
						$hsl = \Login_With_AJAX\Color::hexToHsl($lwa['template_color']);
						$lwa['template_color'] = array(
							'H' => round($hsl['H']),
							'S' => round($hsl['S'] * 100),
							'L' => round($hsl['L'] * 100),
						);
					}catch ( Exception $exception ){
						$lwa['template_color'] = self::$data['template_color'];
					}
				}else{
					$lwa['template_color'] = self::$data['template_color'];
				}
			}
			$lwa['css_vars']['accent-hue'] = $lwa['template_color']['H'];
			$lwa['css_vars']['accent-s'] = $lwa['template_color']['S'].'%';
			$lwa['css_vars']['accent-l'] = $lwa['template_color']['L'].'%';
		}
		//Deal with specific variables
		$lwa['profile_link'] = ( !empty($lwa['profile_link']) && $lwa['profile_link'] !== "false" );
		$lwa['avatar_size'] = !empty($lwa['avatar_size']) && is_numeric($lwa['avatar_size']) ? absint($lwa['avatar_size']) : 60;
		// hook here
		$lwa = apply_filters('lwa_output_data', $lwa, $instance);
		//Add template logic
		$templates = static::load_templates();
		$template = ( !empty($lwa['template']) && array_key_exists($lwa['template'], $templates) ) ? $lwa['template']:self::$data['template'];
		$lwa['template'] = static::$template = $template;
		// convert CSS styles to inline
		if( empty($lwa['css_vars']['avatar-size']) ) $lwa['css_vars']['avatar-size'] = $lwa['avatar_size'].'px';
		foreach( $lwa['css_vars'] as $k => $v ){
			$lwa['css_style'] .= '--'.$k.':'. $v.'; ';
		}
		//Choose the widget content to display.
		$show_preview = isset($lwa['v']) && $lwa['v'] && isset($lwa['force_login_display']) && $lwa['force_login_display'];
		if( is_user_logged_in() && !$show_preview ){
			// one more thing...
			if( !empty($lwa['title_loggedin']) ) {
				$lwa['title_loggedin'] = str_replace(array('%username%', '%USERNAME%'), LoginWithAjax::$current_user->display_name, $lwa['title_loggedin']);
			}
			//Then check for custom templates or theme template default
			$lwa_data = $lwa; // backwards compatibility
			if( !empty($lwa['legacy']) ){
				$template_loc = $templates[$template].'/widget_in.php';
				include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'templates/legacy-default/widget_in.php';
			}else{
				$template_loc = $templates[$template].'/logged-in.php';
				include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'templates/default/logged-in.php';
			}
		}else{
		    //quick/easy WPML fix, should eventually go into a seperate file
		    if(  defined('ICL_LANGUAGE_CODE') ){
		        if( !function_exists('lwa_wpml_input_var') ){
                    function lwa_wpml_input_var(){ echo '<input type="hidden" name="lang" id="lang" value="'.esc_attr(ICL_LANGUAGE_CODE).'" />'; }
		        }
		        foreach( array('login_form','lwa_register_form', 'lostpassword_form') as $action ) add_action($action, 'lwa_wpml_input_var');
		    }
			//First check for custom templates or theme template default
			$lwa_data = $lwa; // backwards compatibility
			if( !empty($lwa['legacy']) ){
				$template_loc = $templates[$template].'/widget_out.php';
				include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'templates/legacy-default/widget_out.php';
			}else{
				$template_loc = $templates[$template].'/login.php';
				include ( $template_loc != '' && file_exists($template_loc) ) ? $template_loc : 'templates/default/login.php';
			}
			//quick/easy WPML fix, should eventually go into a seperate file
			if(  defined('ICL_LANGUAGE_CODE') ){
			    foreach( array('login_form','lwa_register_form', 'lostpassword_form') as $action ) remove_action($action, 'lwa_wpml_input_var');
			}
		}
		static::$template = self::$data['template'];
	}
	
	public static function get_output( $instance = array() ){
		ob_start();
		static::output($instance);
		return ob_get_clean();
	}
	
	public static function widget( $instance = array() ){
		static::output( $instance );
	}

	public static function shortcode($atts){
		$defaults = array(
			'profile_link' => true,
			'template' => 'default',
			'registration' => true,
			'redirect' => false,
			'redirect_logout' => false,
			'remember' => true,
			'rememberme' => 1,
			'refresh' => true,
		);
		$atts = shortcode_atts($defaults, $atts);
		unset($atts['v']);
		unset($atts['force_login_display']);
		return static::get_output( $atts );
	}

	public static function new_user_notification($user_login, $login_link, $user_email, $blogname){
		//Copied out of /wp-includes/pluggable.php
		$message = self::$data['notification_message'];
		$message = str_replace('%USERNAME%', $user_login, $message);
		$message = str_replace('%PASSWORDURL%', $login_link, $message);
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
	 * Gets name of currently loaded template.
	 * @return string
	 */
	public static function get_template(){
		return self::$template;
	}
	
	/**
	 * Returns the URL for a relative filepath which would be located in either a child, parent or plugin folder in order of priority.
	 * 
	 * This would search for $template_path within:
	 * /wp-content/themes/your-child-theme/plugins/login-with-ajax/...
	 * /wp-content/themes/your-parent-theme/plugins/login-with-ajax/...
	 * /wp-content/plugins/login-with-ajax/templates/...
	 * 
	 * It is assumed that the file always exists within the core plugin folder if the others aren't found.
	 * 
	 * @param string $template_path
	 * @return string
	 */
	public static function locate_template_url( $template_path ){
	    if( file_exists(get_stylesheet_directory().'/plugins/login-with-ajax/'.$template_path) ){ //Child Theme (or just theme)
	    	return trailingslashit(get_stylesheet_directory_uri())."plugins/login-with-ajax/$template_path";
	    }elseif( file_exists(get_template_directory().'/plugins/login-with-ajax/'.$template_path) ){ //Parent Theme (if parent exists)
	    	return trailingslashit(get_template_directory_uri())."plugins/login-with-ajax/$template_path";
	    }elseif( file_exists(WP_CONTENT_DIR.'/plugin-templates/login-with-ajax/'.$template_path) ){ //login-with-ajax folder in wp-contents
		    return trailingslashit(WP_CONTENT_DIR)."plugin-templates/login-with-ajax/$template_path";
	     
	    }
	    //Default file in plugin folder
	    return trailingslashit(plugin_dir_url(__FILE__))."templates/$template_path";
	}
	
	/**
	 * Detects whether template file exists in a child or parent theme, false if not. This is used to check if legacy files are still being used.
	 * @param $template_path
	 * @return bool
	 */
	public static function locate_legacy_template( $template_path ){
		$path = '/plugins/login-with-ajax/'.$template_path;
		return file_exists(get_stylesheet_directory().$path) || file_exists(get_template_directory().$path);
	}
	
	public static function get_template_path( $template ){
		static::load_templates();
		if( !empty(static::$templates[$template]) ){
			return static::$templates[$template];
		}
		return false;
	}
	
	public static function get_template_data( $template ){
		if( empty(static::$templates[$template]) ) $template = 'default';
		// get data about a template, we use filters here to provide
		$settings = self::$data;
		if( !empty($settings['legacy']) ){
			$legacy_templates = array('default' => 'Default', 'divs-only' => 'Divs Only', 'modal' => 'Modal');
			$name = !empty($legacy_templates[$template]) ? $legacy_templates[$template] : $template;
			$data = (object) array('label' => sprintf(esc_html__('%s (Legacy)', 'login-with-ajax'), $name), 'path' => static::get_template_path($template) );
		}else{
			$templates = array('default' => 'Default', 'modal' => 'Modal', 'minimalistic' => 'Minimalistic', 'modal-minimalistic' => 'Modal Minimalistic', 'classic' => 'Classic', 'classic-vanilla' => 'Classic Vanilla');
			$name = !empty($templates[$template]) ? $templates[$template] : $template;
			$data = (object) array('label' => $name, 'path' => static::get_template_path($template) );
		}
		$template_data = apply_filters('lwa_get_template_data_'.$template, $data);
		if( $data === $template_data ) $template_data->legacy = true;
		return $template_data;
	}
	
	/**
	 * Searches for templates within the specified directories and loads their data.
	 * This function will first load known templates within the plugin directory, then it will search for templates within the wp-content/plugin-templates/login-with-ajax folder,
	 * and finally in the parent/child theme folders in the plugins/login-with-ajax (if it exists). The last found directory would override the first from plugin > child theme in precedence.
	 * @param boolean $reload If set templates will be reloaded
	 * @return array
	 */
	public static function load_templates($reload = null ){
		if( !empty(static::$templates) && !$reload ) return static::$templates;
		static::$templates = array();
		// we will pre-load a functions.php file to allow pre-loading
		$wp_content_folder = path_join( WP_CONTENT_DIR , "/plugin-templates/login-with-ajax/");
		if( is_dir($wp_content_folder) && file_exists($wp_content_folder.'functions.php') ){
			include($wp_content_folder.'functions.php');
		}
		// allow for short-circuiting template search, maybe desirable for a minor performance boost to avoid unecessary template searching
		do_action('lwa_before_get_templates');
		if( !empty(static::$templates) ) return static::$templates;
		//Get Templates from theme and default by checking for folders - we assume a template works if a folder exists!
		//Note that duplicate template names are overwritten in this order of precedence (highest to lowest) - Child Theme > Parent Theme > wp-content folder > Plugin Defaults
		//First are the defaults in the plugin directory, we know these so hard-code found data
		self::find_templates( path_join( WP_PLUGIN_DIR , basename( dirname( __FILE__ ) ). "/templates/") );
		$plugin_templates_folder = path_join( WP_PLUGIN_DIR , basename( dirname( __FILE__ ) ). "/templates/");
		if( self::$data['legacy'] ) {
			self::$templates = array('default' => $plugin_templates_folder . 'legacy-default', 'modal' => $plugin_templates_folder . 'legacy-modal', 'divs-only' => $plugin_templates_folder . 'legacy-divs-only',);
		}else{
			self::$templates = array('default' => $plugin_templates_folder . 'default', 'modal' => $plugin_templates_folder . 'modal', 'minimalistic' => $plugin_templates_folder . 'minimalistic', 'modal-minimalistic' => $plugin_templates_folder . 'modal-minimalistic', 'classic' => $plugin_templates_folder . 'classic', 'classic-vanilla' => $plugin_templates_folder . 'classic-vanilla');
		}
		// then, add a search for custom folder in wp-contents/plugin-templates/login-with-ajax/ - The new and preferred way if you have themes that get updated and may overwrite custom lwa themes
		self::find_templates($wp_content_folder);
		//Now, the parent theme (if exists)
		if( get_stylesheet_directory() != get_template_directory() ){
			self::find_templates( get_template_directory().'/plugins/login-with-ajax/' );
		}
		//Finally, the child theme
		self::find_templates( get_stylesheet_directory().'/plugins/login-with-ajax/' );
		do_action('lwa_after_get_templates');
		return static::$templates;
	}

	//Checks a directory for folders and populates the template file
	public static function find_templates($dir){
		if (is_dir($dir)) {
		    if ($dh = opendir($dir)) {
		        while (($file = readdir($dh)) !== false) {
		            if(is_dir($dir . $file) && $file != '.' && $file != '..' && $file != '.svn' && $file != '.git'){
		            	//Template dir found, add it to the template array
		            	self::$templates[$file] = path_join($dir, $file);
		            }
		        }
		        closedir($dh);
		    }
		}
	}
	
	public static function get_templates_data(){
		$templates_data = array();
		foreach( static::load_templates() as $template => $path ){
			$templates_data[$template] = static::get_template_data( $template );
		}
		return apply_filters('lwa_get_templates_data', $templates_data);
	}
	
	public static function get_color_hsl( $css = false, $default = false ){
		$hsl = array('H' => 220, 'S' => 86, 'L' => 57); // #3372f0
		if( !empty(static::$data['template_color']) && !$default ){
			$hsl = static::$data['template_color'];
		}
		if( $css ){
			return "hsl(".$hsl['H'].", ".$hsl['S']."%, ".$hsl['L']."%)";
		}
		return $hsl;
	}
	
	/**
	 * @param false $default
	 * @return string
	 */
	public static function get_color_hex( $default = false ){
		$hsl = static::get_color_hsl( false, $default );
		try {
			require_once('assets/php/color.php');
			return Login_With_AJAX\Color::hslToHex( array( 'H' => $hsl['H'], 'S' => $hsl['S']/100, 'L' => $hsl['L']/100) );
		} catch ( Exception $e ){
			return '3372f0'; // return the default color
		}
	}
	
	/**
	 * @param $color
	 * @param bool $default
	 * @return Login_With_AJAX\Color
	 */
	public static function get_color( $color = false, $default = false ){
		require_once('assets/php/color.php');
		try {
			return new Login_With_AJAX\Color(static::get_color_hex($default));
		} catch( Exception $ex ){
			return new Login_With_AJAX\Color('3372f0');
		}
	}
	
	/**
	 * Adds a template query param to the provided link and escapes the link. If $template is not provided, then the currently loaded (or default) template is used. If false is provided then query variable will be removed if present in the url.
	 * @param string $url       The url to add template link
	 * @param string $template
	 * @return string
	 */
	public static function template_link( $url, $template = null ){
		if( $template === null ){
			$template = self::$template;
		}elseif( $template === false ){
			$template = null;
		}
		return esc_url(add_query_arg(array('template'=>$template), $url));
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
	
	/**
	 * Triggered by WP's authenticate hook which retriggers an lwa_authenticate filter, allowing LWA add-ons to hook into this and
	 * the possibility to deactivate all authentication triggers in one go (e.g. if a 2FA is successful).
	 *
	 * @param $result
	 * @return mixed|void
	 */
	public static function authenticate( $result ){
		return apply_filters('lwa_authenticate', $result);
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
	include_once('admin/admin.php');
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