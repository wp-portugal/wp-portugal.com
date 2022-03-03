<?php
/*
Plugin Name: WP Migrate DB Pro Multisite Tools
Plugin URI: https://deliciousbrains.com/wp-migrate-db-pro/
Description: An extension to WP Migrate DB Pro, supporting Multisite migrations.
Author: Delicious Brains
Version: 1.4.1
Author URI: https://deliciousbrains.com
Network: True
*/

// Copyright (c) 2015 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

defined( 'ABSPATH' ) || exit;

require_once 'version.php';
$GLOBALS['wpmdb_meta']['wp-migrate-db-pro-multisite-tools']['folder'] = basename(plugin_dir_path(__FILE__));

const WPMDB_MST_REQUIRED_CORE_VERSION = '2.2';

function get_mdb_version_mst()
{
    $path = __DIR__ . '/../wp-migrate-db-pro/version.php';
    if (!file_exists($path)) {
        return false;
    }

    require_once __DIR__ . '/../wp-migrate-db-pro/version.php';

    return $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'];
}

if (version_compare(PHP_VERSION, '5.6', '>=')) {
    $mdbVersion = get_mdb_version_mst();

    if ($mdbVersion && version_compare($mdbVersion, WPMDB_MST_REQUIRED_CORE_VERSION, '>=')) {
        require_once __DIR__ . '/class/autoload.php';
        require_once __DIR__ . '/setup.php';
    }
}

/**
 * Populate the $wpmdbpro_multisite_tools global with an instance of the WPMDBPro_Multisite_Tools class and return it.
 *
 * @param bool $cli
 *
 * @return \DeliciousBrains\WPMDBMST\MultisiteToolsAddon The one true global instance of the MultisiteToolsAddon class.
 */
function wp_migrate_db_pro_multisite_tools($cli = false)
{
    if (function_exists('wp_migrate_db_pro')) {
        wp_migrate_db_pro();
    } else {
        return false;
    }

    if (function_exists('wpmdb_setup_multisite_tools_addon')) {
        return wpmdb_setup_multisite_tools_addon($cli);
    }
}

/**
 * By default load plugin on admin pages, a little later than WP Migrate DB Pro.
 */
add_action('plugins_loaded', 'wp_migrate_db_pro_multisite_tools', 11);

/**
 * Loads up an instance of the WPMDBPro_Multisite_Tools class, allowing Multisite Tools functionality to be used during CLI migrations.
 */
function wp_migrate_db_pro_multisite_tools_before_cli_load()
{
    // Force load the Multisite Tools addon
    add_filter('wp_migrate_db_pro_multisite_tools_force_load', '__return_true');

    wp_migrate_db_pro_multisite_tools(true);
}

add_action('wp_migrate_db_pro_cli_before_load', 'wp_migrate_db_pro_multisite_tools_before_cli_load');
