<?php
/*
 * This is the page users will see logged out.
 * You can edit this, but for upgrade safety you should copy and modify this file into a folder.
 * See https://docs.loginwithajax.com/advanced/templates/ for more information.
*/
/* @var array $lwa  Array of data supplied to widget */

// set default template to be loaded within the modal window, allows for other templates to call the modal template with their own templates within the modal box.
if( $lwa['template'] == 'modal' ) $lwa['template'] = 'default';
if( empty($lwa['template-parent']) ) $lwa['template-parent'] = 'modal';
?>
<div class="lwa-wrapper lwa-bones lwa-modal-trigger" data-modal-id="lwa-modal-<?php echo esc_attr($lwa['id']); ?>">
	<div class="lwa lwa-modal lwa-<?php echo esc_attr($lwa['template-parent']); ?> pixelbones">
		<button class="lwa-modal-trigger-el"><?php esc_html_e('Log In', 'login-with-ajax'); ?></button>
	</div>
</div>
<div class="lwa-modal-overlay" id="lwa-modal-<?php echo esc_attr($lwa['id']); ?>">
	<div class="lwa-modal-popup">
		<a class="lwa-close-modal">
			<svg viewBox="0 0 20 20">
				<path fill="#000000" d="M15.898,4.045c-0.271-0.272-0.713-0.272-0.986,0l-4.71,4.711L5.493,4.045c-0.272-0.272-0.714-0.272-0.986,0s-0.272,0.714,0,0.986l4.709,4.711l-4.71,4.711c-0.272,0.271-0.272,0.713,0,0.986c0.136,0.136,0.314,0.203,0.492,0.203c0.179,0,0.357-0.067,0.493-0.203l4.711-4.711l4.71,4.711c0.137,0.136,0.314,0.203,0.494,0.203c0.178,0,0.355-0.067,0.492-0.203c0.273-0.273,0.273-0.715,0-0.986l-4.711-4.711l4.711-4.711C16.172,4.759,16.172,4.317,15.898,4.045z"></path>
			</svg>
		</a><!-- close modal -->

		<div class="lwa-modal-content">
			<?php
				$template_path = LoginWithAjax::get_template_path('default');
				if( file_exists($template_path) ){
					include($template_path.'/login.php');
				}else{
					// get the default template path
					include( LOGIN_WITH_AJAX_PATH . '/templates/default/login.php');
				}
			?>
		</div><!-- content -->

	</div><!-- modal -->
</div>