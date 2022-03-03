<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<a class="wpo-collapsible"><?php _e('More', 'wp-optimize'); ?></a>
<div class="wpo-collapsible-content">
	<table class="smush-details">
		<thead>
			<tr>
				<th><?php _e('Size name', 'wp-optimze'); ?></th>
				<th><?php _e('Original', 'wp-optimze'); ?></th>
				<th><?php _e('Compressed', 'wp-optimze'); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($sizes_info as $size => $info) {
				$saved = round((($info['original'] - $info['compressed']) / $info['original'] * 100), 2);
			?>
				<tr>
					<td><?php echo htmlentities($size); ?></td>
					<td><?php echo WP_Optimize()->format_size($info['original'], 1); ?></td>
					<td><?php echo WP_Optimize()->format_size($info['compressed'], 1); ?></td>
					<td><?php echo $saved; ?>%</td>
				</tr>    
			<?php } ?>
		</tbody>
	</table>
</div>
