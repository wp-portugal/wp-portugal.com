<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<h3><?php _e('Logging settings', 'wp-optimize'); ?></h3>

<div id="wp-optimize-logger-settings" class="wpo-fieldgroup">
	<p>
		<a href="#" id="wpo_add_logger_link" class="wpo-repeater__add"><span class="dashicons dashicons-plus"></span> <?php _e('Add logging destination', 'wp-optimize'); ?></a>

	</p>

	<div class="save_settings_reminder"><?php _e('Remember to save your settings so that your changes take effect.', 'wp-optimize');?></div>

	<div id="wp-optimize-logging-options">
	<?php

	$loggers = $wp_optimize->get_logger()->get_loggers();

	if (count($loggers) > 0) {

	?>
			<div class="wpo_logging_header">
				<div class="wpo_logging_logger_title"><?php _e('Destination', 'wp-optimize'); ?></div>
				<div class="wpo_logging_options_title"><?php _e('Options', 'wp-optimize'); ?></div>
				<div class="wpo_logging_status_title"><?php _e('Status', 'wp-optimize'); ?></div>
				<div class="wpo_logging_actions_title"><?php _e('Actions', 'wp-optimize'); ?></div>
			</div>
			<?php

			foreach ($loggers as $logger) {
				$logger_id = strtolower(get_class($logger));

				?>

				<div class="wpo_logging_row" data-id="<?php echo $logger_id; ?>">
					<div class="wpo_logging_logger_row"><span
								class="dashicons dashicons-arrow-right"></span><?php echo $logger->get_description(); ?>
					</div>
					<div class="wpo_logging_options_row"><?php echo $logger->get_options_text(); ?></div>
					<div class="wpo_logging_status_row"><?php echo ($logger->is_enabled() && $logger->is_available()) ? __('Active', 'wp-optimize') : __('Inactive', 'wp-optimize'); ?></div>
					<div class="wpo_logging_actions_row"><a href="#" class="dashicons dashicons-edit"></a><a
								href="#" class="wpo_delete_logger dashicons dashicons-no-alt"></a></div>


					<div class="wpo_additional_logger_options wpo_hidden">
						<input class="wpo_hidden" type="hidden" name="wpo-logger-type[]"
								value="<?php echo $logger_id; ?>"/>
						
						<?php
						$options_list = $logger->get_options_list();
						$options_values = $logger->get_options_values();

						if (!empty($options_list)) {
							foreach ($options_list as $option_name => $placeholder) {
								// check if settings item defined as array.
								if (is_array($placeholder)) {
									$validate = $placeholder[1];
									$placeholder = $placeholder[0];
								} else {
									$validate = '';
								}

								$data_validate_attr = ('' !== $validate ? 'data-validate="'.esc_attr($validate).'"' : '');

								?>
								<input class="wpo_logger_addition_option" type="text"
										name="wpo-logger-options[<?php echo esc_attr($option_name); ?>][]"
										value="<?php echo esc_attr($options_values[$option_name]); ?>"
										placeholder="<?php echo esc_attr($placeholder); ?>"
									<?php echo $data_validate_attr; ?> "/>
								<?php
							}
						}
						?>
						<label>
							<input class="wpo_logger_active_checkbox"
									type="checkbox" <?php checked($logger->is_enabled() && $logger->is_available()); ?> <?php disabled($logger->is_available(), false); ?>>
							<input type="hidden" name="wpo-logger-options[active][]"
									value="<?php echo $logger->is_enabled() ? '1' : '0'; ?>"/>
							<?php _e('Active', 'wp-optimize'); ?>
						</label>
					</div>

				</div>
				<?php
			}
			?>
		<?php
	}
	?>
	</div><!-- End #wp-optimize-logging-options -->
</div><!-- End #wp-optimize-logger-settings -->