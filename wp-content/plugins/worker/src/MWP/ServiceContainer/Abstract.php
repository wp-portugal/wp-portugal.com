<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_ServiceContainer_Abstract implements MWP_ServiceContainer_Interface
{

    private $parameters;

    private $wordPressContext;

    private $eventDispatcher;

    private $actionMapper;

    private $requestStack;

    private $userQuery;

    private $postQuery;

    private $commentQuery;

    private $pluginProvider;

    private $themeProvider;

    private $workerBrand;

    private $autoUpdateManager;

    private $updateManager;

    private $signer;

    private $crypter;

    private $nonceManager;

    private $configuration;

    private $logger;

    private $responseCallback;

    private $errorHandler;

    private $executableFinder;

    private $hitCounter;

    private $jsonMessageHandler;

    private $errorLogger;

    private $systemEnvironment;

    private $updaterSkin;

    private $sessionStore;

    private $migration;

    private $gelfPublisher;

    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;

        $this->parameters += array(
            'request_id'                          => null,
            // This plugin's main file absolute path.
            'worker_realpath'                     => null,
            // This plugin's "basename", ie. 'worker/init.php'.
            'worker_basename'                     => null,
            // Always use PhpSecLib, even if the PHP extension 'openssl' is loaded.
            'prefer_phpseclib'                    => false,
            // Force using fallbacks instead of shell commands
            'disable_shell'                       => false,
            // Emulate disabled PDO extension
            'disable_pdo'                         => false,
            // Emulate disabled mysqli extension
            'disable_mysqli'                      => false,
            // Emulate disabled mysql
            'disable_mysql'                       => false,
            // Do not do self request on the website
            'disable_ping_back'                   => false,
            // When was the logging started (timestamp)
            'log_start'                           => false,
            // Log file to use for all worker logs.
            'log_file'                            => null,
            // GrayLog2 server to use for all worker logs.
            'gelf_server'                         => null,
            // UDP port.
            'gelf_port'                           => null,
            // TCP port.
            'gelf_port_fallback'                  => null,
            // Capture errors in master response.
            'log_errors'                          => false,
            // Pad length used for progress message flushing.
            'message_pad_length'                  => 16384,
            // Minimum log level for streamed messages.
            'message_minimum_level'               => Monolog_Logger::INFO,
            // Memory size (in kilobytes) to allocate for fatal error handling when the request is authenticated.
            'fatal_error_reserved_memory_size'    => 1024,
            // This boundary will be different with each request and it's pretty much a hack for handling exceptions
            // @see MWP_EventListener_ActionException_MultipartException
            'multipart_boundary'                  => uniqid(),
            'hit_counter_blacklisted_ips'         => array(
                // Uptime monitoring robot.
                '/^46\.137\.190\.132$/',
                '/^54\.79\.28\.129$/',
                '/^54\.94\.142\.218$/',
                '/^54\.67\.10\.127$/',
                '/^54\.64\.67\.106$/',
                '/^69\.162\.124\.226$/',
                '/^69\.162\.124\.227$/',
                '/^69\.162\.124\.228$/',
                '/^69\.162\.124\.229$/',
                '/^69\.162\.124\.230$/',
                '/^69\.162\.124\.231$/',
                '/^69\.162\.124\.232$/',
                '/^69\.162\.124\.233$/',
                '/^69\.162\.124\.234$/',
                '/^69\.162\.124\.235$/',
                '/^69\.162\.124\.236$/',
                '/^69\.162\.124\.237$/',
                '/^69\.162\.124\.238$/',
                '/^74\.86\.158\.106$/',
                '/^74\.86\.158\.107$/',
                '/^74\.86\.158\.109$/',
                '/^74\.86\.158\.110$/',
                '/^74\.86\.158\.108$/',
                '/^122\.248\.234\.23$/',
                '/^178\.62\.52\.237$/',
                '/^188\.226\.183\.141$/',
            ),
            'hit_counter_blacklisted_user_agents' => array(
                '/bot/',
                '/crawl/',
                '/feed/',
                '/java\//',
                '/spider/',
                '/curl/',
                '/libwww/',
                '/alexa/',
                '/altavista/',
                '/aolserver/',
                '/appie/',
                '/Ask Jeeves/',
                '/baidu/',
                '/Bing/',
                '/borg/',
                '/BrowserMob/',
                '/ccooter/',
                '/dataparksearch/',
                '/Download Demon/',
                '/echoping/',
                '/FAST/',
                '/findlinks/',
                '/Firefly/',
                '/froogle/',
                '/GomezA/',
                '/Google/',
                '/grub-client/',
                '/htdig/',
                '/http%20client/',
                '/HttpMonitor/',
                '/ia_archiver/',
                '/InfoSeek/',
                '/inktomi/',
                '/larbin/',
                '/looksmart/',
                '/Microsoft URL Control/',
                '/Minimo/',
                '/mogimogi/',
                '/NationalDirectory/',
                '/netcraftsurvey/',
                '/nuhk/',
                '/oegp/',
                '/panopta/',
                '/rabaz/',
                '/Read%20Later/',
                '/Scooter/',
                '/scrubby/',
                '/SearchExpress/',
                '/searchsight/',
                '/semanticdiscovery/',
                '/Slurp/',
                '/snappy/',
                '/Spade/',
                '/TechnoratiSnoop/',
                '/TECNOSEEK/',
                '/teoma/',
                '/twiceler/',
                '/URL2PNG/',
                '/vortex/',
                '/WebBug/',
                '/www\.galaxy\.com/',
                '/yahoo/',
                '/yandex/',
                '/zao/',
                '/zeal/',
                '/ZooShot/',
                '/ZyBorg/',
            ),
        );
    }

    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new InvalidArgumentException(sprintf('The parameter named "%s" does not exist.', $name));
        }

        return $this->parameters[$name];
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function getWordPressContext()
    {
        if ($this->wordPressContext === null) {
            $this->wordPressContext = $this->createWordPressContext();
        }

        return $this->wordPressContext;
    }

    /**
     * @return MWP_WordPress_Context
     */
    protected abstract function createWordPressContext();

    public function getEventDispatcher()
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = $this->createEventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * @return Symfony_EventDispatcher_EventDispatcherInterface
     */
    protected abstract function createEventDispatcher();

    public function getRequestStack()
    {
        if ($this->requestStack === null) {
            $this->requestStack = $this->createRequestStack();
        }

        return $this->requestStack;
    }

    protected abstract function createRequestStack();

    public function getActionRegistry()
    {
        if ($this->actionMapper === null) {
            $this->actionMapper = $this->createActionRegistry();
        }

        return $this->actionMapper;
    }

    /**
     * @return MWP_Action_Registry
     */
    protected abstract function createActionRegistry();

    public function getUserQuery()
    {
        if ($this->userQuery === null) {
            $this->userQuery = $this->createUserQuery();
        }

        return $this->userQuery;
    }

    /**
     * @return MWP_WordPress_Query_User
     */
    protected abstract function createUserQuery();

    public function getPostQuery()
    {
        if ($this->postQuery === null) {
            $this->postQuery = $this->createPostQuery();
        }

        return $this->postQuery;
    }

    protected abstract function createPostQuery();

    public function getCommentQuery()
    {
        if ($this->commentQuery === null) {
            $this->commentQuery = $this->createCommentQuery();
        }

        return $this->commentQuery;
    }

    protected abstract function createCommentQuery();

    /**
     * @return MWP_WordPress_Provider_Plugin
     */
    public function getPluginProvider()
    {
        if ($this->pluginProvider === null) {
            $this->pluginProvider = $this->createPluginProvider();
        }

        return $this->pluginProvider;
    }

    protected abstract function createPluginProvider();

    /**
     * @return MWP_WordPress_Provider_Theme
     */
    public function getThemeProvider()
    {
        if ($this->themeProvider === null) {
            $this->themeProvider = $this->createThemeProvider();
        }

        return $this->themeProvider;
    }

    protected abstract function createThemeProvider();

    /**
     * @return MWP_Worker_Brand
     */
    public function getBrand()
    {
        if ($this->workerBrand === null) {
            $this->workerBrand = $this->createWorkerBrand();
        }

        return $this->workerBrand;
    }

    protected abstract function createWorkerBrand();

    public function getAutoUpdateManager()
    {
        if ($this->autoUpdateManager === null) {
            $this->autoUpdateManager = $this->createAutoUpdateManager();
        }

        return $this->autoUpdateManager;
    }

    protected abstract function createAutoUpdateManager();

    public function getUpdateManager()
    {
        if ($this->updateManager === null) {
            $this->updateManager = $this->createUpdateManager();
        }

        return $this->updateManager;
    }

    /**
     * @return MWP_Updater_UpdateManager
     */
    protected abstract function createUpdateManager();

    public function getSigner()
    {
        if ($this->signer === null) {
            $this->signer = $this->createSigner();
        }

        return $this->signer;
    }

    /**
     * @return MWP_Signer_Interface
     */
    protected abstract function createSigner();

    public function getCrypter()
    {
        if ($this->crypter === null) {
            $this->crypter = $this->createCrypter();
        }

        return $this->crypter;
    }

    /**
     * @return MWP_Crypter_Interface
     */
    protected abstract function createCrypter();

    public function getNonceManager()
    {
        if ($this->nonceManager === null) {
            $this->nonceManager = $this->createNonceManager();
        }

        return $this->nonceManager;
    }

    /**
     * @return MWP_Security_NonceManager
     */
    protected abstract function createNonceManager();

    public function getConfiguration()
    {
        if ($this->configuration === null) {
            $this->configuration = $this->createConfiguration();
        }

        return $this->configuration;
    }

    /**
     * @return MWP_Worker_Configuration
     */
    protected abstract function createConfiguration();

    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = $this->createLogger();
        }

        return $this->logger;
    }

    /**
     * @return Monolog_Logger
     */
    protected abstract function createLogger();

    /**
     * @return MWP_Worker_ResponseCallback
     */
    public function getResponseCallback()
    {
        if ($this->responseCallback === null) {
            $this->responseCallback = $this->createResponseCallback();
        }

        return $this->responseCallback;
    }

    protected abstract function createResponseCallback();

    public function getErrorHandler()
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = $this->createErrorHandler();
        }

        return $this->errorHandler;
    }

    /**
     * @return Monolog_ErrorHandler
     */
    protected abstract function createErrorHandler();

    public function getExecutableFinder()
    {
        if ($this->executableFinder === null) {
            $this->executableFinder = $this->createExecutableFinder();
        }

        return $this->executableFinder;
    }

    /**
     * @return Symfony_Process_ExecutableFinder
     */
    protected abstract function createExecutableFinder();

    public function getHitCounter()
    {
        if ($this->hitCounter === null) {
            $this->hitCounter = $this->createHitCounter();
        }

        return $this->hitCounter;
    }

    /**
     * @return MWP_Extension_HitCounter
     */
    protected abstract function createHitCounter();

    public function getJsonMessageHandler()
    {
        if ($this->jsonMessageHandler === null) {
            $this->jsonMessageHandler = $this->createJsonMessageHandler();
        }

        return $this->jsonMessageHandler;
    }

    /**
     * @return MWP_Monolog_Handler_JsonMessageHandler
     */
    protected abstract function createJsonMessageHandler();

    public function getErrorLogger()
    {
        if ($this->errorLogger === null) {
            $this->errorLogger = $this->createErrorLogger();
        }

        return $this->errorLogger;
    }

    /**
     * @return Monolog_Logger
     */
    protected abstract function createErrorLogger();

    public function getSystemEnvironment()
    {
        if ($this->systemEnvironment === null) {
            $this->systemEnvironment = $this->createSystemEnvironment();
        }

        return $this->systemEnvironment;
    }

    protected abstract function createSystemEnvironment();

    public function getUpdaterSkin()
    {
        if ($this->updaterSkin === null) {
            $this->updaterSkin = $this->createUpdaterSkin();
        }

        return $this->updaterSkin;
    }

    /**
     * @return MWP_Updater_TraceableUpdaterSkin
     */
    protected abstract function createUpdaterSkin();

    public function getSessionStore()
    {
        if ($this->sessionStore === null) {
            $this->sessionStore = $this->createSessionStore();
        }

        return $this->sessionStore;
    }

    /**
     * @return MWP_WordPress_SessionStore
     */
    protected abstract function createSessionStore();

    public function getMigration()
    {
        if ($this->migration === null) {
            $this->migration = $this->createMigration();
        }

        return $this->migration;
    }

    /**
     * @return MWP_Migration_Migration
     */
    protected abstract function createMigration();

    /**
     * {@inheritdoc}
     */
    public function getGelfPublisher()
    {
        if ($this->gelfPublisher === null) {
            $this->gelfPublisher = $this->createGelfPublisher();
        }

        return $this->gelfPublisher;
    }

    /**
     * @return Gelf_Publisher
     */
    protected abstract function createGelfPublisher();
}
