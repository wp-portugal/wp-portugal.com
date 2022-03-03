<?php
if (!defined('WPO_VERSION')) die('No direct access allowed');

?>
<div class="wpo_shade hidden">
	<div class="wpo_shade_inner">
			<span class="dashicons dashicons-update-alt wpo-rotate"></span>
		<h4><?php _e('Loading data...', 'wp-optimize'); ?></h4>
	</div>
</div>

<div class="wpo_section wpo_group">

	<?php
		if (!empty($optimization_results)) {
			echo '<div id="message" class="updated below-h2"><strong>';
			foreach ($optimization_results as $optimization_result) {
				if (!empty($optimization_result->output)) {
					foreach ($optimization_result->output as $line) {
						echo $line."<br>";
					}
				}
			}
			echo '</strong></div>';
		}
	?>

	<?php WP_Optimize()->include_template('database/status-box-contents.php', false, array('optimize_db' => false)); ?>

	<form onsubmit="return confirm('<?php echo esc_js(__('Warning: This operation is permanent. Continue?', 'wp-optimize')); ?>')" action="#" method="post" enctype="multipart/form-data" name="optimize_form" id="optimize_form">
	
	<?php wp_nonce_field('wpo_optimization'); ?>
	
		<h3><?php _e('Optimizations', 'wp-optimize'); ?></h3>


		<div class="wpo-run-optimizations__container">
			<?php $button_caption = apply_filters('wpo_run_button_caption', __('Run all selected optimizations', 'wp-optimize')); ?>
			<input class="button button-primary button-large" type="submit" id="wp-optimize" name="wp-optimize" value="<?php echo esc_attr($button_caption); ?>" /><?php WP_Optimize()->include_template('take-a-backup.php', false, array('checkbox_name' => 'enable-auto-backup')); ?>
		</div>
		
		<?php do_action('wpo_additional_options'); ?>

		<?php
		if ($load_data) {
			WP_Optimize()->include_template('database/optimizations-table.php', false, array('does_server_allows_table_optimization' => $does_server_allows_table_optimization));
		} else {
		?>
			<div class="wp-optimize-optimizations-table-placeholder">
			</div>
		<?php } ?>
		
		<p class="wp-optimize-sensitive-tables-warning">
		<span style="color: #E07575;"><span class="dashicons dashicons-warning"></span> <?php echo __('Warning:', 'wp-optimize').'</span> '.__('Items marked with this icon perform more intensive database operations. In very rare cases, if your database server happened to crash or be forcibly powered down at the same time as an optimization operation was running, data might be corrupted. ', 'wp-optimize').' '.__('You may wish to run a backup before optimizing.', 'wp-optimize'); ?>
		</p>
	</form>
</div>
