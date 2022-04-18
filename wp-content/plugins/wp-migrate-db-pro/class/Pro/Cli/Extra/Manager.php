<?php

namespace DeliciousBrains\WPMDB\Pro\Cli\Extra;

use DeliciousBrains\WPMDB\Pro\Addon\AddonManagerInterface;
use DeliciousBrains\WPMDB\WPMDBDI;

class Manager implements AddonManagerInterface
{

    public function __construct()
    {
        add_action('cli_init', [$this, 'init_base_cli']);
    }


    //Initialize default CLI commands in case no license is entered
    public function init_base_cli()
    {
        \DeliciousBrains\WPMDB\Pro\Cli\Command::register();
    }


    public function register()
    {
        global $wpmdbpro_cli;


        $container = WPMDBDI::getInstance();
        // Load pro classes
        $register_pro = new \DeliciousBrains\WPMDB\Pro\RegisterPro();

        $container->get(CliAddon::class)->register();

        $wpmdbpro_cli = $container->get(Cli::class);
        $wpmdbpro_cli->register();

        if (defined('WP_CLI') && WP_CLI) {
            \DeliciousBrains\WPMDB\Pro\Cli\Extra\Command::register();
        }

        add_filter('wpmdb_addon_registered_cli', '__return_true');
    }


    public function get_license_response_key()
    {
        return 'wp-migrate-db-pro-cli';
    }
}
