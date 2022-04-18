<?php
namespace Login_With_AJAX;
use LoginWithAjax;

// Class initialization
class Admin{
	// action function for above hook
	public static function init() {
		$lwa = get_option('lwa_data');
		add_action ( 'admin_menu', array ('\Login_With_AJAX\Admin', 'menus') );
		if( !empty($_REQUEST['lwa_dismiss_notice']) && wp_verify_nonce($_REQUEST['_nonce'], 'lwa_notice_'.$_REQUEST['lwa_dismiss_notice']) && current_user_can('manage_options') ){
			if( key_exists($_REQUEST['lwa_dismiss_notice'], $lwa['notices']) ){
			    unset($lwa['notices'][$_REQUEST['lwa_dismiss_notice']]);
			    if( empty($lwa['notices']) ) unset($lwa['notices']); 
    			update_option('lwa_data', $lwa);
			}
		}elseif( !empty($lwa['notices']) && is_array($lwa['notices']) && count($lwa['notices']) > 0 && current_user_can('manage_options') ){
			add_action('admin_notices', array('\Login_With_AJAX\Admin', 'admin_notices'));
		}
		static::register_scripts_and_styles();
		include_once('notices/admin-notices.php');
		include_once('notices/notices.php');
		Admin_Notices::$option_name = 'lwa_data';
		Admin_Notices::$option_notices_name = 'lwa_admin_notices';
		
		// disable legacy notice on settings save
		if( !empty($_POST['lwasubmitted']) && current_user_can('list_users') && wp_verify_nonce($_POST['_nonce'], 'login-with-ajax-admin'.get_current_user_id()) ) {
			if (!empty($lwa_data['legacy']) && empty($_POST['lwa_legacy'])) {
				Admin_Notices::remove('v4-legacy');
			}
		}
	}
	
	public static function register_scripts_and_styles(){
		$js_file = defined('WP_DEBUG') && WP_DEBUG ? 'login-with-ajax-admin.js' : 'login-with-ajax-admin.min.js';
		wp_register_script( "login-with-ajax-admin", LOGIN_WITH_AJAX_URL."assets/js/$js_file", array( 'login-with-ajax', 'wp-color-picker', 'iris' ), LOGIN_WITH_AJAX_VERSION );
		$css_file = defined('WP_DEBUG') && WP_DEBUG ? 'login-with-ajax-admin.css' : 'login-with-ajax-admin.min.css';
		wp_register_style( "login-with-ajax-admin", LOGIN_WITH_AJAX_URL."assets/css/$css_file", array(), LOGIN_WITH_AJAX_VERSION );
		// load scripts on lwa settings page
		if( !empty($_REQUEST['page']) && $_REQUEST['page'] === 'login-with-ajax') {
			add_action('admin_enqueue_scripts', '\Login_With_AJAX\Admin::enqueue_scripts_and_styles');
		}
	}
	
	public static function enqueue_scripts_and_styles(){
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'login-with-ajax' );
		wp_enqueue_script('login-with-ajax-admin');
		wp_enqueue_style('login-with-ajax-admin');
	}
	
	public static function menus(){
		$page = add_options_page('Login With Ajax', 'Login With Ajax', 'manage_options', 'login-with-ajax', array('\Login_With_AJAX\Admin','options'));
		add_action('admin_head-'.$page, array('\Login_With_AJAX\Admin','options_head'));
	}

	public static function admin_notices() {
		$lwa = get_option('lwa_data');
	    if( !empty($lwa['notices']['password_link']) ){
    		?>
    		<div class="updated notice notice-success is-dismissible password_link">
                <p>
                    <?php esc_html_e("Since WordPress 4.3 passwords are not emailed to users anymore, they're replaced with a link to create a new password.", 'login-with-ajax'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=login-with-ajax'); ?>"><?php esc_html_e("Check your registration email template.", 'login-with-ajax'); ?></a>
                </p>
                <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss','login-with-ajax') ?></span></button>
            </div>
    	    <script type="text/javascript">
    			jQuery('document').ready(function($){
    				$(document).on('click', '.updated.notice.password_link .notice-dismiss', function(event){
    					jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
							'lwa_dismiss_notice':'password_link', 
							'_nonce':'<?php echo wp_create_nonce('lwa_notice_password_link'); ?>'
        				});
    				});
    			});
    	    </script>
    		<?php
	    }
	}
	
	
	public static function options_head(){
		?>
		<style type="text/css">
			.nwl-plugin table { width:100%; }
			.nwl-plugin table .col { width:100px; }
			.nwl-plugin table input.wide { width:100%; padding:2px; }
			
		</style>
		<?php
	}
	
	public static function sanitize_deeply( $data ){
		if( !is_array($data) ){
			$clean = sanitize_text_field($data);
		} else {
			$clean = array();
			foreach( $data as $k => $v ){
				$clean[$k] = self::sanitize_deeply($v);
			}
		}
		return $clean;
	}
	
	public static function options() {
		global $lwa_data;
		add_option('lwa_data');
		$lwa_data = array( 'legacy' => false ); //legacy is set by default until v5.
		
		if( !empty($_POST['lwasubmitted']) && current_user_can('list_users') && wp_verify_nonce($_POST['_nonce'], 'login-with-ajax-admin'.get_current_user_id()) ){
			//Build the array of options here
			foreach ($_POST as $postKey => $postValue){
				if( $postValue != '' && preg_match('/lwa_role_log(in|out)_/', $postKey) ){
					//Custom role-based redirects
					if( preg_match('/lwa_role_login/', $postKey) ){
						//Login
						$lwa_data['role_login'][str_replace('lwa_role_login_', '', $postKey)] = esc_url_raw($postValue);
					}else{
						//Logout
						$lwa_data['role_logout'][str_replace('lwa_role_logout_', '', $postKey)] = esc_url_raw($postValue);
					}
				}elseif( $postKey === 'lwa_notification_message' ){
					if($postValue != ''){
						$lwa_data[substr($postKey, 4)] = sanitize_textarea_field($postValue);
					}
				}elseif( $postKey === 'lwa_template_color' ){
					$lwa_data['template_color'] = array('H'=>220, 'S' => 87, 'L' => 59);
					$lwa_data['template_color']['H'] = absint($postValue['H']);
					$lwa_data['template_color']['S'] = absint($postValue['S']);
					$lwa_data['template_color']['L'] = absint($postValue['L']);
				}elseif( substr($postKey, 0, 4) == 'lwa_' && $postKey !== 'lwa_nonce' ){
					//For now, no validation, since this is in admin area.
					$postKey = substr($postKey, 4);
					if( !empty($postValue) ){
						$cleanValue = static::sanitize_deeply($postValue);
						$key = sanitize_key($postKey);
						if( $key !== $postKey && $key === strtolower($postKey) ) $key = $postKey; // allow capital versions of key
						if( !empty($cleanValue) ){
							$lwa_data[$key] = $cleanValue;
						}
					}
 				}
			}
			update_option('lwa_data', $lwa_data);
			LoginWithAjax::$data = $lwa_data;
			LoginWithAjax::$template = $lwa_data['template'];
			if( !empty($_POST['lwa_notification_override']) ){
				$override_notification = $_POST['lwa_notification_override'] ? true:false;
				update_option('lwa_notification_override', $override_notification);
			}
			?>
			<div class="updated"><p><strong><?php esc_html_e('Changes saved.'); ?></strong></p></div>
			<?php
		}else{
			$lwa_data = get_option('lwa_data');	
		}
		
		// get tabs settings, in case we want to split them page by page rather than one page
		$tabs_enabled = defined('LWA_SETTINGS_TABS') && LWA_SETTINGS_TABS;
		// button for submission (reused)
		global $lwa_submit_button;
		$lwa_submit_button = '<p class="lwa-actions"><button type="submit" class="button-primary">'. esc_html__('Save Changes','login-with-ajax') .'</button></p>';
		?>
		<div class="wrap tabs-active">
			<h1><?php esc_html_e('Login With Ajax', 'login-with-ajax'); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php
				$tabs = $fixed_tabs = array(
					'general' => esc_html__('General Options','login-with-ajax'),
					'redirection' => esc_html__('Redirection','login-with-ajax'),
					'notifications' => esc_html__('Notifications','login-with-ajax'),
				);
				if( !defined('LWA_PRO_VERSION') && (!defined('LWA_REMOVE_PRO_NAGS') || !LWA_REMOVE_PRO_NAGS) ){
					$tabs['security'] = $fixed_tabs['security'] = esc_html__('Security','login-with-ajax');
					$tabs['go-pro'] = $fixed_tabs['go-pro'] = '<span style="color:green;">'. esc_html__('Pro Features!','login-with-ajax') . '</span>';
				}
				$tabs = apply_filters('lwa_settings_page_tabs', $tabs);
				foreach( $tabs as $tab_key => $tab_name ){
					$tab_link = $tabs_enabled ? esc_url(add_query_arg( array('lwa_tab'=>$tab_key))) : '';
					$active_class = ($tabs_enabled && !empty($_GET['lwa_tab']) && $_GET['lwa_tab'] == $tab_key) || $tab_key == 'general' ? 'nav-tab-active':'';
					echo "<a href='$tab_link#$tab_key' id='lwa-menu-$tab_key' class='nav-tab $active_class'>$tab_name</a>";
				}
				?>
			</h2>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2 lwa-settings">
					<div id="postbox-container-2" class="postbox-container">
						<form action="" method="post">
							<?php
							wp_nonce_field('lwa_options_submitted', 'lwa_nonce');
							foreach( $fixed_tabs as $fixed_tab => $tab_label ){
								if( empty($tabs_enabled) || (!empty($_REQUEST['lwa_tab']) || $_REQUEST['lwa_tab'] == $fixed_tab) ){
									?>
									<div class="lwa-menu-<?php echo $fixed_tab ?> lwa-menu-group">
										<?php include('settings/'.$fixed_tab.'.php'); ?>
									</div>
									<?php
								}
							}
							// output custom tabs content here
							if( !empty($tabs_enabled) && array_key_exists($_REQUEST['lwa_tab'], $tabs) && !array_key_exists($_REQUEST['lwa_tab'], $fixed_tabs) ){
								?>
								<div class="lwa-menu-<?php echo esc_attr($_REQUEST['lwa_tab']) ?> lwa-menu-group">
									<?php do_action('lwa_settings_page_tab_'. $_REQUEST['lwa_tab']); ?>
									<?php echo $lwa_submit_button; ?>
								</div>
								<?php
							}else{
								foreach( $tabs as $tab_key => $tab_name ){
									if( !empty($fixed_tabs[$tab_key]) ) continue;
									?>
									<div class="lwa-menu-<?php echo esc_attr($tab_key) ?> lwa-menu-group" style="display:none;">
										<?php do_action('lwa_settings_page_tab_'. $tab_key); ?>
										<?php echo $lwa_submit_button; ?>
									</div>
									<?php
								}
							}
							?>
							<input type="hidden" name="lwasubmitted" value="1" />
							<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce('login-with-ajax-admin'.get_current_user_id()); ?>" />
						</form>
					</div>
					<?php include('settings/sidebar.php'); ?>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Quick function to avoid having to change translatable strings that use <code> yet we need to run it through esc_html
	 * @param string $string
	 */
	public static function ph_esc( $string ){
		echo str_replace(array('&lt;code&gt;','&lt;/code&gt;'), array('<code>','</code>'), $string);
	}
}

// Start this plugin once all other plugins are fully loaded, but just before LoginWithAjax so we can register scripts
add_action( 'init', '\Login_With_AJAX\Admin::init');
?>