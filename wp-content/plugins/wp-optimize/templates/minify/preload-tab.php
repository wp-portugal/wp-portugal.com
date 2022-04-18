<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<div class="wpo_section wpo_group">
	<form>
	<?php if ($is_cache_enabled) : ?>
		<div id="minify-preload" class="notice notice-warning wpo-warning below-h2 wp-optimize-minify-status-information-notice wpo-show">
			<p>
				<span class="dashicons dashicons-info"></span>
				<?php printf(__('You are using WP-Optimize for page caching. Because of that, the pre-load settings used for page-caching are used. i.e. To configure pre-loading, go to the <a href="%s">settings in the caching section</a> instead.', 'wp-optimize'), admin_url('admin.php?page=wpo_cache&tab=wp_optimize_preload'), __('Cache Preload', 'wp-optimize')); ?><br>
			</p>
		</div>
	<?php endif; ?>
		<h3 class="wpo-first-child"><?php _e('Preload now', 'wp-optimize'); ?></h3>
		<div class="wpo-fieldgroup">
			<p>
				<input id="wp_optimize_run_minify_preload" 
					class="button button-primary" type="submit" 
					name="wp_optimize_run_minify_preload" 
					value="<?php echo $is_running ? esc_attr__('Cancel', 'wp-optimize') : esc_attr__('Run now', 'wp-optimize'); ?>" 
					<?php echo $is_running ? 'data-running="1"' : ''; ?>
					<?php echo $is_cache_enabled ? "disabled" : ""; ?>
				>
				<span id="wp_optimize_preload_minify_status"><?php
					echo htmlspecialchars($status_message);
				?></span>
			</p>
			<span>
				<?php _e('This action will trigger WP-Optimize to generate minify assets by visiting pages to preload them (so that they are ready the first time a human visitor wants them).', 'wp-optimize'); ?>
				<?php _e('If a sitemap is available, then it will be used to determine which assets get minified.', 'wp-optimize'); ?>
			</span>
		</div>
	</form>
</div>
