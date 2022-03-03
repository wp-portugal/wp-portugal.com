<?php
/*
Plugin Name: ManageWP - Worker
Plugin URI: https://managewp.com
Description: We help you efficiently manage all your WordPress websites. <strong>Updates, backups, 1-click login, migrations, security</strong> and more, on one dashboard. This service comes in two versions: standalone <a href="https://managewp.com">ManageWP</a> service that focuses on website management, and <a href="https://godaddy.com/pro">GoDaddy Pro</a> that includes additional tools for hosting, client management, lead generation, and more.
Version: 4.9.13
Author: GoDaddy
Author URI: https://godaddy.com
License: GPL2
Text Domain: worker
Network: true
*/

/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!defined('ABSPATH') && (!defined('MWP_SKIP_BOOTSTRAP') || !MWP_SKIP_BOOTSTRAP)) {
    exit;
}

if (!defined('MAX_PRIORITY_HOOK')) {
    define('MAX_PRIORITY_HOOK', 2147483647);
}

/**
 * Handler for incomplete plugin installations.
 */
if (!function_exists('mwp_fail_safe')):
    /**
     * Reserved memory for fatal error handling execution context.
     */
    $GLOBALS['mwp_reserved_memory'] = str_repeat(' ', 1024 * 20);
    /**
     * If we ever get only partially upgraded due to a server error or misconfiguration,
     * attempt to disable the plugin.
     */
    function mwp_fail_safe()
    {
        $GLOBALS['mwp_reserved_memory'] = null;

        $lastError = error_get_last();

        $acceptedErrorTypes = array(
            E_ERROR,
            E_COMPILE_ERROR,
        );

        if (!$lastError || !in_array($lastError['type'], $acceptedErrorTypes)) {
            return;
        }

        $activePlugins = get_option('active_plugins');
        $workerIndex   = array_search(plugin_basename(__FILE__), $activePlugins);
        if ($workerIndex === false) {
            // Plugin is not yet enabled, possibly in activation context.
            return;
        }

        $errorSource = realpath($lastError['file']);
        // We might be in eval() context.
        if (!$errorSource) {
            return;
        }

        // The only fatal error that we would get would be a 'Class 'X' not found in ...', so look out only for those messages.
        if (!preg_match('/^(Uncaught Error: )?Class \'[^\']+\' not found/', $lastError['message']) &&
            !preg_match('/^(Uncaught Error: )?Call to undefined method /', $lastError['message']) &&
            !preg_match('/^require_once\(\): Failed opening required \'[^\']+\'/', $lastError['message'])
        ) {
            return;
        }

        // Only look for files that belong to this plugin.
        $pluginBase = realpath(dirname(__FILE__));
        if (stripos($errorSource, $pluginBase) !== 0) {
            return;
        }

        // Signal ourselves that the installation is corrupt.
        update_option('mwp_recovering', time());

        $siteUrl = get_option('siteurl');
        $path    = (string)parse_url($siteUrl, PHP_URL_PATH);
        $title   = sprintf("ManageWP Worker corrupt on %s", $siteUrl);
        $to      = get_option('admin_email');
        $brand   = get_option('mwp_worker_brand');
        if (!empty($brand['admin_email'])) {
            $to = $brand['admin_email'];
        }

        $fullError = print_r($lastError, 1);
        $serviceID = (string)get_option('mwp_service_key');
        $body      = sprintf("Corrupt ManageWP Worker v%s installation detected. Site URL in question is %s. User email is %s (service ID: %s). Attempting recovery process at %s. The error that caused this:\n\n<pre>%s</pre>", $GLOBALS['MMB_WORKER_VERSION'], $siteUrl, $to, $serviceID, date('Y-m-d H:i:s'), $fullError);
        mail('recovery@managewp.com', $title, $body, "Content-Type: text/html");

        // If we're inside a cron scope, don't attempt to hide this error.
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        // If we're inside a normal request scope retry the request so user doesn't have to see an ugly error page.
        if (!empty($_SERVER['REQUEST_URI'])) {
            $siteUrl .= substr($_SERVER['REQUEST_URI'], strlen($path));
        }
        if (isset($_SERVER['HTTP_MWP_ACTION'])) {
            echo "\nMWP_RETRY_ME: 1\n", json_encode(array('error' => 'Worker recover started', 'exception' => array(
                'class'       => 'Exception',
                'message'     => 'Worker recover started',
                'code'        => 10038,
                'file'        => __FILE__,
                'line'        => __LINE__,
                'traceString' => '',
                'context'     => array(),
                'type'        => 'WORKER_RECOVER_STARTED',
            ))), "\n";
            exit;
        } elseif (headers_sent()) {
            // The headers are probably sent if the PHP configuration has the 'display_errors' directive enabled. In that case try a meta redirect.
            printf('<meta http-equiv="refresh" content="0; url=%s">', htmlspecialchars($siteUrl, ENT_QUOTES));
        } else {
            header('Location: '.htmlspecialchars($siteUrl, ENT_QUOTES));
        }
        exit;
    }

    register_shutdown_function('mwp_fail_safe');
endif;

if (!class_exists('MwpWorkerResponder', false)):
    /**
     * We're not allowed to use lambda functions because this is PHP 5.2, so use a responder
     * class that's able to access the service container.
     */
    class MwpWorkerResponder
    {

        private $container;

        private $responseSent = false;

        function __construct(MWP_ServiceContainer_Interface $container)
        {
            $this->container = $container;
        }

        /**
         * @param Exception|Error                 $e
         * @param MWP_Http_ResponseInterface|null $response
         *
         * @throws null
         */
        function callback($e = null, MWP_Http_ResponseInterface $response = null)
        {
            if ($response !== null) {
                $responseEvent = new MWP_Event_MasterResponse($response);
                $this->container->getEventDispatcher()->dispatch(MWP_Event_Events::MASTER_RESPONSE, $responseEvent);
                $lastResponse = $responseEvent->getResponse();

                if ($lastResponse !== null) {
                    if (!$this->responseSent) {
                        // This looks pretty ugly, but the "execute PHP" function handles fatal errors and wraps them
                        // in a valid action response. That fatal error may also be handled by the global fatal error
                        // handler, which also wraps the error in a response. We keep the state in this class, so we
                        // don't send a worker response twice, first time as an action response, second time as a
                        // global response.
                        // If this is to be removed, simply remove fatal error handling from the "execute PHP" action.
                        $lastResponse->send();
                        $this->responseSent = true;
                    }
                    exit;
                }
            } elseif ($e !== null) {
                // Exception is thrown and the response is empty. This should never happen, so don't try to hide it.
                throw $e;
            }
        }

        /**
         * @return callable
         */
        public function getCallback()
        {
            return array($this, 'callback');
        }
    }
endif;

if (!function_exists('mwp_container')):
    /**
     * @return MWP_ServiceContainer_Interface
     */
    function mwp_container()
    {
        static $container;

        if ($container === null) {
            $parameters = (array)get_option('mwp_container_parameters', array()) + (array)get_option('mwp_container_site_parameters', array());
            $requestId  = isset($_GET['mwprid']) && is_string($_GET['mwprid']) ? $_GET['mwprid'] : null;
            $container  = new MWP_ServiceContainer_Production(array(
                    'worker_realpath' => __FILE__,
                    'worker_basename' => 'worker/init.php',
                    'worker_version'  => $GLOBALS['MMB_WORKER_VERSION'],
                    'worker_revision' => $GLOBALS['MMB_WORKER_REVISION'],
                    'request_id'      => $requestId,
                ) + $parameters);
        }

        return $container;
    }
endif;

if (!class_exists('MwpRecoveryKit', false)):
    /**
     * This class must be isolated from the rest of the ManageWP Worker library, because
     * we're counting that we have only this file and WordPress bootstrapped.
     */
    class MwpRecoveryKit
    {
        const MAX_LOGGED_ERRORS = 5;

        private static $errorLog = array();

        private static function requestJson($url)
        {
            $response = wp_remote_get($url, array('timeout' => 60));
            if ($response instanceof WP_Error) {
                throw new Exception('Unable to download checksum.json: '.$response->get_error_message());
            }
            if ($response['response']['code'] !== 200) {
                throw new Exception('Unable to download checksum.json: invalid status code ('.$response['response']['code'].')');
            }

            $responseJson = json_decode($response['body'], true);

            if (empty($responseJson) || !is_array($responseJson)) {
                throw new Exception('Error while parsing checksum.json.');
            }

            return $responseJson;
        }

        public function recover($version)
        {
            global $wpdb;
            $lockTime = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'mwp_incremental_recover_lock' LIMIT 1");


            if ($lockTime && time() - (int)$lockTime < 1200) { // lock for 20 minutes
                throw new Exception('Another incremental update or recovery process is already active', 1337);
            }

            register_shutdown_function(array($this, 'releaseLock'));

            update_option('mwp_incremental_recover_lock', time());

            ignore_user_abort(true);
            $dirName           = realpath(dirname(__FILE__));
            $filesAndChecksums = $this->requestJson(sprintf('http://s3-us-west-2.amazonaws.com/mwp-orion-public/worker/raw/%s/checksum.json', $version));

            try {
                $files = $this->recoverFiles($dirName, $filesAndChecksums, $version);
            } catch (Exception $e) {
                $this->releaseLock();
                throw $e;
            }

            $this->releaseLock();

            return $files;
        }

        public function releaseLock()
        {
            delete_option('mwp_incremental_recover_lock');
        }

        public static function selfUpdate()
        {
            if (get_option('mwp_recovering')) {
                return false;
            }

            try {
                $response = self::requestJson('http://s3-us-west-2.amazonaws.com/mwp-orion-public/worker/latest.json');
                $response += array('version' => '0.0.0', 'schedule' => 86400, 'autoUpdate' => false, 'checksum' => array());
                wp_clear_scheduled_hook('mwp_auto_update');
                wp_schedule_single_event(current_time('timestamp') + $response['schedule'], 'mwp_auto_update');
                if (!$response['autoUpdate']) {
                    return false;
                }
                if (version_compare($response['version'], $GLOBALS['MMB_WORKER_VERSION'], '<')) {
                    return false;
                }
                self::recoverFiles(dirname(__FILE__), $response['checksum'], $response['version']);
            } catch (Exception $e) {
                mwp_logger()->error("Self-update failed.", array('exception' => $e));

                return false;
            }

            return true;
        }

        private static function clearUnknownFiles($filesAndChecksums, $fs)
        {
            /** @var WP_Filesystem_Base $fs */
            $base = dirname(__FILE__);
            if (version_compare(phpversion(), '5.3', '<')) {
                $directory = new RecursiveDirectoryIterator($base);
            } else {
                /** @handled constant */
                $directory = new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS);
            }

            $ignoreDelete = array(
                'log.html'      => 1,
                'worker.json'   => 1,
                'init.php'      => 1, // safe-guard
                'functions.php' => 1, // safe-guard
            );

            $files = array_keys(iterator_to_array(new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD)));

            foreach ($files as $file) {
                $file = preg_replace('/^'.preg_quote($base, '/').'/', '', $file, 1, $count);

                if (!$count) {
                    continue;
                }

                $file = strtr($file, '\\', '/');
                $file = ltrim($file, '/');

                if (isset($filesAndChecksums[$file]) || isset($ignoreDelete[$file])) {
                    continue;
                }

                $fs->delete($fs->find_folder(WP_PLUGIN_DIR).'worker/'.$file, false, 'f');
            }
        }

        public static function recoverFiles($dirName, array $filesAndChecksums, $version)
        {
            set_error_handler(array(__CLASS__, 'logError'));
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/template.php';

            $options = array();

            $fsMethod = get_filesystem_method();
            if ($fsMethod !== 'direct') {
                ob_start();
                $options = request_filesystem_credentials('');
                ob_end_clean();
            }

            /** @var WP_Filesystem_Base $fs */
            WP_Filesystem($options);
            $fs = $GLOBALS['wp_filesystem'];

            if (!$fs->connect()) {
                $lastError    = error_get_last();
                $errorMessage = $lastError ? $lastError['message'] : '(no error logged)';
                throw new Exception('Unable to connect to the file system: '.$errorMessage);
            }

            $cachedFilesAndChecksums = $filesAndChecksums;

            // First create directories and remove them from the array.
            // Must be done before shuffling because of nesting.
            foreach ($filesAndChecksums as $relativePath => $checksum) {
                if ($checksum !== '') {
                    continue;
                }
                unset ($filesAndChecksums[$relativePath]);
                $absolutePath = $dirName.'/'.$relativePath;
                // Directories are ordered first.
                if (!is_dir($absolutePath)) {
                    $fs->mkdir($fs->find_folder(WP_PLUGIN_DIR).'worker/'.$relativePath);
                }
            }

            // Check and recreate files. Shuffle them so multiple running instances have a smaller collision.
            $recoveredFiles    = array();
            $filesAndChecksums = self::shuffleAssoc($filesAndChecksums);
            $retryCount        = 0;
            $retryUpTo         = 5;
            $lastError         = null;
            while ($checksum = current($filesAndChecksums)) {
                if ($retryCount >= $retryUpTo) {
                    restore_error_handler();
                    throw new Exception($lastError);
                }
                $relativePath = key($filesAndChecksums);
                $absolutePath = $dirName.'/'.$relativePath;
                if (file_exists($absolutePath) && md5_file($absolutePath) === $checksum) {
                    next($filesAndChecksums);
                    continue;
                }
                $fileUrl  = sprintf('http://s3-us-west-2.amazonaws.com/mwp-orion-public/worker/raw/%s/%s', $version, $relativePath);
                $response = wp_remote_get($fileUrl, array('timeout' => 60));
                if ($response instanceof WP_Error) {
                    $lastError = 'Unable to download file '.$fileUrl.': '.$response->get_error_message();
                    $retryCount++;
                    continue;
                }
                if ($response['response']['code'] !== 200) {
                    $lastError = 'Unable to download file '.$fileUrl.': invalid status code ('.$response['response']['code'].')';
                    $retryCount++;
                    continue;
                }
                $saved = $fs->put_contents($fs->find_folder(WP_PLUGIN_DIR).'worker/'.$relativePath, $response['body']);

                if (!$saved) {
                    if (is_callable(array($fs, '__destruct'))) {
                        $fs->__destruct();
                    }
                    $fs->connect();
                    $lastError = 'File saving failed.';
                    if (count(self::$errorLog)) {
                        $lastError .= sprintf(" Last %d logged errors:%s", min(self::MAX_LOGGED_ERRORS, count(self::$errorLog)), "\n - ".implode("\n - ", self::$errorLog));
                    }
                    $retryCount++;
                    continue;
                }

                $lastError        = null;
                $retryCount       = 0;
                $recoveredFiles[] = $relativePath;
                next($filesAndChecksums);
            }

            self::clearUnknownFiles($cachedFilesAndChecksums, $fs);

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            restore_error_handler();

            return $recoveredFiles;
        }

        public static function logError($code, $message, $file = 'Unknown', $line = 0)
        {
            self::$errorLog[] = sprintf('Error [%d]: %s in %s on line %d', $code, $message, $file, $line);

            if (count(self::$errorLog) > self::MAX_LOGGED_ERRORS) {
                array_shift(self::$errorLog);
            }
        }

        private static function shuffleAssoc($array)
        {
            $keys = array_keys($array);
            shuffle($keys);
            $shuffled = array();
            foreach ($keys as $key) {
                $shuffled[$key] = $array[$key];
            }

            return $shuffled;
        }

        public function selfDeactivate($reason)
        {
            if (isset($_SERVER['MWP2_VERSION_ID'])) {
                return;
            }

            $activePlugins = get_option('active_plugins');
            $workerIndex   = array_search(plugin_basename(__FILE__), $activePlugins);
            if ($workerIndex === false) {
                // Plugin is not yet enabled, possibly in activation context.
                return;
            }
            unset($activePlugins[$workerIndex]);
            // Reset indexes.
            $activePlugins = array_values($activePlugins);

            delete_option('mwp_recovering');
            update_option('active_plugins', $activePlugins);

            $lastErrorMessage = '';
            if ($lastError = error_get_last()) {
                $lastErrorMessage = "\n\nLast error: ".$lastError['message'];
            }
            mail('recovery@managewp.com', sprintf("ManageWP Worker recovery aborted on %s", get_option('siteurl')), sprintf("ManageWP Worker v%s. Reason: %s%s", $GLOBALS['MMB_WORKER_VERSION'], $reason, $lastErrorMessage));
        }
    }
endif;

if (!function_exists('mwp_activation_hook')) {
    function mwp_activation_hook()
    {
        update_option('mwp_incremental_update_active', '');

        if (get_option('mwp_recovering')) {
            update_option('mwp_recovering', '');
            // Run the checksum one last time.
            $recoveryKit = new MwpRecoveryKit();
            try {
                $recoveryKit->recover($GLOBALS['MMB_WORKER_VERSION']);
            } catch (Exception $e) {
                // Deactivating the plugin in activation hook wouldn't work, prevent the activation by triggering an error.
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        mwp_core()->install();
    }
}

if (!function_exists('mwp_try_recovery')):
    function mwp_try_recovery()
    {
        global $wpdb;
        $recoveringTime = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'mwp_recovering' LIMIT 1");

        if (empty($recoveringTime)) {
            return true;
        }

        delete_transient('mwp_recovery_key');
        $recoveryKit = new MwpRecoveryKit();
        try {
            $recoveredFiles = $recoveryKit->recover($GLOBALS['MMB_WORKER_VERSION']);

            // Recovery complete.
            update_option('mwp_recovering', '');
            mail('recovery@managewp.com', sprintf("ManageWP Worker recovered on %s", get_option('siteurl')), sprintf("%d files successfully recovered in this recovery fork of ManageWP Worker v%s. Filesystem method used was <code>%s</code>.\n\n<pre>%s</pre>", count($recoveredFiles), $GLOBALS['MMB_WORKER_VERSION'], get_filesystem_method(), implode("\n", $recoveredFiles)), 'Content-Type: text/html');
        } catch (Exception $e) {
            if ($e->getCode() === 1337) {
                return false;
            }

            if (time() - $recoveringTime > 3600) {
                // If the recovery process does not complete after an hour, deactivate the Worker for safety
                $recoveryKit->selfDeactivate($e->getMessage());
            }

            return false;
        }

        return true;
    }
endif;

if (!function_exists('add_worker_update_info')):
    function add_worker_update_info()
    {
        echo ' The plugin is going to update itself automatically in the next few days.';
    }
endif;

if (!function_exists('mwp_init')):
    function mwp_init()
    {
        // When the plugin deactivates due to a corrupt installation, (de)activation hooks
        // will never get executed, so the 'mwp_recovering' option will never be deleted,
        // making the plugin always force the recovery mode , which may always fail for any
        // reason (eg. the site can't ping itself). Handle that case early.
        register_activation_hook(__FILE__, 'mwp_activation_hook');

        $GLOBALS['MMB_WORKER_VERSION']  = '4.9.13';
        $GLOBALS['MMB_WORKER_REVISION'] = '2021-12-27 00:00:00';

        // Ensure PHP version compatibility.
        if (version_compare(PHP_VERSION, '5.2', '<')) {
            trigger_error("ManageWP Worker plugin requires PHP 5.2 or higher.", E_USER_ERROR);
            exit;
        }

        if ($incrementalUpdateTime = get_option('mwp_incremental_update_active')) {
            if (time() - $incrementalUpdateTime > 600) { // lock for a maximum of 10 minutes for incremental update
                update_option('mwp_incremental_update_active', '');
            } else {
                if (!isset($_SERVER['HTTP_MWP_ACTION'])) {
                    return;
                }

                global $wpdb;

                $tries      = 0;
                $lastResult = true;

                while ($tries < 60 && ($lastResult = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'mwp_incremental_update_active' LIMIT 1"))) {
                    sleep(1);
                    ++$tries;
                }

                if (!$lastResult) {
                    echo "\nMWP_RETRY_ME: 1\n";
                }

                echo "\n", json_encode(array('error' => 'Worker is currently updating; please retry this action in a few seconds.', 'exception' => array(
                    'class'       => 'Exception',
                    'message'     => 'Worker is currently updating; please retry this action in a few seconds.',
                    'code'        => 10037,
                    'file'        => __FILE__,
                    'line'        => __LINE__,
                    'traceString' => '',
                    'context'     => array(),
                    'type'        => 'WORKER_UPDATING',
                ))), "\n";
                exit;
            }
        }

        if ($recoveringTime = get_option('mwp_recovering')) {
            if (isset($_SERVER['HTTP_MWP_ACTION'])) {
                $tries      = 0;
                $lastResult = false;

                while ($tries < 60 && !($lastResult = mwp_try_recovery())) {
                    sleep(1);
                    ++$tries;
                }

                if ($lastResult) {
                    echo "\nMWP_RETRY_ME: 1\n";
                }

                echo "\n", json_encode(array('error' => 'Worker is currently recovering; please retry this action in a few seconds.', 'exception' => array(
                    'class'       => 'Exception',
                    'message'     => 'Worker is currently recovering; please retry this action in a few seconds.',
                    'code'        => 10036,
                    'file'        => __FILE__,
                    'line'        => __LINE__,
                    'traceString' => '',
                    'context'     => array(),
                    'type'        => 'WORKER_RECOVERING',
                ))), "\n";

                exit;
            } else {
                $recoveryKey = get_transient('mwp_recovery_key');
                if (!$passedRecoveryKey = filter_input(INPUT_POST, 'mwp_recovery_key')) {
                    $recoveryKey = md5(uniqid('', true));
                    set_transient('mwp_recovery_key', $recoveryKey, time() + 604800); // 1 week.

                    $headers = array();
                    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                        $headers['AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
                    }

                    // fork only once, so we do not make too many parallel requests to the website
                    $lockTime = get_option('mwp_incremental_recover_lock');

                    if ($lockTime && time() - $lockTime < 1200) { // lock for 20 minutes
                        return;
                    }

                    wp_remote_post(get_bloginfo('wpurl'), array(
                        'reject_unsafe_urls' => false,
                        'headers'            => $headers,
                        'body'               => array(
                            'mwp_recovery_key' => $recoveryKey,
                        ),
                        'timeout'            => 0.01,
                    ));
                } else {
                    if ($recoveryKey !== $passedRecoveryKey) {
                        return;
                    }

                    mwp_try_recovery();
                }

                return;
            }
        }

        if (version_compare(PHP_VERSION, '5.3', '<')) {
            spl_autoload_register('mwp_autoload');
        } else {
            // The prepend parameter was added in PHP 5.3.0
            spl_autoload_register('mwp_autoload', true, true);
        }

        $GLOBALS['mmb_plugin_dir']   = WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__));
        $GLOBALS['_mmb_item_filter'] = array();
        $core                        = mwp_core();

        $siteUrl = function_exists('get_site_option') ? get_site_option('siteurl') : get_option('siteurl');
        define('MMB_XFRAME_COOKIE', 'wordpress_'.md5($siteUrl).'_xframe');

        define('MWP_BACKUP_DIR', WP_CONTENT_DIR.'/managewp/backups');
        define('MWP_DB_DIR', MWP_BACKUP_DIR.'/mwp_db');

        add_filter('deprecated_function_trigger_error', '__return_false');
        add_action('mwp_update_public_keys', 'mwp_refresh_live_public_keys');
        add_action('init', 'mmb_plugin_actions', 99999);
        add_filter('install_plugin_complete_actions', 'mmb_iframe_plugins_fix');
        add_filter('comment_edit_redirect', 'mwb_edit_redirect_override');
        add_action('mwp_auto_update', 'MwpRecoveryKit::selfUpdate');
        add_action('in_plugin_update_message-'.plugin_basename(__FILE__), 'add_worker_update_info');

        add_filter('cron_schedules', 'mwp_link_monitor_cron_recurrence_interval');
        add_action('mwp_check_for_post_update', 'mwp_send_posts_to_link_monitor');
        if (mwp_context()->optionGet('mwp_link_monitor_enabled')) {
            add_action('save_post', 'mwp_add_post_to_link_monitor_check');
            add_action('delete_post', 'mwp_add_post_to_link_monitor_check');

            if (!wp_next_scheduled('mwp_check_for_post_update')) {
                wp_schedule_event(time(), 'every_five_minutes', 'mwp_check_for_post_update');
            }
        }
        // Public key updating cron.
        if (!wp_next_scheduled('mwp_update_public_keys')) {
            wp_schedule_event(time(), 'daily', 'mwp_update_public_keys');
        }

        register_deactivation_hook(__FILE__, array($core, 'deactivate'));
        register_uninstall_hook(dirname(__FILE__).'/functions.php', 'mwp_uninstall');

        // Don't send the "X-Frame-Options: SAMEORIGIN" header if we're logging in inside an iframe.
        if (isset($_COOKIE[MMB_XFRAME_COOKIE])) {
            remove_action('admin_init', 'send_frame_options_header');
            remove_action('login_init', 'send_frame_options_header');
        }

        // Remove legacy scheduler.
        if (wp_next_scheduled('mwp_backup_tasks')) {
            wp_clear_scheduled_hook('mwp_backup_tasks');
        }
        mwp_provision_keys();
        mwp_set_plugin_priority();

        $request   = MWP_Worker_Request::createFromGlobals();
        $container = mwp_container();
        $responder = new MwpWorkerResponder($container);

        $kernel = new MWP_Worker_Kernel($container);
        $kernel->handleRequest($request, $responder->getCallback(), true);

        $mwpMM = get_option('mwp_maintenace_mode');
        if (!empty($mwpMM) && $mwpMM['active']) {
            add_action('admin_notices', 'site_in_mwp_maintenance_mode');
        }
    }

    if (!defined('MWP_SKIP_BOOTSTRAP') || !MWP_SKIP_BOOTSTRAP) {
        if (!get_option('mwp_recovering')) {
            require_once dirname(__FILE__).'/functions.php';
        }

        mwp_init();
    }
endif;
