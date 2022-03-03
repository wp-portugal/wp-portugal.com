<?php
/*
 * Uninstall plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$ms_sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

	if ( 0 < sizeof( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			$blog_id = class_exists( 'WP_Site' ) ? $ms_site->blog_id : $ms_site['blog_id'];
			switch_to_blog( $blog_id );
			plugin_uninstalled();
		}
	}

	restore_current_blog();
} else {
	plugin_uninstalled();
}

/**
 * Delete plugin data when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
	$option_name = 'dbmanager_options';

	delete_option( $option_name );

	wp_clear_scheduled_hook( 'dbmanager_cron_backup' );
	wp_clear_scheduled_hook( 'dbmanager_cron_optimize' );
	wp_clear_scheduled_hook( 'dbmanager_cron_repair' );
}
