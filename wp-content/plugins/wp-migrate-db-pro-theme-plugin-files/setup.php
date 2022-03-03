<?php

use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDBTP\ThemePluginFilesAddon;
use DeliciousBrains\WPMDBTP\ThemePluginFilesLocal;
use DeliciousBrains\WPMDBTP\ThemePluginFilesRemote;
use DeliciousBrains\WPMDBTP\Cli\ThemePluginFilesCli;

function wpmdb_setup_theme_plugin_files_addon()
{
    global $wpmdbpro_theme_plugin_files;

    if (!is_null($wpmdbpro_theme_plugin_files)) {
        return $wpmdbpro_theme_plugin_files;
    }

    if (!function_exists('wp_migrate_db_pro_loaded') || !wp_migrate_db_pro_loaded()) {
        return false;
    }

    $container = WPMDBDI::getInstance();
    $container->get(ThemePluginFilesAddon::class)->register();
    $container->get(ThemePluginFilesLocal::class)->register();
    $container->get(ThemePluginFilesRemote::class)->register();
    $container->get(ThemePluginFilesCli::class)->register();

    $wpmdbpro_theme_plugin_files = $container->get(ThemePluginFilesAddon::class);

    load_plugin_textdomain('wp-migrate-db-pro-theme-plugin-files', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    return $wpmdbpro_theme_plugin_files;
}
