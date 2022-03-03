<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<h3><?php _e('Preload key requests / assets', 'wp-optimize'); ?></h3>
<div class="wpo-fieldgroup">
	<p class="wpo_min-bold-green wpo_min-rowintro">
		<?php _e('Preload critical assets to improve loading speed.', 'wp-optimize'); ?>
		<a href="https://getwpo.com/faqs/preload-critical-assets/"><?php _e('Learn more about preloading key requests.', 'wp-optimize'); ?></a>
	</p>
	<fieldset>
		<legend class="screen-reader-text">
		<?php _e('Preload key requests', 'wp-optimize'); ?>
		</legend>
		<p><strong><?php _e('Preload key requests is a premium feature.', 'wp-optimize'); ?></strong> <a href="<?php echo esc_url(WP_Optimize()->premium_version_link); ?>"><?php _e('Find out more here.', 'wp-optimize'); ?></a></p>
	</fieldset>
</div>
