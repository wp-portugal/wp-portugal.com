<?php
global $lwa_submit_button, $lwa_data, $wp_version;
$templates = LoginWithAjax::get_templates_data();
?>
<table class="form-table">
	<?php if( count($templates) > 1 ) : ?>
		<tr valign="top">
			<th scope="row">
				<label><?php esc_html_e("Default Template", 'login-with-ajax'); ?></label>
			</th>
			<td>
				<select name="lwa_template" >
					<?php foreach( $templates as $template => $template_data ): ?>
						<option <?php echo (!empty($lwa_data['template']) && $lwa_data['template'] == $template) ? 'selected="selected"':""; ?> value="<?php echo esc_attr($template); ?>"><?php echo esc_html($template_data->label); ?></option>
					<?php endforeach; ?>
				</select>
				<p><em><?php esc_html_e("Choose the default theme you'd like to use. This can be overriden in the widget, shortcode and template tags.", 'login-with-ajax'); ?></em></p>
				<p><em><?php esc_html_e("Further documentation for this feature coming soon...", 'login-with-ajax'); ?></em></p>
			</td>
		</tr>
		<?php if( isset($lwa_data['legacy']) && !$lwa_data['legacy'] ): ?>
			<tr valign="top">
				<th scope="row">
					<label><?php esc_html_e("Template Color", 'login-with-ajax'); ?></label>
				</th>
				<td>
					<div style="grid-template-columns: 300px 1fr; width:auto; position: relative; margin: 0 auto; padding: 0px; display: grid; grid-gap: 20px; gap: 20px;">
						<div>
							<input type="hidden" name="lwa_template_color[H]" value="<?php echo !empty($lwa_data['template_color']['H']) ? absint($lwa_data['template_color']['H']) : 220; ?>" id="lwa-template-hsl-h">
							<input type="hidden" name="lwa_template_color[S]" value="<?php echo !empty($lwa_data['template_color']['S']) ? absint($lwa_data['template_color']['S']) : 86; ?>" id="lwa-template-hsl-s">
							<input type="hidden" name="lwa_template_color[L]" value="<?php echo !empty($lwa_data['template_color']['L']) ? absint($lwa_data['template_color']['L']) : 57; ?>" id="lwa-template-hsl-l">
							<input type="text" value="<?php echo LoginWithAjax::get_color_hsl(true); ?>" id="lwa-template-colorpicker" data-default-color="hsl(220,86,57)">
							<p><em><?php esc_html_e("Choose the color to base buttons and links off.", 'login-with-ajax'); ?></em></p>
						</div>
						<div id="lwa-hue-preview" style="border: 2px dotted #dedede; padding:10px 20px 20px;">
							<p style="padding-bottom:10px;">Preview :</p>
							<div class="lwa-wrapper lwa-bones">
								<div class="lwa pixelbones">
									<span class="lwa-status"></span>
									<input type="text" placeholder="<?php esc_html_e( 'Sample input field','login-with-ajax' ) ?>" style="margin-bottom:10px; display:inline-block !important; width:auto !important;">
									<button class="button-primary" onclick="return false;"><?php esc_attr_e('Preview Button','login-with-ajax'); ?></button>
									<a class="lwa-links-remember" href="#" title="<?php esc_attr_e('Sample link text','login-with-ajax') ?>" onclick="return false;"><?php esc_html_e('Sample link text','login-with-ajax') ?></a>
								</div>
							</div>
						</div>
					</div>
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("Disable refresh upon login?", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<input style="margin:0px; padding:0px; width:auto;" type="checkbox" name="lwa_no_login_refresh" value='1' class='wide' <?php echo ( !empty($lwa_data['no_login_refresh']) ) ? 'checked':''; ?>>
			<p><em><?php esc_html_e("If the user logs in and you check the button above, only the login widget will update itself without refreshing the page. Not a good idea if your site shows different content to users once logged in, as a refresh would be needed.", 'login-with-ajax'); ?></em></p>
		</td>
	</tr>
	<?php if( isset($lwa_data['legacy']) ): ?>
		<tr valign="top">
			<th scope="row">
				<label><?php esc_html_e("Enable Legacy Mode?", 'login-with-ajax'); ?></label>
			</th>
			<td>
				<input style="margin:0px; padding:0px; width:auto;" type="checkbox" name="lwa_legacy" value='1' class='wide' <?php echo ( !empty($lwa_data['legacy']) ) ? 'checked':''; ?>>
				<p><em><?php esc_html_e("Login with AJAX 4.0 introduces new templates, replacing the previous templates. Leave this enabled if you still use any of these old templates in shortcodes or widgets and don't want to change them automatically. This may also apply to any templates you've overriden or created in your own themes.", 'login-with-ajax'); ?></em></p>
				<p><em><?php echo sprintf(esc_html("Please see our %s on our documentation site for more information about what has changed and how it may affect you.", 'login-with-ajax'), '<a href="https://docs.loginwithajax.com/migrate-v3/">'. esc_html('Migration Guide') .'</a>'); ?></em></p>
			</td>
		</tr>
	<?php endif; ?>
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e("'Remember Me' checkbox behaviour", 'login-with-ajax'); ?></label>
		</th>
		<td>
			<select name="lwa_rememberme" >
				<option <?php echo (!empty($lwa_data['rememberme']) && $lwa_data['rememberme'] == '0') ? 'selected="selected"':""; ?> value="0"><?php esc_html_e('Hidden and disabled', 'login-with-ajax'); ?></option>
				<option <?php echo (!empty($lwa_data['rememberme']) && $lwa_data['rememberme'] == '1') ? 'selected="selected"':""; ?> value="1"><?php esc_html_e('Visible, unchecked by default', 'login-with-ajax'); ?></option>
				<option <?php echo (!empty($lwa_data['rememberme']) && $lwa_data['rememberme'] == '2') ? 'selected="selected"':""; ?> value="2"><?php esc_html_e('Visible, checked by default', 'login-with-ajax'); ?></option>
				<option <?php echo (!empty($lwa_data['rememberme']) && $lwa_data['rememberme'] == '3') ? 'selected="selected"':""; ?> value="3"><?php esc_html_e('Hidden and checked/enabled', 'login-with-ajax'); ?></option>
			</select>
			<p><em><?php esc_html_e("The 'remember me' checkbox on login forms can be hidden or shown with the option of being enabled/disabled by default.", 'login-with-ajax'); ?></em></p>
		</td>
	</tr>
</table>
<?php do_action('lwa_settings_page_general'); ?>
<?php echo $lwa_submit_button; ?>