<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<div class="wpo_section wpo_group">

	<h3 class="wpo-first-child"><?php _e('Browser static file caching settings (via headers)', 'wp-optimize');?></h3>
	<p>
		<?php echo __("Browser static file caching uses HTTP response headers to advise a visitor's browser to cache non-changing files for a while, so that it doesn't attempt to retrieve them upon every visit.", 'wp-optimize').' '.sprintf('<a href="%s" target="_blank">%s</a>', $info_link, __('Follow this link to get more information.', 'wp-optimize')); ?>
	</p>

	<div class="wpo-fieldgroup">
		<?php if ($is_cloudflare_site) : ?>
		<p class="wpo-enabled"><span class="dashicons dashicons-yes"></span>
			<em><?php _e('Your website seems to use Cloudflare, which handles the browser caching rules.', 'wp-optimize'); ?></em>
		</p>

		<?php else : ?>

		<div id="wpo_browser_cache_status" class="<?php echo $class_name; ?>">
			<span class="wpo-enabled"><span class="dashicons dashicons-yes"></span> <?php printf(__('Browser static file caching headers are currently %s.', 'wp-optimize'), '<strong>'.__('enabled', 'wp-optimize').'</strong>'); ?></span>
			<span class="wpo-disabled"><?php printf(__('Browser static file caching headers are currently %s.', 'wp-optimize'), '<strong>'.__('disabled', 'wp-optimize').'</strong>'); ?></span>
		</div>

		<br>

		<?php

		// add browser cache control section only if browser cache disabled or we added cache settings to .htaccess.
		if (false == $wpo_browser_cache_enabled || $wpo_browser_cache_settings_added) {
		?>

			<div id="wpo_browser_cache_error_message" class="notice error below-h2"><?php
				if (is_wp_error($wpo_browser_cache_enabled) && $wp_optimize->is_apache_server()) {
					echo htmlspecialchars($wpo_browser_cache_enabled->get_error_message());
				}
			?></div>

			<?php
			if ($wp_optimize->is_apache_server()) {
				$button_text = $wpo_browser_cache_enabled ? __('Update', 'wp-optimize') : __('Enable', 'wp-optimize');
				?>
				<form>
					<label><?php _e('Expiration time:', 'wp-optimize'); ?></label>
					<input id="wpo_browser_cache_expire_days" type="number" min="0" step="1" name="browser_cache_expire_days" value="<?php echo esc_attr($wpo_browser_cache_expire_days); ?>">
					<label for="wpo_browser_cache_expire"><?php _e('day(s)', 'wp-optimize'); ?></label>
					<input id="wpo_browser_cache_expire_hours" type="number" min="0" step="1" name="browser_cache_expire_hours" value="<?php echo esc_attr($wpo_browser_cache_expire_hours); ?>">
					<label for="wpo_browser_cache_expire_hours"><?php _e('hour(s)', 'wp-optimize'); ?></label>
					<button class="button-primary" type="button" id="wp_optimize_browser_cache_enable"><?php echo $button_text; ?></button>
					<img class="wpo_spinner display-none" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>"
						width="20" height="20" alt="...">
					<p><?php _e('Empty or 0 values disable the headers.', 'wp-optimize'); ?></p>
				</form>
			<?php
			} else {
				printf('<a href="%s" target="_blank">%s</a>', $faq_link, __('Follow this link to read the article about how to enable browser cache with your server software.', 'wp-optimize'));
			}
			?>

			<div id="wpo_browser_cache_message"></div>
			<pre id="wpo_browser_cache_output" style="display: none;"></pre>
			<?php
		}

		endif;
		?>
	</div><!-- END .wpo-fieldgroup -->
</div><!-- END .wpo_section -->
