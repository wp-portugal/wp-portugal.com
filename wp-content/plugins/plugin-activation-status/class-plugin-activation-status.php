<?php
/**
 * Define the Plugin_Activation_Status class
 * @package Plugin Activation Status
 * @version 1.0.1
 */

class Plugin_Activation_Status {
	var $active_plugins = array();
	var $inactive_plugins = array();
	var $active_on = array();
	var $all_plugins = array();
	var $blogs = array();
	var $sites = array();
	var $use_cache = true;
	
	/**
	 * Construct our Plugin_Activation_Status object
	 * Exits immediately if this is not a multisite install, if this is not the root network
	 * 		or if the current user does not have the delete_plugins cap
	 *
	 * @uses is_multisite() to determine whether this is a multisite install or not
	 * @uses $site_id to determine whether this is the root network or not
	 * @uses current_user_can() to determine whether the current user can delete plugins
	 * @uses add_action() to register the plugin page in the network_admin_menu
	 * @uses add_action() to register the meta boxes in the admin_init hook
	 * @uses add_action() to register the plugin's styles to the wp_enqueue_scripts hook
	 * @uses add_action() to enqueue the plugin's styles on the admin_print_styles hook
	 */
	function __construct() {
		if ( ! is_multisite() || ! is_main_network() || ! current_user_can( 'delete_plugins' ) )
			return;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		if ( isset( $_GET['list_active_plugins'] ) && wp_verify_nonce( $_GET['_active_plugins_nonce'], 'active_plugins' ) ) {
			$this->use_cache = false;
		}
		
		if ( isset( $_POST['pas-action'] ) && wp_verify_nonce( $_POST['_pas_deactivate_plugins'], 'pas_deactivate_plugins' ) ) {
			$this->use_cache = false;
			$this->deactivate_plugins();
		}
	}

	/**
	 * Initiate the plugin text domain
     *
     * @access public
     * @since  1.0.2.1
     * @return bool
	 */
	public function load_textdomain() {
	    load_plugin_textdomain( 'plugin-activation-status', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
    }
	
	/**
	 * Enqueue any scripts and styles that the plugin needs
	 * @uses wp_enqueue_style() to enqueue the plugin's style sheet
	 * @uses wp_enqueue_script() to enqueue the "post" scripts
	 */
	function enqueue_scripts() {
		/*print( "\n<!-- CSS File Location: " . plugins_url( 'plugin-activation-status.css', __FILE__ ) . " -->\n" );*/
		if ( isset( $_GET['page'] ) && 'all_active_plugins' == $_GET['page'] ) {
			wp_enqueue_style( 'plugin-activation-status', plugins_url( 'plugin-activation-status.css', __FILE__ ), array( 'colors' ), '0.2.3', 'all' );
			wp_enqueue_script( 'post' );
		}
	}
	
	/**
	 * Register the admin page
	 * @uses add_submenu_page()
	 */
	function admin_menu() {
		if ( ! is_multisite() || 1 !== intval( $GLOBALS['site_id'] ) )
			return;
		
		add_submenu_page( 'plugins.php', __( 'Locate Active Plugins', 'plugin-activation-status' ), __( 'Active Plugins', 'plugin-activation-status' ), 'delete_plugins', 'all_active_plugins', array( $this, 'submenu_page' ) );
	}
	
	/**
	 * Output the admin page
	 * @uses Plugin_Activation_Status::list_plugins() to output the list of plugins
	 */
	function submenu_page() {
?>
<div id="poststuff" class="wrap metabox-holder">
	<h2><?php _e( 'Locate Active Plugins', 'plugin-activation-status' ) ?></h2>
    <p><?php
        _e( 'This page will display a list of all plugins installed throughout this WordPress installation, and indicate whether that plugin is active on any sites or not. ', 'plugin-activation-status' );
        _e( 'This process can take quite a few resources, so it is not recommended that you run the process during any high-traffic times.', 'plugin-activation-status' );
        ?></p>
<?php
		if ( $this->use_cache ) {
		    $message_text = '<p>';
		    $message_text .= __( 'If you have generated this list before, the most recent version should be displayed below.', 'plugin-activation-status' );
		    $message_text .= ' ' . __( 'The date/time each list was generated is included within the list.', 'plugin-activation-status' );
		    $message_text .= ' ' . __( 'Keep in mind that the dates/times included are your server\'s date/time and may not reflect your local date/time.', 'plugin-activation-status' );
		    $message_text .= ' ' . __( 'The current date/time on your server is %1$s %2$s.', 'plugin-activation-status' );
		    $message_text .= '</p>';
		    $message_text .= '<p>';
		    $message_text .= __( 'If you would like to generate a new list with your current data, please press the "%1$s" button below.', 'plugin-activation-status' );
		    $message_text .= '</p>';

			printf( $message_text, __( 'Continue', 'plugin-activation-status' ), date( get_option( 'date_format' ) ), date( get_option( 'time_format' ) ) );

?>
    <form action="">
    	<input type="hidden" name="page" value="all_active_plugins"/>
        <?php wp_nonce_field( 'active_plugins', '_active_plugins_nonce' ) ?>
        <input type="hidden" name="list_active_plugins" value="1"/>
        <p><input type="submit" class="button button-primary" value="<?php _e( 'Continue', 'plugin-activation-status' ) ?>"/></p>
    </form>
<?php
		}
		
		$this->list_plugins();
?>
</div>
<?php
		return;
	}
	
	/**
	 * Register the two meta boxes used on the admin page
	 * @uses add_meta_box() to register those meta boxes
	 */
	function add_meta_boxes() {
		add_meta_box( 'inactive_plugins', __( 'Inactive Plugins', 'plugin-activation-status' ), array( $this, 'inactive_plugins_metabox' ), 'all_active_plugins' );
		add_meta_box( 'active_plugins', __( 'Active Plugins', 'plugin-activation-status' ), array( $this, 'active_plugins_metabox' ), 'all_active_plugins' );
	}
	
	/**
	 * Retrieve the list of plugins and list them
	 * @uses Plugin_Activation_Status::get_sites() to retrieve a list of site IDs
	 * @uses Plugin_Activation_Status::get_blogs() to retrieve a list of blog IDs
	 * @uses Plugin_Activation_Status::get_network_active_plugins() to retrieve a list of all network-active plugins
	 * @uses $wpdb
	 * @uses Plugin_Activation_Status::$active_on
	 * @uses Plugin_Activation_Status::$active_plugins
	 * @uses Plugin_Activation_Status::$blogs
	 * @uses Plugin_Activation_Status::$inactive_blogs
	 * @uses do_meta_boxes() to output the two plugin lists
	 */
	function list_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			_e( '<p>There was an error retrieving the list of plugins. The get_plugins() function does not seem to exist.</p>', 'plugin-activation-status' );
			return;
		}
		
		if ( false === $this->use_cache )
			$this->parse_plugins();
		
		do_meta_boxes( 'all_active_plugins', 'advanced', null );
	}
	
	function parse_plugins() {
		$this->sites = $this->get_sites();
		$this->blogs = $this->get_blogs();
		
		$network_plugins = $this->get_network_active_plugins();
		global $wpdb;
		
		foreach ( $network_plugins as $k => $val ) {
			$site_name = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key=%s AND site_id=%d", 'site_name', $val->site_id ) );
			$site_domain = $wpdb->get_row( $wpdb->prepare( "SELECT domain, path FROM {$wpdb->site} WHERE id=%d", $val->site_id ) );
			/*print( "\n<!-- Site Domain Information:\nSite ID: {$val->site_id}\nSite Path Info:" );
			var_dump( $site_domain );
			print( "\n-->\n" );*/
			$site_url = 'http://' . $site_domain->domain . $site_domain->path;
			
			if ( empty( $site_name ) )
				$site_name = $site_url;
			
			$v = maybe_unserialize( $val->meta_value );
			if ( ! is_array( $v ) )
				continue;
			if ( count( $v ) <= 0 )
				continue;
			
			$tmp = array_values( $v );
			/**
			 * Some records are stored with the plugin name as the key & the timestamp 
			 * 		of activation as the value; others are stored with just the plugin 
			 * 		name as the value, with numeric keys
			 */
			$v = is_numeric( $tmp[0] ) ? array_keys( $v ) : array_values( $v );
			$this->active_plugins = array_merge( $this->active_plugins, $v );
			foreach ( $v as $p ) {
				$this->active_on[$p]['network'][$val->site_id] = '<a href="' . esc_url( $site_url ) . '">' . $site_name . '</a>';
			}
		}
		
		global $wpdb;
		/**
		 * Retrieve all of the plugins active on individual sites
		 */
		foreach ( $this->blogs as $b ) {
			$wpdb->set_blog_id( $b );
			
			$blog_name = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'blogname' ) );
			$blog_url = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'siteurl' ) );
			
			if ( empty( $blog_name ) )
				$blog_name = $blog_url;
			
			$plugins = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'active_plugins' ) ) );
			if ( ! is_array( $plugins ) )
				continue;
			$tmp = array_values( $plugins );
			if ( count( $tmp ) <= 0 )
				continue;
			/**
			 * Some records are stored with the plugin name as the key & the timestamp 
			 * 		of activate as the value; others are stored with just the plugin 
			 * 		name as the value, with numeric keys
			 */
			$plugins = is_numeric( $tmp[0] ) ? array_keys( $plugins ) : array_values( $plugins );
			$this->active_plugins = array_merge( $this->active_plugins, $plugins );
			foreach ( $plugins as $p ) {
				$this->active_on[$p]['site'][$b] = '<a href="' . esc_url( $blog_url ) . '">' . $blog_name . '</a>';
			}
		}
		
		$this->all_plugins = get_plugins();
		
		/*print( '<pre><code>' );
		var_dump( $this->all_plugins );
		print( '</code></pre>' );*/
		
		foreach ( $this->all_plugins as $k => $v ) {
			if ( ! in_array( $k, $this->active_plugins ) )
				$this->inactive_plugins[] = $k;
		}
	}
	
	/**
	 * Output the Inactive Plugins meta box
	 */
	function inactive_plugins_metabox() {
		$this->list_inactive_plugins();
	}
	
	/**
	 * Output the Active Plugins meta box
	 */
	function active_plugins_metabox() {
		$this->list_active_plugins();
	}

	/**
	 * Retrieve the default message when a plugin list has not yet been generated
     *
     * @access private
     * @since  1.0.2.1
     * @return string
	 */
	private function _get_default_no_list_message() {
		$message_text = '<p>';
		$message_text .= __( 'An existing copy of this list could not be found in the database.', 'plugin-activation-status' );
		$message_text .= __( 'In order to view it, you will need to generate it using the button above.', 'plugin-activation-status' );
		$message_text .= '</p>';

		return $message_text;
    }
	
	/**
	 * Output the Inactive Plugins list
	 */
	function list_inactive_plugins() {
		if ( $this->use_cache ) {
			echo get_site_option( 'pas_inactive_plugins', $this->_get_default_no_list_message() );
			return;
		}
		
		$tmp = array();
		foreach ( $this->inactive_plugins as $p ) {
			if ( array_key_exists( $p, $this->all_plugins ) )
				$tmp[$this->all_plugins[$p]['Name']] = $p;
			else
				$tmp[$p] = $p;
		}
		ksort( $tmp );
		
		ob_start();
?>
    <div class="inactive-plugins plugins">
        <ol>
<?php
		$ct = 0;
		foreach ( $tmp as $p ) {
			$url = network_admin_url( 'plugins.php' );
			$url = wp_nonce_url( sprintf( '%5$s?action=delete-selected&amp;checked[]=%1$s&amp;plugin-status=%2$s&amp;paged=%3$s&amp;s=%4$s', $p, 'all', 1, null, $url ), 'bulk-plugins' );
			$dellink = sprintf( ' (<a href="%s">%s</a>)', $url, __( 'Delete', 'plugin-activation-status' ) );
?>
			<li class="<?php echo $ct%2 ? 'active' : 'inactive' ?>"><?php echo array_key_exists( $p, $this->all_plugins ) ? $this->all_plugins[$p]['Name'] : $p ?><?php echo $dellink ?></li>
<?php
			$ct++;
		}
?>
	    </ol>
        <p><small><em><?php printf( __( 'List generated on %s at %s', 'plugin-activation-status' ), date( get_option( 'date_format' ) ), date( get_option( 'time_format' ) ) ) ?></em></small></p>
	</div>
<?php
		$list = ob_get_clean();
		update_site_option( 'pas_inactive_plugins', $list );
		echo $list;
	}
	
	/**
	 * Output the Active Plugins list
	 */
	function list_active_plugins() {
		if ( $this->use_cache ) {
			echo get_site_option( 'pas_active_plugins', $this->_get_default_no_list_message() );
			return;
		}
		
		$tmp = array();
		foreach ( $this->active_plugins as $p ) {
			if ( array_key_exists( $p, $this->all_plugins ) )
				$tmp[$this->all_plugins[$p]['Name']] = $p;
			else
				$tmp[$p] = $p;
		}
		ksort( $tmp );
		
		ob_start();
?>
    <div class="active-plugins plugins">
    	<table>
        	<thead>
            	<tr>
                	<th><?php _e( '#' ) ?></th>
                	<th><?php _e( 'Plugin' ) ?></th>
                    <th><?php _e( 'Active On' ) ?></th>
                </tr>
            </thead>
            <tfoot>
            	<tr>
                	<th><?php _e( '#' ) ?></th>
                	<th><?php _e( 'Plugin' ) ?></th>
                    <th><?php _e( 'Active On' ) ?></th>
                </tr>
            </tfoot>
            <tbody>
<?php
		$i = 1;
		foreach ( $tmp as $p ) {
?>
				<tr class="<?php echo $i%2 ? 'active' : 'inactive' ?>">
                	<td><?php echo $i; $i++; ?></td>
                	<td><?php echo array_key_exists( $p, $this->all_plugins ) ? $this->all_plugins[$p]['Name'] : $p ?></td>
                    <td>
<?php
			if ( array_key_exists( $p, $this->active_on ) ) {
				if ( array_key_exists( 'network', $this->active_on[$p] ) && ! empty( $this->active_on[$p]['network'] ) ) {
					echo '<h4>' . __( 'Network Activated:', 'plugin-activation-status' ) . '</h4>';
					echo '<ul>';
					foreach ( $this->active_on[$p]['network'] as $id => $n ) {
						echo '<li>' . $id . '. ' . $n . '</li>';
					}
					echo '</ul>';
					printf( '
					<form method="post">
						<p>
							%5$s
							<input type="hidden" name="plugin" value="%1$s"/>
							<input type="hidden" name="pas-action" value="%2$s"/>
							<input type="hidden" name="networks" value="%4$s"/>
							<input type="submit" class="button" value="%3$s"/>
						</p>
					</form>', $p, 'deactivate-all-networks', __( 'Network Deactivate on All Networks', 'plugin-activation-status' ), urlencode( json_encode( $this->active_on[$p]['network'] ) ), wp_nonce_field( 'pas_deactivate_plugins', '_pas_deactivate_plugins', true, false ) );
				}
				if ( array_key_exists( 'site', $this->active_on[$p] ) && ! empty( $this->active_on[$p]['site'] ) ) {
					echo '<h4>' . __( 'Blog Activated:', 'plugin-activation-status' ) . '</h4>';
					echo '<ul>';
					foreach ( $this->active_on[$p]['site'] as $id=>$n ) {
						echo '<li>' . $id . '. ' . $n . '</li>';
					}
					echo '</ul>';
					printf( '
					<form method="post">
						<p>
							%5$s
							<input type="hidden" name="plugin" value="%1$s"/>
							<input type="hidden" name="pas-action" value="%2$s"/>
							<input type="hidden" name="blogs" value="%4$s"/>
							<input type="submit" class="button" value="%3$s"/>
						</p>
					</form>', $p, 'deactivate-all-blogs', __( 'Deactivate on All Sites', 'plugin-activation-status' ), urlencode( json_encode( $this->active_on[$p]['site'] ) ), wp_nonce_field( 'pas_deactivate_plugins', '_pas_deactivate_plugins', true, false ) );
				}
			} else {
				echo '<p>' . __( 'For some reason, a list of the sites and networks on which this plugin is active could not be retrieved', 'plugin-activation-status' ) . '</p>';
			}
?>
                    </td>
                </tr>
<?php
		}
?>
            </tbody>
        </table>
        <p><small><em><?php printf( __( 'List generated on %s at %s', 'plugin-activation-status' ), date( get_option( 'date_format' ) ), date( get_option( 'time_format' ) ) ) ?></em></small></p>
    </div>
<?php
		$list = ob_get_clean();
		update_site_option( 'pas_active_plugins', $list );
		echo $list;
	}
	
	/**
	 * Deactivate plugins according to command
	 */
	function deactivate_plugins() {
		if ( ! isset( $_POST['pas-action'] ) )
			return false;
		
		global $wpdb, $blog_id, $site_id;
		if ( 'deactivate-all-blogs' == $_POST['pas-action'] ) {
			$blogs = json_decode( urldecode( $_POST['blogs'] ) );
			if ( ! is_object( $blogs ) )
				return false;
			$originals = array( 'blog' => $blog_id, 'site' => $site_id );
			foreach ( (array) $blogs as $b => $link ) {
				$wpdb->set_blog_id( $b );
				$active_plugins = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'active_plugins' ) );
				if ( is_wp_error( $active_plugins ) ) {
					continue;
				}
				if ( ! is_array( $active_plugins ) )
					$active_plugins = maybe_unserialize( $active_plugins );
				if ( ! is_array( $active_plugins ) )
					continue;
					
				if ( in_array( $_POST['plugin'], $active_plugins ) ) {
					$index = array_search( $_POST['plugin'], $active_plugins );
					unset( $active_plugins[$index] );
				} elseif ( array_key_exists( $_POST['plugin'], $active_plugins ) ) {
					unset( $active_plugins[$_POST['plugin']] );
				}
				$done = $wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $active_plugins ) ), array( 'option_name' => 'active_plugins' ), array( '%s' ), array( '%s' ) );
			}
			$wpdb->set_blog_id( $originals['blog'], $originals['site'] );
		} elseif ( 'deactivate-all-networks' == $_POST['pas-action'] ) {
			$networks = json_decode( urldecode( $_POST['networks'] ) );
			foreach ( $networks as $n => $link ) {
				$active_plugins = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key=%s AND site_id=%d", 'active_sitewide_plugins', $n ) );
				if ( is_wp_error( $active_plugins ) ) {
					continue;
				}
				if ( ! is_array( $active_plugins ) )
					$active_plugins = maybe_unserialize( $active_plugins );
				if ( ! is_array( $active_plugins ) )
					continue;
					
				if ( in_array( $_POST['plugin'], $active_plugins ) ) {
					$index = array_search( $_POST['plugin'], $active_plugins );
					unset( $active_plugins[$index] );
				} elseif ( array_key_exists( $_POST['plugin'], $active_plugins ) ) {
					unset( $active_plugins[$_POST['plugin']] );
				}
				$done = $wpdb->update( $wpdb->sitemeta, array( 'meta_value' => maybe_serialize( $active_plugins ) ), array( 'meta_key' => 'active_sitewide_plugins', 'site_id' => $n ), array( '%s' ), array( '%s', '%d' ) );
			}
		}
	}
	
	/**
	 * Retrieve a list of network IDs
	 * @return array the list of IDs
	 */
	function get_sites() {
		global $wpdb;
		return $wpdb->get_col( "SELECT id FROM {$wpdb->site} ORDER BY id" );
	}
	
	/**
	 * Retrieve a list of blog IDs
	 * @return array the list of IDs
	 */
	function get_blogs() {
		global $wpdb;
		return $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} ORDER BY blog_id" );
	}
	
	/**
	 * Retrieve an array of the meta values listing network-active plugins
	 * Each list of network-active plugins may need to be unserialized when it's used
	 * @return array the list of site_id and meta_value
	 */
	function get_network_active_plugins() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT site_id, meta_value FROM {$wpdb->sitemeta} WHERE meta_key=%s", 'active_sitewide_plugins' ) );
	}
	
	/**
	 * Retrieve a list of active plugins
	 * @uses Plugin_Activation_Status::$active_plugins
	 * @return array empty array to hold the list of plugins
	 */
	function get_active_plugins() {
		$this->active_plugins = array();
	}
}

