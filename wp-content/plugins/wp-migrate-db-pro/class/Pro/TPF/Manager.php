<?php
namespace DeliciousBrains\WPMDB\Pro\TPF;

use DeliciousBrains\WPMDB\Pro\Addon\AddonManagerInterface;
use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDB\Pro\TPF\Cli\ThemePluginFilesCli;

class Manager implements AddonManagerInterface {

    public function register()
    {
        global $wpmdbpro_theme_plugin_files;

        if (!is_null($wpmdbpro_theme_plugin_files) ) {
            return $wpmdbpro_theme_plugin_files;
        }


        $container = WPMDBDI::getInstance();
        $container->get(ThemePluginFilesAddon::class)->register();
        $container->get(ThemePluginFilesLocal::class)->register();
        $container->get(ThemePluginFilesRemote::class)->register();
        $container->get(ThemePluginFilesCli::class)->register();

        add_filter('wpmdb_addon_registered_tpf', '__return_true');

        return $container->get(ThemePluginFilesAddon::class);
    }

    public function get_license_response_key()
    {
        return 'wp-migrate-db-pro-theme-plugin-files';
    }
}
