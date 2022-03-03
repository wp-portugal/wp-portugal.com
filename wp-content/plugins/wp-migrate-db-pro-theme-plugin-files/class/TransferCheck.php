<?php

namespace DeliciousBrains\WPMDBTP;


use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Http;

class TransferCheck {

	/**
	 * @var FormData
	 */
	private $form_data;
	/**
	 * @var Http
	 */
	private $http;
	/**
	 * @var ErrorLog
	 */
	private $error_log;

	public function __construct(
		FormData $form_data,
		Http $http,
		ErrorLog $error_log
	) {
		$this->form_data = $form_data;
		$this->http      = $http;
		$this->error_log = $error_log;
	}

	/**
	 *
	 * Fires on `wpmdb_initiate_migration`
	 *
	 * @param $state_data
	 *
	 * @return null
	 */
	public function transfer_check( $state_data ) {
	    $message   = null;

		// ***+=== @TODO - revisit usage of parse_migration_form_data
		$form_data = $this->form_data->parse_and_save_migration_form_data($state_data['form_data'] );

        if (!in_array('theme_files', $form_data['current_migration']['stages']) && !in_array('plugin_files', $form_data['current_migration']['stages'])) {
			return;
		}

		if ( ! isset( $state_data['intent'] ) ) {
			$this->error_log->log_error( 'Unable to determine migration intent - $state_data[\'intent\'] empty' );

			return $this->http->end_ajax( json_encode( [
				'wpmdb_error' => 1,
				'body'        => __( 'A problem occurred starting the Theme & Plugin files migration.', 'wp-migrate-db' ),
			] ) );
		}

		$key                 = 'push' === $state_data['intent'] ? 'remote' : 'local';
		$site_details        = $state_data['site_details'][ $key ];
		$tmp_folder_writable = $site_details['local_tmp_folder_writable'];

		// $tmp_folder_writable is `null` if remote doesn't have T&P addon installed
		if ( false !== $tmp_folder_writable ) {
			return;
		}

		$tmp_folder_error_message = isset( $site_details['local_tmp_folder_check']['message'] ) ? $site_details['local_tmp_folder_check']['message'] : '';

		$error_message = __( 'Unfortunately it looks like we can\'t migrate your theme or plugin files. However, running a migration without theme and plugin files should work. Please uncheck the Theme Files checkbox, uncheck the Plugin Files checkbox, and try your migration again.', 'wp-migrate-db' );
		$link          = 'https://deliciousbrains.com/wp-migrate-db-pro/doc/theme-plugin-files-errors/';
		$more          = __( 'More Details »', 'wp-migrate-db' );

		$message = sprintf( '<p class="t-p-error">%s</p><p class="t-p-error">%s <a href="%s" target="_blank">%s</a></p>', $error_message, $tmp_folder_error_message, $link, $more );

		return $this->http->end_ajax( json_encode( [
			'wpmdb_error' => 1,
			'body'        => $message,
		] ) );
	}
}
