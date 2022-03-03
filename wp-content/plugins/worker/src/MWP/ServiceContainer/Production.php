<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_ServiceContainer_Production extends MWP_ServiceContainer_Abstract
{
    /**
     * @return MWP_WordPress_Context
     */
    protected function createWordPressContext()
    {
        return new MWP_WordPress_Context();
    }

    /**
     * @return Symfony_EventDispatcher_EventDispatcherInterface
     */
    protected function createEventDispatcher()
    {
        $dispatcher = new Symfony_EventDispatcher_EventDispatcher();

        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_BrandContactSupport($this->getWordPressContext(), $this->getBrand(), $this->getRequestStack()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_DisableEditor($this->getWordPressContext(), $this->getBrand()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_SetPluginInfo($this->getWordPressContext(), $this->getBrand()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_AddConnectionKeyInfo($this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_SetHitCounter($this->getWordPressContext(), $this->getHitCounter(), $this->getRequestStack(), $this->getParameter('hit_counter_blacklisted_ips'), $this->getParameter('hit_counter_blacklisted_user_agents')));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_AutomaticLogin($this->getWordPressContext(), $this->getNonceManager(), $this->getSigner(), $this->getConfiguration(), $this->getSessionStore()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_AddStatusPage($this->getWordPressContext(), $this->getConfiguration()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_CommandListener($this->getWordPressContext(), $this->getSigner(), $this->getConfiguration(), $this->getNonceManager()));

        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AuthenticateServiceRequest($this->getConfiguration(), $this->getSigner(), $this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_VerifyConnectionInfo($this->getWordPressContext(), $this->getSigner()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AuthenticateRequest($this->getConfiguration(), $this->getSigner(), $this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_SetErrorHandler($this->getErrorLogger(), $this->getErrorHandler(), $this->getRequestStack(), $this->getResponseCallback(), $this, $this->getParameter('log_errors'), $this->getParameter('fatal_error_reserved_memory_size')));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AttachJsonMessageHandler($this->getLogger(), $this->getJsonMessageHandler()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_RemoveUsernameParam());
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AuthenticateLegacyRequest($this->getConfiguration()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_SetRequestSettings($this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_SetCurrentUser($this->getWordPressContext()));

        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_VerifyNonce($this->getNonceManager()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_SetSettings($this->getWordPressContext(), $this->getSystemEnvironment(), $this->getMigration()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_LogRequest($this->getLogger()));

        $dispatcher->addSubscriber(new MWP_EventListener_ActionException_SetExceptionData($this->getConfiguration()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionException_MultipartException($this->getParameter('multipart_boundary')));

        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetActionData());
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetLegacyWebsiteConnectionData($this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_ChainState($this));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetUpdaterLog($this->getUpdaterSkin()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetLegacyPhpExecutionData());
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_FetchFiles($this->getParameter('multipart_boundary')));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_DownloadFile($this->getParameter('multipart_boundary')));

        $dispatcher->addSubscriber(new MWP_EventListener_MasterResponse_LogResponse($this->getLogger()));

        $dispatcher->addSubscriber(new MWP_EventListener_FixCompatibility($this->getWordPressContext()));

        $dispatcher->addSubscriber(new MWP_EventListener_EncodeMasterResponse());

        return $dispatcher;
    }

    protected function createRequestStack()
    {
        return new MWP_Worker_RequestStack();
    }

    /**
     * @return MWP_Action_Registry
     */
    protected function createActionRegistry()
    {
        $mapper = new MWP_Action_Registry();

        $mapper->addDefinition('do_upgrade', new MWP_Action_Definition('mmb_do_upgrade', array('hook_name' => 'wp_loaded', 'hook_priority' => MAX_PRIORITY_HOOK)));
        $mapper->addDefinition('remove_site', new MWP_Action_Definition('mmb_remove_site'));
        $mapper->addDefinition('update_worker', new MWP_Action_Definition('mmb_update_worker_plugin'));
        $mapper->addDefinition('install_addon', new MWP_Action_Definition('mmb_install_addon', array('hook_name' => 'wp_loaded', 'hook_priority' => MAX_PRIORITY_HOOK)));
        $mapper->addDefinition('bulk_action_comments', new MWP_Action_Definition('mmb_bulk_action_comments', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('add_user', new MWP_Action_Definition('mmb_add_user', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('execute_php_code', new MWP_Action_Definition('mmb_execute_php_code'));
        $mapper->addDefinition('edit_users', new MWP_Action_Definition('mmb_edit_users', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('edit_plugins_themes', new MWP_Action_Definition('mmb_edit_plugins_themes', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('worker_brand', new MWP_Action_Definition('mmb_worker_brand'));
        $mapper->addDefinition('maintenance', new MWP_Action_Definition('mmb_maintenance_mode'));
        $mapper->addDefinition('cleanup_delete', new MWP_Action_Definition('cleanup_delete_worker', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_state', new MWP_Action_Definition(array('MWP_Action_GetState', 'execute')));
        $mapper->addDefinition('add_site', new MWP_Action_Definition(array('MWP_Action_ConnectWebsite', 'execute')));
        $mapper->addDefinition('destroy_sessions', new MWP_Action_Definition(array('MWP_Action_DestroySessions', 'execute')));
        $mapper->addDefinition('clear_transient', new MWP_Action_Definition(array('MWP_Action_ClearTransient', 'execute')));
        $mapper->addDefinition('get_stats', new MWP_Action_Definition('mwp_get_stats', array('hook_name' => 'wp_loaded', 'hook_priority' => MAX_PRIORITY_HOOK)));
        $mapper->addDefinition('get_components_stats', new MWP_Action_Definition(array('MWP_Action_GetComponentsStats', 'execute')));
        $mapper->addDefinition('upload_file_action', new MWP_Action_Definition('mmb_upload_file_action'));
        $mapper->addDefinition('download_file_action', new MWP_Action_Definition(array('MWP_Action_DownloadFile', 'execute')));

        // Incremental backup actions
        $mapper->addDefinition('list_files', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_ListFiles', 'queryFiles')));
        $mapper->addDefinition('list_directories', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_ListFiles', 'listDirectories')));
        $mapper->addDefinition('hash_files', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_HashFiles', 'execute')));
        $mapper->addDefinition('fetch_files', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_FetchFiles', 'execute')));
        $mapper->addDefinition('list_tables', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_ListTables', 'listTables')));
        $mapper->addDefinition('checksum_tables', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_ChecksumTables', 'execute')));

        // dump_tables is used before 4.1.8 for streaming
        // Note that dump_tables is used for the ActionResponse_FetchFiles listener
        $mapper->addDefinition('dump_tables', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_StreamTables', 'execute')));

        $mapper->addDefinition('backup_stats', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_Stats', 'execute')));
        $mapper->addDefinition('get_table_schema', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_GetTableSchema', 'execute')));
        $mapper->addDefinition('get_view_schema', new MWP_Action_Definition(array('MWP_Action_IncrementalBackup_GetViewSchema', 'execute')));

        return $mapper;
    }

    protected function createUserQuery()
    {
        return new MWP_WordPress_Query_User($this->getWordPressContext());
    }

    protected function createPostQuery()
    {
        return new MWP_WordPress_Query_Post($this->getWordPressContext());
    }

    protected function createCommentQuery()
    {
        return new MWP_WordPress_Query_Comment($this->getWordPressContext());
    }

    protected function createPluginProvider()
    {
        return new MWP_WordPress_Provider_Plugin($this->getWordPressContext());
    }

    protected function createThemeProvider()
    {
        return new MWP_WordPress_Provider_Theme($this->getWordPressContext());
    }

    protected function createWorkerBrand()
    {
        return new MWP_Worker_Brand($this->getWordPressContext());
    }

    protected function createAutoUpdateManager()
    {
        return new MWP_Updater_AutoUpdateManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Updater_UpdateManager
     */
    protected function createUpdateManager()
    {
        return new MWP_Updater_UpdateManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Signer_Interface
     */
    public function createSigner()
    {
        if ($this->getSystemEnvironment()->isOpenSslLibraryEnabled()) {
            return MWP_Signer_Factory::createOpenSslSigner();
        }

        return MWP_Signer_Factory::createPhpSecLibSigner();
    }

    /**
     * @return MWP_Crypter_Interface
     */
    public function createCrypter()
    {
        if ($this->getSystemEnvironment()->isOpenSslLibraryEnabled()) {
            return MWP_Crypter_Factory::createOpenSslCrypter();
        }

        return MWP_Crypter_Factory::createPhpSecLibCrypter();
    }

    /**
     * @return MWP_Security_NonceManager
     */
    protected function createNonceManager()
    {
        return new MWP_Security_NonceManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Worker_Configuration
     */
    protected function createConfiguration()
    {
        return new MWP_Worker_Configuration($this->getWordPressContext());
    }

    private function createLogStream($file)
    {
        $filePath = dirname(__FILE__) . '/../../../' . $file;

        if (!is_writable(dirname($filePath))) {
            return false;
        }

        $logFile = @fopen($filePath, 'a');

        if ($logFile === false) {
            return false;
        }

        return $logFile;
    }

    protected function createLogger()
    {
        $handlers = array();

        $fileLogging = $this->getParameter('log_file');
        $gelfLogging = $this->getParameter('gelf_server');

        if ($fileLogging || $gelfLogging) {
            $logStart = $this->getParameter('log_start');

            // Save when the first log started
            if (!$logStart) {
                $parameters = $this->getWordPressContext()->optionGet('mwp_container_parameters');

                $parameters['log_start'] = time();

                $this->getWordPressContext()->optionSet('mwp_container_parameters', $parameters);
            }

            // Logs can only go on for two days, always delete them after that
            if ($logStart && time() - $logStart > 172800) {
                // delete log file and disable logging
                if ($fileLogging && @is_file(dirname(__FILE__) . '/../../../' . $fileLogging)) {
                    @unlink(dirname(__FILE__) . '/../../../' . $fileLogging);
                }
                $parameters = $this->getWordPressContext()->optionGet('mwp_container_parameters');

                $fileLogging = null;
                $gelfLogging = null;

                $parameters['gelf_server'] = null;
                $parameters['log_file'] = null;
                $parameters['log_start'] = false;

                $this->getWordPressContext()->optionSet('mwp_container_parameters', $parameters);
            }
        } elseif ($fileLogging && @is_file(dirname(__FILE__) . '/../../../' . $fileLogging)) {
            @unlink(dirname(__FILE__) . '/../../../' . $fileLogging);
        }

        if ($fileLogging && ($logFile = $this->createLogStream($fileLogging))) {
            $fileHandler = new Monolog_Handler_StreamHandler($logFile);
            $fileHandler->setFormatter(new Monolog_Formatter_HtmlFormatter());
            $handlers[] = $fileHandler;
        }

        if ($gelfLogging) {
            $publisher = $this->getGelfPublisher();
            $handlers[] = new Monolog_Handler_LegacyGelfHandler($publisher);
        }

        $processors = array();
        if (count($handlers) > 0) {
            // We do have some loggers set up.
            $processors += array(
                array(new Monolog_Processor_MemoryUsageProcessor(), 'callback'),
                array(new Monolog_Processor_MemoryPeakUsageProcessor(), 'callback'),
                array(new Monolog_Processor_IntrospectionProcessor(), 'callback'),
                array(new Monolog_Processor_PsrLogMessageProcessor(), 'callback'),
                array(new Monolog_Processor_UidProcessor(), 'callback'),
                array(new Monolog_Processor_WebProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_TimeUsageProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_ExceptionProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_ProcessProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_RequestIdProcessor($this->getParameter('request_id')), 'callback'),
            );
        }
        // Always push this handler, because Monolog attaches an STDERR handler if there's no other present.
        $handlers[] = new Monolog_Handler_NullHandler(1000);

        $logger = new Monolog_Logger('worker', $handlers, $processors);

        return $logger;
    }

    protected function createResponseCallback()
    {
        return new MWP_Worker_ResponseCallback();
    }

    protected function createErrorHandler()
    {
        return new Monolog_ErrorHandler($this->getErrorLogger());
    }

    /**
     * @return Symfony_Process_ExecutableFinder
     */
    protected function createExecutableFinder()
    {
        $finder = new MWP_Process_ExecutableFinder();

        $db = $this->getWordPressContext()->getDb();

        if (is_callable(array($db, 'get_var'))) {
            $basePath = rtrim($db->get_var('select @@basedir'), '/\\');
            if ($basePath) {
                $basePath .= '/bin';
                $finder->addExtraDir($basePath);
            }
        }

        return $finder;
    }

    /**
     * @return MWP_Extension_HitCounter
     */
    protected function createHitCounter()
    {
        $counter = new MWP_Extension_HitCounter($this->getWordPressContext(), 60);

        return $counter;
    }

    /**
     * @return MWP_Monolog_Handler_JsonMessageHandler
     */
    protected function createJsonMessageHandler()
    {
        $handler = new MWP_Monolog_Handler_JsonMessageHandler($this->getParameter('message_minimum_level'));
        $handler->setPadLength($this->getParameter('message_pad_length'));

        return $handler;
    }

    /**
     * @return Monolog_Logger
     */
    protected function createErrorLogger()
    {
        return $this->getLogger();
    }

    /**
     * @return MWP_System_Environment
     */
    protected function createSystemEnvironment()
    {
        return new MWP_System_Environment($this);
    }

    /**
     * @return MWP_Updater_TraceableUpdaterSkin
     */
    protected function createUpdaterSkin()
    {
        return new MWP_Updater_TraceableUpdaterSkin();
    }

    /**
     * @return MWP_WordPress_SessionStore
     */
    protected function createSessionStore()
    {
        return new MWP_WordPress_SessionStore($this->getWordPressContext());
    }

    /**
     * @return MWP_Migration_Migration
     */
    protected function createMigration()
    {
        return new MWP_Migration_Migration($this->getWordPressContext());
    }

    protected function createGelfPublisher()
    {
        $server = $this->getParameter('gelf_server');
        $port = $this->getParameter('gelf_port');
        $fallbackPort = $this->getParameter('gelf_port_fallback');

        return new Gelf_Publisher($server, $port, $fallbackPort);
    }
}
