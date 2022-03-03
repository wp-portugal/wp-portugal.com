<?php

use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDBCli\Cli;
use DeliciousBrains\WPMDBCli\CliAddon;

function wpmdb_setup_cli_addon()
{
	$container = WPMDBDI::getInstance();
	// Load pro classes
	$register_pro = new \DeliciousBrains\WPMDB\Pro\RegisterPro();

	$container->get(CliAddon::class)->register();
	$container->get(Cli::class)->register();

	load_plugin_textdomain('wp-migrate-db-pro-cli', false, dirname(plugin_basename(__FILE__)) . '/languages/');

	if (defined('WP_CLI') && WP_CLI) {
		\DeliciousBrains\WPMDBCli\Command::register();
	}
}

function wpmdb_get_cli_addon_instance()
{
	$cli_addon = WPMDBDI::getInstance()->get(Cli::class);
	return $cli_addon;
}
