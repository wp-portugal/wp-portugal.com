<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Converts any fatal PHP error into a valid worker response.
 */
class MWP_EventListener_MasterRequest_SetErrorHandler extends Monolog_Handler_AbstractHandler implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $logger;

    private $errorHandler;

    private $requestStack;

    private $responseCallback;

    private $container;

    private $errors = array();

    private $logErrors;

    private $reservedMemorySize;

    public function __construct(Monolog_Logger $logger, Monolog_ErrorHandler $errorHandler, MWP_Worker_RequestStack $requestStack, MWP_Worker_ResponseCallback $responseCallback, MWP_ServiceContainer_Interface $container, $logErrors = false, $reservedMemorySize = 20)
    {
        $this->logger             = $logger;
        $this->errorHandler       = $errorHandler;
        $this->requestStack       = $requestStack;
        $this->responseCallback   = $responseCallback;
        $this->logErrors          = $logErrors;
        $this->reservedMemorySize = $reservedMemorySize;
        // We need the container here, since referencing the event dispatcher component will create a circular reference,
        // since this is an event listener.
        $this->container = $container;

        parent::__construct(Monolog_Logger::NOTICE, false);
    }

    /**
     * Error data ($record) looks like this:
     *  array (
     *    'message' => 'Fatal Error (E_ERROR): Call to undefined function wp_set_current_user()',
     *    // or
     *    'message' => 'Uncaught exception',
     *    'context' =>
     *    array (
     *      'code' => 1,
     *      'message' => 'Call to undefined function wp_set_current_user()',
     *      'file' => '/home/vagrant/www/wptest.dev/wp-content/plugins/worker/src/MWP/WordPress/Context.php',
     *      'line' => 483,
     *      // or
     *      'exception' => Exception
     *    ),
     *    'level' => 550,
     *    'level_name' => 'ALERT',
     *    'channel' => 'worker',
     *    'datetime' =>
     *    DateTime::__set_state(array(
     *       'date' => '2015-01-06 14:50:44.404504',
     *       'timezone_type' => 3,
     *       'timezone' => 'UTC',
     *    )),
     *    'extra' =>
     *    array (
     *    ),
     *  )
     *
     * @param array $record
     *
     * @return bool
     */
    public function handle(array $record)
    {
        $request = $this->requestStack->getMasterRequest();

        // Everything below CRITICAL is recoverable, so we can log it.
        if ($record['level'] < Monolog_Logger::CRITICAL) {
            if ($this->logErrors) {
                $this->errors[] = $record;
            }

            return false;
        }

        // Everything after this point is a fatal error.
        if (!$request->isAuthenticated()) {
            return false;
        }

        $responseCallback = $this->responseCallback->get();

        if ($responseCallback === null) {
            return false;
        }

        if (isset($record['context']['exception'])) {
            $exception = $record['context']['exception'];
        } else {
            $exception = new MWP_Debug_FatalErrorException($record['message'], $record['context']['code']);
            $exception->setFile($record['context']['file']);
            $exception->setLine($record['context']['line']);
        }

        $errorEvent = new MWP_Event_ActionException($request, $exception);
        $this->container->getEventDispatcher()->dispatch(MWP_Event_Events::ACTION_EXCEPTION, $errorEvent);

        call_user_func($responseCallback, $exception, $errorEvent->getResponse());

        return true;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST  => array('onMasterRequest', 200),
            MWP_Event_Events::MASTER_RESPONSE => array('onMasterResponse', -200),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        if (!$event->getRequest()->isAuthenticated()) {
            return;
        }

        $this->logger->pushHandler($this);
        $this->errorHandler->registerFatalHandler(Monolog_Psr_LogLevel::ALERT, $this->reservedMemorySize);
        $this->errorHandler->registerExceptionHandler(Monolog_Psr_LogLevel::CRITICAL);

        if ($this->logErrors) {
            error_reporting(E_ALL);
            ini_set('display_errors', false);
            $this->errorHandler->registerErrorHandler();
        }
    }

    public function onMasterResponse(MWP_Event_MasterResponse $event)
    {
        if (!$this->logErrors) {
            return;
        }

        if (!$this->requestStack->getMasterRequest()->isAuthenticated() || count($this->errors) === 0) {
            return;
        }

        $response = $event->getResponse();
        $content  = $response->getContent();

        if (!is_array($content)) {
            return;
        }

        $content['errorLog'] = array();

        foreach ($this->errors as $error) {
            /** @var DateTime $date */
            $date         = $error['datetime'];
            $log          = $error['context'];
            $log['level'] = $error['level_name'];
            $log['time']  = $date->format('Y-m-d H:i:s');

            $content['errorLog'][] = $log;
        }

        $response->setContent($content);
    }
}
