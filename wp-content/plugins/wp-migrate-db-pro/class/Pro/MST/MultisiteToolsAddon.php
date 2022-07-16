<?php

namespace DeliciousBrains\WPMDB\Pro\MST;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Multisite\Multisite;
use DeliciousBrains\WPMDB\Common\Profile\ProfileManager;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Sql\TableHelper;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Pro\Addon\Addon;
use DeliciousBrains\WPMDB\Pro\Addon\AddonAbstract;
use DeliciousBrains\WPMDB\Pro\UI\Template;
use DeliciousBrains\WPMDB\WPMDBDI;

class MultisiteToolsAddon extends AddonAbstract
{

    protected $accepted_fields;
    /**
     * @var Multisite
     */
    protected $multisite;
    /**
     * @var Util
     */
    protected $util;
    /**
     * @var MigrationStateManager
     */
    protected $migration_state_manager;
    /**
     * @var Table
     */
    protected $table;
    /**
     * @var TableHelper
     */
    protected $table_helper;
    /**
     * @var array
     */
    protected $form_data;

    public $state_data;
    /**
     * @var Template
     */
    protected $template;
    protected $plugin_dir_path;
    protected $plugin_folder_name;
    protected $template_path;
    /**
     * @var ProfileManager
     */
    protected $profile_manager;
    /**
     * @var FormData
     */
    private $form_data_class;

    protected $blog_id;
    private $container;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var MediaFilesCompat
     */
    private $media_files_compat;

    public $selected_subsite_id;

    public function __construct(
        Addon $addon,
        Properties $properties,
        Multisite $multisite,
        Util $util,
        MigrationStateManager $migration_state_manager,
        Table $table,
        TableHelper $table_helper,
        FormData $form_data_class,
        Template $template,
        ProfileManager $profile_manager,
        DynamicProperties $dynamic_properties,
        Filesystem $filesystem,
        MediaFilesCompat $media_files_compat
    ) {
        parent::__construct($addon, $properties);

        $this->plugin_slug        = $properties->plugin_slug;
        $this->plugin_version     = $properties->plugin_version;
        $this->plugin_dir_path    = __DIR__;
        $this->plugin_folder_name = $properties->plugin_folder_name;
        $this->template_path = $this->plugin_dir_path . 'template/';

        $this->accepted_fields = array(
            'multisite_subsite_export', // TODO: Remove backwards compatibility for CLI once Core/MST/CLI dependencies updated.
            'select_subsite', // TODO: Remove backwards compatibility for CLI once Core/MST/CLI dependencies updated.
            'mst_select_subsite',
            'mst_selected_subsite',
            'mst_destination_subsite',
            'mst_subsite_to_subsite',
            'multisite_tools',
            'new_prefix',
            'keep_active_plugins',
        );

        $this->multisite               = $multisite;
        $this->util                    = $util;
        $this->migration_state_manager = $migration_state_manager;
        $this->table                   = $table;
        $this->table_helper            = $table_helper;
        $this->form_data_class         = $form_data_class;
        $this->template                = $template;
        $this->profile_manager         = $profile_manager;
        $this->dynamic_properties      = $dynamic_properties;
        $this->filesystem              = $filesystem;
        $this->media_files_compat      = $media_files_compat;
        $this->selected_subsite_id     = null;
    }

    public function register()
    {

        $this->addon_name = $this->addon->get_plugin_name('wp-migrate-db-pro-multisite-tools/wp-migrate-db-pro-multisite-tools.php');

        add_action('wpmdb_load_assets', array($this, 'load_assets'));
        add_filter('wpmdb_accepted_profile_fields', array($this, 'accepted_profile_fields'));
        add_filter('wpmdb_establish_remote_connection_data', array($this, 'establish_remote_connection_data'));
        add_filter('wpmdb_data', array($this, 'js_variables'));
        add_filter('wpmdb_initiate_key_rules', array($this, 'filter_key_rules'), 10, 2);
        add_filter('wpmdb_verify_connection_key_rules', array($this, 'filter_key_rules'), 10, 2);
        add_filter('wpmdb_respond_to_verify_connection_key_rules', array($this, 'filter_key_rules'), 10, 2);
        add_filter('wpmdb_initiate_push_pull_post', array($this, 'filter_initiate_post_data'), 10, 2);

        add_filter('wpmdb_diagnostic_info', array($this, 'diagnostic_info'));
        add_filter('wpmdb_exclude_table', array($this, 'filter_table_for_subsite'), 10, 2);
        add_filter('wpmdb_tables', array($this, 'filter_tables_for_subsite'), 10, 2);
        add_filter('wpmdb_table_sizes', array($this, 'filter_table_sizes_for_subsite'), 10, 2);
        add_filter('wpmdb_target_table_name', array($this, 'filter_target_table_name'), 10, 4);
        add_filter('wpmdb_table_row', array($this, 'filter_table_row'), 10, 5);
        add_filter('wpmdb_find_and_replace', array($this, 'filter_find_and_replace'), 10, 3);
        add_filter('wpmdb_finalize_target_table_name', array($this, 'filter_finalize_target_table_name'), 10, 2);
        add_filter('wpmdb_preserved_options', array($this, 'filter_preserved_options'), 10, 2);
        add_filter('wpmdb_preserved_options_data', array($this, 'filter_preserved_options_data'), 10, 2);
        add_filter('wpmdb_get_alter_queries', array($this, 'filter_get_alter_queries'), 10, 2);
        add_filter('wpmdb_replace_site_urls', array($this, 'filter_replace_site_urls'));
        add_filter('wpmdb_backup_header_url', array($this, 'filter_backup_header_url'));
        add_filter('wpmdb_backup_header_included_tables', array($this, 'filter_backup_header_tables'));
        add_filter('wpmdb_backup_header_is_subsite_export', array($this, 'filter_backup_header_is_subsite_export'));

        /**
         * Media Files Hooks
         */
        add_filter('wpmdb_mf_local_uploads_folder', [$this->media_files_compat, 'filter_uploads_path_local'], 10, 2);
        add_filter('wpmdb_mf_remote_uploads_folder', [$this->media_files_compat, 'filter_uploads_path_remote'], 10, 2);
        add_filter('wpmdb_mf_destination_file', [$this->media_files_compat, 'filter_media_destination'], 10, 2);
        add_filter('wpmdb_mf_destination_uploads', [$this->media_files_compat, 'filter_media_uploads'], 10, 2);
        add_filter('wpmdb_mf_media_upload_path', [$this->media_files_compat, 'filter_media_uploads'], 10, 2);
        add_filter('wpmdb_mf_excludes', [$this->media_files_compat, 'filter_media_excludes'], 10, 2);

        $this->container = WPMDBDI::getInstance();
    }

    /**
     * Allow MST params to be passed to certain ajax endpoints.
     *
     * @param array  $rules
     * @param string $context
     *
     * @return mixed
     */
    public function filter_key_rules($rules, $context)
    {
        switch ($context) {
            case 'ajax_verify_connection_to_remote_site':
            case 'wpmdb_verify_connection_key_rules':
            case 'ajax_initiate_migration':
            case 'wpmdb_remote_initiate_migration':
            case 'respond_to_verify_connection_to_remote_site':
            case 'respond_to_remote_initiate_migration':
                $rules['mst_select_subsite']      = 'string';
                $rules['mst_selected_subsite']    = 'int';
                $rules['mst_destination_subsite'] = 'int';
                $rules['new_prefix']              = 'string';
        }

        return $rules;
    }

    public function filter_initiate_post_data($postData, $state_data)
    {
        if (empty($postData) || $postData['action'] !== 'wpmdb_remote_initiate_migration') {
            return $postData;
        }

        if (isset($state_data['mst_select_subsite'])) {
            $postData['mst_select_subsite'] = (string)$state_data['mst_select_subsite'];
        }

        if (isset($state_data['mst_selected_subsite'])) {
            $postData['mst_selected_subsite'] = $state_data['mst_selected_subsite'];
        }

        if (isset($state_data['mst_destination_subsite'])) {
            $postData['mst_destination_subsite'] = $state_data['mst_destination_subsite'];
        }

        if (isset($state_data['new_prefix'])) {
            $postData['new_prefix'] = $state_data['new_prefix'];
        }

        return $postData;
    }

    /**
     * Does the given user need to be migrated?
     *
     * @param int $user_id
     * @param int $blog_id Optional.
     *
     * @return bool
     */
    protected function is_user_required_for_blog($user_id, $blog_id = 0)
    {
        static $users = array();

        if (empty($user_id)) {
            $user_id = 0;
        }

        if (empty($blog_id)) {
            $blog_id = 0;
        }

        if (isset($users[$blog_id][$user_id])) {
            return $users[$blog_id][$user_id];
        }

        if (!is_multisite()) {
            $users[$blog_id][$user_id] = true;

            return $users[$blog_id][$user_id];
        }

        $subsites = $this->util->subsites_list();

        if (empty($subsites) || !array_key_exists($blog_id, $subsites)) {
            $users[$blog_id][$user_id] = false;

            return $users[$blog_id][$user_id];
        }

        if (is_user_member_of_blog($user_id, $blog_id)) {
            $users[$blog_id][$user_id] = true;

            return $users[$blog_id][$user_id];
        }

        // If the user has any posts that are going to be migrated, we need the user regardless of whether they still have access.
        switch_to_blog($blog_id);
        $user_posts = count_user_posts($user_id);
        restore_current_blog();

        if (0 < $user_posts) {
            $users[$blog_id][$user_id] = true;

            return $users[$blog_id][$user_id];
        }

        // If here, user not required.
        $users[$blog_id][$user_id] = false;

        return $users[$blog_id][$user_id];
    }

    /**
     * @param array|false $state_data
     *
     * @return int Will return 0 if not doing MST migration.
     *
     * Will return 0 if not doing MST migration.
     * @return int
     */
    public function selected_subsite($state_data = false)
    {
        $blog_id = 0;

        if ($this->selected_subsite_id) {
            return $this->selected_subsite_id;
        }

        if (!$state_data) {
            $state_data = filter_var_array($_POST, FILTER_SANITIZE_STRING);
        }

        if (empty($state_data)) {
            return 0;
        }

        $select_subsite   = isset($state_data['mst_select_subsite']) ? $state_data['mst_select_subsite'] : false;
        $selected_subsite = isset($state_data['mst_selected_subsite']) ? (int)$state_data['mst_selected_subsite'] : false;

        // During a migration, this is where the subsite's id will be derived.
        if (empty($blog_id) &&
            !empty($select_subsite) &&
            !empty($selected_subsite) &&
            is_numeric($selected_subsite)
        ) {
            $blog_id = $selected_subsite;
        }

        // When loading a saved migration profile, this is where the subsite's id will be derived.
        // @TODO refactor this, we're not using $loaded_profile any more
        global $loaded_profile;

        if (empty($blog_id) &&
            !empty($loaded_profile['mst_select_subsite']) &&
            !empty($loaded_profile['mst_selected_subsite']) &&
            is_numeric($loaded_profile['mst_selected_subsite'])
        ) {
            $blog_id = $loaded_profile['mst_selected_subsite'];
        }

        // Early in a push or pull migration selected subsite might just be injected in ajax params.
        if (empty($blog_id) &&
            !empty($this->state_data['mst_select_subsite']) &&
            !empty($this->state_data['mst_selected_subsite'])
        ) {
            $blog_id = $this->multisite->get_subsite_id($this->state_data['mst_selected_subsite']);
        }

        // If on multisite we can check that selected blog exists as all scenarios would require it.
        if (1 < $blog_id && is_multisite() && !$this->subsite_exists($blog_id)) {
            $blog_id = 0;
        }

        if ($blog_id === 0) {
            return 0;
        }

        $this->selected_subsite_id = $blog_id;

        return $blog_id;
    }

    /**
     * Whitelist multisite tools setting fields for use in AJAX save in core
     *
     * @param array $profile_fields
     *
     * @return array
     */
    public function accepted_profile_fields($profile_fields)
    {
        return array_merge($profile_fields, $this->accepted_fields);
    }

    /**
     * Check the remote site has the multisite tools addon setup
     *
     * @param array $data Connection data
     *
     * @return array Updated connection data
     */
    public function establish_remote_connection_data($data)
    {
        $data['mst_available'] = '1';
        $data['mst_version']   = $this->plugin_version;

        return $data;
    }

    /**
     * Add multisite tools related javascript variables to the page
     *
     * @param array $data
     *
     * @return array
     */
    public function js_variables($data)
    {
        global $loaded_profile;

        $data['mst_version'] = $this->plugin_version;

        // Track originally selected subsite.
        if (empty($loaded_profile) && !empty($data['profile']) && is_numeric($data['profile'])) {
            $loaded_profile = $this->profile_manager->get_profile($data['profile']);
        }

        if (!empty($loaded_profile['mst_select_subsite']) &&
            !empty($loaded_profile['mst_selected_subsite']) &&
            is_numeric($loaded_profile['mst_selected_subsite'])
        ) {
            $data['mst_selected_subsite'] = (int)$loaded_profile['mst_selected_subsite'];
        }

        return $data;
    }

    /**
     * Get translated strings for javascript and other functions.
     *
     * @return array
     */
    public function get_strings()
    {
        static $strings;

        if (!empty($strings)) {
            return $strings;
        }

        $strings = array(
            'new_prefix_contents' => __('Please only enter letters, numbers or underscores for the new table prefix.', 'wp-migrate-db'),
        );

        return $strings;
    }

    /**
     * Retrieve a specific translated string.
     *
     * @param string $key
     *
     * @return string
     */
    public function get_string($key)
    {
        $strings = $this->get_strings();

        return (isset($strings[$key])) ? $strings[$key] : '';
    }

    /**
     * Load multisite tools related assets in core plugin.
     */
    public function load_assets()
    {
        $plugins_url = trailingslashit(plugins_url()) . trailingslashit($this->plugin_folder_name);

        $version     = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? time() : $this->plugin_version;


        $src = $plugins_url . "frontend/public/noop.js";
        wp_enqueue_script(
            'wp-migrate-db-pro-multisite-tools-script',
            $src,
            array(
                'jquery',
                'wp-migrate-db-pro-script-v2',
            ),
            $version,
            true
        );

        wp_localize_script('wp-migrate-db-pro-multisite-tools-script', 'wpmdbmst_strings', $this->get_strings());
        wp_localize_script(
            'wp-migrate-db-pro-multisite-tools-script',
            'wpmdbmst',
            [
                'enabled' => true,
            ]
        );
    }

    /**
     * Adds extra information to the core plugin's diagnostic info
     */
    public function diagnostic_info($diagnostic_info)
    {
        if (is_multisite()) {
            $diagnostic_info['multisite-tools'] = array('Sites' => number_format(get_blog_count()));
        }

        return $diagnostic_info;
    }

    /**
     * Should the given table be excluded from a subsite migration.
     *
     * @param bool   $exclude
     * @param string $table_name
     *
     * @return bool
     */
    public function filter_table_for_subsite($exclude, $table_name)
    {
        if (!is_multisite()) {
            return $exclude;
        }

        $blog_id = $this->selected_subsite();

        if (0 < $blog_id) {
            // wp_users and wp_usermeta are relevant to all sites, shortcut out.
            if ($this->table_helper->table_is('', $table_name, 'non_ms_global')) {
                return $exclude;
            }

            // Following tables are Multisite setup tables and can be excluded from migration.
            if ($this->table_helper->table_is('', $table_name, 'ms_global')) {
                return true;
            }

            global $wpdb;
            $prefix         = $wpdb->base_prefix;
            $prefix_escaped = preg_quote($prefix);

            if (1 == $blog_id) {
                // Exclude tables from non-primary subsites.
                if (preg_match('/^' . $prefix_escaped . '([0-9]+)_/', $table_name, $matches)) {
                    $exclude = true;
                }
            } else {
                $prefix .= $blog_id . '_';
                if (0 !== stripos($table_name, $prefix)) {
                    $exclude = true;
                }
            }
        }

        return $exclude;
    }

    /**
     * Filter the given tables if doing a subsite migration.
     *
     * @param array  $tables
     * @param string $scope
     *
     * @return array
     */
    public function filter_tables_for_subsite($tables, $scope = 'regular')
    {
        if (!is_multisite() || empty($tables)) {
            return $tables;
        }

        // We will not alter backup or temp tables list.
        if (in_array($scope, array('backup', 'temp'))) {
            return $tables;
        }

        $filtered_tables = array();
        $blog_id         = $this->selected_subsite();

        if (0 < $blog_id) {
            foreach ($tables as $key => $value) {
                if (false === $this->filter_table_for_subsite(false, $value)) {
                    $filtered_tables[$key] = $value;
                }
            }
        } else {
            $filtered_tables = $tables;
        }

        return $filtered_tables;
    }

    /**
     * Filter the given tables with sizes if doing a subsite migration.
     *
     * @param array  $table_sizes
     * @param string $scope
     *
     * @return array
     */
    public function filter_table_sizes_for_subsite($table_sizes, $scope = 'regular')
    {
        if (!is_multisite() || empty($table_sizes)) {
            return $table_sizes;
        }

        $tables = $this->filter_tables_for_subsite(array_keys($table_sizes), $scope);

        return array_intersect_key($table_sizes, array_flip($tables));
    }

    /**
     * Change the name of the given table if subsite selected and migration profile has new prefix.
     *
     * @param string $table_name
     * @param string $action
     * @param string $stage
     * @param array  $site_details
     *
     * @return string
     */
    public function filter_target_table_name($table_name, $state_data, $site_details = array(), $subsite_migration = false)
    {
        if( !$subsite_migration ){
            return $table_name;
        }
        $stage  = $state_data['stage'];
        $action = $state_data['intent'];

        $blog_id = $this->selected_subsite($state_data);

        $destination_subsite = isset($state_data['mst_destination_subsite']) ? $state_data['mst_destination_subsite'] : 0;
        if (0 === $destination_subsite && 1 > $blog_id || 'backup' === $stage) {
            return $table_name;
        }

        $new_prefix = isset($state_data['new_prefix']) ? $state_data['new_prefix'] : Persistence::getFromStateData('new_prefix');

        if (empty($new_prefix)) {
            return $table_name;
        }

        global $wpdb;
        $old_prefix = $wpdb->base_prefix;
        if (is_multisite() && 1 < $blog_id && !$this->table_helper->table_is('', $table_name, 'global', '', $blog_id)) {
            $old_prefix .= $blog_id . '_';
        }

        // We do not want to overwrite the global tables unless exporting or target is a single site install.
        if ('savefile' !== $action &&
            (
                ('pull' === $action && 'true' === $site_details['local']['is_multisite']) ||
                ('push' === $action && 'true' === $site_details['remote']['is_multisite'])
            ) &&
            $this->table_helper->table_is('', $table_name, 'global')
        ) {
            $new_prefix .= 'wpmdbglobal_';
        }

        if (0 === stripos($table_name, $old_prefix)) {
            $table_name = substr_replace($table_name, $new_prefix, 0, strlen($old_prefix));
        }

        return $table_name;
    }

    /**
     * Handler for the wpmdb_table_row filter.
     * The given $row can be modified, but if we return false the row will not be used.
     *
     * @param stdClass $row
     * @param string   $table_name
     * @param string   $action
     * @param string   $stage
     *
     * @return bool
     */
    public function filter_table_row($row, $table_name, $state_data)
    {
        $use       = true;
        $stage     = $state_data['stage'];
        $form_data = $state_data['form_data'];

        if ($this->blog_id) {
            $blog_id = $this->blog_id;
        } else {
            $blog_id = $this->selected_subsite($state_data);
        }

        if (1 > $blog_id || 'backup' == $stage) {
            return $use;
        }
        $new_prefix = $state_data['new_prefix'];

        if (empty($new_prefix)) {
            return $row;
        }

        global $wpdb;

        $old_prefix = $state_data['source_prefix'];
        if (is_multisite() && 1 < $blog_id) {
            $old_prefix .= $blog_id . '_';
        }

        if ($this->table_helper->table_is('options', $table_name)) {
            // Rename options records like wp_X_user_roles to wp_Y_user_roles otherwise no users can do anything in the migrated site.
            if (0 === stripos($row->option_name, $old_prefix)) {
                $row->option_name = substr_replace($row->option_name, $new_prefix, 0, strlen($old_prefix));
            }
        }
        if ($this->table_helper->table_is('usermeta', $table_name)) {
            if (!$this->is_user_required_for_blog($row->user_id, $blog_id)) {
                $use = false;
            } elseif (1 == $blog_id) {
                $prefix_escaped = preg_quote($state_data['source_prefix']);
                if (1 === preg_match('/^' . $prefix_escaped . '([0-9]+)_/', $row->meta_key, $matches)) {
                    // Remove non-primary subsite records from usermeta when migrating primary subsite.
                    $use = false;
                } elseif (0 === stripos($row->meta_key, $old_prefix)) {
                    // Rename prefixed keys.
                    $row->meta_key = substr_replace($row->meta_key, $new_prefix, 0, strlen($old_prefix));
                }
            } else {
                if (0 === stripos($row->meta_key, $old_prefix)) {
                    // Rename prefixed keys.
                    $row->meta_key = substr_replace($row->meta_key, $new_prefix, 0, strlen($old_prefix));
                } elseif (0 === stripos($row->meta_key, $state_data['source_prefix'])) {
                    // Remove wp_* records from usermeta not for extracted subsite.
                    $use = false;
                }
            }
        }

        if ($this->table_helper->table_is('users', $table_name)) {
            if (!$this->is_user_required_for_blog($row->ID, $blog_id)) {
                $use = false;
            }
        }

        return $use;
    }

    /**
     * Handler for the wpmdb_find_and_replace filter.
     *
     * @param array  $tmp_find_replace_pairs
     * @param string $intent
     * @param string $site_url
     *
     * @return array
     */
    public function filter_find_and_replace($tmp_find_replace_pairs, $intent, $site_url)
    {
        $blog_id = $this->selected_subsite();

        $this->state_data = $this->migration_state_manager->set_post_data();

        if (1 > $blog_id) {
            return $tmp_find_replace_pairs;
        }

        $source               = ('pull' === $intent) ? 'remote' : 'local';
        $target               = ('pull' === $intent) ? 'local' : 'remote';

        $is_source_multi      = 'true' === $this->state_data['site_details'][$source]['is_multisite'];
        $is_destination_multi = 'true' === $this->state_data['site_details'][$target]['is_multisite'];
        $destination_id       = $is_source_multi && $is_destination_multi ? $this->state_data['mst_destination_subsite'] : $blog_id;
        if ('true' === $this->state_data['site_details'][$source]['is_multisite']) {
            $source_site_url        = $this->state_data['site_details'][$source]['subsites_info'][$blog_id]['site_url'];
            $source_uploads_baseurl = $this->state_data['site_details'][$source]['subsites_info'][$blog_id]['uploads']['baseurl'];
            $source_short_basedir   = $this->state_data['site_details'][$source]['subsites_info'][$blog_id]['uploads']['short_basedir'];
        } else {
            $source_site_url        = $this->state_data['site_details'][$source]['site_url'];
            $source_uploads_baseurl = $this->state_data['site_details'][$source]['uploads']['baseurl'];
            $source_short_basedir   = '';
        }
        $source_site_url        = '//' . untrailingslashit($this->util->scheme_less_url($source_site_url));
        $source_uploads_baseurl = '//' . untrailingslashit($this->util->scheme_less_url($source_uploads_baseurl));

        if (in_array($intent, array('savefile', 'find_replace'))) {
            $target_site_url        = '';
            $target_uploads_baseurl = '';
            $target_short_basedir   = '';

            foreach ($tmp_find_replace_pairs as $find => $replace) {
                if ($find == $source_site_url) {
                    $target_site_url = $replace;
                    break;
                }
            }

            // Append extra path elements from uploads url, removing unneeded subsite specific elements.
            if (!empty($target_site_url)) {
                $target_uploads_baseurl = $target_site_url . substr($source_uploads_baseurl, strlen($source_site_url));

                if (!empty($source_short_basedir) && 'savefile' === $intent) {
                    $target_uploads_baseurl = substr(untrailingslashit($target_uploads_baseurl), 0, -strlen(untrailingslashit($source_short_basedir)));
                }
            }
        } elseif ('true' === $this->state_data['site_details'][$target]['is_multisite']) {
            $target_site_url        = $this->state_data['site_details'][$target]['subsites_info'][$destination_id]['site_url'];
            $target_uploads_baseurl = $this->state_data['site_details'][$target]['subsites_info'][$destination_id]['uploads']['baseurl'];
            $target_short_basedir   = $this->state_data['site_details'][$target]['subsites_info'][$destination_id]['uploads']['short_basedir'];
        } else {
            $target_site_url        = $this->state_data['site_details'][$target]['site_url'];
            $target_uploads_baseurl = $this->state_data['site_details'][$target]['uploads']['baseurl'];
            $target_short_basedir   = '';
        }

        // If we have a target uploads url, we can add in the find/replace we need.
        if (!empty($target_uploads_baseurl)) {
            $target_site_url        = '//' . untrailingslashit($this->util->scheme_less_url($target_site_url));
            $target_uploads_baseurl = '//' . untrailingslashit($this->util->scheme_less_url($target_uploads_baseurl));

            $target_site_url        = apply_filters('wpmdb_mst_target_site_url', $target_site_url);
            $target_uploads_baseurl = apply_filters('wpmdb_mst_target_uploads_baseurl', $target_uploads_baseurl);

            // As we're appending to the find/replace rows, we need to use the already replaced values for altering uploads url.
            $old_uploads_url                          = substr_replace($source_uploads_baseurl, $target_site_url, 0, strlen($source_site_url));
            $tmp_find_replace_pairs[$old_uploads_url] = $target_uploads_baseurl;
        }

        return $tmp_find_replace_pairs;
    }

    /**
     * Change the name of the given table depending on migration profile settings and source and target site setup.
     *
     * @param string $table_name
     * @param string $intent
     * @param array  $site_details
     *
     * @return string
     *
     * This is run in response to the wpmdb_finalize_target_table_name filter on the target site.
     */
    public function filter_finalize_target_table_name($table_name, $state_data)
    {
        $intent       = $state_data['intent'];
        $site_details = $state_data['site_details'];
        if ('find_replace' === $intent) {
            return $table_name;
        }

        $blog_id = isset($state_data['mst_selected_subsite']) ? $state_data['mst_selected_subsite'] : 0;

        if (1 > $blog_id) {
            return $table_name;
        }

        $new_prefix = $state_data['new_prefix'];
        if (empty($new_prefix)) {
            return $table_name;
        }

        // During a MST migration we add a custom prefix to the global tables so that we can manipulate their data before use.
        $type       = isset($state_data['type']) ? $state_data['type'] : $intent;
        $old_prefix = ('push' === $type) ? $state_data['site_details']['local']['prefix'] : $state_data['site_details']['remote']['prefix'];
        if (is_multisite() && $this->table_helper->table_is('', $table_name, 'global', $new_prefix, $blog_id, $old_prefix)) {
            $new_prefix .= 'wpmdbglobal_';
        }
        $destination_subsite = isset($state_data['mst_destination_subsite']) ? $state_data['mst_destination_subsite'] : 0;
        if ((0 < $destination_subsite || !is_multisite()) && 1 < $blog_id && !$this->table_helper->table_is('', $table_name, 'global', $new_prefix, $blog_id, $old_prefix)) {
            $old_prefix .= $blog_id . '_';
        }

        if (0 === stripos($table_name, $old_prefix)) {
            $table_name = substr_replace($table_name, $new_prefix, 0, strlen($old_prefix));
        }

        return $table_name;
    }

    /**
     * Returns validated and sanitized form data.
     *
     * @param array|string $data
     *
     * @return array|string
     */
    public function parse_migration_form_data($data)
    {
        // ***+=== @TODO - revisit usage of parse_migration_form_data
        $form_data = $this->form_data_class->parse_and_save_migration_form_data($data);

        $form_data = array_intersect_key($form_data, array_flip($this->accepted_fields));

        return $form_data;
    }

    /**
     * Alter file path pulled from remote to local equivalent.
     *
     * @param string  $file
     * @param integer $blog_id
     *
     * @return string TODO: Update for multisite <=> multisite (blog_ids)
     *
     * @throws Exception
     *
     * TODO: Update for multisite <=> multisite (blog_ids)
     */
    protected function alter_pulled_file_path($file, $blog_id)
    {
        if (1 > $blog_id) {
            return $file;
        }

        if ($this->state_data['site_details']['local']['is_multisite'] !== $this->state_data['site_details']['remote']['is_multisite']) {
            if (is_multisite()) {
                if (isset($this->state_data['site_details']['local']['subsites_info'][$blog_id]['uploads']['short_basedir'])) {
                    $file = ltrim(trailingslashit($this->state_data['site_details']['local']['subsites_info'][$blog_id]['uploads']['short_basedir']) . $file, '/');
                } else {
                    throw new \Exception(__('Expected local subsite "short_basedir" missing from `state_data`.', 'wp-migrate-db'));
                }
            } else {
                if (isset($this->state_data['site_details']['remote']['subsites_info'][$blog_id]['uploads']['short_basedir'])) {
                    $file = substr($file, strlen($this->state_data['site_details']['remote']['subsites_info'][$blog_id]['uploads']['short_basedir']));
                } else {
                    throw new \Exception(__('Expected remote subsite "short_basedir" missing from `state_data`.', 'wp-migrate-db'));
                }
            }
        }

        return $file;
    }

    /**
     * Maybe change options keys to be preserved.
     *
     * @param array  $preserved_options
     * @param string $intent
     *
     * @return array
     */
    public function filter_preserved_options($preserved_options, $intent = '')
    {
        $state_data = $intent === 'push' ? Persistence::getRemoteStateData() : Persistence::getStateData();
        $blog_id    = $this->selected_subsite($state_data);

        if (0 < $blog_id && in_array($intent, array('push', 'pull'))) {
            $preserved_options = $this->table->preserve_active_plugins_option($preserved_options);
        }

        return $preserved_options;
    }

    /**
     * Maybe preserve the WPMDB plugins if they aren't already preserved.
     *
     * @param array  $preserved_options_data
     * @param string $intent
     *
     * @return array
     */
    public function filter_preserved_options_data($preserved_options_data, $intent = '')
    {
        $state_data = $intent === 'push' ? Persistence::getRemoteStateData() : Persistence::getStateData();
        $blog_id    = $this->selected_subsite($state_data);

        if (0 < $blog_id && in_array($intent, array('push', 'pull'))) {
            $preserved_options_data = $this->table->preserve_wpmdb_plugins($preserved_options_data);
        }

        return $preserved_options_data;
    }

    /**
     * Append more queries to be run at finalize_migration.
     *
     * @param array $queries
     *
     * @return array
     */
    public function filter_get_alter_queries($queries, $state_data)
    {
        $blog_id = isset($state_data['mst_destination_subsite']) ? $state_data['mst_destination_subsite'] : $this->selected_subsite($state_data);

        if (1 > $blog_id) {
            return $queries;
        }

        if (!is_multisite() || 'pull' !== $state_data['intent'] || empty($state_data['tables'])) {
            return $queries;
        }

        global $wpdb;

        $tables = explode(',', $state_data['tables']);

        $target_users_table    = null;
        $source_users_table    = null;
        $target_usermeta_table = null;
        $source_usermeta_table = null;
        $posts_imported        = false;
        $target_posts_table    = null;
        $target_postmeta_table = null;
        $comments_imported     = false;
        $target_comments_table = null;
        $intent = isset($state_data['type']) ? $state_data['type'] : $state_data['intent'];
        $source_prefix = ('pull' === $intent) ? $state_data['site_details']['remote']['prefix'] : $state_data['site_details']['local']['prefix'];
        foreach ($tables as $table) {
            if (empty($source_users_table) && $this->table_helper->table_is('users', $table, 'table', $source_prefix, 0, $source_prefix)) {
                $target_users_table = Util::prefix_updater($table, $state_data['source_prefix'], $state_data['destination_prefix']);
                $source_users_table = $this->filter_finalize_target_table_name($table, $state_data);
                continue;
            }
            if (empty($source_usermeta_table) && $this->table_helper->table_is('usermeta', $table, 'table', $source_prefix, 0, $source_prefix)) {
                $target_usermeta_table = Util::prefix_updater($table, $state_data['source_prefix'], $state_data['destination_prefix']);
                $source_usermeta_table = $this->filter_finalize_target_table_name($table, $state_data);
                continue;
            }
            if (!$posts_imported && $this->table_helper->table_is('posts', $table, 'table', $source_prefix)) {
                $posts_imported     = true;
                $target_posts_table = $this->filter_finalize_target_table_name($table, $state_data);
                continue;
            }
            if ($this->table_helper->table_is('postmeta', $table, 'table', $source_prefix)) {
                $target_postmeta_table = $this->filter_finalize_target_table_name($table, $state_data);
                continue;
            }
            if (!$comments_imported && $this->table_helper->table_is('comments', $table, 'table', $source_prefix)) {
                $comments_imported     = true;
                $target_comments_table = $this->filter_finalize_target_table_name($table, $state_data);
                continue;
            }
        }

        // Find users that already exist and update their content to adopt existing user id and remove from import.
        if (!empty($source_users_table)) {
            $updated_user_ids           = array();
            $temp_prefix                = $state_data['temp_prefix'];
            $temp_source_users_table    = $temp_prefix . $source_users_table;
            $temp_source_usermeta_table = $temp_prefix . $source_usermeta_table;

            $sql = "
					SELECT source.id AS source_id, target.id AS target_id FROM `{$temp_source_users_table}` AS source, `{$target_users_table}` AS target
					WHERE target.user_login = source.user_login
					AND target.user_email = source.user_email
				";

            $user_ids_to_update = $wpdb->get_results($sql, ARRAY_A);

            //If users match from both sites
            if (!empty($user_ids_to_update)) {
                foreach ($user_ids_to_update as $user_ids) {
                    $blogs_of_user = get_blogs_of_user($user_ids['target_id']);

                    // Log user for exclusion from import.
                    $updated_user_ids[] = $user_ids['source_id'];

                    //Add new blog capabilities to imported users
                    if (null !== $source_usermeta_table && !array_key_exists($blog_id, $blogs_of_user)) {
                        $queries = $this->update_usermeta_for_imported_users($queries, $user_ids, $temp_source_usermeta_table, $target_usermeta_table, $blog_id);
                    }

                    if (empty($blogs_of_user) || array_key_exists($blog_id, $blogs_of_user)) {
                        // Only update content ownership if user id has changed.
                        if ($user_ids['source_id'] !== $user_ids['target_id']) {
                            if ($posts_imported) {
                                $queries[]['query'] = "
									UPDATE `{$target_posts_table}`
									SET post_author = {$user_ids['target_id']}
									WHERE post_author = {$user_ids['source_id']}
									;\n
								";
                            }

                            if ($comments_imported) {
                                $queries[]['query'] = "
									UPDATE `{$target_comments_table}`
									SET user_id = {$user_ids['target_id']}
									WHERE user_id = {$user_ids['source_id']}
									;\n
								";
                            }
                        }
                    }
                }
            }

            $queries[]['query'] = "ALTER TABLE `{$target_users_table}` ADD COLUMN wpmdb_user_id BIGINT(20) UNSIGNED;\n";

            $where = '';
            if (!empty($updated_user_ids)) {
                $where = 'WHERE u2.id NOT IN (' . implode(',', $updated_user_ids) . ')';
            }
            $queries[]['query'] = "INSERT INTO `{$target_users_table}` (user_login, user_pass, user_nicename, user_email, user_url, user_registered, user_activation_key, user_status, display_name, wpmdb_user_id)
					SELECT u2.user_login, u2.user_pass, u2.user_nicename, u2.user_email, u2.user_url, u2.user_registered, u2.user_activation_key, u2.user_status, u2.display_name, u2.id
					FROM `{$source_users_table}` AS u2
					{$where};\n";

            if (!empty($source_usermeta_table)) {
                $queries[]['query'] = "INSERT INTO `{$target_usermeta_table}` (user_id, meta_key, meta_value)
						SELECT u.id, m2.meta_key, m2.meta_value
						FROM `{$source_usermeta_table}` AS m2
						JOIN `{$target_users_table}` AS u ON m2.user_id = u.wpmdb_user_id;\n";
            }

            if ($posts_imported) {
                $queries[]['query'] = "
						UPDATE `{$target_posts_table}` AS p, `{$target_users_table}` AS u
						SET p.post_author = u.id
						WHERE p.post_author = u.wpmdb_user_id
						;\n";
            }

            if (!is_null($target_postmeta_table) && !empty($user_ids)) {
                $queries[]['query'] = "
										UPDATE `{$target_postmeta_table}`
										SET {$target_postmeta_table}.meta_value = {$user_ids['target_id']}
										WHERE {$target_postmeta_table}.meta_key = '_edit_last'
										AND {$target_postmeta_table}.meta_value = {$user_ids['source_id']};
										";
            }

            if ($comments_imported) {
                $queries[]['query'] = "
						UPDATE `{$target_comments_table}` AS c, `{$target_users_table}` AS u
						SET c.user_id = u.id
						WHERE c.user_id = u.wpmdb_user_id
						;\n";
            }
            $queries[]['query'] = "DROP TABLE `{$source_users_table}`;\n";

            $queries[]['query'] = "ALTER TABLE `{$target_users_table}` DROP COLUMN wpmdb_user_id;\n";
        }

        // Cleanup imported usermeta table, whether used by above user related queries or not.
        // TODO: Maybe support updating usermeta without imported users table?
        if (!empty($source_usermeta_table)) {
            $queries[]['query'] = "DROP TABLE `{$source_usermeta_table}`;\n";
        }

        return $queries;
    }


    /**
     * Filters the URLs used by WPMDB_Replace.
     *
     * @param array $site_urls
     *
     * @TODO add unit tests for this method
     * @return array
     */
    function filter_replace_site_urls($site_urls)
    {
        if (isset($this->form_data['mst_select_subsite']) && '1' === $this->form_data['mst_select_subsite']) {
            $selected_subsite_id = $this->form_data['mst_selected_subsite'];

            foreach (array('local', 'remote') as $which) {
                if (isset($this->state_data['site_details'][$which]['subsites_info'][$selected_subsite_id])) {
                    $subsite_base = $this->state_data['site_details'][$which]['subsites_info'][$selected_subsite_id];
                    $subsite_url  = $subsite_base['site_url'];

                    // Use home_url if it's set.
                    if (isset($subsite_base['home_url'])) {
                        $subsite_url = $subsite_base['home_url'];
                    }

                    $site_urls[$which] = $subsite_url;
                }
            }
        }

        return $site_urls;
    }

    /**
     * Updates the URL in the export header for MST exports
     *
     * @param string $url
     *
     * @return string
     */
    function filter_backup_header_url($url)
    {
        $selected_subsite = $this->selected_subsite();

        if (0 !== $selected_subsite &&
            'backup' !== $this->state_data['stage'] &&
            isset($this->state_data['site_details']['local']['subsites_info'][$selected_subsite])) {
            $subsite_base = $this->state_data['site_details']['local']['subsites_info'][$selected_subsite];
            $url          = $subsite_base['home_url'];
        }

        return $url;
    }

    /**
     * @param array $tables
     *
     * @return array
     */
    function filter_backup_header_tables($tables)
    {
        if (!is_multisite() || 'backup' === $this->state_data['stage']) {
            return $tables;
        }

        foreach ($tables as $key => $table) {
            $tables[$key] = $this->filter_target_table_name($table, Persistence::getStateData());
        }

        return $tables;
    }

    function filter_backup_header_is_subsite_export()
    {
        return 0 === $this->selected_subsite() ? 'false' : 'true';
    }

    /**
     * Does the passed subsite (ID) exist?
     *
     * @param int $blog_id
     *
     * @return bool
     */
    public function subsite_exists($blog_id)
    {
        if (!is_multisite()) {
            return false;
        }

        if (version_compare($GLOBALS['wp_version'], '4.6', '>=')) {
            $blogs = get_sites(array('number' => false));
        } else {
            $blogs = wp_get_sites(array('limit' => 0));
        }

        if (empty($blogs)) {
            return false;
        }

        foreach ($blogs as $blog) {
            $blog = (array)$blog;
            if (!empty($blog['blog_id']) && $blog_id == $blog['blog_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array  $queries
     * @param array  $user_ids
     * @param string $temp_source_usermeta_table
     * @param string $target_usermeta_table
     * @param int    $blog_id
     *
     * @return array
     */
    protected function update_usermeta_for_imported_users($queries, $user_ids, $temp_source_usermeta_table, $target_usermeta_table, $blog_id)
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $blog_id_string = 1 === (int)$blog_id ? $prefix : "{$prefix}{$blog_id}_";

        $sql = "SELECT meta_value as value
			FROM `{$temp_source_usermeta_table}`
			WHERE ( meta_key = '{$blog_id_string}user_level' OR meta_key = '{$blog_id_string}capabilities' )
			AND user_id={$user_ids['source_id']}
			ORDER BY meta_key";

        $result = $wpdb->get_results($sql, OBJECT);

        if ($result) {
            //User level
            $queries[]['query'] = "
				INSERT INTO `{$target_usermeta_table}` (user_id, meta_key, meta_value)
				VALUES ({$user_ids['target_id']}, '{$blog_id_string}user_level', {$result[1]->value} );
				\n";

            //Capabilities
            $queries[]['query'] = "
				INSERT INTO `{$target_usermeta_table}`(user_id, meta_key, meta_value)
				VALUES({$user_ids['target_id']}, '{$blog_id_string}capabilities', '{$result[0]->value}')
				;\n";
        }

        return $queries;
    }
}
