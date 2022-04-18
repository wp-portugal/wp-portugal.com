<?php

namespace DeliciousBrains\WPMDB\Pro\TPF;

use DeliciousBrains\WPMDB\Common\Error\HandleRemotePostError;
use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\WPMDBRestAPIServer;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Pro\Queue\Manager;
use DeliciousBrains\WPMDB\Pro\Queue\QueueHelper;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\FileProcessor;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\TransferManager;
use DeliciousBrains\WPMDB\Pro\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Pro\Transfers\Receiver;

/**
 * Class ThemePluginFilesLocal
 *
 * Handles local themes/plugins logic
 *
 */
class ThemePluginFilesLocal
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
     * @var \DeliciousBrains\WPMDB\Common\Util\Util
     */
    public $util;
    /**
     * @var MigrationStateManager
     */
    public $migration_state_manager;
    /**
     * @var Http
     */
    public $http;
    /**
     * @var TransferCheck
     */
    private $check;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var WPMDBRestAPIServer
     */
    private $rest_API_server;
    /**
     * @var Helper
     */
    private $http_helper;
    /**
     * @var QueueHelper
     */
    private $queue_helper;

    public function __construct(
        Util $util,
        \DeliciousBrains\WPMDB\Common\Util\Util $common_util,
        FileProcessor $file_processor,
        Manager $queue_manager,
        TransferManager $transfer_manager,
        Receiver $receiver,
        MigrationStateManager $migration_state_manager,
        Http $http,
        Filesystem $filesystem,
        TransferCheck $check,
        WPMDBRestAPIServer $rest_API_server,
        Helper $http_helper,
        QueueHelper $queue_helper
    ) {
        $this->util                    = $common_util;
        $this->queueManager            = $queue_manager;
        $this->transfer_util           = $util;
        $this->file_processor          = $file_processor;
        $this->transfer_manager        = $transfer_manager;
        $this->receiver                = $receiver;
        $this->migration_state_manager = $migration_state_manager;
        $this->http                    = $http;
        $this->check                   = $check;
        $this->filesystem              = $filesystem;
        $this->rest_API_server         = $rest_API_server;
        $this->http_helper             = $http_helper;
        $this->queue_helper            = $queue_helper;
    }

    public function register()
    {
        add_action('wpmdb_initiate_migration', array($this->check, 'transfer_check'));
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes()
    {
        $this->rest_API_server->registerRestRoute(
            '/tpf-initiate-file-migration',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'ajax_initiate_file_migration'],
            ]
        );

        $this->rest_API_server->registerRestRoute(
            '/tpf-get-queue-items',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'ajax_get_queue_items'],
            ]
        );

        $this->rest_API_server->registerRestRoute(
            '/tpf-transfer-files',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'ajax_transfer_files'],
            ]
        );
    }

    /**
     *
     * @TODO Break this up into smaller, testable functions
     * @return bool|null
     */
    public function ajax_initiate_file_migration()
    {
        $_POST = $this->http_helper->convert_json_body_to_post();
        $this->util->set_time_limit();

        $key_rules = array(
            'action'             => 'key',
            'stage'              => 'string',
            'plugins_excludes'   => 'json',
            'themes_excludes'    => 'json',
            'migration_state_id' => 'key',
            'folders'            => 'json_array',
            'theme_folders'      => 'json_array',
            'themes_option'      => 'string',
            'plugin_folders'     => 'json_array',
            'plugins_option'     => 'string',
            'is_cli_migration'   => 'int',
            'nonce'              => 'key',
        );

        $state_data = Persistence::setPostData($key_rules, __METHOD__);
        $current_option = $state_data[$state_data['stage']. '_option'];
        if (empty($state_data['folders']) && $current_option !== 'except' ) {
            return $this->transfer_util->ajax_error(__('Error: empty folder list supplied.', 'wp-migrate-db'));
        }

        $excludes = isset($state_data[$state_data['stage']. '_excludes']) ? trim($state_data[$state_data['stage']. '_excludes'], '" \t\n\r\0\x0B') : [];
        $split_excludes = [];
        if (!is_array($excludes)) {
            $split_excludes = preg_split('/\r\n|\r|\n/', stripcslashes($excludes)); //stripcslashes() makes the $excludes string double quoted so we can use preg_split()
        }

        //Cleanup partial chunk files.
        $this->transfer_util->cleanup_temp_chunks(WP_CONTENT_DIR . DIRECTORY_SEPARATOR, 'tmpchunk');

        //State data populated
        $files = $state_data['folders'];

        if (!is_array($files)) {
            return $this->transfer_util->ajax_error(__('Invalid folder list supplied (invalid array)', 'wp-migrate-db'));
        }

        // @TODO this needs to be implemented for remotes on a pull
        $verified_folders = $this->verify_files_for_migration($files);

        if (empty($state_data['is_cli_migration'])) {
            Util::enable_scandir_bottleneck();
        }

        if ('pull' === $state_data['intent']) {
            // Set up local meta data
            $file_list = $this->transfer_util->get_remote_files($files, 'wpmdbtp_respond_to_get_remote_' . $state_data['stage'], $split_excludes);
        } else {
            // Push = get local files
            $abs_path  = 'plugins' === $state_data['stage'] ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/themes/';
            $file_list = $this->file_processor->get_local_files($verified_folders, $abs_path, $split_excludes, $state_data['stage'], null, null,'push');
        }

        if (is_wp_error($file_list)) {
            return $this->http->end_ajax($file_list);
        }

        $queue_status = $this->queue_helper->populate_queue($file_list, $state_data['intent'], $state_data['stage'], $state_data['migration_state_id']);
        set_site_transient('wpmdb_queue_status', $queue_status);

        if (isset($file_list['meta']['scan_completed'])) {
            if (true === $file_list['meta']['scan_completed']) {
                return $this->http->end_ajax(['queue_status' => $queue_status]);
            }
            return $this->http->end_ajax(
                [
                    'recursive_queue'   => true,
                    'items_count'       => $queue_status['total']
                ]);
        }

        return $this->http->end_ajax(['queue_status' => $queue_status]);
    }

    /**
     * Get queue items in batches to populate the UI
     *
     * @return mixed|null
     */
    public function ajax_get_queue_items()
    {
        return $this->queue_helper->get_queue_items();
    }

    /**
     * @return null
     */
    public function ajax_transfer_files()
    {
        $_POST = $this->http_helper->convert_json_body_to_post();

        $this->util->set_time_limit();

        $key_rules = array(
            'action'             => 'key',
            'stage'              => 'string',
            'offset'             => 'numeric',
            'folders'            => 'json_array',
            'theme_folders'      => 'json_array',
            'themes_option'      => 'string',
            'plugin_folders'     => 'json_array',
            'plugins_option'     => 'string',
            'migration_state_id' => 'key',
            'nonce'              => 'key',
        );

        $state_data = Persistence::setPostData($key_rules, __METHOD__);

        $count = apply_filters('wpmdbtp_file_batch_size', 100);
        $data  = $this->queueManager->list_jobs($count);

        $processed = $this->transfer_util->process_file_data($data);

        if (empty($data)) {
            do_action('wpmdbtp_file_transfer_complete');

            // Clear out queue in case there is a next step
            $this->queueManager->truncate_queue();

            return $this->http->end_ajax(['status' => 'complete']);
        }

        $remote_url = $state_data['url'];
        $processed  = $this->transfer_manager->manage_file_transfer($remote_url, $processed, $state_data);

        $result = [
            'status' => $processed,
        ];

        //Client should check error status for files and if a 500 is encountered kill the migration stage
        return $this->http->end_ajax($result);
    }

    public function verify_files_for_migration($files)
    {
        $paths = [];

        foreach ($files as $file) {
            if ($this->filesystem->file_exists($file)) {
                $paths[] = $file;
            }
        }

        return $paths;
    }

}
