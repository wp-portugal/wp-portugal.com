<?php
/*
  Plugin Name: CF7 Google Sheet Connector
  Plugin URI: https://wordpress.org/plugins/cf7-google-sheets-connector/
  Description: Send your Contact Form 7 data to your Google Sheets spreadsheet.
  Version: 4.9.2
  Author: GSheetConnector
  Author URI: https://www.gsheetconnector.com/
  Text Domain: gsconnector
 */

if ( !defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

// Declare some global constants
define( 'GS_CONNECTOR_VERSION', '4.9.2' );
define( 'GS_CONNECTOR_DB_VERSION', '4.9.2' );
define( 'GS_CONNECTOR_ROOT', dirname( __FILE__ ) );
define( 'GS_CONNECTOR_URL', plugins_url( '/', __FILE__ ) );
define( 'GS_CONNECTOR_BASE_FILE', basename( dirname( __FILE__ ) ) . '/google-sheet-connector.php' );
define( 'GS_CONNECTOR_BASE_NAME', plugin_basename( __FILE__ ) );
define( 'GS_CONNECTOR_PATH', plugin_dir_path( __FILE__ ) ); //use for include files to other files
define( 'GS_CONNECTOR_PRODUCT_NAME', 'Google Sheet Connector' );
define( 'GS_CONNECTOR_CURRENT_THEME', get_stylesheet_directory() );
load_plugin_textdomain( 'gsconnector', false, basename( dirname( __FILE__ ) ) . '/languages' );

/*
 * include utility classes
 */
if ( !class_exists( 'Gs_Connector_Utility' ) ) {
   include( GS_CONNECTOR_ROOT . '/includes/class-gs-utility.php' );
}
if ( !class_exists( 'Gs_Connector_Service' ) ) {
   include( GS_CONNECTOR_ROOT . '/includes/class-gs-service.php' );
}
//Include Library Files
require_once GS_CONNECTOR_ROOT . '/lib/vendor/autoload.php';

include_once( GS_CONNECTOR_ROOT . '/lib/google-sheets.php');

/*
 * Main GS connector class
 * @class Gs_Connector_Free_Init
 * @since 1.0
 */

class Gs_Connector_Free_Init {

   /**
    *  Set things up.
    *  @since 1.0
    */
   public function __construct() {
      //run on activation of plugin
      register_activation_hook( __FILE__, array( $this, 'gs_connector_activate' ) );

      //run on deactivation of plugin
      register_deactivation_hook( __FILE__, array( $this, 'gs_connector_deactivate' ) );

      //run on uninstall
      register_uninstall_hook( __FILE__, array( 'Gs_Connector_Free_Init', 'gs_connector_free_uninstall' ) );

      // validate is contact form 7 plugin exist
      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );

      // register admin menu under "Contact" > "Integration"
      add_action( 'admin_menu', array( $this, 'register_gs_menu_pages' ) );

      // load the js and css files
      add_action( 'init', array( $this, 'load_css_and_js_files' ) );
      
      // load the classes
		add_action( 'init', array( $this, 'load_all_classes' ) );

      // Add custom link for our plugin
      add_filter( 'plugin_action_links_' . GS_CONNECTOR_BASE_NAME, array( $this, 'gs_connector_plugin_action_links' ) );
      add_action( 'wp_dashboard_setup', array( $this, 'add_gs_connector_summary_widget' ) );
      
      add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
      
      // redirect to integration page after update
      add_action('admin_init', array( $this, 'redirect_after_upgrade' ), 999 );
   }

   /**
    * Do things on plugin activation
    * @since 1.0
    */
   public function gs_connector_activate( $network_wide ) {
      global $wpdb;
      $this->run_on_activation();
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         // check if it is a network activation - if so, run the activation function for each blog id
         if ( $network_wide ) {
            // Get all blog ids
            $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ( $blogids as $blog_id ) {
               switch_to_blog( $blog_id );
               $this->run_for_site();
               restore_current_blog();
            }
            return;
         }
      }

      // for non-network sites only
      $this->run_for_site();
   }

   /**
    * deactivate the plugin
    * @since 1.0
    */
   public function gs_connector_deactivate( $network_wide ) {
      
   }

   /**
    *  Runs on plugin uninstall.
    *  a static class method or function can be used in an uninstall hook
    *
    *  @since 1.5
    */
   public static function gs_connector_free_uninstall() {
      global $wpdb;
      Gs_Connector_Free_Init::run_on_uninstall_free();
      
      if( ! is_plugin_active( 'cf7-google-sheets-connector-pro/google-sheet-connector-pro.php' ) || ( ! file_exists( plugin_dir_path( __DIR__ ).'cf7-google-sheets-connector-pro/google-sheet-connector-pro.php' ) ) ) {
         return;
      }
      
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         //Get all blog ids; foreach of them call the uninstall procedure
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            Gs_Connector_Free_Init::delete_for_site_free();
            restore_current_blog();
         }
         return;
      }
      Gs_Connector_Free_Init::delete_for_site_free();
   }

   /**
    * Validate parent Plugin Contact Form 7 exist and activated
    * @access public
    * @since 1.0
    */
   public function validate_parent_plugin_exists() {
      $plugin = plugin_basename( __FILE__ );
      if ( ( ! is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) || ( ! file_exists( plugin_dir_path( __DIR__ ).'contact-form-7/wp-contact-form-7.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'contact_form_7_missing_notice' ) );
         add_action( 'network_admin_notices', array( $this, 'contact_form_7_missing_notice' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) {
            // Do not sanitize it because we are destroying the variables from URL
            unset( $_GET['activate'] );
         }
      }
   }

   /**
    * If Contact Form 7 plugin is not installed or activated then throw the error
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function contact_form_7_missing_notice() {
      $plugin_error = Gs_Connector_Utility::instance()->admin_notice( array(
         'type' => 'error',
         'message' => __( 'Google Sheet Connector Add-on requires Contact Form 7 plugin to be installed and activated.', 'gsconnector' )
      ) );
      echo $plugin_error;
   }

   /**
    * Create/Register menu items for the plugin.
    * @since 1.0
    */
   public function register_gs_menu_pages() {
      if ( current_user_can( 'wpcf7_edit_contact_forms' ) ) {
         $current_role = Gs_Connector_Utility::instance()->get_current_user_role();
         add_submenu_page( 'wpcf7', __( 'Google Sheets', 'gsconnector' ), __( 'Google Sheets', 'gsconnector' ), $current_role, 'wpcf7-google-sheet-config', array( $this, 'google_sheet_configuration' ) );
      }
   }

   /**
    * Google Sheets page action.
    * This method is called when the menu item "Google Sheets" is clicked.
    * @since 1.0
    */
   public function google_sheet_configuration() {
      include( GS_CONNECTOR_PATH . "includes/pages/google-sheet-settings.php" );
   }

   /**
    * Google Sheets page action.
    * This method is called when the menu item "Google Sheets" is clicked.
    *
    * @since 1.0
    */
   public function google_sheet_config() {
      ?>         	  
      <div class="wrap gs-form"> 
         <h1><?php echo esc_html( __( 'Contact Form 7 - Google Sheet Integration', 'gsconnector' ) ); ?></h1>
         <div class="gs-parts">
            <div class="gs-card" id="googlesheet">
               <h2 class="title"><?php echo esc_html( __( 'Google Sheets', 'gsconnector' ) ); ?></h2>

               <div class="inside">
                  <p class="gs-alert"> <?php echo esc_html( __( 'Click "Get code" to retrieve your code from Google Drive to allow us to access your spreadsheets. And paste the code in the below textbox. ', 'gsconnector' ) ); ?></p>
                  <p>
                     <label><?php echo esc_html( __( 'Google Access Code', 'gsconnector' ) ); ?></label>
                     <?php if (!empty(get_option('gs_token')) && get_option('gs_token') !== "") { ?>
 <input type="text" name="gs-code" id="gs-code" value="" disabled placeholder="<?php echo esc_html(__('Currently Active', 'gsconnector')); ?>"/>
               <input type="button" name="deactivate-log" id="deactivate-log" value="<?php _e('Deactivate', 'gsconnector'); ?>" class="button button-primary" />
               <span class="tooltip"> <img src="<?php echo GS_CONNECTOR_URL; ?>assets/img/help.png" class="help-icon"> <span class="tooltiptext tooltip-right">On deactivation, all your data saved with authentication will be removed and you need to reauthenticate with your google account.</span></span>
               <span class="loading-sign-deactive">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
            <?php } else { ?>
               <input type="text" name="gs-code" id="gs-code" value="" placeholder="<?php echo esc_html(__('Enter Code', 'gsconnector')); ?>"/>
                    <a href="https://accounts.google.com/o/oauth2/auth?access_type=offline&approval_prompt=force&client_id=1075324102277-drjc21uouvq2d0l7hlgv3bmm67er90mc.apps.googleusercontent.com&redirect_uri=urn%3Aietf%3Awg%3Aoauth%3A2.0%3Aoob&response_type=code&scope=https%3A%2F%2Fspreadsheets.google.com%2Ffeeds%2F+https://www.googleapis.com/auth/userinfo.email+https://www.googleapis.com/auth/drive.metadata.readonly" target="_blank" class="button">Get Code</a>
                     <?php } ?>
                  </p>
 <?php if (empty(get_option('gs_token'))) { ?>
                  <p> 
                     <input type="button" name="save-gs-code" id="save-gs-code" value="<?php _e( 'Save', 'gsconnector' ); ?>"
                            class="button button-primary" />
                      <?php } ?>
                     <span class="loading-sign">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                  </p>
				  
				  <?php
					$token = get_option('gs_token');
					if ( ! empty( $token ) && $token !== "") {
						$google_sheet = new CF7GSC_googlesheet();		
						$email_account = $google_sheet->gsheet_print_google_account_email(); 
						
						if( $email_account ) { ?>
							<p class="connected-account"><?php printf( __( 'Connected email account: %s', 'gsheetconnector-gravityforms' ), $email_account ); ?><p>
						<?php }else{?>
                      <p style="color:red" ><?php echo esc_html(__('Something wrong ! Your Auth Code may be wrong or expired. Please deactivate and do Re-Authentication again. ', 'gsconnector')); ?></p>
                    <?php 
                   } 
					}?>

                  <p>
                     <label><?php echo esc_html( __( 'Debug Log', 'gsconnector' ) ); ?></label>
                     <label><a href= "<?php echo plugins_url( '/logs/log.txt', __FILE__ ); ?>" target="_blank" class="debug-view" >View</a></label>
                     <label><a class="debug-clear" >Clear</a></label>
                     <span class="clear-loading-sign">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                  </p>
                  <p id="gs-validation-message"></p>
                  <span id="deactivate-message"></span>
                  <!-- set nonce -->
                  <input type="hidden" name="gs-ajax-nonce" id="gs-ajax-nonce" value="<?php echo wp_create_nonce( 'gs-ajax-nonce' ); ?>" />

               </div>
            </div>
            <div class="gs-sidebar-block">
               <h2 class="title">Rate us</h2>
               <a target="_blank" href="https://wordpress.org/support/plugin/cf7-google-sheets-connector/reviews/#new-post"><div class="gs-stars"></div></a>
               <p><?php echo __( "Did Contact Form 7 - Google Sheet Connector help you out ? Please leave a 5-star review. Thank you!", "gsconnector" ); ?></p><br/>
               <a target="_blank" href="https://wordpress.org/support/plugin/cf7-google-sheets-connector/reviews/?filter=5" class="button button-primary gs-review-button" rel="noopener noreferrer">Write a review</a>
            </div>
            <div class="gs-support">
               <h2 class="title"><?php echo esc_html( __( 'Need a helping hand ?', 'gsconnector' ) ); ?></h2>
               <p><h4><?php echo __( "Please ask for help on ", "gsconnector" ); ?><a href="https://wordpress.org/support/plugin/cf7-google-sheets-connector" target="_blank"><?php echo __( "Support Forum", "gsconnector" ); ?></a><?php echo __( " and ", "gsconnector" ); ?><a href="mailto:helpdesk@gsheetconnector.com"><?php echo __( "Email. ", "gsconnector" ); ?></a><br/><br/><?php echo __( "Do provide us detailed information about the issue along with wordpress version. ", "gsconnector" ); ?></h4></p>
            </div>
         </div>
         <div>
            <a href="https://www.gsheetconnector.com/" target="_blank"><img src="<?php echo GS_CONNECTOR_URL . 'assets/img/google-connector_banner-pro-repo.jpg'; ?>" class="gs-banner-img"></a>
         </div>
      </div>
      <?php
   }

   public function load_css_and_js_files() {
      add_action( 'admin_print_styles', array( $this, 'add_css_files' ) );
      add_action( 'admin_print_scripts', array( $this, 'add_js_files' ) );
   }

   /**
    * enqueue CSS files
    * @since 1.0
    */
   public function add_css_files() {
      if ( is_admin() && ( isset( $_GET['page'] ) && ( ( $_GET['page'] == 'wpcf7-new' ) || ( $_GET['page'] == 'wpcf7-google-sheet-config' ) || ( $_GET['page'] == 'wpcf7' ) ) ) ) {
         wp_enqueue_style( 'gs-connector-css', GS_CONNECTOR_URL . 'assets/css/gs-connector.css', GS_CONNECTOR_VERSION, true );
         wp_enqueue_style( 'gs-connector-faq-css', GS_CONNECTOR_URL . 'assets/css/faq-style.css', GS_CONNECTOR_VERSION, true );
      }
   }

   /**
    * enqueue JS files
    * @since 1.0
    */
   public function add_js_files() {
      if ( is_admin() && ( isset( $_GET['page'] ) && ( ( $_GET['page'] == 'wpcf7-new' ) || ( $_GET['page'] == 'wpcf7-google-sheet-config' ) ) ) ) {
         wp_enqueue_script( 'gs-connector-js', GS_CONNECTOR_URL . 'assets/js/gs-connector.js', GS_CONNECTOR_VERSION, true );
         wp_enqueue_script( 'jquery-json', GS_CONNECTOR_URL . 'assets/js/jquery.json.js', '', '2.3', true );
         
		}
      
      if ( is_admin() ) {
         wp_enqueue_script( 'gs-connector-adds-js', GS_CONNECTOR_URL . 'assets/js/gs-connector-adds.js', GS_CONNECTOR_VERSION, true );
         
		}
   }
   
   /**
    * Function to load all required classes
    * @since 2.8
    */
   public function load_all_classes() {
      if ( ! class_exists( 'GS_Connector_Adds' ) ) {
			include( GS_CONNECTOR_PATH . 'includes/class-gs-adds.php' );
		}
   }

   /**
    * called on upgrade. 
    * checks the current version and applies the necessary upgrades from that version onwards
    * @since 1.0
    */
   public function run_on_upgrade() {
      $plugin_options = get_site_option( 'google_sheet_info' );
      
      if ($plugin_options['version'] <= "3.0") {
         $this->upgrade_database_40();
      }

      // update the version value
      $google_sheet_info = array(
         'version' => GS_CONNECTOR_VERSION,
         'db_version' => GS_CONNECTOR_DB_VERSION
      );
      update_site_option( 'google_sheet_info', $google_sheet_info );
   }
   
   public function upgrade_database_40() {
      global $wpdb;

      // look through each of the blogs and upgrade the DB
      if (function_exists('is_multisite') && is_multisite()) {
         //Get all blog ids;
         $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
         foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            $this->upgrade_helper_40();
            restore_current_blog();
         }
         return;
      }
      $this->upgrade_helper_40();
   }
   
   public function upgrade_helper_40() {
      // Add the transient to redirect.
      set_transient('cf7gs_upgrade_redirect', true, 30);
   }
   
   public function redirect_after_upgrade() {
      if ( ! get_transient('cf7gs_upgrade_redirect') ) {
         return;
      }
      $plugin_options = get_site_option( 'google_sheet_info' );
      if( $plugin_options['version'] == "4.0") {
         delete_transient('cf7gs_upgrade_redirect');
         wp_safe_redirect('admin.php?page=wpcf7-google-sheet-config');
      }
   }

   /**
    * Add custom link for the plugin beside activate/deactivate links
    * @param array $links Array of links to display below our plugin listing.
    * @return array Amended array of links.    * 
    * @since 1.5
    */
   public function gs_connector_plugin_action_links( $links ) {
      // We shouldn't encourage editing our plugin directly.
		unset( $links['edit'] );
		
      // Add our custom links to the returned array value.
      return array_merge( array(
       '<a href="' . admin_url( 'admin.php?page=wpcf7-google-sheet-config' ) . '">' . __( 'Settings', 'gsconnector' ) . '</a>',
		 '<a class="upgradeProSet" style="color: red;font-weight: 600;font-style: italic;" href="https://www.gsheetconnector.com/cf7-google-sheet-connector-pro?gsheetconnector-ref=17"  target="__blank">' . __( 'Upgrade to PRO', 'gsconnector' ) . '</a>',
              ), $links );
   }

   public function add_gs_connector_summary_widget() {
      wp_add_dashboard_widget( 'gs_dashboard', __( 'CF7 Google Sheet Connector', 'gsconnector' )."<img style='width:60px' src='".GS_CONNECTOR_URL."assets/img/CF7GSheet-Connector-logo.png'>", array( $this, 'gs_connector_summary_dashboard' ) );
   }

   public function gs_connector_summary_dashboard() {
      include_once( GS_CONNECTOR_ROOT . '/includes/pages/cf7gs-dashboard-widget.php' );
   }

   /**
    * Called on activation.
    * Creates the site_options (required for all the sites in a multi-site setup)
    * If the current version doesn't match the new version, runs the upgrade
    * @since 1.0
    */
   private function run_on_activation() {
      $plugin_options = get_site_option( 'google_sheet_info' );
      if ( false === $plugin_options ) {
         $google_sheet_info = array(
            'version' => GS_CONNECTOR_VERSION,
            'db_version' => GS_CONNECTOR_DB_VERSION
         );
         update_site_option( 'google_sheet_info', $google_sheet_info );
      } else if ( GS_CONNECTOR_DB_VERSION != $plugin_options['version'] ) {
         $this->run_on_upgrade();
      }
   }

   /**
    * Called on activation.
    * Creates the options and DB (required by per site)
    * @since 1.0
    */
   private function run_for_site() {
      if ( !get_option( 'gs_access_code' ) ) {
         update_option( 'gs_access_code', '' );
      }
      if ( !get_option( 'gs_verify' ) ) {
         update_option( 'gs_verify', 'invalid' );
      }
      if ( !get_option( 'gs_token' ) ) {
         update_option( 'gs_token', '' );
      }
   }

   /**
    * Called on uninstall - deletes site_options
    *
    * @since 1.5
    */
   private static function run_on_uninstall_free() {
      if ( ! defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
         exit();
      }

      delete_site_option( 'google_sheet_info' );
   }

   /**
    * Called on uninstall - deletes site specific options
    *
    * @since 1.5
    */
   private static function delete_for_site_free() {
      if( ! is_plugin_active( 'cf7-google-sheets-connector-pro/google-sheet-connector-pro.php' ) || ( ! file_exists( plugin_dir_path( __DIR__ ).'cf7-google-sheets-connector-pro/google-sheet-connector-pro.php' ) ) ) {
         delete_option( 'gs_access_code' );
         delete_option( 'gs_verify' );
         delete_option( 'gs_token' );
         delete_post_meta_by_key( 'gs_settings' );
      }
   }

   /**
    * Build System Information String
    * @global object $wpdb
    * @return string
    * @since 5.3
    */
   public function get_cf7gs_system_info() {
      global $wpdb;
      // Get theme info
      $theme_data = wp_get_theme();
      $theme = $theme_data->Name . ' ' . $theme_data->Version;
      $parent_theme = $theme_data->Template;
      if ( !empty( $parent_theme ) ) {
         $parent_theme_data = wp_get_theme( $parent_theme );
         $parent_theme = $parent_theme_data->Name . ' ' . $parent_theme_data->Version;
      }

      $host = 'DBH: ' . DB_HOST . ', SRV: ' . $_SERVER['SERVER_NAME'];

      $return = '### Begin System Info ###' . "\n\n";

      // Start with the basics...
      $return .= '-- Site Info' . "\n\n";
      $return .= 'Site URL:                 ' . site_url() . "\n";
      $return .= 'Home URL:             ' . home_url() . "\n";
      $return .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

      // Can we determine the site's host?
      if ( $host ) {
         $return .= "\n" . '-- Hosting Provider' . "\n\n";
         $return .= 'Host:                     ' . $host . "\n";
      }

      $locale = get_locale();

      // WordPress configuration
      $return .= "\n" . '-- WordPress Configuration' . "\n\n";
      $return .= 'Version:                          ' . get_bloginfo( 'version' ) . "\n";
      $return .= 'Language:                      ' . (!empty( $locale ) ? $locale : 'en_US' ) . "\n";
      $return .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
      $return .= 'Active Theme:               ' . $theme . "\n";
      if ( $parent_theme !== $theme ) {
         $return .= 'Parent Theme:           ' . $parent_theme . "\n";
      }
      $return .= 'Show On Front:             ' . get_option( 'show_on_front' ) . "\n";

      // Only show page specs if frontpage is set to 'page'
      if ( get_option( 'show_on_front' ) == 'page' ) {
         $front_page_id = get_option( 'page_on_front' );
         $blog_page_id = get_option( 'page_for_posts' );

         $return .= 'Page On Front:              ' . ( $front_page_id != 0 ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
         $return .= 'Page For Posts:             ' . ( $blog_page_id != 0 ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
      }

      $return .= 'ABSPATH:                      ' . ABSPATH . "\n";
      $return .= 'WP_DEBUG:                   ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
      $return .= 'Memory Limit:               ' . WP_MEMORY_LIMIT . "\n";
      $return .= 'Registered Post Stati:    ' . implode( ', ', get_post_stati() ) . "\n";

      // Get plugins that have an update
      $updates = get_plugin_updates();

      // Must-use plugins
      // NOTE: MU plugins can't show updates!
      $muplugins = get_mu_plugins();
      if ( count( $muplugins ) > 0 ) {
         $return .= "\n" . '-- Must-Use Plugins' . "\n\n";

         foreach ( $muplugins as $plugin => $plugin_data ) {
            $return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
         }
      }

      // WordPress active plugins
      $return .= "\n" . '-- WordPress Active Plugins' . "\n\n";

      $plugins = get_plugins();
      $active_plugins = get_option( 'active_plugins', array() );

      foreach ( $plugins as $plugin_path => $plugin ) {
         if ( !in_array( $plugin_path, $active_plugins ) )
            continue;

         $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' ( needs update - ' . $updates[$plugin_path]->update->new_version . ' )' : '';
         $return .= $plugin['Name'] . '  :  ' . $plugin['Version'] . $update . "\n";
      }

      // WordPress inactive plugins
      $return .= "\n" . '-- WordPress Inactive Plugins' . "\n\n";

      foreach ( $plugins as $plugin_path => $plugin ) {
         if ( in_array( $plugin_path, $active_plugins ) )
            continue;

         $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
         $return .= $plugin['Name'] . '  :  ' . $plugin['Version'] . $update . "\n";
      }

      if ( is_multisite() ) {
         // WordPress Multisite active plugins
         $return .= "\n" . '-- Network Active Plugins' . "\n\n";

         $plugins = wp_get_active_network_plugins();
         $active_plugins = get_site_option( 'active_sitewide_plugins', array() );

         foreach ( $plugins as $plugin_path ) {
            $plugin_base = plugin_basename( $plugin_path );

            if ( !array_key_exists( $plugin_base, $active_plugins ) )
               continue;

            $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
            $plugin = get_plugin_data( $plugin_path );
            $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
         }
      }

      // Server configuration (really just versioning)
      $return .= "\n" . '-- Webserver Configuration' . "\n\n";
      $return .= 'PHP Version:                 ' . PHP_VERSION . "\n";
      $return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
      $return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";

      // PHP configs... now we're getting to the important stuff
      $return .= "\n" . '-- PHP Configuration' . "\n\n";
      $return .= 'Memory Limit:               ' . ini_get( 'memory_limit' ) . "\n";
      $return .= 'Upload Max Size:           ' . ini_get( 'upload_max_filesize' ) . "\n";
      $return .= 'Post Max Size:                ' . ini_get( 'post_max_size' ) . "\n";
      $return .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
      $return .= 'Time Limit:                     ' . ini_get( 'max_execution_time' ) . "\n";
      $return .= 'Max Input Vars:            ' . ini_get( 'max_input_vars' ) . "\n";
      $return .= 'Display Errors:               ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

      // PHP extensions and such
      $return .= "\n" . '-- PHP Extensions' . "\n\n";
      $return .= 'cURL:                     ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
      $return .= 'fsockopen:            ' . ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) . "\n";
      $return .= 'SOAP Client:          ' . ( class_exists( 'SoapClient' ) ? 'Installed' : 'Not Installed' ) . "\n";
      $return .= 'Suhosin:                ' . ( extension_loaded( 'suhosin' ) ? 'Installed' : 'Not Installed' ) . "\n";

      // Session stuff
      $return .= "\n" . '-- Session Configuration' . "\n\n";
      $return .= 'Session:                  ' . ( isset( $_SESSION ) ? 'Enabled' : 'Disabled' ) . "\n";
      // The rest of this is only relevant is session is enabled
      if ( isset( $_SESSION ) ) {
         $return .= 'Session Name:             ' . esc_html( ini_get( 'session.name' ) ) . "\n";
         $return .= 'Cookie Path:              ' . esc_html( ini_get( 'session.cookie_path' ) ) . "\n";
         $return .= 'Save Path:                ' . esc_html( ini_get( 'session.save_path' ) ) . "\n";
         $return .= 'Use Cookies:              ' . ( ini_get( 'session.use_cookies' ) ? 'On' : 'Off' ) . "\n";
         $return .= 'Use Only Cookies:         ' . ( ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off' ) . "\n";
      }

      $return .= "\n" . '### End System Info ###';

      return $return;
   }

}

// Initialize the google sheet connector class
$init = new Gs_Connector_Free_Init();
