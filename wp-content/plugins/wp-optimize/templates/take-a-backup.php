<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

global $wpo_backup_initialized, $wpo_take_backup_checkbox_id;

$updraftplus_admin = !empty($GLOBALS['updraftplus_admin']) ? $GLOBALS['updraftplus_admin'] : null;
$updraftplus = !empty($GLOBALS['updraftplus']) ? $GLOBALS['updraftplus'] : null;

// Check if UpdraftPlus plugin status.
$updraftplus_status = $this->is_installed('UpdraftPlus - Backup/Restore');
$check_version = false;

// If UpdraftPlus Admin exists along with Method and active, then call the update modal.
if (is_a($updraftplus_admin, 'UpdraftPlus_Admin') && is_callable(array($updraftplus_admin, 'add_backup_scaffolding'))) {
	if (!$wpo_backup_initialized) {
		$updraftplus_admin->add_backup_scaffolding(__('Backup before running optimizations', 'wp-optimize'), array($updraftplus_admin, 'backupnow_modal_contents'));
	}
	$wpo_backup_initialized = true;
	$check_version = true;
} else {

	// When pulling this template from UDC-RPC it gives $updraftplus_admin as null and the above condition
	// will always result into $disabled_backup = 'disabled', making the backup checkbox on UDC unclickable,
	// this makes sense since we're not logging directly into the admin dashboard but through UDC. Since,
	// we're giving the user an option to make a backup using UDP before optimizing on UDC, therefore we
	// need a way to enable the checkbox (making it clickable), giving control to the user whether he or she
	// needs to backup before running the optimization process.
	//
	// Having to check whether UDP is installed and active is enough for UDC to run its local backup process
	// if the user wishes to backup before optimizing. Of course, when $updraftplus_admin is null we assumed
	// that the request is coming from UDC-RPC.
	if (null === $updraftplus_admin && true === $updraftplus_status['installed'] && true === $updraftplus_status['active']) {
		$disabled_backup = '';
		$check_version = true;

	} else {
		// Disabled UpdraftPlus.
		$disabled_backup = 'disabled';
	}
}

if (true === $check_version) {
	// Check version.
	if (version_compare($updraftplus->version, '1.12.33', '<')) {
		$disabled_backup = 'disabled';
		$updraftplus_version_check = true;
	} else {
		$disabled_backup = '';
		$updraftplus_version_check = false;
	}
}

$label_text = (isset($label) && '' !== $label) ? $label : __('Take a backup with UpdraftPlus before doing this', 'wp-optimize');

if (!isset($default_checkbox_value)) {
	$default_checkbox_value = 'false';
}

$option_value = $options->get_option($checkbox_name, $default_checkbox_value);

$is_checked = ('true' == $option_value);

?>
<p class="wpo-take-a-backup">
	<input class="enable-auto-backup" name="<?php echo $checkbox_name; ?>" id="<?php echo $checkbox_name; ?>" type="checkbox" value="true" <?php checked($is_checked);?> <?php echo $disabled_backup; ?> />
	<label for="<?php echo $checkbox_name; ?>"> <?php echo $label_text; ?> </label>

	<?php
	// UpdraftPlus is not installed.
	if ('disabled' == $disabled_backup && !$updraftplus_status['installed']) {
		echo '<small><a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=updraftplus'), 'install-plugin_updraftplus').'"> '.__('Follow this link to install UpdraftPlus, to take a backup before optimization', 'wp-optimize').' </a></small>';
	} else {
		// Build activate url.
		$activate_url = add_query_arg(array(
			'_wpnonce'    => wp_create_nonce('activate-plugin_updraftplus/updraftplus.php'),
			'action'      => 'activate',
			'plugin'      => 'updraftplus/updraftplus.php'
		), network_admin_url('plugins.php'));

		// If is network admin then add to link newtwork activation.
		if (is_network_admin()) {
			$activate_url = add_query_arg(array('networkwide' => 1), $activate_url);
		}

		// Check updraftplus version first.
		if (!empty($updraftplus_version_check)) {
			echo '<small>'.__('UpdraftPlus needs to be updated to 1.12.33 or higher in order to backup the database before optimization.', 'wp-optimize').' <a href="'.admin_url('update-core.php').'">'.__('Please update UpdraftPlus to the latest version.', 'wp-optimize').'</a></small>';
		} else {
			if ($updraftplus_status['installed'] && !$updraftplus_status['active']) {
				echo '<small><a href="'.$activate_url.'"> '.__('UpdraftPlus is installed but currently not active. Follow this link to activate UpdraftPlus, to take a backup before optimization.', 'wp-optimize').' </a></small>';
			}
		}
	}
	?>
</p>