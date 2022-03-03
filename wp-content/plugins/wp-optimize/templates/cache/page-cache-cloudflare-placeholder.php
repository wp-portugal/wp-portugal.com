<?php if (!defined('ABSPATH')) die('No direct access.'); ?>
<h3 class="wpo-cloudflare-cache-options purge-cache"> <?php _e('Cloudflare settings', 'wp-optimize'); ?></h3>
<div class="wpo-fieldgroup cache-options wpo-cloudflare-cache-options purge-cache">
	<p>
		<input id="purge_cloudflare_cache" disabled type="checkbox" name="purge_cloudflare_cache" class="cache-settings">
		<label for="purge_cloudflare_cache"><?php _e('Purge Cloudflare cached pages when the WP-Optimize cache is purged', 'wp-optimize'); ?> <em><?php printf(__('(This feature requires %s)', 'wp-optimize'), '<a target="_blank" href="'.WP_Optimize()->maybe_add_affiliate_params('https://getwpo.com/buy/').'">WP Optimize Premium</a>'); ?></em></label>
		
	</p>
</div>
