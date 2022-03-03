<?php

namespace DeliciousBrains\WPMDBMST\CliCommand;

use DeliciousBrains\WPMDB\Common\Cli\Cli;
use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Profile\ProfileManager;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Sql\TableHelper;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Pro\Addon\Addon;
use DeliciousBrains\WPMDB\Common\Multisite\Multisite;
use DeliciousBrains\WPMDB\Pro\UI\Template;
use DeliciousBrains\WPMDBMST\MediaFilesCompat;
use DeliciousBrains\WPMDBMST\MultisiteToolsAddon;

class MultisiteToolsAddonCli extends MultisiteToolsAddon
{

    /**
     * @var Cli
     */
    private $cli;

    function __construct(
        Addon $addon,
        Properties $properties,
        Multisite $multisite,
        Util $util,
        MigrationStateManager $migration_state_manager,
        Table $table,
        TableHelper $table_helper,
        FormData $form_data,
        Template $template,
        ProfileManager $profile_manager,
        Cli $cli,
        DynamicProperties $dynamic_properties,
        Filesystem $filesystem,
        MediaFilesCompat $media_files_compat
    ) {
        parent::__construct(
            $addon,
            $properties,
            $multisite,
            $util,
            $migration_state_manager,
            $table,
            $table_helper,
            $form_data,
            $template,
            $profile_manager,
            $dynamic_properties,
            $filesystem,
            $media_files_compat
        );
        $this->cli = $cli;
    }

    public function register()
    {
        // Add support for extra CLI args with a lower priority so that it can check media options.
        add_filter('wpmdb_cli_filter_get_extra_args', array($this, 'filter_extra_args'), 10, 1);
        add_filter('wpmdb_cli_filter_get_profile_data_from_args', array($this, 'mst_add_extra_cli_args'), 14, 3);

        // Only runs on push or pull
        add_filter('wpmdb_cli_default_find_and_replace', array($this, 'filter_cli_default_find_and_replace'), 10, 2);

        add_filter('wpmdb_cli_initiate_migration_args', [$this, 'filter_initiate_post_data_cli'], 10, 2);
        add_filter('wpmdb_cli_filter_before_migration', array($this, 'extend_mst_cli_migration'), 10, 2);
    }

     /**
     * Add MST to profile.
     *
     * @param array $post_data
     * @param array $profile
     * @return array
     */
    public function extend_mst_cli_migration($profile, $post_data)
    {
        if (!isset($profile['multisite_tools'])) {
            return $profile;
        }

        $mst = $profile['multisite_tools'];
        if (!isset($mst['enabled']) || !$mst['enabled']) {
            return $profile;
        }

        if (!isset($mst['selected_subsite'], $mst['new_prefix'])) {
            return $profile;
        }
        $mst_select_subsite = $profile['mst_select_subsite'] ? '1' : '0';
        
        $destination = ('pull' === $post_data['intent']) ? $post_data['site_details']['local'] : $post_data['site_details']['remote'];
        $prefix = in_array($post_data['intent'], ['push', 'pull']) ? $destination['prefix'] : $mst['new_prefix'];
        if ( "true" === $destination['is_multisite'] && 1 < $mst['selected_subsite']) {
            $prefix .= $mst['selected_subsite'] . '_';
        }
        $mst_args = [
            'mst_select_subsite'   => $mst_select_subsite,
            'mst_selected_subsite' => (int)$mst['selected_subsite'],
            'new_prefix'           => $prefix,
        ];

        $profile = array_merge($profile, $mst_args);

        return $profile;
    }

    public function filter_initiate_post_data_cli($args, $profile)
    {
        if (!isset($args['form_data'])) {
            return $args;
        }

        $form_data_parsed = json_decode($args['form_data'], true);

        if (isset($form_data_parsed['mst_select_subsite'], $form_data_parsed['mst_selected_subsite'])
            && $form_data_parsed['mst_select_subsite']) {
            $args['mst_select_subsite']   = '1';
            $args['mst_selected_subsite'] = $form_data_parsed['mst_selected_subsite'];
        }

        if (isset($form_data_parsed['new_prefix'])) {
            $args['new_prefix'] = $form_data_parsed['new_prefix'];
        }

        return $args;
    }

    /**
     * Add extra CLI args used by this plugin.
     *
     * @param array $args
     *
     * @return array
     */
    public function filter_extra_args($args = array())
    {
        $args[] = 'subsite';
        $args[] = 'prefix';

        return $args;
    }

    /**
     * Add support for extra CLI args.
     *
     * @param array $profile
     * @param array $args
     * @param array $assoc_args
     *
     * @return array
     */
    function mst_add_extra_cli_args($profile, $args, $assoc_args)
    {
        if (!is_array($profile)) {
            return $profile;
        }

        // --subsite=<blog-id|subsite-url>
        $mst_select_subsite   = false;
        $mst_selected_subsite = 0;
        if (isset($assoc_args['subsite'])) {
            if (!is_multisite() && 'savefile' === $profile['action']) {
                return $this->cli->cli_error(__('The installation must be a Multisite network to make use of the export subsite option', 'wp-migrate-db-pro-multisite-tools'));
            }
            if (empty($assoc_args['subsite'])) {
                return $this->cli->cli_error(__('A valid Blog ID or Subsite URL must be supplied to make use of the subsite option', 'wp-migrate-db-pro-multisite-tools'));
            }
            if (is_multisite()) {
                $mst_selected_subsite = $this->multisite->get_subsite_id($assoc_args['subsite']);

                if (false === $mst_selected_subsite) {
                    return $this->cli->cli_error(__('A valid Blog ID or Subsite URL must be supplied to make use of the subsite option', 'wp-migrate-db-pro-multisite-tools'));
                }
            } else {
                $mst_selected_subsite = $assoc_args['subsite'];
            }

            $mst_select_subsite = true;
        }

        // --prefix=<new-table-prefix>
        global $wpdb;
        $new_prefix = $wpdb->base_prefix;
        if (isset($assoc_args['prefix'])) {
            if (false === $mst_select_subsite) {
                return $this->cli->cli_error(__('A new table name prefix may only be specified for subsite exports.', 'wp-migrate-db-pro-multisite-tools'));
            }
            if (empty($assoc_args['prefix'])) {
                return $this->cli->cli_error(__('A valid prefix must be supplied to make use of the prefix option', 'wp-migrate-db-pro-multisite-tools'));
            }
            $new_prefix = trim($assoc_args['prefix']);

            if (sanitize_key($new_prefix) !== $new_prefix) {
                return $this->cli->cli_error($this->get_string('new_prefix_contents'));
            }
        }

        // Disable Media Files Select Subsites if using Subsite Migration.
        if ($mst_select_subsite && !empty($profile['mf_select_subsites']) && !empty($profile['mf_selected_subsites'])) {
            unset($profile['mf_select_subsites'], $profile['mf_selected_subsites']);
        }

        $filtered_profile = compact(
            'mst_select_subsite',
            'mst_selected_subsite',
            'new_prefix'
        );

        return array_merge($profile, $filtered_profile);
    }

    /**
     * Ensure CLI has appropriate default find and replace values when doing MST.
     *
     * @param array $profile
     * @param array $post_data
     *
     * @return array
     *
     * TODO: Update for multisite <=> multisite (blog_ids)
     */
    public function filter_cli_default_find_and_replace($profile, $post_data)
    {
        if (is_wp_error($profile)) {
            return $profile;
        }

        $state_data = $this->migration_state_manager->set_post_data();

        if (!empty($state_data)) {
            $post_data = array_merge($post_data, $state_data);
        }

        if (empty($profile['mst_select_subsite']) || empty($profile['mst_selected_subsite'])) {
            return $profile;
        }

        $source = ('pull' === $post_data['intent']) ? $post_data['site_details']['remote'] : $post_data['site_details']['local'];
        $target = ('pull' === $post_data['intent']) ? $post_data['site_details']['local'] : $post_data['site_details']['remote'];

        $blog_id = false;

        if ('true' === $source['is_multisite'] && !empty($source['subsites'])) {
            $blog_id = $this->multisite->get_subsite_id($profile['mst_selected_subsite'], $source['subsites']);
        } elseif ('true' === $target['is_multisite'] && !empty($target['subsites'])) {
            $blog_id = $this->multisite->get_subsite_id($profile['mst_selected_subsite'], $target['subsites']);
        }

        if (false === $blog_id) {
            return $profile;
        }

        if ('true' === $source['is_multisite'] && !empty($source['subsites_info'][$blog_id]['site_url'])) {
            $profile['search_replace']['standard_search_replace']['domain']['search'] = '//' . untrailingslashit($this->util->scheme_less_url($source['subsites_info'][$blog_id]['site_url']));
        }

        if ('true' === $target['is_multisite'] && !empty($target['subsites_info'][$blog_id]['site_url'])) {
            $profile['search_replace']['standard_search_replace']['domain']['replace'] = '//' . untrailingslashit($this->util->scheme_less_url($target['subsites_info'][$blog_id]['site_url']));
        }

        return $profile;
    }
}
