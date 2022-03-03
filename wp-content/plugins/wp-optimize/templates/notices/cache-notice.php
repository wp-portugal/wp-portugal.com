<?php if (!defined('ABSPATH')) die('No direct access.'); ?>
<div class="wpo_info below-h2">

	<?php if ($message) : ?>
		<h3><?php _e('Page caching issue.', 'wp-optimize'); ?></h3>
		<p><?php echo $message; ?></p>
	<?php endif; ?>

</div>
