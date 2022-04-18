<div style="border:2px dashed #ccc; margin:10px 0px; padding:20px; ">
	<h3>reCaptcha</h3>
	<p>
		<?php esc_html_e('Add Google reCaptcha integration to both regular WP forms and our login forms, including support for v2 and v3 modes.', 'login-with-ajax'); ?>
	</p>
	<a href="https://loginwithajax.com/gopro/" class="button-primary" target="_blank"><?php esc_html_e('Go Pro!', 'login-with-ajax'); ?></a>
</div>
<div style="border:2px dashed #ccc; margin:10px 0px; padding:20px; ">
	<h3><?php esc_html_e("Login Limitations", 'login-with-ajax-pro'); ?></h3>
	<p>
		<?php esc_html_e('Prevent brute force attacks by limiting login attempts and blocking user accounts after multiple failed attempts..', 'login-with-ajax'); ?>
	</p>
	<a href="https://loginwithajax.com/gopro/" class="button-primary" target="_blank"><?php esc_html_e('Go Pro!', 'login-with-ajax'); ?></a>
</div>
<div style="border:2px dashed #ccc; margin:10px 0px; padding:20px; ">
	<h3><?php esc_html_e("2-Factor Authentication (2FA)", 'login-with-ajax-pro'); ?></h3>
	<p>
		<?php esc_html_e('Add 2FA to your login flow, and add a further layer of security for your user accounts. Compatible with both reCaptcha and Login Limitations.', 'login-with-ajax'); ?>
	</p>
	<a href="https://loginwithajax.com/gopro/" class="button-primary" target="_blank"><?php esc_html_e('Go Pro!', 'login-with-ajax'); ?></a>
</div>
<em><?php echo sprintf(esc_html__('You can remove this tab by adding this line to your wp-config.php file - %s', 'login-with-ajax'), "<code>define('LWA_REMOVE_PRO_NAGS', '0');</code>"); ?></em>