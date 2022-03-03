<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<div class="wpo-unused-images-section">
	<h3 class="wpo-first-child"><?php _e('Unused images', 'wp-optimize');?></h3>
	<div id="wpo_unused_images">
		<?php for ($i=0; $i < 10; $i++) : ?>
			<div class="wpo_unused_image">
				<label class="wpo_unused_image_thumb_label">
					<div class="thumbnail">
						<span class="dashicons dashicons-format-image"></span>
					</div>
				</label>
			</div>
		<?php endfor; ?>
		<div class="wpo-unused-images__premium-mask">
			<a class="wpo-unused-images__premium-link" href="<?php esc_attr_e($wp_optimize->premium_version_link); ?>" target="_blank"><?php _e('Manage unused images with WP-Optimize Premium.', 'wp-optimize'); ?></a>
		</div>
	</div>
</div>

<div class="wpo-image-sizes-section">
	<h3><?php _e('Image sizes', 'wp-optimize'); ?></h3>
	<div class="wpo-fieldgroup premium-only">
		<h3><?php _e('Registered image sizes', 'wp-optimize'); ?></h3>
		<p class="red"><?php _e("This feature is for experienced users. Don't remove registered image sizes if you are not sure that images with selected sizes are not used on your site.", 'wp-optimize'); ?></p>
		<div id="registered_image_sizes">
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">registered-image-size (42.2 KB - Total: 3)<br>
			</label>
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">registered-image-size (42.2 KB - Total: 3)<br>
			</label>
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">registered-image-size (42.2 KB - Total: 3)<br>
			</label>
		</div>
		<h3><?php _e('Unused image sizes', 'wp-optimize');?></h3>
		<p class="hide_on_empty">
			<?php _e('These image sizes were used by some of the themes or plugins installed previously and they remain within your database.', 'wp-optimize'); ?>
			<a href="https://codex.wordpress.org/Post_Thumbnails#Add_New_Post_Thumbnail_Sizes" target="_blank"><?php _e('Read more about custom image sizes here.', 'wp-optimize'); ?></a>
		</p>
		<div id="unused_image_sizes">
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">unused-image-size (42.2 KB - Total: 3)<br>
			</label>
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">unused-image-size (42.2 KB - Total: 3)<br>
			</label>
			<label class="unused-image-sizes__label">
				<input type="checkbox" class="unused-image-sizes">unused-image-size (42.2 KB - Total: 3)<br>
			</label>
		</div>
		<div class="wpo_remove_selected_sizes_btn__container">
			<button type="buton" class="button button-primary" disabled="disabled"><?php _e('Remove selected sizes', 'wp-optimize'); ?></button>
		</div>
		<div class="wpo-unused-image-sizes__premium-mask">
			<a class="wpo-unused-images__premium-link" href="<?php esc_attr_e($wp_optimize->premium_version_link); ?>" target="_blank"><?php _e('Take control of WordPress image sizes with WP-Optimize Premium.', 'wp-optimize'); ?></a>
		</div>
	</div>
</div>
