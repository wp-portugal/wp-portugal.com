<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<tbody id="the-list">
<?php

	// Check for InnoDB tables.
	// Check for windows servers.
	$sqlversion = $wp_optimize->get_db_info()->get_version();
	$tablesstatus = $wp_optimize->get_optimizer()->get_tables();
	$is_multisite_mode = $wp_optimize->is_multisite_mode();
	$total_gain = 0;
	$no = 0;
	$row_usage = 0;
	$data_usage = 0;
	$index_usage = 0;
	$overhead_usage = 0;
	$non_inno_db_tables = 0;
	$inno_db_tables = 0;
	$small_overhead_size = 1048576;
	
	foreach ($tablesstatus as $tablestatus) {
		$no++;
		echo '<tr 
			data-tablename="'.esc_attr($tablestatus->Name).'"
			data-type="'.esc_attr($tablestatus->Engine).'"
			data-optimizable="'.($tablestatus->is_optimizable ? 1 : 0).'"
			'.($is_multisite_mode ? 'data-blog_id="'.($tablestatus->blog_id).'"' : '').'
		>'."\n";
		echo '<td data-colname="'.__('No.', 'wp-optimize').'">'.number_format_i18n($no).'</td>'."\n";
		echo '<td data-tablename="'.esc_attr($tablestatus->Name).'" data-colname="'.__('Table', 'wp-optimize').'">'.htmlspecialchars($tablestatus->Name);

		if (!empty($tablestatus->plugin_status)) {
			if ($tablestatus->wp_core_table) {
				echo "<br><span style='font-size: 11px;'>".__('Belongs to:', 'wp-optimize')."</span> ";
				echo "<span style='font-size: 11px;'>".__('WordPress core', 'wp-optimize')."</span>";
			} else {
				echo '<div class="table-plugins">';
				echo "<span style='font-size: 11px;'>".__('Known plugins that use this table name:', 'wp-optimize')."</span> ";
				$separator = '<br>';
				foreach ($tablestatus->plugin_status as $plugins_status) {
					$plugin = $plugins_status['plugin'];
					$status = $plugins_status['status'];

					echo $separator;

					echo " <a href='https://wordpress.org/plugins/{$plugin}/' target='_blank'><span style='font-size: 11px;'>{$plugin}</a>";

					if (false == $status['installed']) {
						echo " <span style='font-size: 11px; color: #9B0000; font-weight: bold;'>[".__('not installed', 'wp-optimize')."]</span>";
					} elseif (false == $status['active']) {
						echo " <span style='font-size: 11px; color: #9B0000; font-weight: bold;'>[".__('inactive', 'wp-optimize')."]</span>";
					}
				}
				echo '</div>';
			}
		}

		echo "</td>\n";

		echo '<td data-colname="'.__('Records', 'wp-optimize').'" data-raw_value="'.esc_attr(intval($tablestatus->Rows)).'">'.number_format_i18n($tablestatus->Rows).'</td>'."\n";
		echo '<td data-colname="'.__('Data Size', 'wp-optimize').'" data-raw_value="'.esc_attr(intval($tablestatus->Data_length)).'">'.$wp_optimize->format_size($tablestatus->Data_length).'</td>'."\n";
		echo '<td data-colname="'.__('Index Size', 'wp-optimize').'" data-raw_value="'.esc_attr(intval($tablestatus->Index_length)).'">'.$wp_optimize->format_size($tablestatus->Index_length).'</td>'."\n";

		if ($tablestatus->is_optimizable) {
			echo '<td data-colname="'.__('Type', 'wp-optimize').'" data-optimizable="1">'.htmlspecialchars($tablestatus->Engine).'</td>'."\n";

			echo '<td data-colname="'.__('Overhead', 'wp-optimize').'" data-raw_value="'.esc_attr(intval($tablestatus->Data_free)).'">';
			$font_colour = ($optimize_db ? (($tablestatus->Data_free > $small_overhead_size) ? '#0000FF' : '#004600') : (($tablestatus->Data_free > $small_overhead_size) ? '#9B0000' : '#004600'));
			echo '<span style="color:'.$font_colour.';">';
			echo $wp_optimize->format_size($tablestatus->Data_free);
			echo '</span>';
			echo '</td>'."\n";

			$overhead_usage += $tablestatus->Data_free;
			$total_gain += $tablestatus->Data_free;
			$non_inno_db_tables++;
		} else {
			echo '<td data-colname="'.__('Type', 'wp-optimize').'" data-optimizable="0">'.htmlspecialchars($tablestatus->Engine).'</td>'."\n";
			echo '<td data-colname="'.__('Overhead', 'wp-optimize').'">';
			echo '<span style="color:#0000FF;">-</span>';
			echo '</td>'."\n";

			$inno_db_tables++;
		}

		echo '<td data-colname="'.__('Actions', 'wp-optimize').'">'.apply_filters('wpo_tables_list_additional_column_data', '', $tablestatus).'</td>';

		$row_usage += $tablestatus->Rows;
		$data_usage += $tablestatus->Data_length;
		$index_usage += $tablestatus->Index_length;

		echo '</tr>'."\n";
	}

	// THis extra tbody with class of tablesorter-no-sort
	// Is for tablesorter and it will not allow the total bar
	// At the bottom of the table information to be sorted with the rest of the data
	echo '<tbody class="tablesorter-no-sort">'."\n";

	echo '<tr class="thead">'."\n";
	echo '<th>'.__('Total:', 'wp-optimize').'</th>'."\n";
	echo '<th>'.sprintf(_n('%s Table', '%s Tables', $no, 'wp-optimize'), number_format_i18n($no)).'</th>'."\n";
	echo '<th>'.number_format_i18n($row_usage).'</th>'."\n";
	echo '<th>'.$wp_optimize->format_size($data_usage).'</th>'."\n";
	echo '<th>'.$wp_optimize->format_size($index_usage).'</th>'."\n";
	echo '<th>'.'-'.'</th>'."\n";
	echo '<th>';

	$font_colour = (($optimize_db) ? (($overhead_usage > $small_overhead_size) ? '#0000FF' : '#004600') : (($overhead_usage > $small_overhead_size) ? '#9B0000' : '#004600'));
	
	echo '<span style="color:'.$font_colour.'">'.$wp_optimize->format_size($overhead_usage).'</span>';
	
	?>
	</th>
	<th><?php _e('Actions', 'wp-optimize'); ?></th>
	</tr>
</tbody>
