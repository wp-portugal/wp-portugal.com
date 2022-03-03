<?php

namespace DeliciousBrains\WPMDBTP;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\Scramble;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Pro\Queue\Manager;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\FileProcessor;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\PluginHelper;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\TransferManager;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;
use DeliciousBrains\WPMDB\Pro\Transfers\Sender;

class ThemePluginFilesRemote
{

    /**
     * @var Util
     */
    public $transfer_util;
    /**
     * @var TransferManager
     */
    public $transfer_manager;
    /**
     * @var FileProcessor
     */
    public $file_processor;
    /**
     * @var Manager
     */
    public $queueManager;
    /**
     * @var Receiver
     */
    public $receiver;
    /**
     * @var Http
     */
    private $http;
    /**
     * @var Helper
     */
    private $http_helper;
    /**
     * @var MigrationStateManager
     */
    private $migration_state_manager;
    /**
     * @var Settings
     */
    private $settings;
    /**
     * @var Properties
     */
    private $properties;
    /**
     * @var Sender
     */
    private $sender;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Scramble
     */
    private $scrambler;
    /**
     * @var PluginHelper
     */
    private $plugin_helper;

    public function __construct(
        Util $util,
        FileProcessor $file_processor,
        Manager $queue_manager,
        TransferManager $transfer_manager,
        Receiver $receiver,
        Http $http,
        Helper $http_helper,
        MigrationStateManager $migration_state_manager,
        Settings $settings,
        Properties $properties,
        Sender $sender,
        Filesystem $filesystem,
        Scramble $scramble,
        PluginHelper $plugin_helper
    ) {
        $this->queueManager            = $queue_manager;
        $this->transfer_util           = $util;
        $this->file_processor          = $file_processor;
        $this->transfer_manager        = $transfer_manager;
        $this->receiver                = $receiver;
        $this->http                    = $http;
        $this->http_helper             = $http_helper;
        $this->migration_state_manager = $migration_state_manager;
        $this->settings                = $settings->get_settings();
        $this->properties              = $properties;
        $this->sender                  = $sender;
        $this->filesystem              = $filesystem;
        $this->scrambler               = $scramble;
        $this->plugin_helper           = $plugin_helper;
    }

    public function register()
    {
        add_action('wp_ajax_nopriv_wpmdbtp_respond_to_get_remote_themes', array($this, 'ajax_tp_respond_to_get_remote_themes'));
        add_action('wp_ajax_nopriv_wpmdbtp_respond_to_get_remote_plugins', array($this, 'ajax_tp_respond_to_get_remote_plugins'));

        add_action('wp_ajax_nopriv_wpmdbtp_respond_to_save_queue_status', array($this, 'ajax_tp_respond_to_save_queue_status'));
        add_action('wp_ajax_nopriv_wpmdbtp_transfers_send_file', array($this, 'ajax_tp_respond_to_request_files',));
        add_action('wp_ajax_nopriv_wpmdbtp_transfers_receive_file', array($this, 'ajax_tp_respond_to_post_file'));

        add_filter('wpmdb_establish_remote_connection_data', array($this, 'establish_remote_connection_data'));
    }

    public function establish_remote_connection_data($data)
    {
        $receiver         = $this->receiver;
        $tmp_folder_check = $receiver->is_tmp_folder_writable('themes');

        $data['remote_theme_plugin_files_available'] = true;
        $data['remote_theme_plugin_files_version']   = $GLOBALS['wpmdb_meta']['wp-migrate-db-pro-theme-plugin-files']['version'];
        $data['remote_tmp_folder_check']             = $tmp_folder_check;
        $data['remote_tmp_folder_writable']          = $tmp_folder_check['status'];

        //@todo revisit - this doesn't seem to be used anywhere, why are we check remote folder status?
        return $data;
    }

    public function ajax_tp_respond_to_get_remote_themes()
    {
        $this->respond_to_get_remote_folders('themes');
    }

    public function ajax_tp_respond_to_get_remote_plugins()
    {
        $this->respond_to_get_remote_folders('plugins');
    }

    /**
     * @param $stage
     *
     * @return mixed|null
     */
    public function respond_to_get_remote_folders($stage)
    {
        return $this->plugin_helper->respond_to_get_remote_folders($stage);
    }

    /**
     *
     * Fired off a nopriv AJAX hook that listens to pull requests for file batches
     *
     * @return mixed
     */
    public function ajax_tp_respond_to_request_files()
    {
       return $this->plugin_helper->respond_to_request_files();
    }

    /**
     *
     * Respond to request to save queue status
     *
     * @return mixed|null
     */
    public function ajax_tp_respond_to_save_queue_status()
    {
        return $this->plugin_helper->respond_to_save_queue_status();
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function ajax_tp_respond_to_post_file()
    {
        return $this->plugin_helper->respond_to_post_file();
    }
}
