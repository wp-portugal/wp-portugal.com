<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<div id="wpo_smush_settings">
	<div class="wpo-info">
		<a class="wpo-info__trigger" href="#"><span class="dashicons dashicons-sos"></span> <?php _e('How to use the image compression feature', 'wp-optimize'); ?> <span class="wpo-info__close"><?php _e('Close', 'wp-optimize'); ?></span></a>
		<div class="wpo-info__content">
			<p><strong><?php _e('Not sure how to use the image compression feature?', 'wp-optimize'); ?></strong> <br><?php _e('Watch our howto video below.', 'wp-optimize'); ?></p>
			<div class="wpo-video-preview">
				<a href="https://vimeo.com/333938451" data-embed="https://player.vimeo.com/video/333938451?color=df6926&title=0&byline=0&portrait=0" target="_blank"><img src="<?php echo trailingslashit(WPO_PLUGIN_URL); ?>images/notices/image-compression-video-preview.png" alt="Video preview" /></a>
			</div>
			<small>(<?php _e('Loads a video hosted on vimeo.com', 'wp-optimize'); ?>) - <a href="https://vimeo.com/333938451" target="_blank"><?php _e('Open the video in a new window', 'wp-optimize'); ?></a></small>
		</div>
	</div>
	<p>
		<?php _e('Note: Currently this feature uses third party services from reSmush.it. The performance of this free image compression service may be limited for large workloads. We are working on a premium service.', 'wp-optimize'); ?>
	</p>
	<div class="wpo-fieldgroup">
		<div class="autosmush wpo-fieldgroup__subgroup<?php echo $smush_options['autosmush'] ? ' active' : ''; ?>">
			<label class="switch" for="smush-automatically">
				<input type="checkbox" id="smush-automatically" <?php checked($smush_options['autosmush']); ?> >
				<span class="slider round"></span>
			</label>
			<label for="smush-automatically"><?php _e('Automatically compress newly-added images', 'wp-optimize');?>
				<span tabindex="0" data-tooltip="<?php echo __('The images will be added to a background queue, which will start automatically within the next hour.', 'wp-optimize').' '.__('This prevents the site from being slowed down during media uploads.', 'wp-optimize').' '.__('The time taken to complete the compression will depend upon the size and quantity of the images.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
			</label>
		</div>

		<div class="wpo-fieldgroup__subgroup">
			<label class="switch">
				<input type="checkbox" id="smush-show-metabox" class="smush-options" <?php checked($smush_options['show_smush_metabox']); ?> > 
				<span class="slider round"></span>
			</label>
			<label for="smush-show-metabox" class="smush-options">
				<?php _e('Show compression meta-box on an image\'s dashboard media page.', 'wp-optimize');?>
				<span tabindex="0" data-tooltip="<?php esc_attr_e('The image compression metabox allows you to compress specific images from the media library. But if you are using a solution other than WP-Optimize to compress your images, you can hide these metaboxes by disabling this switch.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
			</label>
		</div>

		<div class="compression_options">
			<h3><?php _e('Compression options', 'wp-optimize');?></h3>
			<input type="radio" id="enable_lossy_compression" name="compression_level" <?php checked($smush_options['image_quality'], 90); ?> class="smush-options compression_level"> 
			<label for="enable_lossy_compression"><?php _e('Prioritize maximum compression', 'wp-optimize');?></label>
			<span tabindex="0" data-tooltip="<?php _e('Uses lossy compression to ensure maximum savings per image. The resulting images are of a slightly lower quality', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
			<br>						
			<input type="radio" id="enable_lossless_compression" <?php checked($smush_options['image_quality'], 100); ?>name="compression_level" class="smush-options compression_level"> 
			<label for="enable_lossless_compression"><?php _e('Prioritize retention of detail', 'wp-optimize');?></label>
			<span tabindex="0" data-tooltip="<?php _e('Uses lossless compression, which results in much better image quality but lower file size savings per image', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
			<br>
			<input id="enable_custom_compression" <?php checked($custom); ?> type="radio" name="compression_level" class="smush-options compression_level"> 
			<label for="enable_custom_compression"><?php _e('Custom', 'wp-optimize');?></label>
			<br>
			<div class="smush-options custom_compression" <?php if (!$custom) echo 'style="display:none;"';?> >
				<span class="slider-start"><?php _e('Maximum compression', 'wp-optimize');?></span>
				<input id="custom_compression_slider" class="compression_level" data-max="Maximum Compression"  type="range" step="5" value="<?php echo $smush_options['image_quality']; ?>" min="60" max="85" list="number" />
				<datalist id="number">
					<option value="60"/>
					<option value="65"/>
					<option value="70"/>
					<option value="75"/>
					<option value="80"/>
					<option value="85"/>
				</datalist>
				<span class="slider-end"><?php _e('Best image quality', 'wp-optimize');?></span>
			</div>
			<p><?php _e('Not sure what to choose?', 'wp-optimize'); ?> <a href="https://getwpo.com/lossy-vs-lossless-image-compression-a-guide-to-the-trade-off-between-image-size-and-quality/" target="_blank"><?php _e('Read our article "Lossy vs Lossless image compression"', 'wp-optimize'); ?></a></p>
			<?php if (defined('WPO_USE_WEBP_CONVERSION') && true === WPO_USE_WEBP_CONVERSION) : ?>
				<h3><?php _e('WebP conversion', 'wp-optimize');?></h3>
				<?php
					$converters = WP_Optimize()->get_options()->get_option('webp_converters', false);
					if (!$does_server_allows_local_webp_conversion) {
						printf('<p>%1$s</p>', __('Note: Local WebP conversion tools are not allowed on your server.', 'wp-optimize'));
					} elseif (!empty($converters)) {
						printf('<p>%1$s <strong>' . implode(', ', $converters) . '</strong></p>', __('Available WebP conversion tools:', 'wp-optimize'));
					?>
						<input type="checkbox" id="enable_webp_conversion" name="webp_conversion" <?php checked($smush_options['webp_conversion']); ?> class="smush-options webp_conversion"> 
						<label for="enable_webp_conversion"><?php _e('Create WebP version of image', 'wp-optimize');?></label>
						<span tabindex="0" data-tooltip="<?php _e('Creates WebP image format and serves it whenever possible.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
						<br>						
				<?php
					} else {
						printf('<p>%1$s</p>', __('No WebP conversion tools are available on your web-server.', 'wp-optimize'));
					}
			endif;
			?>
		</div>
		<button type="button" class="button button-link wpo-toggle-advanced-options"><span class="text"><span class="dashicons dashicons-arrow-down-alt2"></span> <span class="wpo-toggle-advanced-options__text-show"><?php _e('Show advanced options', 'wp-optimize');?></span><span class="wpo-toggle-advanced-options__text-hide"><?php _e('Hide advanced options', 'wp-optimize');?></span></span></button>
		<div class="smush-advanced wpo-advanced-options">
			<div class="compression_server">
				<h3><?php _e('Compression service', 'wp-optimize');?></h3>
				<div> <input type="radio" name="compression_server" id="resmushit" value="resmushit" <?php checked($smush_options['compression_server'], 'resmushit'); ?> >			  
				<label for="resmushit">
					<h4><?php _e('reSmush.it', 'wp-optimize');?></h4>
					<p><?php _e('Can keep EXIF data', 'wp-optimize');?></p>
					<small><?php _e('Service provided by reSmush.it', 'wp-optimize'); ?></small>
				  </label>
				</div>
			</div>
			<br>
			<h3><?php _e('More options', 'wp-optimize');?></h3>
			<div class="image_options">
				<input type="checkbox" id="smush-preserve-exif" class="smush-options preserve_exif" <?php checked($smush_options['preserve_exif']); ?> >
				<label for="smush-preserve-exif" class="smush-options preserve_exif"><?php _e('Preserve EXIF data', 'wp-optimize');?></label>
				<br>
				<input type="checkbox" id="smush-backup-original" class="smush-options back_up_original" <?php checked($smush_options['back_up_original']); ?> > 
				<label for="smush-backup-original"><?php _e('Backup original images', 'wp-optimize');?></label>
				<span tabindex="0" data-tooltip="<?php _e('The original images are stored alongside the compressed images, you can visit the edit screen of the individual images in the Media Library to restore them.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
				<br>
				<input type="checkbox" id="smush-backup-delete" class="smush-options back_up_original" <?php checked($smush_options['back_up_delete_after']); ?> >
				<label for="smush-backup-delete"><?php _e('Automatically delete image backups after', 'wp-optimize');?><input id="smush-backup-delete-days" type="number" min="1" value="<?php echo (0 !== intval($smush_options['back_up_delete_after_days'])) ? intval($smush_options['back_up_delete_after_days']) : 50; ?>"><?php _e('days', 'wp-optimize');?></label><label> — <?php _e('or', 'wp-optimize'); ?></label> <button type="button" id="wpo_smush_delete_backup_btn" class="wpo_primary_small button"><?php _e('Delete all backup images now', 'wp-optimize'); ?></button>
				<img id="wpo_smush_delete_backup_spinner" class="display-none" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
				<span id="wpo_smush_delete_backup_done" class="dashicons dashicons-yes display-none save-done"></span>
				<br>
				<button type="button" id="wpo_smush_mark_all_as_uncompressed_btn" class="wpo_primary_small button"><?php _e('Mark all images as uncompressed', 'wp-optimize'); ?></button>
				<br>
				<br>
				<button type="button" id="wpo_smush_restore_all_compressed_images_btn" class="wpo_primary_small button"><?php _e('Restore all compressed images', 'wp-optimize'); ?></button> <span tabindex="0" data-tooltip="<?php esc_attr_e('Only the original image will be restored. In order to restore the other sizes, you should use a plugin such as "Regenerate Thumbnails".', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
			</div>
		</div>
		<div class="save-options">
			<input type="button" id="wpo_smush_images_save_options_button" style="display:none" class="wpo_primary_small button-primary" value="<?php esc_attr_e('Save options', 'wp-optimize'); ?>" />
			<img id="wpo_smush_images_save_options_spinner" class="display-none" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
			<span id="wpo_smush_images_save_options_done" class="display-none"><span class="dashicons dashicons-yes"></span> <?php _e('Saved options', 'wp-optimize');?></span>
			<span id="wpo_smush_images_save_options_fail" class="display-none"><span class="dashicons dashicons-no"></span> <?php _e('Failed to save options', 'wp-optimize');?></span>
		</div>
	</div>

	<div class="uncompressed-images">
		<h3><?php _e('Uncompressed images', 'wp-optimize');?></h3>
		<div class="wpo_smush_images_buttons_wrap">
			<div class="smush-select-actions. align-left">
				<a href="javascript:;" id="wpo_smush_images_select_all"><?php _e('Select all', 'wp-optimize');?></a> /
				<a href="javascript:;" id="wpo_smush_images_select_none"><?php _e('Select none', 'wp-optimize');?></a>
			</div>
			<div class="smush-refresh-icon align-right">
				<a href="javascript:;" id="wpo_smush_images_refresh" class="wpo-refresh-button"><?php _e('Refresh image list', 'wp-optimize');?> 
					<span class="dashicons dashicons-image-rotate"></span>
				</a>
				<img class="wpo_smush_images_loader" width="16" height="16" src="<?php echo admin_url(); ?>/images/spinner-2x.gif" />
			</div>
		</div>
		<div id="wpo_smush_images_grid"></div>
		<div class="smush-actions">
			<input type="button" id="wpo_smush_images_btn" class="wpo_primary_small button-primary align-left" value="<?php esc_attr_e('Compress the selected images', 'wp-optimize'); ?>" />
			<input type="button" id="wpo_smush_mark_as_compressed" class="wpo_primary_small button align-left" value="<?php esc_attr_e('Mark as already compressed', 'wp-optimize'); ?>" />
			<input type="button" id="wpo_smush_get_logs" class="wpo_smush_get_logs wpo_primary_small button-primary align-right" value="<?php esc_attr_e('View logs', 'wp-optimize'); ?>" />
		</div>
	</div>
</div>

<div id="wpo_smush_images_information_container" style="display:none;">
	<div id="wpo_smush_images_information_wrapper"> 
	<h3 id="wpo_smush_images_information_heading"><?php _e('Compressing images', 'wp-optimize');?></h3>
	<h4 id="wpo_smush_images_information_server"></h4>
	<div class="progress-bar orange stripes">
		<span style="width: 100%"></span>
	</div>
	<p><?php _e('The selected images are being processed; please do not close the browser', 'wp-optimize');?></p>
	<table id="smush_stats" class="smush_stats_table">
		<tbody>
			<tr class="smush_stats_row">
				<td> <?php _e('Images pending', 'wp-optimize');?></td>
				<td id="smush_stats_pending_images">&nbsp;...</td>
			</tr>
			<tr class="smush_stats_row">
				<td> <?php _e('Images completed', 'wp-optimize');?></td>
				<td id="smush_stats_completed_images">&nbsp;...</td>
			</tr>
			<tr class="smush_stats_row">
				<td> <?php _e('Size savings', 'wp-optimize');?></td>
				<td id="smush_stats_bytes_saved">&nbsp;...</td>
			</tr>
			<tr class="smush_stats_row">
				<td> <?php _e('Average savings per image', 'wp-optimize');?></td>
				<td id="smush_stats_percent_saved">&nbsp;...</td>
			</tr>
			<tr class="smush_stats_row">
				<td> <?php _e('Time elapsed', 'wp-optimize');?></td>
				<td id="smush_stats_timer">&nbsp;</td>
			</tr>
		</tbody>
	</table>
	</div>
	<input type="button" id="wpo_smush_images_pending_tasks_cancel_button" class="wpo_primary_small button-primary" value="<?php esc_attr_e('Cancel', 'wp-optimize'); ?>" />
</div>

<div id="smush-complete-summary" class="complete-animation" style="display:none;">
	<span class="dashicons dashicons-no-alt close"></span>
	<div class="animation"> 
		<div class="checkmark-circle">
		  <div class="background"></div>
		  <div class="checkmark draw"></div>
		</div>
	</div>
	<div id="summary-message"></div>
	<input type="button" id="wpo_smush_get_logs" class="wpo_smush_get_logs wpo_primary_small button-primary" value="<?php esc_attr_e('View logs', 'wp-optimize'); ?>" />
	<input type="button" id="wpo_smush_clear_stats_btn" class="wpo_primary_small button-primary align-right" value="<?php esc_attr_e('Clear compression statistics', 'wp-optimize'); ?>" />
	<img id="wpo_smush_images_clear_stats_spinner" class="display-none align-right" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
	<span id="wpo_smush_images_clear_stats_done" class="dashicons dashicons-yes display-none save-done align-right"></span>
	<span class="clearfix"></span>
	<input type="button" class="wpo_primary_small button-primary wpo_smush_stats_cta_btn" value="<?php esc_attr_e('Close', 'wp-optimize'); ?>" />
</div>

<div id="smush-log-modal" class="complete-animation" style="display:none;">
	<div id="log-panel"></div>
	<a href="#" class="wpo_primary_small button-primary"> <?php _e('Download log file', 'wp-optimize'); ?></a>
	<input type="button" class="wpo_primary_small button-primary close" value="<?php esc_attr_e('Close', 'wp-optimize'); ?>" />
</div>

<div id="smush-information-modal" style="display:none;">
	<div class="smush-information"></div>
	<input type="button" class="wpo_primary_small button-primary information-modal-close" value="<?php esc_attr_e('Close', 'wp-optimize'); ?>" />
</div>

<div id="smush-information-modal-cancel-btn" style="display:none;">
	<div class="smush-information"></div>
	<input type="button" class="wpo_primary_small button-primary" value="<?php esc_attr_e('Cancel', 'wp-optimize'); ?>" />
</div>
