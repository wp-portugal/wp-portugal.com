<?php if (!defined('WPO_VERSION')) die('No direct access allowed');

$sqlversion = (string) $wp_optimize->get_db_info()->get_version();
?>

<p class="wpo-system-status"><em>WP-Optimize <?php echo WPO_VERSION; ?> - <?php _e('running on:', 'wp-optimize'); ?> PHP <?php echo htmlspecialchars(PHP_VERSION); ?>, MySQL <?php echo htmlspecialchars($sqlversion); ?> - <?php echo htmlspecialchars(PHP_OS); ?></em></p>
