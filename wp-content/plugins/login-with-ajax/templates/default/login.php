<?php
/*
 * This is the page users will see logged out.
 * You can edit this, but for upgrade safety you should copy and modify this file into a folder.
 * See https://docs.loginwithajax.com/advanced/templates/ for more information.
*/
/* @var array $lwa  Array of data supplied to widget */
?>
<div class="lwa-wrapper <?php if( empty($lwa['vanilla']) ) echo "lwa-bones"; ?>">
	<div class="lwa lwa-<?php echo esc_attr($lwa['template']); ?> <?php if( empty($lwa['vanilla']) ) echo "pixelbones"; ?> lwa-login" <?php if( !empty($lwa['css_style']) ) echo "style='{$lwa['css_style']}'" ?>>
		<form class="lwa-form" action="<?php echo LoginWithAjax::get_login_url(); ?>" method="post">
			<?php if( !empty($lwa['title']) ): ?>
				<p class="lwa-title"><?php echo esc_html($lwa['title']); ?></p>
			<?php endif; ?>
			<div class="lwa-username input-field">
				<label for="lwa_user_login_<?php echo $lwa['id'] ?>"><?php esc_html_e( 'Username','login-with-ajax' ) ?></label>
				<input type="text" name="log" id="lwa_user_login_<?php echo $lwa['id'] ?>" placeholder="<?php esc_html_e( 'Username','login-with-ajax' ) ?>" class="u-full-width">
			</div>
			<div class="lwa-password input-field">
				<label for="lwa_user_pass_<?php echo $lwa['id'] ?>"><?php esc_html_e( 'Password','login-with-ajax' ) ?></label>
				<input type="password" name="pwd" id="lwa_user_pass_<?php echo $lwa['id'] ?>" placeholder="<?php esc_html_e( 'Password','login-with-ajax' ) ?>" class="u-full-width">
			</div>

			<div class="lwa-login_form">
				<?php do_action('login_form'); ?>
				<?php do_action('lwa_login_form', $lwa); ?>
			</div>

			<div class="grid-container submit">
				<div class="lwa-submit-button">
					<input type="submit" name="wp-submit" class="button-primary" value="<?php esc_attr_e('Log In','login-with-ajax'); ?>" tabindex="100" >
					<input type="hidden" name="lwa_profile_link" value="<?php echo esc_attr($lwa['profile_link']); ?>">
					<input type="hidden" name="login-with-ajax" value="login">
					<?php if( !empty($lwa['redirect']) ): ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url($lwa['redirect']); ?>">
					<?php endif; ?>
				</div>

				<div class="lwa-links">
					<?php if( !empty($lwa['rememberme']) && absint($lwa['rememberme']) == 3 ): ?>
					<input name="rememberme" type="hidden" value="forever">
					<?php elseif( !empty($lwa['rememberme']) && absint($lwa['rememberme']) > 0 ): ?>
					<label>
						<span class="label-body"><?php esc_html_e( 'Remember Me','login-with-ajax' ) ?></span>
						<input name="rememberme" type="checkbox" class="lwa-rememberme" value="forever" <?php echo absint($lwa['rememberme']) === 2 ? 'checked':'' ?>>
					</label>
					<?php endif; ?>
					<?php if( !empty($lwa['remember']) ): ?>
						<a class="lwa-links-remember" href="<?php echo LoginWithAjax::get_remember_url(false); ?>" title="<?php esc_attr_e('Password Lost and Found','login-with-ajax') ?>"><?php esc_attr_e('Lost your password?','login-with-ajax') ?></a>
					<?php endif; ?>
					<?php if ( get_option('users_can_register') && !empty($lwa['registration']) ) : ?>
						<a href="<?php echo LoginWithAjax::get_register_url(false); ?>" class="lwa-links-register-inline"><?php esc_html_e('Register','login-with-ajax'); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</form>
		<?php if( !empty($lwa['remember']) && $lwa['remember'] == 1 ): ?>
			<form class="lwa-remember" action="<?php echo LoginWithAjax::get_remember_url(); ?>" method="post" style="display:none;">
				<p class="lwa-title"><?php esc_html_e("Forgotten Password",'login-with-ajax'); ?></p>
				<div class="lwa-remember-email input-field">
					<?php $msg = __("Enter username or email",'login-with-ajax'); ?>
					<label for="lwa_user_remember_<?php echo $lwa['id'] ?>"><?php echo esc_html($msg); ?></label>
					<input type="text" name="user_login" id="lwa_user_remember_<?php echo $lwa['id'] ?>" placeholder="<?php echo esc_attr($msg); ?>">
				</div>
				<?php
				do_action('lostpassword_form');
				do_action('lwa_lostpassword_form', $lwa);
				?>
				<div class="lwa-submit-button">
					<input type="submit" value="<?php esc_attr_e("Get New Password", 'login-with-ajax'); ?>" class="button-primary">
					<a href="#" class="lwa-links-remember-cancel button"><?php esc_attr_e("Cancel", 'login-with-ajax'); ?></a>
					<input type="hidden" name="login-with-ajax" value="remember">
				</div>
			</form>
		<?php endif; ?>
		<?php if ( get_option('users_can_register') && !empty($lwa['registration']) && $lwa['registration'] == 1 ) : ?>
			<div class="lwa-register" style="display:none;" >
				<form class="registerform" action="<?php echo LoginWithAjax::get_register_url($lwa['template']); ?>" method="post">
					<p class="lwa-title"><strong><?php esc_html_e('Register For This Site','login-with-ajax'); ?></strong></p>
					<p><?php esc_html_e('A password will be e-mailed to you.','login-with-ajax') ?></p>
					<div class="lwa-username input-field">
						<?php $msg = __('Username','login-with-ajax'); ?>
						<label for="user_login_<?php echo $lwa['id'] ?>"><?php echo esc_html($msg); ?></label>
						<input type="text" name="user_login" id="user_login_<?php echo $lwa['id'] ?>" placeholder="<?php echo esc_attr($msg); ?>">
					</div>
					<div class="lwa-email input-field">
						<?php $msg = __('E-mail','login-with-ajax'); ?>
						<label for="user_email_<?php echo $lwa['id'] ?>"><?php echo esc_html($msg); ?></label>
						<input type="text" name="user_email" id="user_email_<?php echo $lwa['id'] ?>" placeholder="<?php echo esc_attr($msg); ?>">
					</div>
					<?php
					//If you want other plugins to play nice, you need this:
					do_action('register_form');
					do_action('lwa_register_form', $lwa);
					?>
					<div class="lwa-submit-button">
						<input type="submit" class="button-primary" value="<?php esc_attr_e('Register', 'login-with-ajax'); ?>" tabindex="100">
						<a href="#" class="lwa-links-register-inline-cancel button"><?php esc_html_e("Cancel", 'login-with-ajax'); ?></a>
						<input type="hidden" name="login-with-ajax" value="register">
					</div>
				</form>
			</div>
		<?php endif; ?>
	</div>
</div>