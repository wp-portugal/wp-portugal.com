<?php

use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDBMST\CliCommand\MultisiteToolsAddonCli;
use DeliciousBrains\WPMDBMST\MultisiteToolsAddon;

function wpmdb_setup_multisite_tools_addon($cli)
{
    global $wpmdbpro_multisite_tools;

    if (!is_null($wpmdbpro_multisite_tools)) {
        return $wpmdbpro_multisite_tools;
    }

    $container = WPMDBDI::getInstance();
    $container->get(MultisiteToolsAddon::class)->register();
    $container->get(MultisiteToolsAddonCli::class)->register();

    if ($cli) {
        $wpmdbpro_multisite_tools = WPMDBDI::getInstance()->get(MultisiteToolsAddonCli::class);
    } else {
        $wpmdbpro_multisite_tools = WPMDBDI::getInstance()->get(MultisiteToolsAddon::class);
    }


    // Allows hooks to bypass the regular admin / ajax checks to force load the addon (required for the CLI addon).
    $force_load = apply_filters('wp_migrate_db_pro_multisite_tools_force_load', false);

    if (false === $force_load && !is_null($wpmdbpro_multisite_tools)) {
        return $wpmdbpro_multisite_tools;
    }

    if (false === $force_load && (!function_exists('wp_migrate_db_pro_loaded') || !wp_migrate_db_pro_loaded() || (is_multisite() && wp_is_large_network()))) {
        return false;
    }

    load_plugin_textdomain('wp-migrate-db-pro-multisite-tools', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    return $wpmdbpro_multisite_tools;
}

