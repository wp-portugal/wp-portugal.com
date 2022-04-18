<?php

namespace DeliciousBrains\WPMDB\Pro\Cli\Extra;

class Command extends \DeliciousBrains\WPMDB\Pro\Cli\Command
{
    public static function register()
    {
        \WP_CLI::add_command('wpmdb', 'DeliciousBrains\WPMDB\Pro\Cli\Extra\WPMDBCLI_Deprecated'); // deprecated older command
        \WP_CLI::add_command('migratedb', 'DeliciousBrains\WPMDB\Pro\Cli\Extra\Command');
        \WP_CLI::add_command('migrate', 'DeliciousBrains\WPMDB\Pro\Cli\Extra\Command');
    }

    /**
     * Run a find/replace on the database.
     *
     * ## OPTIONS
     *
     * [--find=<strings>]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     *     Table names should be quoted as needed, i.e. when using a comma in the
     *     find/replace string.
     *
     *     The --replace=<strings> argument should be used in conjunction to specify
     *     the replace values for the strings found using this argument. The number
     *     of strings specified in this argument should match the number passed into
     *     --replace=<strings> argument.
     *
     * [--replace=<strings>]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     *     Should be used in conjunction with the --find=<strings> argument, see it's
     *     documentation for further explanation of the find & replace functionality.
     *
     * [--regex-find]
     * : A regex pattern to match against when performing a string find
     * and replace across the database.
     *
     * [--regex-replace]
     * : A replace string that may contain references of the form \n or $n, with the latter
     * form being the preferred one. Every such reference will be replaced by the text captured by the n'th
     * parenthesized pattern used in the --regex-find pattern.
     *
     * [--case-sensitive-find]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     * [--case-sensitive-replace]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     * [--include-tables=<tables>]
     * : The comma separated list of tables to search. Excluding this parameter
     * will run a find & replace on all tables in your database that begin with your
     * installation's table prefix, e.g. wp_.
     *
     * [--backup=<prefix|selected|table_one,table_two,table_etc>]
     * : Perform a backup of the destination site's database tables before replacing it.
     *
     *     Accepted values:
     *
     *     * prefix - Backup only tables that begin with your installation's
     *                table prefix (e.g. wp_)
     *     * selected - Backup only tables selected for migration (as in --include-tables)
     *     * A comma separated list of the tables to backup.
     *
     * [--exclude-post-types=<post-types>]
     * : A comma separated list of post types to exclude from the find & replace.
     * Excluding this parameter will run a find & replace on all post types.
     *
     * [--skip-replace-guids]
     * : Do not perform a find & replace on the guid column in the wp_posts table.
     *
     * [--exclude-spam]
     * : Exclude spam comments.
     *
     * [--include-transients]
     * : Include transients (temporary cached data).
     *
     * [--subsite=<blog-id|subsite-url>]
     * : Run a find & replace on the given subsite. Requires the Multisite Tools addon.
     *
     * ## EXAMPLES
     *
     *     wp migratedb find-replace
     *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
     *        --replace=http://bradt.ca,/home/bradt.ca
     *        --include-tables=wp_posts,wp_postmeta
     *        --backup=selected
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @subcommand find-replace
     */
    public function find_replace($args, $assoc_args)
    {
        parent::find_replace($args, $assoc_args);
    }

    /**
     * Import an SQL file into the database.
     *
     * ## OPTIONS
     *
     * <import-file>
     * : The path of the SQL file to import.
     *
     * [--find=<strings>]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     *     Table names should be quoted as needed, i.e. when using a comma in the
     *     find/replace string.
     *
     *     The --replace=<strings> argument should be used in conjunction to specify
     *     the replace values for the strings found using this argument. The number
     *     of strings specified in this argument should match the number passed into
     *     --replace=<strings> argument.
     *
     * [--replace=<strings>]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     *     Should be used in conjunction with the --find=<strings> argument, see it's
     *     documentation for further explanation of the find & replace functionality.
     *
     * [--regex-find]
     * : A regex pattern to match against when performing a string find
     * and replace across the database.
     *
     * [--regex-replace]
     * : A replace string that may contain references of the form \n or $n, with the latter
     * form being the preferred one. Every such reference will be replaced by the text captured by the n'th
     * parenthesized pattern used in the --regex-find pattern.
     *
     * [--case-sensitive-find]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     * [--case-sensitive-replace]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     * [--backup=<prefix|selected|table_one,table_two,table_etc>]
     * : Perform a backup of the destination site's database tables before replacing it.
     *
     *     Accepted values:
     *
     *     * prefix - Backup only tables that begin with your installation's
     *                table prefix (e.g. wp_)
     *     * selected - Backup only tables selected for migration (as in --include-tables)
     *     * A comma separated list of the tables to backup.
     *
     * ## EXAMPLES
     *
     *     wp migratedb import ./migratedb.sql \
     *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
     *        --replace=http://bradt.ca,/home/bradt.ca
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function import($args, $assoc_args)
    {
        $assoc_args['action']      = 'import';
        $assoc_args['import-file'] = trim($args[0]);

        if (empty($assoc_args['import-file'])) {
            \WP_CLI::error(__('You must provide an import file.', 'wp-migrate-db-cli'));
        }

        $profile = $this->_get_profile_data_from_args($args, $assoc_args);

        if (is_wp_error($profile)) {
            \WP_CLI::error($profile);
        }

        $this->_perform_cli_migration($profile);
    }

    /**
     * Push local DB up to remote.
     *
     * ## OPTIONS
     *
     * <url>
     * : The URL of the remote site. Should include the URL encoded basic
     * authentication credentials (if required). e.g. http://user:password@example.com
     *
     *     Must include the WordPress directory if WordPress is stored in a subdirectory.
     *     e.g. http://example.com/wp
     *
     * <secret-key>
     * : The remote site's secret key.
     *
     * [--find=<strings>]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     *     Table names should be quoted as needed, i.e. when using a comma in the
     *     find/replace string.
     *
     *     The --replace=<strings> argument should be used in conjunction to specify
     *     the replace values for the strings found using this argument. The number
     *     of strings specified in this argument should match the number passed into
     *     --replace=<strings> argument.
     *
     *     If omitted, a set of 2 find and replace pairs will be performed by default:
     *
     *       1. Strings containing URLs referencing the source site will be replace
     *          by the destination URL.
     *
     *       2. Strings containing root file paths referencing the source site will
     *          be replaced by the destination root file path.
     *
     * [--replace=<strings>]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     *     Should be used in conjunction with the --find=<strings> argument, see it's
     *     documentation for further explanation of the find & replace functionality.
     *
     * [--regex-find]
     * : A regex pattern to match against when performing a string find
     * and replace across the database.
     *
     * [--regex-replace]
     * : A replace string that may contain references of the form \n or $n, with the latter
     * form being the preferred one. Every such reference will be replaced by the text captured by the n'th
     * parenthesized pattern used in the --regex-find pattern.
     *
     * [--case-sensitive-find]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     * [--case-sensitive-replace]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     * [--include-tables=<tables>]
     * : The comma separated list of tables to migrate. Excluding this parameter
     * will migrate all tables in your database that begin with your
     * installation's table prefix, e.g. wp_.
     *
     * [--exclude-database]
     * : Will not perform any table/database migration.
     *
     * [--exclude-post-types=<post-types>]
     * : A comma separated list of post types to exclude. Excluding this parameter
     * will migrate all post types.
     *
     * [--skip-replace-guids]
     * : Do not perform a find & replace on the guid column in the wp_posts table.
     *
     * [--exclude-spam]
     * : Exclude spam comments.
     *
     * [--preserve-active-plugins]
     * : Preserves the active_plugins option (which plugins are activated/deactivated).
     *
     * [--include-transients]
     * : Include transients (temporary cached data).
     *
     * [--backup=<prefix|selected|table_one,table_two,table_etc>]
     * : Perform a backup of the destination site's database tables before replacing it.
     *
     *     Accepted values:
     *
     *     * prefix - Backup only tables that begin with your installation's
     *                table prefix (e.g. wp_)
     *     * selected - Backup only tables selected for migration (as in --include-tables)
     *     * A comma separated list of the tables to backup.
     *
     * [--media=<all|since-date>]
     * : Perform a migration of the media files. Requires the Media Files addon.
     *
     *     Accepted values:
     *
     *     * all - Uploads all media files to the remote site.
     *     * since-date - Uploads all media files that have been added to the
     *                    local site since the provided. Use --media-date to pass a date.
     *
     * [--media-date=<Y-m-d>]
     * : The date to use for media migrations.
     *
     *     * Date must be in Y-m-d H:i:s format (i.e. '2020-01-28 09:10:00').
     *     * Time is optional.
     *     * Server timezone will be used.
     *
     * [--exclude-media=<string>]
     * : A comma-separated list of media files and folders that should be excluded
     * from the migration.
     *
     * [--subsite=<blog-id|subsite-url>]
     * : Push the given subsite to the remote single site install.
     * Requires the Multisite Tools addon.
     *
     * [--subsite-source=<blog-id|subsite-url>]
	 * : Push the given subsite to another subsite, used in conjunction with subsite-destination. Requires the Multisite Tools addon.
	 *
	 * [--subsite-destination=<blog-id|subsite-url>]
	 * : Push the given subsite to another subsite, used in conjunction with subsite-source. Requires the Multisite Tools addon.
     *
     * [--theme-files=<all|theme-one,theme-two,theme-etc>]
     * : Perform a migration of the theme files. Requires the Theme & Plugin files addon.
     *
     *     Accepted values:
     *
     *     * all - Uploads all themes to the remote site.
     *     * A comma separated list of themes to migrate. See `wp theme list` for a list
     *       of theme slugs.
     *
     * [--plugin-files=<all|plugin-one,plugin-two,plugin-etc>]
     * : Perform a migration of the plugin files. Requires the Theme & Plugin files addon.
     *
     *     Accepted values:
     *
     *     * all - Uploads all plugins to the remote site.
     *     * A comma separated list of plugins to migrate. See `wp plugin list` for a list
     *       of plugin slugs.
     *
     * [--exclude-theme-plugin-files=<string>]
     * : A comma-separated list of theme or plugin files and folders that should be excluded
     * from the migration.
     *
     * ## EXAMPLES
     *
     *     wp migratedb push http://bradt.ca LJPmq3t8h6uuN7aqQ3YSnt7C88Wzzv5BVPlgLbYE \
     *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
     *        --replace=http://bradt.ca,/home/bradt.ca
     *        --include-tables=wp_posts,wp_postmeta
     *        --media=all
     *        --theme-files=twentytwenty
     *        --plugin-files=hello-dolly,akismet
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @since 1.1
     */
    public function push($args, $assoc_args)
    {
        $assoc_args['action'] = 'push';
        $profile              = $this->_get_profile_data_from_args($args, $assoc_args);
        if (is_wp_error($profile)) {
            \WP_CLI::error(Cli::cleanup_message($profile->get_error_message()));
        }

        $this->_perform_cli_migration($profile);
    }

    /**
     * Pull remote DB down to local.
     *
     * ## OPTIONS
     *
     * <url>
     * : The URL of the remote site. Should include the URL encoded basic
     * authentication credentials (if required). e.g. http://user:password@example.com
     *
     *     Must include the WordPress directory if WordPress is stored in a subdirectory.
     *     e.g. http://example.com/wp
     *
     * <secret-key>
     * : The remote site's secret key.
     *
     * [--find=<strings>]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     *     Table names should be quoted as needed, i.e. when using a comma in the
     *     find/replace string.
     *
     *     The --replace=<strings> argument should be used in conjunction to specify
     *     the replace values for the strings found using this argument. The number
     *     of strings specified in this argument should match the number passed into
     *     --replace=<strings> argument.
     *
     *     If omitted, a set of 2 find and replace pairs will be performed by default:
     *
     *       1. Strings containing URLs referencing the source site will be replace
     *          by the destination URL.
     *
     *       2. Strings containing root file paths referencing the source site will
     *          be replaced by the destination root file path.
     *
     * [--replace=<strings>]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     *     Should be used in conjunction with the --find=<strings> argument, see it's
     *     documentation for further explanation of the find & replace functionality.
     *
     * [--regex-find]
     * : A regex pattern to match against when performing a string find
     * and replace across the database.
     *
     * [--regex-replace]
     * : A replace string that may contain references of the form \n or $n, with the latter
     * form being the preferred one. Every such reference will be replaced by the text captured by the n'th
     * parenthesized pattern used in the --regex-find pattern.
     *
     * [--case-sensitive-find]
     * : A comma separated list of strings to find when performing a string find
     * and replace across the database.
     *
     * [--case-sensitive-replace]
     * : A comma separated list of replace value strings to implement when
     * performing a string find & replace across the database.
     *
     * [--include-tables=<tables>]
     * : The comma separated list of tables to migrate. Excluding this parameter
     * will migrate all tables in your database that begin with your
     * installation's table prefix, e.g. wp_.
     *
     * [--exclude-database]
     * : Will not perform any table/database migration.
     *
     * [--exclude-post-types=<post-types>]
     * : A comma separated list of post types to exclude. Excluding this parameter
     * will migrate all post types.
     *
     * [--skip-replace-guids]
     * : Do not perform a find & replace on the guid column in the wp_posts table.
     *
     * [--exclude-spam]
     * : Exclude spam comments.
     *
     * [--preserve-active-plugins]
     * : Preserves the active_plugins option (which plugins are activated/deactivated).
     *
     * [--include-transients]
     * : Include transients (temporary cached data).
     *
     * [--backup=<prefix|selected|table_one,table_two,table_etc>]
     * : Perform a backup of the destination site's database tables before replacing it.
     *
     *     Accepted values:
     *
     *     * prefix - Backup only tables that begin with your installation's
     *                table prefix (e.g. wp_)
     *     * selected - Backup only tables selected for migration (as in --include-tables)
     *     * A comma separated list of the tables to backup.
     *
     * [--media=<all|since-date>]
     * : Perform a migration of the media files. Requires the Media Files addon.
     *
     *     Accepted values:
     *
     *     * all - Downloads all media files from the remote site.
     *     * since-date - Downloads all media files that have been added to the
     *                    remote site since the provided date. Use --media-date to pass a date.
     *
     * [--media-date=<Y-m-d>]
     * : The date to use for media migrations.
     *
     *     * Date must be in Y-m-d H:i:s format (i.e. '2020-01-28 09:10:00').
     *     * Time is optional.
     *     * Server timezone will be used.
     *
     * [--exclude-media=<string>]
     * : A comma-separated list of media files and folders that should be excluded
     * from the migration.
     *
     * [--subsite=<blog-id|subsite-url>]
     * : Pull the remote single site install into the given subsite.
     * Requires the Multisite Tools addon.
     *
     * [--subsite-source=<blog-id|subsite-url>]
	 * : Pull the given subsite to another subsite, used in conjunction with subsite-destination. Requires the Multisite Tools addon.
	 *
	 * [--subsite-destination=<blog-id|subsite-url>]
	 * : Pull the given subsite to another subsite, used in conjunction with subsite-source. Requires the Multisite Tools addon.
     *
     * [--theme-files=<all|theme-one,theme-two,theme-etc>]
     * : Perform a migration of the theme files. Requires the Theme & Plugin files addon.
     *
     *     Accepted values:
     *
     *     * all - Downloads all themes from the remote site.
     *     * A comma separated list of themes to migrate. See `wp theme list` for a list
     *       of theme slugs.
     *
     * [--plugin-files=<all|plugin-one,plugin-two,plugin-etc>]
     * : Perform a migration of the plugin files. Requires the Theme & Plugin files addon.
     *
     *     Accepted values:
     *
     *     * all - Downloads all plugins from the remote site.
     *     * A comma separated list of plugins to migrate. See `wp plugin list` for a list
     *       of plugin slugs.
     *
     * [--exclude-theme-plugin-files=<string>]
     * : A comma-separated list of theme or plugin files and folders that should be excluded
     * from the migration.
     *
     * ## EXAMPLES
     *
     *     wp migratedb pull http://bradt.ca LJPmq3t8h6uuN7aqQ3YSnt7C88Wzzv5BVPlgLbYE \
     *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
     *        --replace=http://bradt.ca,/home/bradt.ca
     *        --include-tables=wp_posts,wp_postmeta
     *        --media=all
     *        --theme-files=twentytwenty
     *        --plugin-files=hello-dolly,akismet
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @since 1.1
     */
    public function pull($args, $assoc_args)
    {
        $assoc_args['action'] = 'pull';

        $profile = $this->_get_profile_data_from_args($args, $assoc_args);
        if (is_wp_error($profile)) {
            \WP_CLI::error(Cli::cleanup_message($profile->get_error_message()));
        }

        $this->_perform_cli_migration($profile);
    }

    /**
     * Run a migration.
     *
     * ## OPTIONS
     *
     * <profile>
     * : ID of the profile to use for the migration.
     *
     * ## EXAMPLES
     *
     *  wp migratedb migrate 1
     *
     * @synopsis <profile>
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @since    1.0
     */
    public function migrate($args, $assoc_args)
    {
        $profile = $args[0];

        $this->_perform_cli_migration($profile, $assoc_args);
    }

    /**
     * Returns a list of profiles.
     *
     * @since 1.2.4
     */
    public function profiles()
    {
        $profiles = get_site_option('wpmdb_saved_profiles');

        // Display error if no profiles are present
        if (!is_array($profiles) || empty($profiles)) {
            \WP_CLI::error(__('There are no saved profiles for this site.', 'wp-migrate-db'));

            return;
        }

        // Get profile information in CLI format
        $cli_items = [];
        foreach ($profiles as $key => $profile) {
            $data = json_decode($profile['value'], true);

            if (!is_array($data)) {
                continue;
            }

            // Allow actions to be translated for output
            $action_strings = [
                'push'         => _x('push', 'Export data to remote database', 'wp-migrate-db'),
                'pull'         => _x('pull', 'Import data from remote database', 'wp-migrate-db'),
                'savefile'     => _x('export', 'Export file from local database', 'wp-migrate-db'),
                'find_replace' => _x('find & replace', 'Run a find & replace on local database', 'wp-migrate-db'),
                'import'       => _x('import', 'Import data from SQL file', 'wp-migrate-db'),
                'backup_local' => _x('backup', 'Backup the local database', 'wp-migrate-db'),
            ];

            $action = $data['current_migration']['intent'];

            if (isset($action_strings[$action])) {
                $profile['action'] = strtoupper($action_strings[$action]);
            } else {
                $profile['action'] = '---';
            }

            $remote = '---';
            if (in_array($action, ['push', 'pull'])) {
                $remote = isset($data['connection_info']) && isset($data['connection_info']['connection_state']) ? $data['connection_info']['connection_state']['url'] : '---';
            }

            //Populate CLI items with saved profile information
            $cli_items[] = [
                _x('ID', 'Profile list column heading', 'wp-migrate-db') => $key,
                _x('NAME', 'Profile list column heading', 'wp-migrate-db') => $profile['name'],
                _x('ACTION', 'Profile list column heading', 'wp-migrate-db') => $profile['action'],
                _x('REMOTE', 'Profile list column heading', 'wp-migrate-db') => $remote,
            ];
        }

        // Set CLI column headers. Must match `cli_items` keys
        $cli_keys = array_keys(reset($cli_items));
        \WP_CLI\Utils\format_items('table', $cli_items, $cli_keys);
    }

    /**
     * Run a migration.
     *
     * ## OPTIONS
     *
     * <profile>
     * : ID or name of the profile to use for the migration.
     *
     * [--import-file=<file>]
     * : The SQL file to import.
     *
     * ## EXAMPLES
     *
     *    wp migratedb profile 1
     *    wp migratedb profile 'Push to live'
     *
     * @synopsis <profile> [--import-file=<file>]
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @since    1.1
     */
    public function profile($args, $assoc_args)
    {
        // uses migrate method
        $this->migrate($args, $assoc_args);
    }

    // overrides _perform_cli_migration from WPMDB_Command
    protected function _perform_cli_migration($profile, $assoc_args = [])
    {
        global $wpmdbpro_cli;

        if (empty($wpmdbpro_cli)) {
            \WP_CLI::error(__('WP Migrate CLI class not available.', 'wp-migrate-db'));

            return;
        }

        $result = $wpmdbpro_cli->cli_migration($profile, $assoc_args);

        if (true === $result) {
            \WP_CLI::success(__('Migration successful.', 'wp-migrate-db'));
        } elseif (!is_wp_error($result)) {
            \WP_CLI::success(sprintf(__('Export saved to: %s', 'wp-migrate-db'), $result));
        } elseif (is_wp_error($result)) {
            \WP_CLI::error(Cli::cleanup_message($result->get_error_message()));
        }
    }
}

/**
 * Deprecated WP Migrate DB Pro command. Use migratedb instead.
 */
class WPMDBCLI_Deprecated extends Command
{
    /**
     * Run a migration.
     *
     * ## OPTIONS
     *
     * <profile>
     * : ID or name of the profile to use for the migration.
     *
     * ## EXAMPLES
     *
     *    wp wpmdb migrate 1
     *    wp wpmdb migrate 'Push to live'
     *
     * @synopsis <profile>
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @since    1.0
     */
    public function migrate($args, $assoc_args)
    {
        parent::migrate($args, $assoc_args);
    }
}
