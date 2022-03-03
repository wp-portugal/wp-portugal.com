<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<?php
$lazyload_options = $options->get_option('lazyload', array(
	'images' => false,
	'iframes' => false,
	'skip_classes' => '',
));

$read_more_link = 'https://developers.google.com/web/fundamentals/performance/lazy-loading-guidance/images-and-video/';

?>

<div id="wpo_lazy_load_settings">
	<h3 class="wpo-first-child"><?php _e('Lazy-load images'); ?></h3>
	<div class="wpo-fieldgroup premium-only">
		<p>
			<?php _e('Lazy-loading is technique that defers loading of non-critical resources (images, video) at page load time. Instead, these non-critical resources are loaded at the point they are needed (e.g. the user scrolls down to them).', 'wp-optimize'); ?>
			<br>
			<a href="<?php echo $read_more_link; ?>" target="_blank"><?php _e('Follow this link to read more about lazy-loading images and video', 'wp-optimize'); ?></a>
		</p>
		<ul>
			<li><label><input type="checkbox" name="lazyload[images]" <?php checked($lazyload_options['images']); ?> disabled /><?php _e('Images', 'wp-optimize'); ?></label></li>
			<li><label><input type="checkbox" name="lazyload[iframes]" <?php checked($lazyload_options['iframes']); ?> disabled /><?php _e('Iframes and Videos', 'wp-optimize'); ?></label></li>
		</ul>

		<p>
			<?php _e('Skip image classes', 'wp-optimize');?><br>
			<input type="text" name="lazyload[skip_classes]" id="wpo_lazyload_skip_classes" value="<?php echo esc_attr($lazyload_options['skip_classes']); ?>" disabled readonly /><br>
			<small><?php _e('Enter the image class or classes comma-separated. Supports wildcards. Example: image-class1, image-class2, thumbnail*, ...', 'wp-optimize'); ?></small>
		</p>

		<div class="wpo-unused-image-sizes__premium-mask">
			<a class="wpo-unused-images__premium-link" href="<?php esc_attr_e($wp_optimize->premium_version_link); ?>" target="_blank"><?php _e('Enable Lazy-loading with WP-Optimize Premium.', 'wp-optimize'); ?></a>
		</div>
	</div>
</div>
