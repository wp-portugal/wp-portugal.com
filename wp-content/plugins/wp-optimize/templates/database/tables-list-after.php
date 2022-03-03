<?php
if (!defined('WPO_VERSION')) die('No direct access allowed');

if ($load_data) {
	$optimizer = WP_Optimize()->get_optimizer();
	list ($db_size, $total_gain) = $optimizer->get_current_db_size();
}

?>
<h3><?php _e('Total size of database:', 'wp-optimize'); ?> <span id="optimize_current_db_size"><?php
	if ($load_data) {
		echo $db_size;
	} else {
		echo '...';
	}
?></span></h3>

<?php
if ($optimize_db) {
	?>

	<h3><?php _e('Optimization results:', 'wp-optimize'); ?></h3>
	<p style="color: #0000ff;" id="optimization_table_total_gain">
	<?php
	if ($total_gain > 0) {
		echo __('Total space saved:', 'wp-optimize').' <span>'.$wp_optimize->format_size($total_gain).'</span> ';
		$optimizer->update_total_cleaned(strval($total_gain));
	}
	?>
	</p>
	<?php
}

?>

<script type="text/html" id="tmpl-wpo-table-delete">
	<h3><span class="dashicons dashicons-warning"></span> <?php _e('Are you sure?', 'wp-optimize'); ?></h3>
	<div class="notice notice-warning">
		<p><?php _e('WARNING - some plugins might not be detected as installed or activated if they are in unknown folders (for example premium plugins).', 'wp-optimize').' '.__('Only delete a table if you are sure of what you are doing, and after taking a backup.', 'wp-optimize'); ?></p>
		<p><?php _e('If none of the plugins listed were ever installed on this website, you should not delete this table as it is likely to be used by an unlisted plugin.', 'wp-optimize'); ?></p>
	</div>
	<h4><?php printf(__('You are about to remove the table %s.', 'wp-optimize'), '<span class="table-name">{{data.table_name}}</span>'); ?></h4>
	<div class="wpo-table-delete--plugins">
		{{{data.plugins_list}}}
	</div>
	<# if (data.no_backup) { #>
		<p class="no-backup-detected">
			<input type="checkbox" id="confirm_deletion_without_backup"> <strong><?php _e('No automatic backup was detected.', 'wp-optimize'); ?></strong> <?php _e('I confirm that I will be able to revert the changes if needed.', 'wp-optimize'); ?>
		</p>
	<# } #>
	<p>
		<input type="checkbox" id="confirm_table_deletion"> <?php _e('I confirm that I have understood the risks in doing that, and that I know what I am doing.', 'wp-optimize'); ?>
	</p>
	<p>
		<input type="checkbox" id="ignores_table_delete_warning"> <?php _e('Do not show this warning again.', 'wp-optimize'); ?>
	</p>
	<button type="button" class="button button-primary delete-table" disabled><?php _e('Remove the table', 'wp-optimize'); ?></button>
	<button type="button" class="button cancel wpo-modal--close"><?php _e('Cancel', 'wp-optimize'); ?></button>
</script>