<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<li id="<?php echo $file['uid']; ?>">
	<span class="filename"><a href="<?php echo esc_url($file['file_url']); ?>" target="_blank"><?php echo htmlspecialchars($file['filename']); ?></a> (<?php echo $file['fsize']; ?>)</span>
	<a href="#" class="log"><?php _e('Show information', 'wp-optimize'); ?></a>
	<div class="hidden save_notice">
		<p><?php _e('The file was added to the list', 'wp-optimize'); ?></p>
		<p><button class="button button-primary save-exclusions"><?php _e('Save the changes', 'wp-optimize'); ?></button></p>
	</div>
	<div class="hidden wpo_min_log"><?php
	if ($file['log']) {
		WP_Optimize()->include_template(
			'minify/cached-file-log.php',
			false,
			array(
				'log' => $file['log']
			)
		);
	}
	?></div>
</li>