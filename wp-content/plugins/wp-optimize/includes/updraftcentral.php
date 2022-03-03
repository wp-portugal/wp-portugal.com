<?php

if (!defined('WPO_PLUGIN_MAIN_PATH')) die('No direct access allowed');

/**
 * This file is the bootstrapper for UpdraftCentral integration: i.e. it registers what is necessary to deal with commands in the wpoptimize namespace.
 */
class WP_Optimize_UpdraftCentral {

	public function __construct() {
		add_filter('updraftplus_remotecontrol_command_classes', array($this, 'updraftcentral_remotecontrol_command_classes'));
		add_filter('updraftcentral_remotecontrol_command_classes', array($this, 'updraftcentral_remotecontrol_command_classes'));
		add_action('updraftcentral_command_class_wanted', array($this, 'updraftcentral_command_class_wanted'));
	}
	
	/**
	 * Register our class
	 *
	 * @param string $command_classes Passing over an array of command classes.
	 */
	public function updraftcentral_remotecontrol_command_classes($command_classes) {
		if (is_array($command_classes)) $command_classes['wpoptimize'] = 'UpdraftCentral_WP_Optimize_Commands';
		return $command_classes;
	}
	
	/**
	 * Load the class when required
	 *
	 * @param string $command_php_class Passing over if there are any command classes.
	 */
	public function updraftcentral_command_class_wanted($command_php_class) {
		if ('UpdraftCentral_WP_Optimize_Commands' == $command_php_class) {
			// This fragment is only needed for compatibility with UD < 1.12.30 - thenceforth, the class can be assumed to exist.
			if (!class_exists('UpdraftCentral_Commands')) {
				include_once(apply_filters('updraftcentral_command_base_class_at', UPDRAFTPLUS_DIR.'/central/commands.php'));
			}
			include_once(WPO_PLUGIN_MAIN_PATH.'includes/class-updraftcentral-wp-optimize-commands.php');
		}
	}
}

new WP_Optimize_UpdraftCentral();
