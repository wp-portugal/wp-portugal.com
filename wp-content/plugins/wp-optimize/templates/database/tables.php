<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<div class="wpo_shade hidden">
	<div class="wpo_shade_inner">
			<span class="dashicons dashicons-update-alt wpo-rotate"></span>
		<h4><?php _e('Loading data...', 'wp-optimize'); ?></h4>
	</div>
</div>

<?php
// This next bit belongs somewhere else, I think.
?>
<?php if ($optimize_db) { ?>
	<p><?php _e('Optimized all the tables found in the database.', 'wp-optimize'); ?></p>
<?php } ?>
<?php

// used for output premium functionality
do_action('wpo_tables_list_before');

?>

<?php $wp_optimize->include_template('take-a-backup.php', false, array('label' => __('Take a backup with UpdraftPlus before any actions upon tables (recommended).', 'wp-optimize'), 'default_checkbox_value' => 'true', 'checkbox_name' => 'enable-auto-backup-1')); ?>

<p class="wpo-table-list-filter"><strong><?php echo __('Database name:', 'wp-optimize')." '".htmlspecialchars(DB_NAME)."'"; ?><a id="wp_optimize_table_list_refresh" href="#" class="wpo-refresh-button"><span class="dashicons dashicons-image-rotate"></span><?php _e('Refresh data', 'wp-optimize'); ?></a></strong> <input id="wpoptimize_table_list_filter" class="search" type="search" value="" placeholder="<?php esc_attr_e('Search for table', 'wp-optimize'); ?>" data-column="1" /></p>

<?php
$optimizer = WP_Optimize()->get_optimizer();
$table_prefix = $optimizer->get_table_prefix();
if (!$table_prefix) {
?>
<p class="wpo-table-list-filter"><span style="color: #0073aa;"><span class="dashicons dashicons-info"></span> <?php echo __('Note:', 'wp-optimize').'</span> '.__('Your WordPress install does not use a database prefix, so WP-Optimize was not able to differentiate which tables belong to WordPress so all tables are listed below.', 'wp-optimize'); ?></p>
<?php
}
?>

<table id="wpoptimize_table_list" class="wp-list-table widefat striped tablesorter wp-list-table-mobile-labels">
	<thead>
		<tr>
			<th><?php _e('No.', 'wp-optimize'); ?></th>
			<th class="column-primary"><?php _e('Table', 'wp-optimize'); ?></th>
			<th><?php _e('Records', 'wp-optimize'); ?></th>
			<th><?php _e('Data Size', 'wp-optimize'); ?></th>
			<th><?php _e('Index Size', 'wp-optimize'); ?></th>
			<th><?php _e('Type', 'wp-optimize'); ?></th>
			<th><?php _e('Overhead', 'wp-optimize'); ?></th>
			<th><?php _e('Actions', 'wp-optimize'); ?></th>
		</tr>
	</thead>
	<?php
	if ($load_data) {
		WP_Optimize()->include_template('database/tables-body.php', false, array('optimize_db' => $optimize_db));
	} else {
	?>
		<tbody>
			<tr>
				<td></td>
				<td class="loading" align="center" colspan="6"><img class="wpo-ajax-template-loader" width="16" height="16" src="<?php echo admin_url(); ?>images/spinner-2x.gif" /> <?php _e('Loading tables list...', 'wp-optimize'); ?></td>
				<td></td>
			</tr>
		</tbody>
	<?php } ?>
</table>


<div id="wpoptimize_table_list_tables_not_found"><?php _e('Tables not found.', 'wp-optimize'); ?></div>

<?php

WP_Optimize()->include_template('database/tables-list-after.php', false, array('optimize_db' => $optimize_db, 'load_data' => $load_data));
