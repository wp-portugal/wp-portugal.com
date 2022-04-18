<?php
namespace Login_With_AJAX\Blocks;
use LoginWithAjax;

class Login {

	public static $widget_default_args = array(
		'before_widget' => '<div class="widget %s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widgettitle">',
		'after_title'   => '</h2>',
	);

	public static $widget_args;

	public static function init() {
		wp_register_style( 'lwa-login-styles', plugins_url( 'build/style-index.css', __FILE__ ), array(), LOGIN_WITH_AJAX_VERSION );
		wp_register_script('lwa-blocks-login-editor', plugins_url( 'build/index.js', __FILE__ ), array('wp-block-editor','wp-blocks','wp-i18n','wp-server-side-render','wp-components','login-with-ajax') );
		add_action('wp_print_scripts', '\Login_With_AJAX\Blocks\Login::localize_script', 1000);

		$template_color = LoginWithAjax::get_color_hsl();
		$template_color['hex'] = LoginWithAjax::get_color_hex(); //save to pass onto colorpicker
		register_block_type( __DIR__, array(
			'title'       => _x( 'Login With AJAX', 'block title', 'login-with-ajax' ),
			'description' => _x( 'Login With AJAX block to generate a login widget.', 'block description', 'login-with-ajax' ),
			'render_callback' => '\Login_With_AJAX\Blocks\Login::render',
			'attributes' => array(
				'title' => array(
					'type' => 'string',
					'default' => __('Log In','login-with-ajax'),
				),
				'title_loggedin' => array(
					'type' => 'string',
					'default' => __( 'Hi', 'login-with-ajax' ).' %USERNAME%',
				),
				'template' => array(
					'type' => 'string',
					'default' => 'default',
				),
				'profile_link' => array(
					'type' => 'boolean',
					'default' => 1,
				),
				'registration' => array(
					'type' => 'number',
					'default' => 1,
				),
				'remember' => array(
					'type' => 'number',
					'default' => 1,
				),
				'v' => array( //logged in/out view in block editor
					'type' => 'boolean',
					'default' => true,
				),
				'widget_title' => array( // legacy to display widget title
					'type' => 'boolean',
					'default' => false,
				),
				'template_color' => array(
					'type' => 'object',
					'default' => $template_color,
				),
				// logged in stuff
				'avatar_rounded' => array(
					'type' => 'boolean',
					'default' => false,
				),
				'avatar_size' => array(
					'type' => 'number',
					'default' => 60,
				),
				'loggedin_vertical' => array(
					'type' => 'boolean',
					'default' => false,
				)
			),
		));
		// add sidebar hooks to detect widget title
		add_action('dynamic_sidebar_before', '\Login_With_AJAX\Blocks\Login::dynamic_sidebar_before', 10, 1);
		add_action('dynamic_sidebar_after', '\Login_With_AJAX\Blocks\Login::dynamic_sidebar_after', 10, 1);
	}

	/**
	 * Localize the block script with data only if it has been enqeued, to prevent unecessary processing.
	 */
	public static function localize_script(){
		if( wp_script_is('lwa-blocks-login-editor') ){
			$templates = array();
			foreach( LoginWithAjax::get_templates_data() as $template => $data ){ $templates[] = array('label'=> $data->label, 'value'=> $template); }
			wp_localize_script('lwa-blocks-login-editor', 'LoginWithAjax', array('templates' => $templates ) );
		}
	}

	public static function render( $attributes ){
		$attributes['force_login_display'] = defined('REST_REQUEST') && true === REST_REQUEST && 'edit' === filter_input( INPUT_GET, 'context', FILTER_SANITIZE_STRING );
		ob_start();
		if( !empty($attributes['widget_title']) ){
			$args = static::get_widget_args();
			if( !is_user_logged_in() && !empty($attributes['title']) ){
				echo $args['before_title'];
				echo '<span class="lwa-title">';
				echo esc_html($attributes['title']);
				echo '</span>';
				echo $args['after_title'];
				// unset title, set to widget_title for future reference
				$attributes['widget_title'] = $attributes['title'];
				unset($attributes['title']);
			}elseif( is_user_logged_in() && !empty($attributes['title_loggedin']) ) {
				echo $args['before_title'];
				echo '<span class="lwa-title">';
				echo str_replace('%username%', LoginWithAjax::$current_user->display_name, $attributes['title_loggedin']);
				echo '</span>';
				echo $args['after_title'];
				// unset title, set to widget_title for future reference
				$attributes['widget_title'] = $attributes['title_loggedin'];
				unset($attributes['title_loggedin']);
			}
		}
		LoginWithAjax::output( $attributes );
		return ob_get_clean();
	}

	public static function get_widget_args(){
		return !empty(static::$widget_args) ? static::$widget_args : static::$widget_default_args;
	}

	public static function dynamic_sidebar_before( $index ){
		global $wp_registered_sidebars;
		if( !empty($wp_registered_sidebars[ $index ]) ) {
			$sidebar = $wp_registered_sidebars[$index];
			static::$widget_args = $sidebar;
		}
	}

	public static function dynamic_sidebar_after( $index ){
		static::$widget_args = null;
	}
}
Login::init();
