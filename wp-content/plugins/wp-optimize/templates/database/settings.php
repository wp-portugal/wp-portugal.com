<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<div id="wp-optimize-database-settings" class="wpo_section wpo_group">
	<form action="#" method="post" enctype="multipart/form-data" name="database_settings_form" id="database_settings_form">
		<div id="wpo_database_settings_warnings"></div>
		<?php
		WP_Optimize()->include_template('database/settings-general.php');
		WP_Optimize()->include_template('database/settings-auto-cleanup.php', false, array('show_innodb_option' => false));
		?>

		<div id="wp-optimize-save-database-settings-results"></div>

		<input type="hidden" name="action" value="save_redirect">
		
		<?php wp_nonce_field('wpo_optimization', '_wpnonce_db_settings'); ?>

		<div class="wp-optimize-settings-save-results"></div>

		<input id="wp-optimize-save-database-settings" class="button button-primary wpo-save-settings" type="submit" name="wp-optimize-settings" value="<?php esc_attr_e('Save settings', 'wp-optimize'); ?>" />
		
		<img class="wpo_spinner wpo-saving-settings" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
		
		<span class="dashicons dashicons-yes display-none save-done"></span>

	</form>
</div><!-- end #wp-optimize-general-settings -->
