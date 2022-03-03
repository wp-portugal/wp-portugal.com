<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>
<?php
$trackback_action_arr = $options->get_option('trackbacks_action', array());
$comments_action_arr = $options->get_option('comments_action', array());
?>
<h3><?php _e('Trackback/comments actions', 'wp-optimize'); ?></h3>
<div class="wpo-fieldgroup">
	<div class="wpo-fieldgroup__subgroup">
		<h3 class="wpo-first-child"><?php _e('Trackbacks', 'wp-optimize'); ?></h3>

		<div id="trackbacks_notice"></div>

		<p>
			<small><?php _e('Use these buttons to enable or disable any future trackbacks on all your previously published posts.', 'wp-optimize'); ?></small>
		</p>
		
		<p>
			<button class="button btn-updraftplus" type="button" id="wp-optimize-disable-enable-trackbacks-enable" name="wp-optimize-disable-enable-trackbacks-enable"><?php _e('Enable', 'wp-optimize'); ?></button>
			
			<button class="button btn-updraftplus" type="button" id="wp-optimize-disable-enable-trackbacks-disable" name="wp-optimize-disable-enable-trackbacks-disable"><?php _e('Disable', 'wp-optimize'); ?></button>
			<img id="trackbacks_spinner" class="wpo_spinner" src="<?php esc_attr_e(admin_url('images/spinner-2x.gif')); ?>" alt="...">
			<span id="trackbacks_actionmsg">	
			<?php
			if (!empty($trackback_action_arr)) {
				
				$trackback_action_timestamp = WP_Optimize()->format_date_time($trackback_action_arr['timestamp']);
				if ($trackback_action_arr['action']) {
					echo sprintf(__('All trackbacks on existing posts were enabled on the %s.', 'wp-optimize'), $trackback_action_timestamp);
				} else {
					echo sprintf(__('All trackbacks on existing posts were disabled on the %s.', 'wp-optimize'), $trackback_action_timestamp);
				}
			
			}
			?>
			</span>
		</p>

	</div>

	<div class="wpo-fieldgroup__subgroup">

		<h3><?php _e('Comments', 'wp-optimize'); ?></h3>
		
		<div id="comments_notice"></div>

		<p><small><?php _e('Use these buttons to enable or disable any future comments on all your previously published posts.', 'wp-optimize'); ?></small></p>

		<p>
			<button class="button btn-updraftplus" type="button" id="wp-optimize-disable-enable-comments-enable" name="wp-optimize-disable-enable-comments-enable"><?php _e('Enable', 'wp-optimize'); ?></button>

			<button class="button btn-updraftplus" type="button" id="wp-optimize-disable-enable-comments-disable" name="wp-optimize-disable-enable-comments-disable"><?php _e('Disable', 'wp-optimize'); ?></button>

			<img id="comments_spinner" class="wpo_spinner" src="<?php esc_attr_e(admin_url('images/spinner-2x.gif')); ?>" alt="...">
			<span id="comments_actionmsg">
			<?php
			if (!empty($comments_action_arr)) {
				
				$comments_action_timestamp = WP_Optimize()->format_date_time($comments_action_arr['timestamp']);
				if ($comments_action_arr['action']) {
					echo sprintf(__('All comments on existing posts were enabled on the %s.', 'wp-optimize'), $comments_action_timestamp);
				} else {
					echo sprintf(__('All comments on existing posts were disabled on the %s.', 'wp-optimize'), $comments_action_timestamp);
				}
			
			}
			?>
			</span>
		</p>

	</div>
</div>
