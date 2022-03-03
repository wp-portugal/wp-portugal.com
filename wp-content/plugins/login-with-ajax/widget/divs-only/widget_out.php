<?php 
/*
 * This is the page users will see logged out. 
 * You can edit this, but for upgrade safety you should copy and modify this file into your template folder.
 * The location from within your template folder is plugins/login-with-ajax/ (create these directories if they don't exist)
*/
?>
<div class="lwa lwa-divs-only">
	<span class="lwa-status"></span>
	<form class="lwa-form" action="<?php echo esc_url(LoginWithAjax::$url_login); ?>" method="post">
		<div class="lwa-username">
			<label><?php esc_html_e( 'Username','login-with-ajax' ) ?></label>
			<input type="text" name="log" id="lwa_user_login" class="input" />
		</div>

		<div class="lwa-password">
			<label><?php esc_html_e( 'Password','login-with-ajax' ) ?></label>
			<input type="password" name="pwd" id="lwa_user_pass" class="input" />
		</div>
		
		<div class="lwa-login_form">
			<?php do_action('login_form'); ?>
		</div>
   
		<div class="lwa-submit-button">
			<input type="submit" name="wp-submit" id="lwa_wp-submit" value="<?php esc_attr_e('Log In','login-with-ajax'); ?>" tabindex="100" />
			<input type="hidden" name="lwa_profile_link" value="<?php echo esc_attr($lwa_data['profile_link']); ?>" />
			<input type="hidden" name="login-with-ajax" value="login" />
			<?php if( !empty($lwa_data['redirect']) ): ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url($lwa_data['redirect']); ?>" />
			<?php endif; ?>
		</div>
		
		<div class="lwa-links">
			<input name="rememberme" type="checkbox" class="lwa-rememberme" value="forever" /> <label><?php esc_html_e( 'Remember Me','login-with-ajax' ) ?></label>
			<br />
        	<?php if( !empty($lwa_data['remember']) ): ?>
			<a class="lwa-links-remember" href="<?php echo esc_attr(LoginWithAjax::$url_remember); ?>" title="<?php esc_attr_e('Password Lost and Found','login-with-ajax') ?>"><?php esc_attr_e('Lost your password?','login-with-ajax') ?></a>
			<?php endif; ?>
			<?php if ( get_option('users_can_register') && !empty($lwa_data['registration']) ) : ?>
			<br />  
			<a href="<?php echo esc_attr(LoginWithAjax::$url_register); ?>" class="lwa-links-register-inline"><?php esc_html_e('Register','login-with-ajax'); ?></a>
			<?php endif; ?>
		</div>
	</form>
	<?php if( !empty($lwa_data['remember']) && $lwa_data['remember'] == 1 ): ?>
	<form class="lwa-remember" action="<?php echo esc_attr(LoginWithAjax::$url_remember); ?>" method="post" style="display:none;">
		<p><strong><?php esc_html_e("Forgotten Password",'login-with-ajax'); ?></strong></p>
		<div class="lwa-remember-email">  
			<?php $msg = __("Enter username or email",'login-with-ajax'); ?>
			<input type="text" name="user_login" id="lwa_user_remember" value="<?php echo esc_attr($msg); ?>" onfocus="if(this.value == '<?php echo esc_attr($msg); ?>'){this.value = '';}" onblur="if(this.value == ''){this.value = '<?php echo esc_attr($msg); ?>'}" />
			<?php do_action('lostpassword_form'); ?>
		</div>
		<div class="lwa-submit-button">
			<input type="submit" value="<?php esc_attr_e("Get New Password", 'login-with-ajax'); ?>" />
			<a href="#" class="lwa-links-remember-cancel"><?php esc_attr_e("Cancel", 'login-with-ajax'); ?></a>
			<input type="hidden" name="login-with-ajax" value="remember" />         
		</div>
	</form>
	<?php endif; ?>
	<?php if ( get_option('users_can_register') && !empty($lwa_data['registration']) && $lwa_data['registration'] == 1 ) : ?>
	<div class="lwa-register" style="display:none;" >
		<form class="registerform" action="<?php echo esc_attr(LoginWithAjax::$url_register); ?>" method="post">
			<p><strong><?php esc_html_e('Register For This Site','login-with-ajax'); ?></strong></p>         
			<div class="lwa-username">
				<?php $msg = __('Username','login-with-ajax'); ?>
				<input type="text" name="user_login" id="user_login"  value="<?php echo esc_attr($msg); ?>" onfocus="if(this.value == '<?php echo esc_attr($msg); ?>'){this.value = '';}" onblur="if(this.value == ''){this.value = '<?php echo esc_attr($msg); ?>'}" />   
		  	</div>
		  	<div class="lwa-email">
		  		<?php $msg = __('E-mail','login-with-ajax'); ?>
				<input type="text" name="user_email" id="user_email"  value="<?php echo esc_attr($msg); ?>" onfocus="if(this.value == '<?php echo esc_attr($msg); ?>'){this.value = '';}" onblur="if(this.value == ''){this.value = '<?php echo esc_attr($msg); ?>'}"/>   
			</div>
			<?php
				//If you want other plugins to play nice, you need this: 
				do_action('register_form'); 
			?>
			<p class="lwa-submit-button">
				<?php esc_html_e('A password will be e-mailed to you.','login-with-ajax') ?>
				<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="<?php esc_attr_e('Register', 'login-with-ajax'); ?>" tabindex="100" />
				<a href="#" class="lwa-links-register-inline-cancel"><?php esc_html_e("Cancel", 'login-with-ajax'); ?></a>
				<input type="hidden" name="login-with-ajax" value="register" />
			</p>
		</form>
	</div>
	<?php endif; ?>
</div>