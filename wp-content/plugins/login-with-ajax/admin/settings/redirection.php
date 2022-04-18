<?php
global $lwa_submit_button, $lwa_data;
?>
<h3><?php esc_html_e("Login Redirection Settings", 'login-with-ajax'); ?></h3>
<p><em><?php echo esc_html(sprintf(__("If you'd like to send the user to a specific URL after %s, enter a full URL (e.g. http://wordpress.org/) in the fields below. The following placeholders can be used in all %s redirect links", 'login-with-ajax'), __('login','login-with-ajax'), __('login','login-with-ajax'))); ?></em></p>
<p>
<ul>
	<li><em><?php esc_html_e("Enter %LASTURL% to send the user back to the page they were previously on.", 'login-with-ajax'); ?></em></li>
	<li><em><?php esc_html_e("Use %USERNAME% and it will be replaced with the username of person logging in.", 'login-with-ajax'); ?></em></li>
	<li><em><?php esc_html_e("Use %USERNICENAME% and it will be replaced with a url-friendly username of person logging in.", 'login-with-ajax'); ?></em></li>
	<?php if( class_exists('SitePress') ): ?>
		<li><em><?php self::ph_esc(esc_html__("Use %LANG% and it will be replaced with the current language used in multilingual URLs, for example, English may be <code>en</code>", 'login-with-ajax')); ?></em></li>
	<?php endif; ?>
</ul>
</p>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("Global Login Redirect", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<input type="text" name="lwa_login_redirect" value='<?php echo (!empty($lwa_data['login_redirect'])) ? esc_attr($lwa_data['login_redirect']):''; ?>' class='widefat'><br>
			<em><?php esc_html_e("If you'd like to send the user to a specific URL after login, enter it here (e.g. http://wordpress.org/)", 'login-with-ajax'); ?></em>
			<?php
			//WMPL itegrations
			function lwa_icl_inputs( $name, $lwa_data ){
				if( function_exists('icl_get_languages') ){
					$langs = icl_get_languages();
					if( count($langs) > 1 ){
						?>
						<table id="lwa_<?php echo esc_attr($name); ?>_langs" class="form-table">
							<?php
							foreach($langs as $lang){
								if( substr(get_locale(),0,2) != $lang['language_code'] ){
									?>
									<tr>
										<th style="width:100px;"><?php echo esc_html($lang['translated_name']); ?>: </th>
										<td><input type="text" name="lwa_<?php echo esc_attr($name); ?>_<?php echo esc_attr($lang['language_code']); ?>" value='<?php echo ( !empty($lwa_data[$name.'_'.$lang['language_code']]) ) ? esc_attr($lwa_data[$name.'_'.$lang['language_code']]):''; ?>' class="widefat"></td>
									</tr>
									<?php
								}
							}
							?>
						</table>
						<em><?php esc_html_e('With WPML enabled you can provide different redirection destinations based on language too.','login-with-ajax'); ?></em>
						<?php
					}
				}
			}
			lwa_icl_inputs('login_redirect', $lwa_data);
			?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("Role-Based Custom Login Redirects", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<em><?php esc_html_e("If you would like a specific user role to be redirected to a custom URL upon login, place it here (blank value will default to the global redirect)", 'login-with-ajax'); ?></em>
			<table class="form-table">
				<?php
				//Taken from /wp-admin/includes/template.php Line 2715
				$editable_roles = get_editable_roles();
				//WMPL integration
				function lwa_icl_inputs_roles( $name, $lwa_data, $role ){
					if( function_exists('icl_get_languages') ){
						$langs = icl_get_languages();
						if( count($langs) > 1 ){
							?>
							<table id="lwa_<?php echo esc_attr($name); ?>_langs">
								<?php
								foreach($langs as $lang){
									if( substr(get_locale(),0,2) != $lang['language_code'] ){
										?>
										<tr>
											<th style="width:100px;"><?php echo esc_html($lang['translated_name']); ?>: </th>
											<td><input type="text" name="lwa_<?php echo esc_attr($name); ?>_<?php echo esc_attr($role); ?>_<?php echo esc_attr($lang['language_code']); ?>" value='<?php echo ( !empty($lwa_data[$name][$role.'_'.$lang['language_code']]) ) ? esc_attr($lwa_data[$name][$role.'_'.$lang['language_code']]):''; ?>' class="widefat"></td>
										</tr>
										<?php
									}
								}
								?>
							</table>
							<em><?php esc_html_e('With WPML enabled you can provide different redirection destinations based on language too.','login-with-ajax'); ?></em>
							<?php
						}
					}
				}
				foreach( $editable_roles as $role => $details ) {
					$role_login = ( !empty($lwa_data['role_login']) && is_array($lwa_data['role_login']) && array_key_exists($role, $lwa_data['role_login']) ) ? $lwa_data['role_login'][$role]:''
					?>
					<tr>
						<th class="col"><?php echo translate_user_role($details['name']) ?></th>
						<td>
							<input type='text' class='widefat' name='lwa_role_login_<?php echo esc_attr($role) ?>' value="<?php echo esc_attr($role_login); ?>">
							<?php
							lwa_icl_inputs_roles('role_login', $lwa_data, esc_attr($role));
							?>
						</td>
					</tr>
					<?php
				}
				?>
			</table>
		</td>
	</tr>
</table>


<h3><?php esc_html_e("Logout Redirection Settings", 'login-with-ajax'); ?></h3>
<p><em><?php echo esc_html(sprintf(__("If you'd like to send the user to a specific URL after %s, enter a full URL (e.g. http://wordpress.org/) in the fields below. The following placeholders can be used in all %s redirect links", 'login-with-ajax'), __('logout','login-with-ajax'), __('logout','login-with-ajax'))); ?></em></p>
<ul>
	<li><em><?php esc_html_e("Enter %LASTURL% to send the user back to the page they were previously on.", 'login-with-ajax'); ?></em></li>
	<?php if( class_exists('SitePress') ): ?>
		<li><em><?php self::ph_esc(esc_html__("Use %LANG% and it will be replaced with the current language used in multilingual URLs, for example, English may be <code>en</code>", 'login-with-ajax')); ?></em></li>
	<?php endif; ?>
</ul>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("Global Logout Redirect", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<input type="text" name="lwa_logout_redirect" value='<?php echo (!empty($lwa_data['logout_redirect'])) ? esc_attr($lwa_data['logout_redirect']):''; ?>' class='widefat'>
			<?php
			lwa_icl_inputs('logout_redirect', $lwa_data);
			?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("Role-Based Custom Logout Redirects", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<em><?php esc_html_e("If you would like a specific user role to be redirected to a custom URL upon logout, place it here (blank value will default to the global redirect)", 'login-with-ajax'); ?></em>
			<table class="form-table">
				<?php
				//Taken from /wp-admin/includes/template.php Line 2715
				$editable_roles = get_editable_roles();
				foreach( $editable_roles as $role => $details ) {
					$role_logout = ( !empty($lwa_data['role_logout']) && is_array($lwa_data['role_logout']) && array_key_exists($role, $lwa_data['role_logout']) ) ? $lwa_data['role_logout'][$role]:''
					?>
					<tr>
						<th class='col'><?php echo translate_user_role($details['name']) ?></th>
						<td>
							<input type='text' class='widefat' name='lwa_role_logout_<?php echo esc_attr($role) ?>' value="<?php echo esc_attr($role_logout); ?>">
							<?php lwa_icl_inputs_roles('role_logout', $lwa_data, $role); ?>
						</td>
					</tr>
					<?php
				}
				?>
			</table>
		</td>
	</tr>
</table>
<?php do_action('lwa_settings_page_redirection'); ?>
<?php echo $lwa_submit_button; ?>