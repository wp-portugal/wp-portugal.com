<?php

namespace DeliciousBrains\WPMDBTP;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\MigrationState\StateDataContainer;
use DeliciousBrains\WPMDB\Container;
use DeliciousBrains\WPMDB\Pro\Queue\Manager;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\Chunker;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\PluginHelper;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;
use DeliciousBrains\WPMDB\WPMDBDI;

class ThemePluginFilesFinalize
{

    /**
     * @var FormData
     */
    private $form_data;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Util
     */
    private $transfer_helpers;
    /**
     * @var ErrorLog
     */
    private $error_log;
    /**
     * @var Http
     */
    private $http;
    /**
     * @var StateDataContainer
     */
    private $state_data_container;
    /**
     * @var Manager
     */
    private $manager;
    /**
     * @var MigrationStateManager
     */
    private $migration_state_manager;
    /**
     * @var PluginHelper
     */
    private $plugin_helper;

    public function __construct(
        FormData $form_data,
        Filesystem $filesystem,
        Util $transfer_helpers,
        ErrorLog $error_log,
        Http $http,
        StateDataContainer $state_data_container,
        Manager $manager,
        MigrationStateManager $migration_state_manager,
        PluginHelper $plugin_helper
    ) {
        $this->form_data               = $form_data;
        $this->filesystem              = $filesystem;
        $this->transfer_helpers        = $transfer_helpers;
        $this->error_log               = $error_log;
        $this->http                    = $http;
        $this->state_data_container    = $state_data_container;
        $this->manager                 = $manager;
        $this->migration_state_manager = $migration_state_manager;
        $this->plugin_helper           = $plugin_helper;
    }

    public function maybe_finalize_tp_migration()
    {
        // @todo - revisit
        $form_data = $this->form_data->getCurrentMigrationData();
        $intent    = $form_data['intent'];

        $state_data = $intent === 'push' ? Persistence::getRemoteStateData() : Persistence::getStateData();

        if (!isset($state_data['stage'])) {
            return false;
        }

        if (!in_array($state_data['stage'], array('themes', 'plugins'))) {
            return false;
        }

        // Check that the number of files transferred is correct, throws exception
        $this->verify_file_transfer($state_data);

        $form_data = Persistence::getMigrationOptions();

        $current_migration = $form_data['current_migration'];

        if (!in_array('theme_files', $current_migration['stages']) && !in_array('plugin_files', $current_migration['stages'])) {
            return;
        }

        $files_to_migrate = array(
            'themes'  => isset($current_migration['stages'], $state_data['theme_folders']) && in_array('theme_files', $current_migration['stages']) ? $state_data['theme_folders'] : [],
            'plugins' => isset($current_migration['stages'], $state_data['plugin_folders']) && in_array('plugin_files', $current_migration['stages']) ? $state_data['plugin_folders'] : [],
        );
        $migration_id     = $intent === 'push' ? $state_data['remote_state_id'] : $state_data['migration_state_id'];

        foreach ($files_to_migrate as $stage => $folder) {
            $dest_path = trailingslashit(('plugins' === $stage) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'themes');
            $tmp_path  = Receiver::get_temp_dir() . $stage . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
            foreach ($folder as $file_folder) {
                if ($stage === 'plugins') {
                    $folder_name = basename(str_replace('\\', '/', $file_folder));
                } else { //Themes
                    $manifest    = $this->transfer_helpers->load_manifest($stage, $migration_id);
                    $folder_name = $this->transfer_helpers->get_folder_name($file_folder, $dest_path, $tmp_path, $manifest['manifest']);

                    if (!$folder_name) {
                        return $this->http->end_ajax(new \WP_Error('wpmdb_no_folder_name', sprintf(__('Unable to determine folder name for theme %s'), $folder)));
                    }
                }

                $dest_folder = $dest_path . $folder_name;
                $tmp_source  = $tmp_path . $folder_name;

                if (is_link($dest_folder)) {
                    $dest_folder = readlink($dest_folder);
                }

                $return = $this->move_folder_into_place($tmp_source, $dest_folder, $stage);

                if (is_wp_error($return)) {
                    return $this->transfer_helpers->ajax_error($return->get_error_message());
                }
            }
        }
    }

    /**
     * @param string $source
     * @param string $dest
     * @param string $stage
     *
     * @return bool|\WP_Error
     */
    public function move_folder_into_place(
        $source,
        $dest,
        $stage
    ) {
        $fs          = $this->filesystem;
        $dest_backup = false;

        if (!$fs->file_exists($source)) {
            $message = sprintf(__('Temporary file not found when finalizing Theme & Plugin Files migration: %s ', 'wp-migrate-db-pro-theme-plugin-files'), $source);
            $this->error_log->log_error($message);

            return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
        }

        if ($fs->file_exists($dest)) {
            if (!$fs->is_writable($dest)) {
                $message = sprintf(__('Unable to overwrite destination file when finalizing Theme & Plugin Files migration: %s', 'wp-migrate-db-pro-theme-plugin-files'), $source);
                $this->error_log->log_error($message);
                error_log($message);

                return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
            }

            $backup_dir = Receiver::get_temp_dir() . $stage . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
            if (!$fs->is_dir($backup_dir)) {
                $fs->mkdir($backup_dir);
            }
            $dest_backup = $backup_dir . basename($dest) . '.' . time() . '.bak';
            $dest_backup = $fs->move($dest, $dest_backup) ? $dest_backup : false;
        }

        if (!$fs->move($source, $dest)) {
            $message = sprintf(__('Unable to move file into place when finalizing Theme & Plugin Files migration. Source: %s | Destination: %s', 'wp-migrate-db-pro-theme-plugin-files'), $source, $dest);
            $this->error_log->log_error($message);
            error_log($message);

            // attempt to restore backup
            if ($dest_backup) {
                $fs->move($dest_backup, $dest);
            }

            return new \WP_Error('wpmdbpro_theme_plugin_files_error', $message);
        }

        return true;
    }

    /**
     * Runs on local site
     */
    public function cleanup_transfer_migration()
    {
        $stages = $this->form_data->getMigrationStages();

        if (!$stages) {
            return;
        }

        if (!empty(array_intersect(['theme_files', 'plugin_files'], $stages))) {
            $this->plugin_helper->cleanup_transfer_migration('themes');
        }
    }

    /**
     * Runs on remote site, on `wpmdb_respond_to_push_cancellation` hook
     */
    public function remove_tmp_files_remote()
    {
        $stages = $this->form_data->getMigrationStages();

        if (!$stages) {
            return;
        }
        // Only run if no media files stage
        if (!empty(array_intersect(['theme_files', 'plugin_files'], $stages))) {
            $this->plugin_helper->remove_tmp_files('themes', 'remote');
        }
    }

    /**
     *
     * Fires on the `wpmdb_before_finalize_migration` hook
     *
     * @return bool
     * @throws \Exception
     */
    public function verify_file_transfer($state_data)
    {
        if (isset($state_data['stage']) && !in_array($state_data['stage'], array('themes', 'plugins'))) {
            return false;
        }

        $stages    = array();
        $form_data = $this->form_data->getCurrentMigrationData();

        if (isset($form_data['stages']) && in_array('theme_files', $form_data['stages']) && isset($state_data['theme_folders'])) {
            $stages[] = 'themes';
        }

        if (isset($form_data['stages']) && in_array('plugin_files', $form_data['stages']) && isset($state_data['plugin_folders'])) {
            $stages[] = 'plugins';
        }

        $migration_key = isset($state_data['type']) && 'push' === $state_data['type'] ? $state_data['remote_state_id'] : $state_data['migration_state_id'];

        foreach ($stages as $stage) {
            $filename      = '.' . $migration_key . '-manifest';
            $manifest_path = Receiver::get_temp_dir() . $stage . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
            $queue_info    = unserialize(file_get_contents($manifest_path));

            if (!$queue_info) {
                throw new \Exception(sprintf(__('Unable to verify file migration, %s does not exist.'), $manifest_path));
            }

            if (!isset($queue_info['total'])) {
                continue;
            }

            try {
                // Throws exception
                $this->transfer_helpers->check_manifest($queue_info['manifest'], $stage);
            } catch (\Exception $e) {
                return $this->http->end_ajax(json_encode(array('wpmdb_error' => 1, 'body' => $e->getMessage())));
            }
        }
    }

}
