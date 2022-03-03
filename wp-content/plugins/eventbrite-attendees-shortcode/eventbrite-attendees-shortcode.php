<?php
/**
 * Plugin Name: Eventbrite Attendees Shortcode
 * Plugin URI: http://austin.passy.co/wordpress-plugins/eventbrite-attendees-shortcode/
 * Description: List your attendees from your <a href="http://www.eventbrite.com/r/thefrosty">Eventbrite</a> event. Get your API user key <a href="https://www.eventbrite.com/userkeyapi">here</a>.
 * Version: 1.1.3
 * Author: Austin Passy
 * Author URI: http://austin.passy.co
 *
 * Developers can learn more about the WordPress shortcode API:
 * @link http://codex.wordpress.org/Shortcode_API
 *
 * @copyright 2009 - 2014
 * @author Austin Passy
 * @link http://austinpassy.com/2009/08/20/eventbrite-attendee-shortcode-plugin
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package Eventbrite_Attendees_Shortcode
 */

if ( !class_exists( 'Eventbrite_Attendees_Shortcode' ) ) {
class Eventbrite_Attendees_Shortcode {

	/**
	 * Holds the instances of this class.
	 *
	 * @since  0.4
	 * @access private
	 * @var    object
	 */
	private static $instance;
	
	public static $eas_script;
	
	/* Constants */
	const version = '1.1.3',
		  domain  = 'eventbrite-attendees';
		  
	/* Vars */
	var $settings_page,
		$prefix;
	
	/**
	 * Private settings
	 */
	private	$settings_api,
			$meta_box,
			$app_key = '6VULQ5N2UNDNZQP6TI';

	/**
	 * Returns the instance.
	 *
	 * @since  0.4
	 * @access public
	 * @return object
	 */
	public static function instance() {

		if ( !self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
	
	/**
	 * Sets up needed actions/filters for the plugin to initialize.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	public function __construct() {
		
		$this->prefix = 'eventbrite_attendees_shortcode';

		/* Set the constants needed by the plugin. */
		add_action( 'plugins_loaded', array( $this, 'constants' ), 1 );

		/* Load additional actions. */
		add_action( 'plugins_loaded', array( $this, 'add_actions' ), 3 );

		/* Load additional filters. */
		add_action( 'plugins_loaded', array( $this, 'add_filters' ), 3 );

		/* Internationalize the text strings used. */
		add_action( 'plugins_loaded', array( $this, 'i18n' ), 2 );

		/* Load all files. */
		add_action( 'plugins_loaded', array( $this, 'includes' ), 4 );
	}

	/**
	 * Defines constants for the plugin.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function constants() {
		
		/* Set constant file. */
		define( 'EVENTBRITE_ATTENDEES_FILE', __FILE__ );

		/* Set constant path to the plugin directory. */
		define( 'EVENTBRITE_ATTENDEES_DIR', trailingslashit( plugin_dir_path( EVENTBRITE_ATTENDEES_FILE ) ) );

		/* Set constant path to the plugin URI. */
		define( 'EVENTBRITE_ATTENDEES_URI', trailingslashit( plugin_dir_url( EVENTBRITE_ATTENDEES_FILE ) ) );
	}

	/**
	 * Add Actions.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function add_actions() {		
		add_action( 'wp_print_footer_scripts',	array( $this, 'scripts' ) );
		
		/* Shortcode */
		add_action( 'init',						array( $this, 'add_shortcode' ), 19 );
				
		/* Settings */
		add_action( 'admin_init',					array( $this, 'admin_init' ), 9 );
		add_action( 'admin_menu',					array( $this, 'admin_menu' ), 9 );
	}
	
	/**
	 * Add shorcodes.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function add_shortcode() {
		add_shortcode( 'eventbrite-attendees',	array( $this, 'shortcode' ) );
	}
	
	/**
	 * Add Filters.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function add_filters() {		
		add_filter( 'plugin_action_links',		array( $this, 'plugin_action' ), 10, 2 );
	}

	/**
	 * Loads the translation files.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function i18n() {
		load_plugin_textdomain( self::domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Loads admin files.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function includes() {

		if ( is_admin() ) {
			
			// Settings API
			require_once( EVENTBRITE_ATTENDEES_DIR . 'library/admin/class.settings-api.php' );
				$this->settings_api = new Eventbrite_Attendees_Shortcode_Settings_API;
				$this->settings_api->set_prefix( $this->prefix );
				$this->settings_api->set_domain( self::domain );
				$this->settings_api->set_version( self::version );
				
			require_once( EVENTBRITE_ATTENDEES_DIR . 'library/admin/class.meta-box.php' );
				$this->meta_box = new Eventbrite_Attendees_Meta_Box;
				$this->meta_box->set_domain( self::domain );
				$this->meta_box->set_version( self::version );
				
			// Dashboard widget
			if ( 'on' !== $this->get_option( 'dashboard', $this->prefix . '_help' ) ) {
				require_once( EVENTBRITE_ATTENDEES_DIR . 'library/admin/dashboard.php' );
				$dashboard = new Extendd_Dashboard_Widget;
				$dashboard->set_plugin( $this->prefix );
				$dashboard->set_args( array( 'enqueue' => true ) );
			}
		}
		
		// Eventbrite
		require_once( EVENTBRITE_ATTENDEES_DIR . 'library/classes/Eventbrite.php' );
	}
	
	/**
	 * Register the plugin scripts.
	 *
	 * @since  0.4
	 * @access public
	 * @return void
	 */
	function scripts() {
		if ( !self::$eas_script )
			return;
		
		// Style
		wp_enqueue_style( self::domain, plugins_url( 'library/css/eventbrite-attendees.css', EVENTBRITE_ATTENDEES_FILE ), null, self::version, 'screen' );
		wp_print_styles( self::domain );
		
		// Script
		wp_enqueue_script( self::domain, plugins_url( 'library/js/eventbrite-attendees.js', EVENTBRITE_ATTENDEES_FILE ), array( 'jquery' ), self::version, false );
		wp_print_scripts( self::domain );
	}
	
	/** 
	 * Registers settings section and fields
 	 */
    function admin_init() {
				
        $this->sections = array(
            array(
                'id'	=> $this->prefix . '_developer',
                'title' => __( 'Settings', self::domain )
            ),
			array(
                'id'	=> $this->prefix . '_help',
                'title' => __( 'Help', self::domain )
            ),
        );

        $fields = array(
            $this->prefix . '_developer' => array(
				/**
                array(
					'name'		=> 'app_key',
					'label'		=> __( 'APP Key', self::domain ),
					'desc'		=> sprintf( __( 'Enter your app key. Get one here: %s', self::domain ),
						make_clickable( 'https://www.eventbrite.com/api/key' ) ),
					'type' 		=> 'text',
					'size'		=> 'regular',
					'default' 	=> '',
					'sanitize_callback' => 'esc_attr',
                ),
				 */
                array(
					'name'		=> 'user_key',
					'label'		=> __( 'User Key', self::domain ),
					'desc'		=> '<br>' . sprintf( __( 'Get it here: %s', self::domain ),
						make_clickable( 'https://www.eventbrite.com/userkeyapi' ) ),
					'type' 		=> 'text',
					'size'		=> 'regular',
					'default' 	=> '',
					'sanitize_callback' => 'esc_attr',
                ),
			),
			$this->prefix . '_help' => array(
                array(
                    'name'		=> 'help',
                    'label'		=> '',
                    'desc'		=> sprintf( '
						<p><strong>%s</strong></p>
						<p><code>[eventbrite-attendees id="YOUR_EVENT_ID" sort="true|false" clickable="true|false"]</code></p>
						<p>%s<ul>
							<li>%s</li>
							<li>%s</li>
							<li>%s</li>
						</ul></p>',
							__( 'Use the shortcode in your page or posts like this:', self::domain ),
							__( 'Shortcode args:', self::domain ),
							sprintf( __( 'Replacing the "id" with your <a href="%s" rel="external" target="_blank" title="Eventbrite">Eventbrite</a> event id.', self::domain ),
								'http://www.eventbrite.com/r/thefrosty' ),
							__( 'sort: Should the attendee list be sorted by puchase date?', self::domain ),
							__( 'clickable: Should links be clickable?', self::domain )
						),
                    'type' 		=> 'html',
                ),
                array(
                    'name'		=> 'dashboard',
                    'label'		=> __( 'Hide Dashboard', self::domain ),
                    'desc'		=> __( 'Check to disable the dashboard widget.', self::domain ),
                    'type' 		=> 'checkbox',
                    'default' 	=> false,
                ),
			),
        );
		
        //set sections and fields
		$this->settings_api->set_sections( $this->sections );
		$this->settings_api->set_fields( $fields );

        //initialize them
        $this->settings_api->admin_init();
		
		add_action( $this->prefix . '_settings_sidebars', array( $this, 'sidebar' ), 1 );
		
		return $this;
    }

    /**
	 * Register the plugin page
	 */
    function admin_menu() {
		$options_page = add_options_page( __( 'Eventbrite Attendees Shortcode', self::domain ), __( 'Eventbrite Attendees', self::domain ), 'edit_plugins', self::domain, array( $this, 'plugin_page' ) );		
		
		add_action( 'admin_footer-' . $options_page, array( $this->settings_api, 'inline_jquery' ) );
    }	

	/**
	 * Display the plugin settings options page
	 */
    function plugin_page() {
        echo '<div class="wrap">';
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
        echo '</div>';		
    }

	/**
	 * Shortcode function
	 *
	 * @since 0.1
	 * @use [eventbrite-attendees
	 *			id="384870157"
	 *			user_key="USER_KEY"
	 *			sort="true|false"
	 *			clickable="true|false"]
	 */
	function shortcode( $args ) {
		
		/**
		 * Only Display
		 *
		 * can use: address,profile,ticket_id,quantity,first_name,last_name,email, currency,amount_paid,order_id,created,modified, event_date,discount,affiliate,order_type,barcodes,answers
		 */
		$only_display = apply_filters( 'eventbrite_attendees_only_display',
			array(
				'first_name',
				'last_name',
				'profile',
				'email',
				/**
				'ticket_id',
				'currency',
				'amount_paid',
				'order_id',
				'created',
				'modified',
				'event_date',
				'discount',
				'affiliate',
				'order_type',
				'barcodes',
				'address',
				'quantity',
				'answers'
				//**/
			)
		);
		//var_dump( $only_display ); exit;
		
		$defaults = array (
			'id'				=> '',
			'sort'				=> 'true',
			'clickable'		=> 'true',
			'only_display'		=> implode( ',', $only_display ),
			'do_not_display'	=> 'profile,answers,address'
		);
		
		// Parse incoming $args into an array and merge it with $defaults
		$args = wp_parse_args( $args, $defaults );
		//var_dump( $args ); exit;
				
		// Bail early
		if ( empty( $args['id'] ) )
			return sprintf( __( 'Please enter a valid <a href="%s">Eventbrite</a> "id".', self::domain ), 'http://www.eventbrite.com/r/thefrosty' );
		
		$transient = 'event_list_attendees_id_' . substr( md5( json_encode( $args ) ), 0, 21 );
		
//		delete_transient( $transient );
		
		if ( false === ( $attendees = get_transient( $transient ) ) ) :
			// Initialize the API client
			// Eventbrite API / Application key (REQUIRED)
			// http://www.eventbrite.com/api/key/
			// Eventbrite user_key (OPTIONAL, only needed for reading/writing private user data)
			// http://www.eventbrite.com/userkeyapi
			$auth = array(
				'app_key'	=> apply_filters( 'eventbrite_attendees_app_key', $this->app_key ),
				'user_key'	=> $this->get_option( 'user_key', $this->prefix . '_developer' ),
			);
			$eventbrite	= new Eventbrite( $auth );
			$attendees		= new stdClass;
			
			try {
				$attendees = $eventbrite->event_list_attendees( array( 'id' => $args['id'], 'only_display' => $args['only_display'], 'do_not_display' => $args['do_not_display'] ) );
			}
			catch ( Exception $e ) {
				$attendees = null;
			}
			
			if ( is_null( $attendees ) ) {
				delete_transient( $transient );
				return sprintf( __( 'An error has occurred%s', self::domain ), is_user_logged_in() && current_user_can('edit_pages') ? '<br><pre>' . $e->getMessage() . '</pre>' : '' );
			}
				
			self::$eas_script = true;
			
			set_transient( $transient, $attendees, HOUR_IN_SECONDS );
		endif;
			
		$sort	= filter_var( $args['sort'], FILTER_VALIDATE_BOOLEAN );
		$click	= filter_var( $args['clickable'], FILTER_VALIDATE_BOOLEAN );
		
		return $this->attendee_list_to_html( $attendees, $sort, $click );
	}
	
	/**
     * Helper function to print attendee HTML from Object Arrray.
     *
     * @param object	$attendee the attendee meta fields.
     * @param boolean	$sort should we resort the order?
     * @param boolean	$clickable should the value be clickable
     * @return string
	 */
	function attendee_list_to_html( $attendees, $sort = true, $clickable = true ) {
		$event = isset( $attendees->attendees[0]->attendee->event_id ) ? $attendees->attendees[0]->attendee->event_id : '';
		$html  = "<div class='eb-attendees-list' data-event-id='$event'>\n";
		
		if ( isset( $attendees->attendees ) ) {
			if ( $sort ) {
				usort( $attendees->attendees, array( $this, 'sort_attendees_by_created_date' ) );
			}
			//sort by name
			//usort( $attendees->attendees, array( $this, 'sort_attendees_by_name' ) );
			//render the attendee as HTML
			foreach( $attendees->attendees as $attendee ) {
				$html .= $this->attendee_to_html( $attendee->attendee, $clickable );
			}
		}
		else {
			$html .= '<ul><li class="eb-attendee-list-item">' . __( 'You can be the first to register for this event.', self::somain ) . "</li></ul>\n";
		}
		
		$html .= "</div>\n";
			
		return $html;
	}

	/**
     * Helper function to print attendee HTML.
     *
     * @param object	$attendee the attendee meta fields.
     * @param boolean	$clickable should the value be clickable
     * @return string
	 */
	function attendee_to_html( $attendee, $clickable ) {
		global $attendee_website;
		
	//	return '<pre>' . print_r( $attendee, true ) . '</pre>'; exit;
		
		$keys = (array) apply_filters( 'eventbrite_attendees_keys_to_unset', array( 'gender', 'age', 'blog', 'job_title', 'cell_phone', 'birth_date', 'notes', 'prefix', 'suffix' ) );
		$keys = array_merge( array_unique( $keys ), array( 'event_id', 'id' ) );
		
		$html = "<ul>\n";
		
		if ( ( isset( $attendee->first_name ) && !empty( $attendee->first_name ) ) && isset( $attendee->last_name ) && !empty( $attendee->last_name ) ) {
			$attendee->display_name = $attendee->first_name . ' ' . $attendee->last_name;
			unset( $attendee->first_name, $attendee->last_name );
		}
		
		$order = array( 'display_name', 'first_name', 'last_name', 'company', 'email', 'website' );
		
		foreach( $attendee as $name => $value ) {
			if ( in_array( $name, $keys ) ) {
				unset( $attendee->$name );
				continue;
			}
			
			if ( 'website' == $name ) {
				$attendee_website = $value;
			}
			
			ob_start();
			$this->attendee_template( $name, $value, $clickable );
		//	$html .= defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ? " $name:" : '';
			$html .= ob_get_clean();
		}
		
		$html .= "</ul>\n";
		
		return $html;
	}
	
	/**
	 *
	 */
	function attendee_template( $name, $value, $clickable ) {
		
		$folder		= apply_filters( 'eventbrite_attendees_folder_template', 'eventbrite-attendees-template' );
		$template	= trailingslashit( get_stylesheet_directory() ) . $folder . '/' . $name . '.php';	
				
		if ( !file_exists( $template ) ) {
			$template = trailingslashit( EVENTBRITE_ATTENDEES_DIR ) . 'template/' . $name . '.php';
		}
		
		if ( !file_exists( $template ) ) {
			$template = trailingslashit( EVENTBRITE_ATTENDEES_DIR ) . 'template/default.php';
		}
		
		include( $template );
	}
	
	/**
     * If value created exists return order by purchase date.
     *
     * @return int
	 */
	function sort_attendees_by_created_date( $x, $y ) {
		if ( isset( $x->attendee->created ) && isset( $y->attendee->created ) ) {
			if ( $x->attendee->created == $y->attendee->created ) {
				return 0;
			}
			return ( $x->attendee->created > $y->attendee->created ) ? -1 : 1;
		}
		return 0;
	}
	
	/**
     * Return order by first_name
     *
     * @return string
	 */
	function sort_attendees_by_name( $x, $y ) {
		return strcmp( $x->attendee->first_name, $y->attendee->first_name );
	}

	/**
	 * Sidebar info about this plugin
	 *
	 * @since	2.0
	 * @return	string
	 */
	function sidebar( $args ) {
		$content  = '<ul class="social">';
		$content .= '<li><span class="genericon genericon-user"></span>&nbsp;<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7329157">' . __( 'Support this plugin and buy me a beer', self::domain ) . '</a></li>';
		$content .= '<li><span class="genericon genericon-star"></span>&nbsp;<a href="http://wordpress.org/plugins/eventbrite-attendees-shortcode/">' . __( 'Rate this plugin on WordPress.org', self::domain ) . '</a></li>';
		$content .= '<li><span class="genericon genericon-wordpress"></span>&nbsp;<a href="http://wordpress.org/support/plugin/eventbrite-attendees-shortcode/">' . __( 'Get support on WordPress.org', self::domain ) . '</a></li>';

		$content .= '</ul>';
		$this->settings_api->postbox( $this->prefix . '_sidebar', sprintf( __( '<a href="%s">%s</a> | <code>version %s</code>', self::domain ), 'http://austin.passy.co/wordpress-plugins/eventbrite-attendees-shortcode/', ucwords( str_replace( '-', ' ', self::domain ) ), self::version ), $content, false );
	}

    /**
     * Get the value of a settings field
     *
     * @param string  $option  settings field name
     * @param string  $section the section name this field belongs to
     * @param string  $default default text if it's not found
     * @return string
     */
    function get_option( $option, $section, $default = '' ) {

        $options = get_option( $section );

        if ( isset( $options[$option] ) ) {
            return $options[$option];
        }

        return $default;
    }
	
	/**
	 * Plugin Action /Settings on plugins page
	 * @since 0.2
	 * @package plugin
	 */
	function plugin_action( $links, $file ) {
		if ( $file === plugin_basename( EVENTBRITE_ATTENDEES_FILE ) ) {
			$settings_link = '<a href="' . sprintf( admin_url( 'options-general.php?page=%s' ), self::domain ) . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}
	
}
};

/**
 * The main function responsible for returning the one true
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $eas = EVENTBRITE_ATTENDEES_SHORTCODE(); ?>
 *
 * @return The one true Instance
 */
if ( !function_exists( 'EVENTBRITE_ATTENDEES_SHORTCODE' ) ) {
	function EVENTBRITE_ATTENDEES_SHORTCODE() {
		return Eventbrite_Attendees_Shortcode::instance();
	}
}

// Out of the frying pan, and into the fire.
EVENTBRITE_ATTENDEES_SHORTCODE();