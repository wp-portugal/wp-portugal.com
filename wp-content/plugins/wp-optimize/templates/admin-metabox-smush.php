<?php if (!defined('WPO_VERSION')) die('No direct access.'); ?>
<div id='smush-metabox-inside-wrapper'>
	<div class='wpo_restore_single_image' <?php echo $restore_display; ?>>
		<div class='restore_possible' <?php echo $restore_action; ?>>
			<label for='wpo_restore_single_image_<?php echo $post_id; ?>'>
				<span class='alignleft'>  <?php _e('Restore original', 'wp-optimize'); ?></span>
			</label>
			<input type='button' id='wpo_restore_single_image_<?php echo $post_id; ?>' data-blog='<?php echo get_current_blog_id(); ?>' data-id="<?php echo $post_id; ?>" class='button-primary button alignright' value="<?php esc_attr_e('Restore', 'wp-optimize');?>">
		</div>
		<p id='smush_info' class='wpo_restore_single_image'><?php echo $smush_info; ?></p>
		<div id="wpo_smush_details"><?php echo $smush_details; ?></div>
	</div>
	<div class='wpo_smush_single_image compression_level' <?php echo $smush_display; ?>>
		<label for="enable_lossy_compression">
			<input type="radio" id="enable_lossy_compression" name="compression_level" class="smush-options compression_level" <?php checked($smush_options['image_quality'], 60); ?>> 
			<?php _e('Prioritize maximum compression', 'wp-optimize');?>
			<span tabindex="0" data-tooltip="<?php _e('Potentially uses lossy compression to ensure maximum savings per image, the resulting images are of a slightly lower quality', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
		</label>
		<label for="enable_lossless_compression">
			<input type="radio" id="enable_lossless_compression" name="compression_level" class="smush-options compression_level" <?php checked($smush_options['image_quality'], 100); ?>> 
			<?php _e('Prioritize retention of detail', 'wp-optimize');?>
			<span tabindex="0" data-tooltip="<?php _e('Uses lossless compression, which results in much better image quality but lower filesize savings per image', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span> </span>
		</label>
		<label for="enable_custom_compression">
			<input id="enable_custom_compression" type="radio" name="compression_level" class="smush-options compression_level" <?php checked($custom); ?>> 
			<?php _e('Custom', 'wp-optimize');?>
		</label>
		<div class="smush-options custom_compression" <?php if (!$custom) echo 'style="display:none;"';?>>
			<span class="alignleft"><?php _e('Maximum compression', 'wp-optimize');?></span>
			<input id="custom_compression_slider" class="compression_level" data-max="Maximum Compression"  type="range" step="5" value="<?php echo intval($smush_options['image_quality']); ?>" min="65" max="85" list="number" />
			<datalist id="number">
				<option value="65"/>
				<option value="70"/>
				<option value="75"/>
				<option value="80"/>
				<option value="85"/>
			</datalist>
			<span class="alignright"><?php _e('Best image quality', 'wp-optimize');?></span>
		</div>
	</div>
	<a href="#" class="wpo-toggle-advanced-options wpo_smush_single_image" <?php echo $smush_display; ?>><?php _e('Show advanced options', 'wp-optimize');?></a>
	<div class='smush-advanced wpo-advanced-options'>
		<h4><?php _e('Service provider', 'wp-optimize');?></h4>
		<fieldset class="compression_server">
			<label for="resmushit"> 
				<input type="radio" id="resmushit" name="compression_server_<?php echo $post_id; ?>" value="resmushit" <?php checked($smush_options['compression_server'], 'resmushit'); ?>>
				<a href="http://resmush.it" target="_blank"><?php _e('reSmush.it', 'wp-optimize');?></a>
			</label>
		</fieldset>
		<h4><?php _e('Other options', 'wp-optimize');?></h4>			
		<fieldset class="other_options">
			<label for='smush_backup_<?php echo $post_id; ?>'>
				<input type='checkbox' name='smush_backup_<?php echo $post_id; ?>' id='smush_backup_<?php echo $post_id; ?>' <?php checked($smush_options['back_up_original']); ?>>
				<span><?php _e('Backup original', 'wp-optimize'); ?></span>
			</label>
			<label for='smush_exif_<?php echo $post_id; ?>'>
				<input type='checkbox' name='smush_exif_<?php echo $post_id; ?>' id='smush_exif_<?php echo $post_id; ?>' class="preserve_exif" <?php checked($smush_options['preserve_exif']); ?>>
				<span><?php _e('Keep EXIF data', 'wp-optimize'); ?></span>
			</label>
		</fieldset>
   </div>

	<?php if ($compressed_by_another_plugin) { ?>
		<p><b><?php _e('Note: This image is already compressed by another plugin', 'wp-optimize'); ?></b></p>
	<?php } ?>

	<div class='wpo_smush_single_image action_button' <?php echo $smush_display; ?> >
		<input type='button' data-blog='<?php echo get_current_blog_id(); ?>' data-id="<?php echo $post_id; ?>" id='smush_compress_<?php echo $post_id; ?>' class='button-primary button' value='<?php _e('Compress', 'wp-optimize'); ?>'/>
   </div>

	<div class='wpo_smush_mark_single_image action_button' <?php echo $smush_mark; ?> >
		<input type='button' data-blog='<?php echo get_current_blog_id(); ?>' data-id="<?php echo $post_id; ?>" id='smush_mark_<?php echo $post_id; ?>' class='button' value='<?php _e('Mark as already compressed', 'wp-optimize'); ?>'/>
	</div>

	<div class='wpo_smush_unmark_single_image action_button' <?php echo $smush_unmark; ?> >
		<input type='button' data-blog='<?php echo get_current_blog_id(); ?>' data-id="<?php echo $post_id; ?>" id='smush_unmark_<?php echo $post_id; ?>' class='button' value='<?php _e('Mark as uncompressed', 'wp-optimize'); ?>'/>
	</div>

	<?php

	$menu_page_url = menu_page_url('wpo_images', false);

	if ('' === $menu_page_url && !is_multisite()) {
		$menu_page_url = admin_url('admin.php?page=wpo_images');
	}

	if (is_multisite()) {
		$menu_page_url = network_admin_url('admin.php?page=wpo_images');
	}
	?>
	<div class="smush-metabox-dashboard-link">
		<a href="<?php echo $menu_page_url; ?>"><?php _e('WP-Optimize image settings', 'wp-optimize'); ?></a>
	</div>
</div>

<div id="smush-information-modal" class="wp-core-ui" style="display:none;">
	<div class="smush-information"></div>
	<input type="button" class="wpo_primary_small button-primary information-modal-close" value="<?php esc_attr_e('Close', 'wp-optimize'); ?>" />
</div>

<div id="smush-information-modal-cancel-btn" style="display:none;">
	<div class="smush-information"></div>
	<input type="button" class="wpo_primary_small button-primary" value="<?php esc_attr_e('Cancel', 'wp-optimize'); ?>" />
</div>

<script type="text/javascript">jQuery(document).trigger('admin-metabox-smush-loaded');</script>
