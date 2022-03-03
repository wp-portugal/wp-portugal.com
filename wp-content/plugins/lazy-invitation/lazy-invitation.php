<?php
/**
 * Plugin Name: BAW Slack Lazy invitation
 * Description: Slack Lazy Invitation lets you auto invite anyone to your Slack Group.
 * Author: Julio Potier
 * Author URI: http://wp-rocket.me
 * Plugin URI: http://boiteaweb.fr/?p=8611
 * Version: 1.4
 * Licence: GPLv2
 * Domain: bawsi
*/

define( 'BAWSI_VERSION', '1.4' );

/**
 * Load plugin textdomain.
 *
 * @since 1.0
 */
add_action( 'plugins_loaded', 'bawsi_load_textdomain' );
function bawsi_load_textdomain() {
	load_plugin_textdomain( 'bawsi', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );
}

add_action( 'load-settings_page_slackinvit_settings', 'bawsi_new_option' );
function bawsi_new_option() {
	$bawsi_options = get_option( 'bawsi' );
	$bawsi_options = ! $bawsi_options ? get_site_option( 'bawsi' ) : $bawsi_options;
	if ( isset( $bawsi_options['groupname'], $bawsi_options['token'] ) ) {
		$bawsi_options['groups'] = $bawsi_options['groupname'] . ',' . $bawsi_options['token'];
		unset( $bawsi_options['groupname'], $bawsi_options['token'] );
		update_option( 'bawsi', $bawsi_options );
	}
}

/**
* Prints the form, do the request or print messages
*
* @since 1.1 Support recaptcha lib v2 and v1
* @since 1.0
*/
add_action( 'login_form_slack-invitation', 'bawsi_do_invit' );
function bawsi_do_invit() {
	bawsi_new_option();
	$bawsi_options = get_option( 'bawsi' );
	$bawsi_options = ! $bawsi_options ? get_site_option( 'bawsi' ) : $bawsi_options;
	$message = '';
	if ( ! isset( $bawsi_options['groups'] ) || false == trim( $bawsi_options['groups'] ) ) {
		$message .= '<p class="message">' . __( '<b>ERROR</b> Groups are not set!', 'bawsi' ) . '</p><br>';
		$response = true;
	}
	$groups = array_filter( explode( "\n", $bawsi_options['groups'] ) );
	foreach( $groups as $k => $g ) {
		$temp = explode( ',', $g );
		$groups[ reset( $temp ) ] = end( $temp );
		unset( $groups[ $k ] );
	}
	unset( $g );
	$group = isset( $_POST['group'] ) ? $_POST['group'] : null;
	$token = isset( $groups[ $group ] ) ? $groups[ $group ] : '';
	if ( empty( $message ) && ! $token ) {
		$message = '<p class="message">';
		if ( count( $groups ) === 1 ) {
			$message .= sprintf( __( 'Enter your email address to join the <a href="http://%1$s.slack.com"><b>%1$s</b></a> group on Slack.', 'bawsi' ), esc_html( key( $groups ) ) );
		} else {
			$message .= __( 'Enter your email address to join the selected group on Slack.', 'bawsi' );
		}
		$message .= '</p>';
	}
	if ( ! empty( $_POST['email'] ) && isset( $group ) && is_email( $_POST['email'] ) ) {
		if ( class_exists( 'ReCAPTCHAPlugin' ) ) {
			$recaptcha = new ReCAPTCHAPlugin('recaptcha_options');
			$errors = new WP_Error();
			$errors = $recaptcha->validate_recaptcha_response( $errors );
			if ( count( $errors->errors ) ) {
				wp_die( 'Captcha Error' );
			}
		} elseif ( function_exists( 'gglcptch_login_check' ) ) {
			global $gglcptch_options;
			$privatekey = $gglcptch_options['private_key'];
			$resp = null;
			if ( 'v2' == $gglcptch_options['recaptcha_version'] ) {
				require_once( WP_PLUGIN_DIR . '/google-captcha/lib_v2/recaptchalib.php' );
				if ( class_exists( 'ReCaptcha' ) ) {
					$reCaptcha = new ReCaptcha( $privatekey );
					$gglcptch_g_recaptcha_response = isset( $_POST["g-recaptcha-response"] ) ? $_POST["g-recaptcha-response"] : '';
					$resp = $reCaptcha->verifyResponse( $_SERVER["REMOTE_ADDR"], $gglcptch_g_recaptcha_response );
				}
				if ( $resp != null && ! $resp->success ) {
					wp_die( 'Captcha Error' );
				}
			} else {
				require_once( WP_PLUGIN_DIR . '/google-captcha/lib/recaptchalib.php' );
				if ( function_exists( 'gglcptch_recaptcha_check_answer' ) ) {
					$gglcptch_recaptcha_challenge_field = isset( $_POST['recaptcha_challenge_field'] ) ? $_POST['recaptcha_challenge_field'] : '';
					$gglcptch_recaptcha_response_field = isset( $_POST['recaptcha_response_field'] ) ? $_POST['recaptcha_response_field'] : '';
					$resp = gglcptch_recaptcha_check_answer( $privatekey, $_SERVER['REMOTE_ADDR'], $gglcptch_recaptcha_challenge_field, $gglcptch_recaptcha_response_field );
				}
				if ( $resp != null && ! $resp->is_valid ) {
					wp_die( 'Captcha Error' );
				}
			}
		}
		$data = array( 
			'email' => $_POST['email'], 
			'channels' => '',
			'first_name' => '', 
			'token' => trim( $token ),
			'set_active' => 'true',
			'_attempts' => '1',
		);
		$slack_url = esc_url( 'https://' . $group .'.slack.com' );
		$default_slack_avatar = apply_filters( 'slack-invitation-default-avatar', 'https://slack-assets2.s3-us-west-2.amazonaws.com/10068/img/slackbot_192.png', $group );
		$dom = '<p class="message" style="min-height:64px"><img src="' . $default_slack_avatar . '" style="float:left;height:64px;width=64px" heigt="64" width="64"> ';
		if ( $token ) {
			$response = wp_remote_post( $slack_url . '/api/users.admin.invite?t=1', array( 'body' => $data ) );
		} else {
			$response = new WP_Error();
		}
		if( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$return = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $return->ok ) {
				$botname = apply_filters( 'slack-invitation-default-botname', 'SlackBot', $group );
				$message = $dom . sprintf( __( 'Invitation launched, thank you.<br>See you soon on <a href="%1$s">%2$s</a>.<br><i>- %3$s</i>', 'bawsi' ), $slack_url, esc_html( $group ), $botname );
			} 
			if ( isset( $return->error ) ) {
				switch( $return->error ) {
					case 'already_in_team' :
						$message = $dom . __( 'You are already in this team!', 'bawsi' );
					break;
					case 'already_invited' :
					case 'sent_recently' :
						$message = $dom . __( 'You have already been invited in this team!', 'bawsi' );
					break;					
					case 'invalid_auth' :
						$message = $dom . __( 'The Slack Invit Token API is not correct, please tell the webmaster!', 'bawsi' );
					break;
					default:
						$message = $dom . sprintf( __( 'Unknow error: %s', 'bawsi' ), esc_html( $return->error ) );
					break;
				}
			}
		} else {
			$message = $dom . sprintf( __( 'Unknow error: %s', 'bawsi' ), wp_remote_retrieve_response_code( $response ) );
		}
		$message .= '</p>';
	}

	if ( count( $groups ) === 1 ) {
		$select = '<input type="hidden" name="group" value="' . esc_attr( key( $groups ) ) . '">';
	} else {
		$select = '<p>';
		$select = __( 'Join the team', 'bawsi' );
		$select .= ' <select name="group">';
		foreach( $groups as $g => $t ) {
			$select .= sprintf( '<option value="%1$s">%1$s</option>', esc_html( $g ) );
		}
		$select .= '</select></p>';
	}

	login_header( __( 'SlackBot Invitation', 'bawsi' ), $message );
	if ( ! isset( $response ) ) {
	?>
		<style>
		.message{}
		</style>
		<form action="" method="post">
			<?php echo $select; ?>
			<p><?php _e( 'Email: ', 'bawsi' ); ?><input type="text" name="email" value="" title="<?php esc_attr_e( 'Email address', 'bawsi' ); ?>" /></p>
			<?php do_action( 'slack-invitation-before-submit' ); ?>
			<p><input type="submit" value="<?php esc_attr_e( 'Get an invitation', 'bawsi' ); ?>" name="submit" id="submit" class="button button-primary"/></p>
		</form>
	<?php
	} elseif ( true !== $response ) {
	?>
		<p id="backtoblog"><a href="<?php echo site_url( 'wp-login.php?action=slack-invitation' ); ?>"><?php _e( 'Ask for another invitation?', 'bawsi' ); ?></a></p>
	<?php 
	}
	login_footer( 'email' );
	die();
}

/**
* Add the default option
*
* @since 1.0
*/
register_activation_hook( __FILE__, 'bawsi_activation' );
function bawsi_activation() {
	if ( ! get_option( 'bawsi' ) ) {
		add_option( 'bawsi', 
			array( 'groups' => array() ), 
		'', 'no' );
	}
}

/**
* Remove the option
*
* @since 1.0
*/
register_uninstall_hook( __FILE__, 'bawsi_uninstall' );
function bawsi_uninstall() {
	delete_option( 'bawsi' );
}

/**
* Add the menu
*
* @since 1.0
*/
add_action( 'admin_menu', 'bawsi_setting_menu' );
function bawsi_setting_menu() {
	add_options_page( 'Slack Invit', 'Slack Invit', 'manage_options', 'slackinvit_settings', 'slackinvit_settings_page' );
	register_setting( 'bawsi_settings', 'bawsi' );
}

/**
* Settings page callback
*
* @since 1.0
*/
function slackinvit_settings_page() {
	add_settings_section( 'bawsi_settings_page', __( 'General', 'bawsi' ), 'bawsi_info', 'bawsi_settings' );
		add_settings_field( 'bawsi_field_groups', __( 'Groups', 'bawsi' ), 'bawsi_field_groups', 'bawsi_settings', 'bawsi_settings_page' );
		// add_settings_field( 'bawsi_field_addgroup', __( 'Add a group', 'bawsi' ), 'bawsi_field_addgroup', 'bawsi_settings', 'bawsi_settings_page' ); // 1.5
		add_settings_field( 'bawsi_field_token', __( 'Token Help', 'bawsi' ), 'bawsi_field_token', 'bawsi_settings', 'bawsi_settings_page' );
?>
	<div class="wrap">
		<h2>Slack Lazy Invitation <small>v<?php echo BAWSI_VERSION; ?></small></h2>

		<form action="options.php" method="post">
			<?php settings_fields( 'bawsi_settings' ); ?>
			<?php do_settings_sections( 'bawsi_settings' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
<?php
}

/**
* Settings section callback
*
* @since 1.0
*/
function bawsi_info() {
	_e( '<p>Remember that your username will be used for the invitation mail.</p>', 'bawsi' );
	printf( __( '<p>Here comes your %s to share!</p>', 'bawsi' ), '<a href="' . site_url( 'wp-login.php?action=slack-invitation' ) . '">' . __( 'Invitation Page', 'bawsi' ) . '</a>' );
}

/**
* Print the add group interface
*
* @since 1.3
*/
function bawsi_field_groups() {
	$bawsi_options = get_option( 'bawsi' );
	$bawsi_options = ! $bawsi_options ? get_site_option( 'bawsi' ) : $bawsi_options;
	?>
	<div class="hidden">
	<?php
	if ( isset( $bawsi_options['groups'] ) && ! empty( $bawsi_options['groups'] ) ) {
		$groups = explode( "\n", $bawsi_options['groups'] );
		foreach ( $groups as $data ) {
			$data = explode( ',', $data );
			$group = trim( reset( $data ) );
			$token = trim( end( $data ) );
			echo '<p style="border: 1px solid #999; border-radius: 5px; padding: 2px; display: block; padding-bottom: 3px; background: #ddd;" data-group="' . $group . '"><span class="dashicons dashicons-dismiss"></span> <b>' . $group . '</b> <i>('. $token .')</i></p>'; }
	} else {
		_e( '<i>No groups yet, add one?</i>', 'bawsi' );
	}
	?>
	</div>
	<p><textarea id="groups" name="bawsi[groups]" cols="80" rows="5">
<?php
if ( isset( $bawsi_options['groups'] ) && ! empty( $bawsi_options['groups'] ) ) {
	echo esc_textarea( $bawsi_options['groups'] );
}
?>
</textarea><br>
	<p><?php _e( 'Format <code>GroupName,xxxx-00000000000-00000000000-00000000000-0000000000</code> One per line', 'bawsi' ); ?></p>
	</p>
	<hr>
	<?php
}

/*
* Print the add group interface
*
* @since 1.5
function bawsi_field_addgroup() {
	$bawsi_options = get_option( 'bawsi' );
	?>
	<label><input type="text" name="bawsi[groupname]" id="groupname" value=""></label>
	<p class="description">
	<?php _e( 'This is the name of your slack group.', 'bawsi' ); ?>
	</p>
	<label><input type="text" name="bawsi[grouptoken]" id="grouptoken" value=""></label>
	<p class="description">
	<?php _e( 'This is the security token of your slack invitation group.', 'bawsi' ); ?>
	</p>
	<?php
	submit_button( __( 'Add', 'bawsi' ), 'sedondary small', 'add' );
}
*/

/**
* Print the groupname input
*
* @since 1.0
*/
function bawsi_field_groupname() {
	$bawsi_options = get_option( 'bawsi' );
	?>
	<label><input type="text" name="bawsi[groupname]" value="<?php echo esc_attr( $bawsi_options['groupname'] ); ?>"></label>
	<p class="description">
	<?php _e( 'This is the name of your slack group.', 'bawsi' ); ?>
	</p>
	<?php
}

/**
* Print the token input + bookmarklet
*
* @since 1.0
*/
function bawsi_field_token() {
	?>
	<p><a class="button button-small button-secondary" href="javascript:prompt('Slack Invit API Token', boot_data.api_token);" onclick="alert('<?php echo esc_js( __( 'Drag/drop me in our browser toolbar before!', 'bawsi' ) ); ?>');return false;"><?php _e( 'SlackInvit Token Api', 'bawsi' ); ?></a></p>
	<p class="description">
		<?php _e( 'To find your token you have to use the bookmarklet below <i>(drag/drop in your brower toolbar)</i> on your invitation page like <code>https://YOURGROUP.slack.com/admin/invites</code>', 'bawsi' ); ?><br>
		<?php _e( 'Why do i have to do that?', 'bawsi' ); ?><br>
		<?php _e( 'Because this is the only available token to auto invite people on your Slack.', 'bawsi' ); ?>
	</p>
<?php
}

/**
* Add the settings links on plugins page
*
* @since 1.0
*/
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bawsi_settings_action_links' );
function bawsi_settings_action_links( $links ) {
	array_unshift( $links, '<a href="' . site_url( 'wp-login.php?action=slack-invitation' ) . '">' . __( 'Invitation Page', 'bawsi' ) . '</a>' );
	array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=slackinvit_settings' ) . '">' . __( 'Settings' ) . '</a>' );
	return $links;
}

/**
* Add the slug into sf move login
*
* @since 1.0
*/
add_filter( 'sfml_additional_slugs', 'bawsi_slackinvit_slug' );
function bawsi_slackinvit_slug( $slugs ) {
	$slugs['slack-invitation'] = 'SlackInvit';
	return $slugs;
}

/**
 * Add support for captchas
 *
 * @since 1.2
 **/
add_action( 'slack-invitation-before-submit', 'bawsi_captcha_support', 9 );
function bawsi_captcha_support() {
	if ( class_exists( 'ReCAPTCHAPlugin' ) ) {
		$recaptcha = new ReCAPTCHAPlugin( 'recaptcha_options' );
		echo '<p>' . $recaptcha->get_recaptcha_html() . '</p>';
	} elseif( function_exists( 'gglcptch_display' ) ) {
		echo '<p>' . gglcptch_display() . '</p>';
	}
}