<?php

namespace DeliciousBrains\WPMDB\Pro\MST;

use DeliciousBrains\WPMDB\Pro\Addon\AddonManagerInterface;
use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDB\Pro\MST\CliCommand\MultisiteToolsAddonCli;

class Manager implements AddonManagerInterface
{

    private $cli = false;

    public function register()
    {

        add_action('wp_migrate_db_pro_cli_before_load', function () {
            $this->cli = true;
            return $this->init();
        });

        return $this->init();
    }

    private function init() {
        global $wpmdbpro_multisite_tools;

        if ( ! is_null($wpmdbpro_multisite_tools) ) {
            return $wpmdbpro_multisite_tools;
        }

        $container = WPMDBDI::getInstance();
        $container->get(MultisiteToolsAddon::class)->register();
        $container->get(MultisiteToolsAddonCli::class)->register();

        if ($this->cli) {
            $wpmdbpro_multisite_tools = WPMDBDI::getInstance()->get(MultisiteToolsAddonCli::class);
        } else {
            $wpmdbpro_multisite_tools = WPMDBDI::getInstance()->get(MultisiteToolsAddon::class);
        }

        add_filter('wpmdb_addon_registered_mst', '__return_true');

        // Allows hooks to bypass the regular admin / ajax checks to force load the addon (required for the CLI addon).
        $force_load = apply_filters('wp_migrate_db_pro_multisite_tools_force_load', false);

        if (false === $force_load && ! is_null($wpmdbpro_multisite_tools)) {
            return $wpmdbpro_multisite_tools;
        }

        if (false === $force_load
            && ((is_multisite() && wp_is_large_network()))) {
            return false;
        }

        return $wpmdbpro_multisite_tools;
    }

    public function get_license_response_key()
    {
        return 'wp-migrate-db-pro-multisite-tools';
    }
}
