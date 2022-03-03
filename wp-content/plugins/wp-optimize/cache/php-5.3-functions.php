<?php

/**
 * Get path to wp-config.php when called from WP-CLI.
 *
 * @return string
 */
function wpo_wp_cli_locate_wp_config() {
	$config_path = '';

	if (is_callable('\WP_CLI\Utils\locate_wp_config')) {
		// phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ns_separatorFound
		$config_path = \WP_CLI\Utils\locate_wp_config();
	}

	return $config_path;
}
