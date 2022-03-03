<?php

namespace DeliciousBrains\WPMDB\Pro\Cli;

use DeliciousBrains\WPMDB\Common\Cli\Cli;
use DeliciousBrains\WPMDB\Common\Cli\CliManager;
use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Migration\FinalizeMigration;
use DeliciousBrains\WPMDB\Common\Migration\InitiateMigration;
use DeliciousBrains\WPMDB\Common\Migration\MigrationManager;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Profile\ProfileImporter;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;

class Export extends Cli
{

	function __construct(
		FormData $form_data,
		Util $util,
		CliManager $cli_manager,
		Table $table,
		ErrorLog $error_log,
		InitiateMigration $initiate_migration,
		FinalizeMigration $finalize_migration,
		Helper $http_helper,
		MigrationManager $migration_manager,
		MigrationStateManager $migration_state_manager
	) {
		parent::__construct(
			$form_data,
			$util,
			$cli_manager,
			$table,
			$error_log,
			$initiate_migration,
			$finalize_migration,
			$http_helper,
			$migration_manager,
			$migration_state_manager
		);
	}

	public function register()
	{
		parent::register();
		// add support for extra args
		add_filter('wpmdb_cli_filter_get_extra_args', array($this, 'filter_extra_args_cli_export'), 10, 1);
		add_filter('wpmdb_cli_filter_get_profile_data_from_args', array($this, 'add_extra_args_for_pro_export'), 10, 3);

		// extend get_tables_to_migrate with migrate_select
		add_filter('wpmdb_cli_tables_to_migrate', array($this, 'tables_to_migrate_include_select'), 10, 3);
	}

	/**
	 * Add extra CLI args used by this plugin.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_extra_args_cli_export($args = array())
	{
		$args[] = 'include-tables';
		$args[] = 'exclude-post-types';
		$args[] = 'include-tables';
		$args[] = 'regex-find';
		$args[] = 'regex-replace';

		return $args;
	}

	/**
	 * Add support for extra args in export
	 *
	 * @param array $profile
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return array
	 */
	function add_extra_args_for_pro_export($profile, $args, $assoc_args)
	{
		if (!is_array($profile)) {
			return $profile;
		}

		// --include-tables=<tables>
		if (!empty($assoc_args['include-tables'])) {
			$table_migrate_option = 'migrate_select';
			$select_tables        = explode(',', $assoc_args['include-tables']);
		} else {
			$select_tables        = array();
			$table_migrate_option = 'migrate_only_with_prefix';
		}

		// --exclude-post-types=<post-types>
		$exclude_post_types = '0';
		$select_post_types  = array();
		if (!empty($assoc_args['exclude-post-types'])) {
			$exclude_post_types = '1';
			$all_post_types     = $this->table->get_post_types();
			$select_post_types  = explode(',', $assoc_args['exclude-post-types']);
		}

		$filtered_profile = compact(
			'table_migrate_option',
			'exclude_post_types',
			'select_post_types',
			'select_tables'
		);

		return array_merge($profile, $filtered_profile);
	}

	/**
	 * Use tables from --include-tables assoc arg if available
	 *
	 * @param array $tables_to_migrate
	 * @param array $profile
	 * @param array $migration
	 *
	 * @return array
	 */
	function tables_to_migrate_include_select($tables_to_migrate, $profile, $migration)
	{
		if (isset($profile['table_migrate_option']) && in_array($profile['action'], array('find_replace', 'savefile'))) {
			if ('migrate_select' === $profile['table_migrate_option'] && !empty($profile['select_tables'])) {
				$tables_to_migrate = array_intersect($profile['select_tables'], $this->table->get_tables());
			}
		}

		return $tables_to_migrate;
	}
}
