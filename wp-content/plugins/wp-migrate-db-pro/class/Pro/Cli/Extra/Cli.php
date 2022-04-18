<?php

namespace DeliciousBrains\WPMDB\Pro\Cli\Extra;

use DeliciousBrains\WPMDB\Common\BackupExport;
use DeliciousBrains\WPMDB\Common\Cli\CliManager;
use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Migration\FinalizeMigration;
use DeliciousBrains\WPMDB\Common\Migration\InitiateMigration;
use DeliciousBrains\WPMDB\Common\Migration\MigrationManager;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Multisite;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Pro\Cli\Export;
use DeliciousBrains\WPMDB\Pro\Import;
use DeliciousBrains\WPMDB\Pro\Migration\Connection\Local;
use DeliciousBrains\WPMDB\Common\Migration\Flush;
use DeliciousBrains\WPMDB\WPMDBDI;
use DeliciousBrains\WPMDB\Pro\Migration\Connection;

class Cli extends Export
{
    /**
     * Instance of WPMDBPro.
     *
     * @var WPMDBPro
     */
    protected $wpmdbpro;

    /**
     * Delay Between Requests
     *
     * @var delay_between_requests
     */
    protected $delay_between_requests = 0;

    /**
     * Profile ID, if using a saved profile.
     *
     * @var int
     */
    protected $profileID = 0;

    /* remote connection info */
    protected $remote;
    protected $migration;
    /**
     * @var Local
     */
    private $connection;
    /**
     * @var BackupExport
     */
    private $backup_export;
    /**
     * @var Properties
     */
    private $properties;
    /**
     * @var Multisite
     */
    private $multisite;
    /**
     * @var Import
     */
    private $import;
    /**
     * @var Flush
     */
    private $flush;
    /**
     * @var DynamicProperties
     */
    private $dynamic_properties;

    public function __construct(
        FormData $form_data,
        Util $util,
        CliManager $cli_manager,
        Table $table,
        ErrorLog $error_log,
        InitiateMigration $initiate_migration,
        FinalizeMigration $finalize_migration,
        Helper $http_helper,
        MigrationManager $migration_manager,
        MigrationStateManager $migration_state_manager,
        Connection $connection,
        BackupExport $backup_export,
        Properties $properties,
        Multisite\Multisite $multisite,
        Import $import
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

        $this->backup_export      = $backup_export;
        $this->properties         = $properties;
        $this->multisite          = $multisite;
        $this->import             = $import;
        $this->dynamic_properties = DynamicProperties::getInstance();
    }

    public function register()
    {
        parent::register();

        $container        = WPMDBDI::getInstance();
        $this->connection = $container->get(Local::class);
        $this->flush      = $container->get(Flush::class);

        // extra profile fields
        add_filter('wpmdb_accepted_profile_fields', [$this, 'accepted_profile_fields']);

        // announce extra args
        add_filter('wpmdb_cli_filter_get_extra_args', [$this, 'filter_extra_args'], 10, 1);

        // process push/pull profile args
        add_filter('wpmdb_cli_filter_get_profile_data_from_args', [$this, 'add_extra_args_for_addon_migrations'], 10, 3);

        // add backup tables
        add_filter('wpmdb_cli_filter_before_migrate_tables', [$this, 'backup_before_migrate_tables'], 10, 1);

        // extend cli_migration with push/pull functionality
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'extend_cli_migration'], 10, 2);

        // extend get_tables to migrate with push/pull functionality
        add_filter('wpmdb_cli_tables_to_migrate', [$this, 'extend_tables_to_migrate'], 10, 1);

        //extend get_row_counts_from_table_list with remote tables if necessary
        add_filter('wpmdb_cli_get_row_counts_from_table_list', [$this, 'get_push_pull_row_counts'], 10, 2);

        // check for wpmdbpro version
        add_filter('wpmdb_cli_profile_before_migration', [$this, 'check_wpmdbpro_version_before_migration'], 10, 1);

        // enable profile migrations
        add_filter('wpmdb_cli_profile_before_migration', [$this, 'get_wpmdbpro_profile_before_migration'], 10, 1);

        // check for MF plugin locally
        add_filter('wpmdb_cli_profile_before_migration', [$this, 'check_local_wpmdbpro_media_files_before_migration'], 20, 1);

        // Add extra pull migration data
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'handle_pull_post_type_exclusion'], 20, 2);

        // check remote for MF plugin after remote connection has been made
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'check_remote_wpmdbpro_media_files_before_migration'], 20, 2);

        // check for MST plugin locally
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'check_local_wpmdbpro_mst_before_migration'], 20, 1);

        // check remote for MST plugin after remote connection has been made
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'check_remote_wpmdbpro_mst_before_migration'], 20, 2);

        // check remote for TPF plugin after remote connection has been made
        add_filter('wpmdb_cli_filter_before_cli_initiate_migration', [$this, 'check_remote_wpmdbpro_tpf_before_migration'], 20, 2);

        // flush rewrite rules
        add_filter('wpmdb_cli_finalize_migration_response', [$this, 'finalize_flush'], 20, 2);

        // add backup stage
        add_filter('wpmdb_cli_initiate_migration_args', [$this, 'initate_migration_enable_backup'], 10, 2);

        // use remote tables for pull migration
        add_filter('wpmdb_cli_filter_source_tables', [$this, 'set_remote_source_tables_for_pull'], 10, 2);

        // filter progress label for backup/migration
        add_filter('wpmdb_cli_progress_label', [$this, 'modify_progress_label'], 10, 2);

        // pass through pro filter including remote
        add_filter('wpmdb_cli_finalize_migration', [$this, 'apply_pro_cli_finalize_migration_filter'], 10, 0);

        // args for finalizing pro cli migrations
        add_filter('wpmdb_cli_finalize_migration_args', [$this, 'apply_pro_cli_finalize_migration_args'], 10, 3);

        // add delay between requests
        add_action('wpmdb_before_remote_post', [$this, 'do_delay_between_requests'], 10, 0);
        add_action('wpmdb_media_files_cli_before_migrate_media', [$this, 'do_delay_between_requests'], 10, 0);
        add_action('wpmdb_theme_plugin_files_cli_before_migrate_files', [$this, 'do_delay_between_requests'], 10, 0);

        // add import stage
        add_action('wpmdb_cli_during_cli_migration', [$this, 'cli_import'], 10, 2);
    }

    /**
     * Adds extra supported fields to the profile.
     *
     * @param $fields
     *
     * @return array
     */
    public function accepted_profile_fields($fields)
    {
        $fields[] = 'import_file';
        $fields[] = 'media_files';

        return $fields;
    }

    /**
     * Get profile by key.
     *
     * @since 1.1
     *
     * @param  int $key Profile key
     *
     * @return array|WP_Error If profile exists return array, otherwise WP_Error.
     */
    public function get_profile_by_key($key)
    {
        $profiles = get_site_option('wpmdb_saved_profiles');

        if (!isset($profiles[$key])) {
            return $this->cli_error(__('Profile ID not found.', 'wp-migrate-db'));
        }

        $this->profileID = $key;

        return $profiles[$this->profileID];
    }

    /**
     * Get profile by name.
     *
     * @param  string $name
     *
     * @return array|WP_Error
     */
    public function get_profile_by_name($name)
    {
        $profiles = get_site_option('wpmdb_saved_profiles', []);
        $names    = array_column($profiles, 'name');
        $counts   = array_count_values($names);

        if (1 < $counts[$name]) {
            return $this->cli_error(__('There is more than one profile with that name, please use the profile ID instead. See wp migratedb profiles for help.', 'wp-migrate-db'));
        }

        $key = array_search($name, $names);

        if (false !== $key) {
            $this->profileID = ++$key;

            return $profiles[$this->profileID];
        }

        return $this->cli_error(__('Profile not found.', 'wp-migrate-db'));
    }

    /**
     * Retrieve information from the remote machine, e.g. tables, prefix, bottleneck, gzip, etc
     *
     * @return array
     */
    public function verify_remote_connection($profile)
    {
        do_action('wpmdb_cli_before_verify_connection_to_remote_site', $profile);

        \WP_CLI::log(__('Verifying connection...', 'wp-migrate-db'));

        $connection_info            = preg_split('/\s+/', $profile['connection_info']);
        $remote_site_args           = $this->post_data;
        $remote_site_args['intent'] = $profile['action'];
        $remote_site_args['url']    = trim($connection_info[0]);
        $remote_site_args['key']    = trim($connection_info[1]);
        $this->post_data            = apply_filters('wpmdb_cli_verify_connection_to_remote_site_args', $remote_site_args, $profile);

        $response = $this->verify_connection_to_remote_site($this->post_data);

        $verified_response = $this->verify_cli_response($response, 'ajax_verify_connection_to_remote_site()');
        if (!is_wp_error($verified_response)) {
            $verified_response = apply_filters('wpmdbpro_cli_verify_connection_response', $verified_response);
        }

        return $verified_response;
    }

    /**
     * Stub for ajax_verify_connection_to_remote_site()
     *
     * @param array|bool $args
     *
     * @return array
     */
    public function verify_connection_to_remote_site($args = false)
    {
        $_POST    = $args;
        $response = $this->connection->ajax_verify_connection_to_remote_site();

        return $response;
    }

    /**
     * Stub for ajax_flush()
     *
     * @param array|bool $args
     *
     * @return bool|null
     */
    public function flush($args = false)
    {
        $_POST    = $args;
        $response = $this->flush->ajax_flush();

        return $response;
    }

    /**
     * Add extra CLI args used by this plugin.
     *
     * @param array $args
     *
     * @return array
     */
    public function filter_extra_args($args = [])
    {
        $args[] = 'preserve-active-plugins';
        $args[] = 'include-transients';
        $args[] = 'backup';
        $args[] = 'import-file';

        return $args;
    }

    /**
     * Extend get_profile_data_from_args with options for push/pull
     * hooks on: wpmdb_cli_filter_get_profile_data_from_args
     *
     * @param array $profile
     * @param array $args
     * @param array $assoc_args
     *
     * @return array|WP_Error
     */
    public function add_extra_args_for_addon_migrations($profile, $args, $assoc_args)
    {
        if (!is_array($profile)) {
            return $profile;
        }

        $import_file     = null;
        $connection_info = null;

        if (in_array($assoc_args['action'], ['push', 'pull'])) {
            if (empty($args[0]) || empty($args[1])) {
                return $this->cli_error(__('URL and secret-key are required', 'wp-migrate-db'));
            }
            $connection_info = sprintf('%s %s', $args[0], $args[1]);
        }

        if ('import' === $assoc_args['action']) {
            $import_file = $assoc_args['import-file'];
        }

        // --preserve-active-plugins
        $keep_active_plugins = intval(isset($assoc_args['preserve-active-plugins']));

        // --include-transients.
        $exclude_transients = intval(!isset($assoc_args['include-transients']));

        // --backup.
        $create_backup = 0;
        $backup_option = 'backup_only_with_prefix';
        $select_backup = [];
        if (!empty($assoc_args['backup'])) {
            $create_backup = 1;
            if (!in_array($assoc_args['backup'], ['prefix', 'selected'])) {
                $backup_option = 'backup_manual_select';
                $select_backup = explode(',', $assoc_args['backup']);
            } elseif ('selected' === $assoc_args['backup']) {
                $backup_option = 'backup_selected';
            }
        }

        $filtered_profile = compact(
            'connection_info',
            'exclude_transients',
            'keep_active_plugins',
            'create_backup',
            'backup_option',
            'select_backup',
            'import_file'
        );

        return array_merge($profile, $filtered_profile);
    }

    /**
     * Add backup stage when selected
     * hooks on: wpmdb_cli_filter_before_migrate_tables
     *
     * @param array $filter_vars
     *
     * @return array|WP_Error
     */
    public function backup_before_migrate_tables($filter_vars)
    {
        $this->post_data = $this->dynamic_properties->post_data;

        // No good reason this should happen, but lets not risk an undefined index warning
        if (!array_key_exists('tables', $filter_vars)) {
            return $filter_vars;
        }

        $tables = $filter_vars['tables'];

        if ('push' === $this->profile['action']) {
            $all_tables      = $this->remote['tables'];
            $prefixed_tables = $this->remote['prefixed_tables'];
        } else {
            $all_tables      = $this->table->get_tables();
            $prefixed_tables = $this->table->get_tables('prefix');
        }

        $tables_to_backup = $this->backup_export->get_tables_to_backup($this->profile, $prefixed_tables, $all_tables);
        if (
            'backup' == $this->post_data['stage'] &&
            'backup_manual_select' == $this->profile['backup_option'] &&
            array_diff($this->profile['select_backup'], $tables_to_backup)
        ) {
            return $this->cli_error(__('Invalid backup option or non-existent table selected for backup.', 'wp-migrate-db'));
        }

        $tables         = ('backup' == $this->post_data['stage']) ? $tables_to_backup : $tables;
        $stage_iterator = ('backup' == $this->post_data['stage']) ? 1 : 2;

        return compact('tables', 'stage_iterator');
    }

    /**
     * Extend cli_migration with push/pull
     * hooks on: wpmdb_cli_filter_before_cli_initiate_migration
     *
     * @param array $profile
     *
     * @return array
     */
    public function extend_cli_migration($profile, $post_data = [])
    {
        if (in_array($profile['action'], ['push', 'pull'])) {
            $this->remote = $this->verify_remote_connection($profile);
            if (is_wp_error($this->remote)) {
                return $this->remote;
            }

            if (!empty($post_data)) {
                $this->post_data = array_merge($this->post_data, $post_data);
            }

            $this->post_data['gzip']       = ('1' == $this->remote['gzip']) ? 1 : 0;
            $this->post_data['bottleneck'] = $this->remote['bottleneck'];
            $this->post_data['prefix']     = $this->remote['prefix'];

            $this->post_data['site_details']['remote'] = $this->remote['site_details'];

            // set delay between requests if remote has a delay
            if (isset($this->remote['delay_between_requests'])) {
                $this->delay_between_requests = $this->remote['delay_between_requests'];
            }

            if (!empty($this->remote['temp_prefix'])) {
                $this->post_data['temp_prefix'] = $this->remote['temp_prefix'];
            }

            // Default the find/replace pairs if nothing specified so that we don't break the target.
            $search_replace = $profile['search_replace'];
            if (
                !isset($search_replace['custom_search_replace']) ||
                empty($search_replace['custom_search_replace'])
            ) {
                $search_replace['standard_search_replace']  = $this->get_standard_search_replace_pairs($profile['action']);
                $search_replace['standard_search_visible']  = true;
                $search_replace['standard_options_enabled'] = ['domain', 'path'];
                $profile['search_replace']                  = $search_replace;

                $profile = apply_filters('wpmdb_cli_default_find_and_replace', $profile, $this->post_data);
            }
        }

        $this->dynamic_properties->post_data = $this->post_data;

        return $profile;
    }

    /**
     * Gets the default search/replace pairs in an array.
     *
     * @param string $action Whether we're pushing or pulling.
     *
     * @return array
     */
    public function get_standard_search_replace_pairs($action = 'push')
    {
        $local_url   = preg_replace('#^https?:#', '', Util::home_url());
        $local_path  = $this->util->get_absolute_root_file_path();
        $remote_url  = preg_replace('#^https?:#', '', $this->remote['url']);
        $remote_path = $this->remote['path'];
        $push        = 'push' === $action;

        return [
            'domain' => [
                'search'  => $push ? $local_url : $remote_url,
                'replace' => $push ? $remote_url : $local_url,
                'enabled' => true,
            ],
            'path' => [
                'search'  => $push ? $local_path : $remote_path,
                'replace' => $push ? $remote_path : $local_path,
                'enabled' => true,
            ],
        ];
    }

    /**
     * Return correct set of tables to migrate on push/pull migrations
     * hooks on: wpmdb_cli_tables_to_migrate
     *
     * @param array $tables_to_migrate
     *
     * @return array
     */
    public function extend_tables_to_migrate($tables_to_migrate)
    {
        if (null === $this->profile && !empty($this->dynamic_properties->profile)) {
            $this->profile = $this->dynamic_properties->profile;
        }

        if (empty($this->post_data) && !empty($this->dynamic_properties->post_data)) {
            $this->post_data = $this->dynamic_properties->post_data;
        }

        if ('push' == $this->profile['action']) {
            if ('migrate_only_with_prefix' == $this->profile['table_migrate_option']) {
                $tables_to_migrate = $this->table->get_tables('prefix');
            } elseif ('migrate_select' == $this->profile['table_migrate_option']) {
                $tables_to_migrate = array_intersect($this->profile['select_tables'], $this->table->get_tables());
            }
        } elseif ('pull' == $this->profile['action']) {
            if ('migrate_only_with_prefix' == $this->profile['table_migrate_option']) {
                $tables_to_migrate = $this->remote['prefixed_tables'];
            } elseif ('migrate_select' == $this->profile['table_migrate_option']) {
                $tables_to_migrate = array_intersect($this->profile['select_tables'], $this->remote['tables']);
            } else {
                $tables_to_migrate = $this->remote['prefixed_tables'];
            }
        } elseif ('import' === $this->profile['action'] && 'find_replace' === $this->post_data['stage']) {
            $temp_tables       = $this->table->get_tables('temp');
            $tables_to_migrate = [];

            if (isset($this->profile['select_tables']) && !empty($this->profile['select_tables'])) {
                $selected_tables = $this->profile['select_tables'];

                foreach ($selected_tables as $table) {
                    if (in_array($this->properties->temp_prefix . $table, $temp_tables)) {
                        $tables_to_migrate[] = $this->properties->temp_prefix . $table;
                    }
                }
            } else {
                $tables_to_migrate = $temp_tables;
            }
        }

        return $tables_to_migrate;
    }

    /**
     * Return correct row counts for stage/migration type
     * hooks on: wpmdb_cli_get_row_counts_from_table_list
     *
     * @param array $cached_stage_results
     * @param int   $stage
     *
     * @return array
     */
    public function get_push_pull_row_counts($cached_stage_results, $stage)
    {
        $migration_type    = $this->profile['action'];
        $local_table_rows  = $cached_stage_results;
        $remote_table_rows = isset($this->remote['table_rows']) ? $this->remote['table_rows'] : 0;

        if (1 === $stage) { // 1 = backup stage, 2 = migration stage
            $cached_stage_results = ('push' === $migration_type) ? $remote_table_rows : $local_table_rows;
        } else {
            $cached_stage_results = ('pull' === $migration_type) ? $remote_table_rows : $local_table_rows;
        }

        return $cached_stage_results;
    }

    /**
     * Error if WPMDBPro version is not compatible
     * hooks on: wpmdb_cli_profile_before_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function check_wpmdbpro_version_before_migration($profile)
    {
        // TODO: maybe instantiate WPMDBPro_CLI_Addon to make WPMDBPro_Addon::meets_version_requirements() available here
        $wpmdb_pro_version = $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'];
        if (!version_compare($wpmdb_pro_version, '1.8.3', '>=')) {
            return $this->cli_error(__('Please update WP Migrate.', 'wp-migrate-db'));
        }

        return $profile;
    }

    /**
     * Get profile by key
     * hooks on: wpmdb_cli_profile_before_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function get_wpmdbpro_profile_before_migration($profile)
    {
        if (is_wp_error($profile) || is_array($profile)) {
            return $profile;
        }

        if (empty($profile)) {
            return $this->cli_error(__('Profile ID missing.', 'wp-migrate-db'));
        }

        if (is_numeric($profile)) {
            $profile = $this->get_profile_by_key(absint($profile));
        } else {
            $profile = $this->get_profile_by_name($profile);
        }

        if (is_wp_error($profile)) {
            return $profile;
        }

        $imported = isset($profile['imported']);
        $profile  = json_decode($profile['value'], true);

        if ($imported) {
            $outdated = $this->maybe_show_outdated_profile_error($profile);
            if (is_wp_error($outdated)) {
                return $outdated;
            }
        }

        return $profile;
    }

    /**
     * Checks if the provided profile has outdated settings,
     * and returns an error if so.
     *
     * @param array $profile The profile to check.
     *
     * @return boolean|WP_Error
     */
    public function maybe_show_outdated_profile_error($profile)
    {
        $outdated          = false;
        $current_migration = isset($profile['current_migration']) ? $profile['current_migration'] : [];
        $intent            = $current_migration['intent'];

        if (isset($current_migration['post_types_option']) && 'all' !== $current_migration['post_types_option'] && in_array($intent, ['pull', 'import'])) {
            $outdated = true;
        }

        if (isset($profile['media_files'])) {
            if (isset($profile['media_files']['enabled']) && true === $profile['media_files']['enabled']) {
                $outdated = true;
            }
        }

        if ($outdated) {
            $path = is_multisite() ? 'settings.php' : 'tools.php';
            $url  = add_query_arg(['page' => 'wp-migrate-db-pro'], network_admin_url($path));

            $message = __('This profile is from an older version of WP Migrate and some settings have changed.', 'wp-migrate-db');
            $message .= "<br />";
            $message .= sprintf(
	            __('Please visit %s to update the profile.', 'wp-migrate-db'),
	            $url
            );

            $outdated = new \WP_Error(
                'wpmdb-outdated-profile',
                $message
            );
        }

        return $outdated;
    }

    /**
     * Check if MF option enabled in profile but plugin not active locally.
     * hooks on: wpmdb_cli_profile_before_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function check_local_wpmdbpro_media_files_before_migration($profile)
    {
        if (is_wp_error($profile)) {
            return $profile;
        }

        if (isset($profile['media_files']) && true === $profile['media_files']['enabled']) {
            if (false === class_exists('\DeliciousBrains\WPMDB\Pro\MF\MediaFilesAddon')) {
                return $this->cli_error(__('The profile is set to migrate media files, however migrating media files is not supported with the current license.', 'wp-migrate-db'));
            }
        }

        return $profile;
    }

    /**
     * Check if MF option enabled in profile but plugin not active on remote and that selected subsites make sense if being used.
     * hooks on: wpmdb_cli_filter_before_cli_initiate_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function check_remote_wpmdbpro_media_files_before_migration($profile)
    {
        if (is_wp_error($profile)) {
            return $profile;
        }

        if (isset($profile['media_files']) && true === $profile['media_files']['enabled']) {
            if (!isset($this->remote['media_files_max_file_uploads'])) {
                return $this->cli_error(__('The profile is set to migrate media files, however migrating media files is not supported with the current license of the remote site.', 'wp-migrate-db'));
            }
        }

        return $profile;
    }

    /**
     * Check this is migration needs MST but it isn't installed locally.
     * hooks on: wpmdb_cli_profile_before_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function check_local_wpmdbpro_mst_before_migration($profile)
    {
        if (is_wp_error($profile) || in_array($profile['action'], [
                'savefile',
                'find_replace',
                'import',
            ])) {
            return $profile;
        }

        $local_is_multisite  = is_multisite();
        $remote_is_multisite = 'true' === $this->remote['site_details']['is_multisite'];

        if ($local_is_multisite === $remote_is_multisite) {
            return $profile;
        }

        if (false === class_exists('\DeliciousBrains\WPMDB\Pro\MST\MultisiteToolsAddon')) {
            return $this->cli_error(__('The profile is set to migrate between a single site and a multisite, however this type of multisite migration is not supported with the current license.', 'wp-migrate-db'));
        }

        return $profile;
    }

    /**
     * Check if --subsite param is passed in the profile but the MST plugin is not installed/active on remote
     * hooks on: wpmdb_cli_filter_before_cli_initiate_migration
     *
     * @param $profile
     *
     * @return array|WP_Error
     */
    public function check_remote_wpmdbpro_mst_before_migration($profile)
    {

        if (is_wp_error($profile) || in_array($profile['action'], [
            'savefile',
            'find_replace',
            'import',
        ])) {
            return $profile;
        }

        $local_is_multisite    = is_multisite();
        $remote_is_multisite   = 'true' === $this->remote['site_details']['is_multisite'];
        $is_subsite_migration  = isset($profile['mst_select_subsite']) && true == $profile['mst_select_subsite'];
        $is_two_multisites = $local_is_multisite && $remote_is_multisite;
        // Two single site installs, nothing for us to do here.
        if (!$local_is_multisite && !$remote_is_multisite) {
            return $profile;
        }

        if ($is_two_multisites && !$is_subsite_migration) {
            return $profile;
        }

        // At this point we know that MST is needed.
        if (!isset($this->remote['mst_available'])) {
            return $this->cli_error(__('The profile is set to migrate a subsite, however subsite migrations are not supported with the current license of the remote site.', 'wp-migrate-db'));
        }

        // Validate remote subsite ID.
        if (!$local_is_multisite && $remote_is_multisite) {
            $profile['mst_selected_subsite'] = $this->multisite->get_subsite_id($profile['mst_selected_subsite'], $this->remote['site_details']['subsites']);
        }

        if (false === $profile['mst_selected_subsite']) {
            return $this->cli_error(__('A valid Blog ID or Subsite URL must be supplied to make use of the subsite option', 'wp-migrate-db'));
        }

        if ($is_two_multisites) {
            if(!$remote_is_multisite) {
                return $this->cli_error(__('The profile is set to perform a subsite to subsite migration, however the remote website is a single site installation.', 'wp-migrate-db-pro-cli'));
            }

            $remote_subsite = 'push' === $profile['action'] ? $profile['mst_destination_subsite'] : $profile['mst_selected_subsite'];
            if (!$this->multisite->get_subsite_id($remote_subsite, $this->remote['site_details']['subsites'])) {
                return $this->cli_error(__('A valid Blog ID or Subsite URL must be supplied to subsite-destination to make use of the subsite option', 'wp-migrate-db-pro-cli'));
            }
        }

        if (
            1 < $profile['mst_selected_subsite']
            && !preg_match('/\w+_\d_/', $profile['new_prefix']) // Prefix already set in saved profiles
            && (
                ('pull' === $profile['action'] && $local_is_multisite) ||
                ('push' === $profile['action'] && $remote_is_multisite))
        ) {
            // @TODO this changes a correct prefix when a pull profile is run
            $profile['new_prefix'] .= $profile['mst_selected_subsite'] . '_';
        }

        return $profile;
    }

    /**
     * Check if TPF options enabled in profile but plugin not active on remote and that selected themes/plugins make sense if being used.
     * hooks on: wpmdb_cli_filter_before_cli_initiate_migration
     *
     * @param array $profile
     *
     * @return array|WP_Error
     */
    public function check_remote_wpmdbpro_tpf_before_migration($profile)
    {
        if (is_wp_error($profile) || !in_array($profile['action'], ['push', 'pull'])) {
            return $profile;
        }

        if (!isset($profile['theme_plugin_files'])) {
            return $profile;
        }

        $theme_plugin_files = $profile['theme_plugin_files'];
        $tpf_enabled        = false;

        if (isset($theme_plugin_files['plugin_files']) && true === $theme_plugin_files['plugin_files']['enabled']) {
            $tpf_enabled = true;
        }

        if (isset($theme_plugin_files['theme_files']) && true === $theme_plugin_files['theme_files']['enabled']) {
            $tpf_enabled = true;
        }

        if ($tpf_enabled) {
            if (!isset($this->remote['theme_plugin_files_available'])) {
                return $this->cli_error(__('The profile is set to migrate theme or plugin files, however migrating theme and plugin files is not supported with the current license.', 'wp-migrate-db'));
            }

            $profile = apply_filters('wpmdb_cli_filter_tpf_profile_args', $profile, $this->remote);
        }

        return $profile;
    }

    /**
     * Flush rewrite rules
     * hooks on: wpmdb_cli_finalize_migration_response
     *
     * @param string $response
     *
     * @return string
     */
    public function finalize_flush($response, $post_data)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        \WP_CLI::log(_x('Flushing caches and rewrite rules...', 'The caches and rewrite rules for the target are being flushed', 'wp-migrate-db'));

        $response = $this->flush($post_data);

        return $this->verify_cli_response($response, 'finalize_flush()');
    }

    /**
     * Check profile for backup option and set stage appropriately
     * hooks on: wpmdb_cli_initiate_migration_args
     *
     * @param array $migration_args
     * @param array $profile
     *
     * @return array
     */
    public function initate_migration_enable_backup($migration_args, $profile)
    {
        if ('0' != $profile['create_backup']) {
            $migration_args['stage'] = 'backup';
        }

        return $migration_args;
    }

    /**
     * Use remote tables for pull migration
     * hooks on: wpmdb_cli_filter_source_tables
     *
     * @param $source_tables
     *
     * @return array
     */
    public function set_remote_source_tables_for_pull($source_tables, $profile)
    {
        if ('pull' == $profile['action']) {
            $source_tables = $this->remote['tables'];
        }

        return $source_tables;
    }

    /**
     * Update progress label for migrations / backups
     * hooks on: 'wpmdb_cli_progress_label
     *
     * @param string $progress_label
     * @param int    $stage
     *
     * @return string
     */
    public function modify_progress_label($progress_label, $stage)
    {
        if (!in_array($this->profile['action'], ['savefile', 'backup_local', 'find_replace'])) {
            if (1 === $stage) { // 1 = backup stage, 2 = migration stage
                $progress_label = __('Performing backup', 'wp-migrate-db');
            } else {
                $progress_label = __('Migrating tables', 'wp-migrate-db');

                if ('import' === $this->profile['action']) {
                    $progress_label = __('Running find & replace', 'wp-migrate-db');
                }
            }
        }

        return $progress_label;
    }

    /**
     * Apply pro only finalize migration filter
     * hooks on: wpmdb_cli_finalize_migration
     *
     * @return mixed
     */
    public function apply_pro_cli_finalize_migration_filter()
    {
        return apply_filters('wpmdb_pro_cli_finalize_migration', true, $this->profile, $this->remote, $this->post_data);
    }

    /**
     * Apply args for pro cli finalize migration
     * hooks on: wpmdb_cli_finalize_migration_args
     */
    public function apply_pro_cli_finalize_migration_args($post_data, $profile, $migration)
    {
        if (0 !== $this->profileID) {
            $post_data['profileID']   = $this->profileID;
            $post_data['profileType'] = 'saved';
        }

        return $post_data;
    }

    public function do_delay_between_requests()
    {
        if (0 < $this->delay_between_requests) {
            sleep($this->delay_between_requests);
        }
    }

    /**
     * Imports a file to the database.
     *
     * @param $post_data
     * @param $profile
     */
    public function cli_import($post_data, $profile)
    {
        if ('import' !== $this->profile['action']) {
            return;
        }

        $file     = $this->profile['import_file'];
        $importer = $this->import;

        if ($importer->file_is_gzipped($file)) {
            $file = $importer->decompress_file($this->profile['import_file']);
        }

        $chunk      = 0;
        $num_chunks = $importer->get_num_chunks_in_file($file);

        if (is_wp_error($num_chunks)) {
            $error = $num_chunks->get_error_message();
            $this->error_log->log_error($error);
            \WP_CLI::error($error);
        }

        $file_object                    = $importer->get_file_object($file);
        $file_header                    = $file_object->fread(2000);
        $this->post_data['import_info'] = $importer->parse_file_header($file_header);

        $current_query = '';
        $progress      = \WP_CLI\Utils\make_progress_bar(__('Importing file', 'wp-migrate-db'), $num_chunks);

        while ($num_chunks > $chunk) {
            $import = $importer->import_chunk($file, $chunk, $current_query);

            if (is_wp_error($import)) {
                $error = $import->get_error_message();
                $this->error_log->log_error($error);
                \WP_CLI::error($error);
            }

            $current_query = $import['current_query'];

            $chunk++;

            $progress->tick();
        }

        $progress->finish();

        $search_replace = $this->profile['search_replace'];

        if (isset($search_replace['custom_search_replace']) && !empty($search_replace['custom_search_replace'])) {
            $this->post_data['stage'] = 'find_replace';
            $this->migrate_tables();
        }
    }

    public function handle_pull_post_type_exclusion($profile, $post_data)
    {
        if ($profile['action'] === 'pull' && !empty($profile['current_migration']['cli_exclude_post_types'])) {
            $profile['current_migration']['post_types_selected'] = array_merge($profile['current_migration']['post_types_selected'], array_values(array_diff($this->remote['post_types'], $profile['current_migration']['cli_exclude_post_types'])));
        }

        return $profile;
    }
}
