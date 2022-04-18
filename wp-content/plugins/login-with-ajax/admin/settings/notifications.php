<?php
global $lwa_submit_button, $lwa_data, $wp_version;
$templates = LoginWithAjax::get_templates_data();
?>
<p>
	<em><?php esc_html_e("If you'd like to override the default Wordpress email users receive once registered, make sure you check the box below and enter a new email subject and message.", 'login-with-ajax'); ?></em><br>
	<em><?php esc_html_e("If this feature doesn't work, please make sure that you don't have another plugin installed which also manages user registrations (e.g. BuddyPress and MU).", 'login-with-ajax'); ?></em>
</p>
<table class="form-table">
	<tr valign="top">
		<th>
			<label><?php esc_html_e("Override Default Email?", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<input style="margin:0px; padding:0px; width:auto;" type="checkbox" name="lwa_notification_override" value='1' class='wide' <?php echo ( !empty($lwa_data['notification_override']) && $lwa_data['notification_override'] == '1' ) ? 'checked="checked"':''; ?>>
		</td>
	</tr>
	<tr valign="top">
		<th>
			<label><?php esc_html_e("Subject", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<?php
			if(empty($lwa_data['notification_subject'])){
				$lwa_data['notification_subject'] = esc_html__('Your registration at %BLOGNAME%', 'login-with-ajax');
			}
			?>
			<input type="text" name="lwa_notification_subject" value='<?php echo (!empty($lwa_data['notification_subject'])) ? esc_attr($lwa_data['notification_subject']) : ''; ?>' class='wide'>
			<em><?php self::ph_esc(esc_html__("<code>%USERNAME%</code> will be replaced with a username.", 'login-with-ajax')); ?></em><br>
			<?php if( version_compare($wp_version, '4.3', '>=') ): ?>
				<em><strong><?php echo sprintf(esc_html__("%s will be replaced with a link to set the user password.", 'login-with-ajax'), '<code>%PASSWORDURL%</code>'); ?></strong></em><br>
			<?php else: ?>
				<em><?php self::ph_esc(esc_html__("<code>%PASSWORDURL%</code> will be replaced with the user's password.", 'login-with-ajax')); ?></em><br>
			<?php endif; ?>
			<em><?php self::ph_esc(esc_html__("<code>%BLOGNAME%</code> will be replaced with the name of your blog.", 'login-with-ajax')); ?></em>
			<em><?php self::ph_esc(esc_html__("<code>%BLOGURL%</code> will be replaced with the url of your blog.", 'login-with-ajax')); ?></em>
		</td>
	</tr>
	<tr valign="top">
		<th>
			<label><?php _e("Message", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<?php
			if( empty($lwa_data['notification_message']) ){
				if( version_compare($wp_version, '4.3', '>=') ){
					$lwa_data['notification_message'] = esc_html__('Thanks for signing up to our blog.

You can login with the following credentials by visiting %BLOGURL%

Username: %USERNAME%
To set your password, visit the following address: %PASSWORDURL%

We look forward to your next visit!

The team at %BLOGNAME%', 'login-with-ajax');
				}else{
					$lwa_data['notification_message'] = esc_html__('Thanks for signing up to our blog.

You can login with the following credentials by visiting %BLOGURL%

Username : %USERNAME%
Password : %PASSWORDURL%

We look forward to your next visit!

The team at %BLOGNAME%', 'login-with-ajax');
				}
			}
			?>
			<textarea name="lwa_notification_message" class='wide' style="width:100%; height:250px;"><?php echo esc_html($lwa_data['notification_message']); ?></textarea>
			<em><?php self::ph_esc(esc_html__("<code>%USERNAME%</code> will be replaced with a username.", 'login-with-ajax')); ?></em><br>
			<?php if( version_compare($wp_version, '4.3', '>=') ): ?>
				<em><strong><?php echo sprintf(esc_html__("%s will be replaced with a link to set the user password.", 'login-with-ajax'), '<code>%PASSWORDURL%</code>'); ?></strong></em><br>
			<?php else: ?>
				<em><?php self::ph_esc(esc_html__("<code>%PASSWORDURL%</code> will be replaced with the user's password.", 'login-with-ajax')); ?></em><br>
			<?php endif; ?>
			<em><?php self::ph_esc(esc_html__("<code>%BLOGNAME%</code> will be replaced with the name of your blog.", 'login-with-ajax')); ?></em>
			<em><?php self::ph_esc(esc_html__("<code>%BLOGURL%</code> will be replaced with the url of your blog.", 'login-with-ajax')); ?></em>
		</td>
	</tr>
</table	>
<?php do_action('lwa_settings_page_notifications'); ?>
<?php echo $lwa_submit_button; ?>