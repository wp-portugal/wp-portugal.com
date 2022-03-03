<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<div id="wp-optimize-general-settings" class="wpo_section wpo_group">
	<form action="#" method="post" enctype="multipart/form-data" name="settings_form" id="settings_form">
		<div id="wpo_settings_warnings"></div>


		<?php WP_Optimize()->include_template('settings/settings-general.php'); ?>
		<?php WP_Optimize()->include_template('settings/settings-trackback-and-comments.php'); ?>
		<?php WP_Optimize()->include_template('settings/settings-logging.php'); ?>

		<?php do_action('wpo_after_general_settings'); ?>

		<div id="wp-optimize-settings-save-results"></div>

		<input type="hidden" name="action" value="save_redirect">
		
		<?php wp_nonce_field('wpo_optimization'); ?>

		<h3 class="wpo-first-child"><?php _e('Wipe settings', 'wp-optimize'); ?></h3>

		<div class="wpo-fieldgroup">
			<p>
				<small><?php _e('This button will delete all of WP-Optimize\'s settings. You will then need to enter all your settings again. You can also do this before deactivating/deinstalling WP-Optimize if you wish.', 'wp-optimize'); ?></small>
				<br>
				<br>
				<input class="button wpo-wipe-settings" type="button" name="wp-optimize-wipe-settings" value="<?php esc_attr_e('Wipe settings', 'wp-optimize'); ?>" />

				<img class="wpo_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">

				<span class="dashicons dashicons-yes display-none save-done"></span>

			</p>
		</div>

		<div>
			<input class="button button-primary wpo-save-settings" type="submit" name="wp-optimize-settings" value="<?php esc_attr_e('Save settings', 'wp-optimize'); ?>" />

			<img class="wpo_spinner wpo-saving-settings" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">

			<span class="dashicons dashicons-yes display-none save-done"></span>
		</div>

	</form>
</div><!-- end #wp-optimize-general-settings -->
