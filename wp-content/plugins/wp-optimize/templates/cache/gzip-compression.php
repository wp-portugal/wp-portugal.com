<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<div class="wpo_section wpo_group">

	<h3 class="wpo-first-child"><?php _e('Gzip compression settings', 'wp-optimize');?></h3>
	<p>
		<span class="dashicons dashicons-info"></span> <span><?php _e("This option improves the performance of your website and decreases its loading time. When a visitor makes a request, the server compresses the requested resource before sending it leading to smaller file sizes and faster loading.", 'wp-optimize'); ?>
			<?php printf('<a href="%s" target="_blank">%s</a>', $info_link, __('Follow this link to get more information about Gzip compression.', 'wp-optimize')); ?>
		</span>
	</p>

	<?php if (is_wp_error($wpo_gzip_headers_information)) {
		$class = 'notice notice-error';
		printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $wpo_gzip_headers_information->get_error_message()));
	} else {
		?>
		<?php if ($wpo_gzip_compression_enabled && !is_wp_error($wpo_gzip_compression_enabled) && !$wpo_gzip_compression_enabled_by_wpo) : ?>
			<div class="wpo-fieldgroup wpo-gzip-already-enabled">
				<p><span class="dashicons dashicons-yes"></span> 
				<?php if (is_array($wpo_gzip_headers_information) && 'brotli' == $wpo_gzip_headers_information['compression']) { ?>
					<?php _e('Your server uses Brotli compression instead of Gzip, which is good.', 'wp-optimize'); ?>
				<?php } else { ?>
					<?php _e('Gzip compression is already enabled.', 'wp-optimize'); ?>
				<?php } ?>
				<?php if ($is_cloudflare_site) { ?>
					<em><?php _e('It seems to be handled by Cloudflare.', 'wp-optimize'); ?></em>
				<?php } ?>

				</p>
			</div>
		<?php else : ?>
			<div class="wpo-fieldgroup">
				<p id="wpo_gzip_compression_status" class="<?php echo $class_name; ?>">
					<strong class="wpo-enabled"><?php _e('Gzip compression is currently ENABLED.', 'wp-optimize'); ?></strong>
					<strong class="wpo-disabled"><?php _e('Gzip compression is currently DISABLED.', 'wp-optimize'); ?></strong>
					<?php if (!$wp_optimize->is_apache_server() || ($wpo_gzip_compression_enabled && false == $wpo_gzip_compression_settings_added)) : ?>
						<a href="#" class="wpo-refresh-gzip-status" title="<?php esc_attr_e('Press this to see if any changes were made to your Gzip configuration', 'wp-optimize'); ?>"><?php _e('Check status again', 'wp-optimize'); ?> <img class="wpo_spinner display-none" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>"></a>
					<?php endif; ?>
				</p>
				<br>
				<?php

				// add gzip compression section only if gzip compression disabled or we added cache settings to .htaccess.
				if (is_wp_error($wpo_gzip_compression_enabled) || false == $wpo_gzip_compression_enabled || $wpo_gzip_compression_settings_added) {

					if ($wp_optimize->is_apache_server()) {
						$button_text = (!is_wp_error($wpo_gzip_compression_enabled) && $wpo_gzip_compression_enabled) ? __('Disable', 'wp-optimize') : __('Enable', 'wp-optimize');
						?>
						<form>
							<button class="button-primary" type="button"
								id="wp_optimize_gzip_compression_enable" data-enable="<?php echo $wpo_gzip_compression_enabled ? '0' : '1'; ?>"><?php echo $button_text; ?></button>
								<img class="wpo_spinner display-none" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>"
							width="20" height="20" alt="...">
							<br>
						</form>
					<?php
					} else {
						printf('<a href="%s" target="_blank">%s</a>', $faq_link, __('Follow this link to read the article about how to enable Gzip compression with your server software.', 'wp-optimize'));
					}
				}
				?>
			</div>

		<div id="wpo_gzip_compression_error_message">
			<?php
			if (is_wp_error($wpo_gzip_compression_enabled)) {
			echo htmlspecialchars($wpo_gzip_compression_enabled->get_error_message());
			}
			?>
		</div>
		<pre id="wpo_gzip_compression_output" style="display: none;"></pre>

		<?php endif; ?>
	<?php } ?>
</div>
