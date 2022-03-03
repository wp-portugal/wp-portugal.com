<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<h5><?php echo esc_html($log->header); ?></h5>
<ul><?php
foreach ((array) $log->files as $handle => $file) {
	$file_path = untrailingslashit(get_home_path()) . $file->url;
	$file_size = file_exists($file_path) ? ' (' . WP_Optimize()->format_size(@filesize($file_path)) . ')' : ''; // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

	echo '<li'.($file->success ? '' : ' class="failed"').'><span class="wpo_min_file_url"><a href="'.esc_url(get_home_url().$file->url).'" target="_blank">'.htmlspecialchars($file->url).'</a>'.$file_size.'</span>';
	if (property_exists($file, 'debug')) echo '<span class="wpo_min_file_debug">'.htmlspecialchars($file->debug).'</span>';
	echo ' <span class="wrapper">';
	printf(' <a href="#" data-url="%1$s" class="exclude">%2$s</a>', htmlspecialchars($file->url), __('Exclude', 'wp-optimize'));
	$minify_config = get_option('wpo_minify_config');
	if (preg_match('/\.js$/i', $file->url, $matches)) {
		if ('individual' === $minify_config['enable_defer_js']) {
			printf(' | <a href="#" data-url="%1$s" class="defer">%2$s</a>', htmlspecialchars($file->url), __('Defer loading', 'wp-optimize'));
		}
	} elseif (preg_match('/\.css$/i', $file->url, $matches)) {
		printf(' | <a href="#" data-url="%1$s" class="async">%2$s</a>', htmlspecialchars($file->url), __('Load asynchronously', 'wp-optimize'));
	}
	echo '</span></li>';
}
?>
</ul>
