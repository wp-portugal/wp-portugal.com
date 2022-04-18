<?php
namespace Login_With_AJAX;
use Login_With_AJAX\Blocks\Login;
use LoginWithAjax;

/*
 * This is the first version of this add-on, some structural things may change here as we make refinements.
 * Therefore, please be mindful about potential breaking changes if you're using our hooks/filters here or in verification methods.
 * Please get in touch if you're extending this add-on, so we're aware of any potential use-cases to consider.
 *
 * Things to do:
 * Add timeout
 * Add 'remember' button for devices
 * Add custom email template editor
 */

class AJAXify {
	
	public static function init(){
		// Admin
		if( is_admin() ){
			add_action('lwa_settings_page_general', '\Login_With_AJAX\AJAXify::admin_settings');
		}
		// load AJAXify if enabled
		if( !empty(LoginWithAjax::$data['ajaxify']['wp_login']) ) {
			// login hooks to check if authentication needed
			add_action('login_enqueue_scripts', '\Login_With_AJAX\AJAXify::enqueue_wp_login');
			add_action('login_footer', '\Login_With_AJAX\AJAXify::footer');
			add_action('login_head', '\Login_With_AJAX\AJAXify::head');
			// trigger loaded
			do_action('lwa_ajaxify_loaded');
		}
	}
	
	/**
	 * Enqueue the JS and CSS we'd need to make AJAX work on the WP Login page.
	 * @return void
	 */
	public static function enqueue_wp_login(){
		\LoginWithAjax::enqueue_scripts_and_styles(true);
	}
	
	public static function head(){
		?>
		<style type="text/css">
			#login .lwa-status { display: none !important; }
			#login .lwa-status.lwa-status-invalid, #login .lwa-status.lwa-status-confirm { display: block !important; }
		</style>
		<?php
	}

	public static function footer(){
		?>
		<script type="text/javascript">
			jQuery(document).ready( function($){
				// add fields that'll allow LWA to work, and override the regular status element
				$('#loginform, #registerform, #lostpasswordform').wrap('<div class="lwa-wrapper"></div>')
					.wrap('<div class="lwa"></div>')
					.addClass('lwa-form');
				$('#loginform').append( $('<input type="hidden" name="login-with-ajax" value="login">') );
				$('#registerform').append( $('<input type="hidden" name="login-with-ajax" value="register">') );
				$('#lostpasswordform').append( $('<input type="hidden" name="login-with-ajax" value="remember">') );
				$(document).on('lwa_addStatusElement', function(e, form, statusElement){
					if( form.attr('id') === 'loginform' || form.attr('id') === 'registerform' || form.attr('id') === 'lostpasswordform' ) {
						if( !statusElement.hasClass('lwa-ajaxify-status') ) {
							let el = $('div.lwa-ajaxify-status');
							if (el.length === 0) {
								el = $('<div class="lwa-status login lwa-ajaxify-status"></div>');
							}
							statusElement[0] = el[0];
							if( statusElement.length === 0 ){ statusElement.length = 1; }
							$('#loginform span.lwa-status').remove();
						}
						let lwa = form.closest('.lwa');
						statusElement.prependTo( lwa );
					}
				});
				$(document).on('lwa_handleStatus', function(e, response, statusElement){
					if( statusElement.hasClass('lwa-ajaxify-status') ) {
						if( response.result ){
							statusElement.attr('id', '');
							statusElement.addClass('success');
						}else{
							statusElement.attr('id', 'login_error');
							statusElement.removeClass('success');
						}
					}
				});
				$(document).on('lwa_pre_ajax', function(e, response, form, statusElement){
					if( form.attr('id') === 'loginform' || form.attr('id') === 'registerform' || form.attr('id') === 'lostpasswordform' ) {
						statusElement.hide();
					}
				});
				$(document).on('lwa_register lwa_remember', function(e, response, form, statusElement){
					if( form.attr('id') === 'registerform' || form.attr('id') === 'lostpasswordform' ) {
						if( response.result ){
							form.hide();
							form.find('input').val('');
						}
					}
				});
			});
		</script>
		<?php
	}
	
	public static function admin_settings(){
		$lwa = get_option('lwa_data', array());
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label><?php esc_html_e("AJAXify WP Login", 'login-with-ajax-pro'); ?></label>
				</th>
				<td>
					<input type="checkbox" name="lwa_ajaxify[wp_login]" id="lwa_ajaxify_wp_login" value='1' <?php echo ( !empty($lwa['ajaxify']['wp_login']) ) ? 'checked':''; ?> >
					<p><em><?php esc_html_e('Add AJAX effects to the regular WP Login page area, preventing a full page reload for every login, password recovery or registration attempt.', 'login-with-ajax-pro'); ?></em></p>
				</td>
			</tr>
		</table>
		<?php
	}
	
}
AJAXify::init();