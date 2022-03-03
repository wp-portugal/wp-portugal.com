<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<h3 class="wpo-first-child"><?php _e('General settings', 'wp-optimize'); ?></h3>
<div class="wpo-fieldgroup">
	<p>
		<label>
			<input name="enable-admin-bar" id="enable-admin-bar" type="checkbox" value ="true" <?php echo ($options->get_option('enable-admin-menu', 'false') == 'true') ? 'checked="checked"' : ''; ?> />
			<?php _e('Enable admin bar menu', 'wp-optimize'); ?>
		</label>
		<br>
		<small><?php _e('This option will add a link labeled "WP-Optimize" in the top admin bar, for easy access to the different features.', 'wp-optimize'); ?> <?php _e('Requires a page refresh after saving the settings.', 'wp-optimize'); ?></small>
	</p>
	<p>
		<label>
			<input name="enable_cache_in_admin_bar" id="enable-cache-admin-bar" type="checkbox" value ="1" <?php checked($options->get_option('enable_cache_in_admin_bar', true)); ?> />
			<?php _e('Enable the caching menu in the admin bar', 'wp-optimize'); ?>
		</label>
		<br>
		<small><?php _e('This option will add a caching menu on the top admin bar.', 'wp-optimize'); ?> <?php _e('Requires a page refresh after saving the settings.', 'wp-optimize'); ?></small>
	</p>
</div>