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
 * Class MWP_Worker_Kernel
 */
class MWP_Worker_Kernel
{

    /**
     * @var MWP_ServiceContainer_Interface
     */
    private $container;

    /**
     * @var Symfony_EventDispatcher_EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var MWP_Worker_RequestStack|null
     */
    private $requestStack;

    public function __construct(MWP_ServiceContainer_Interface $container)
    {
        $this->container    = $container;
        $this->dispatcher   = $container->getEventDispatcher();
        $this->requestStack = $container->getRequestStack();
        // We will need master response callback for fatal error handling.
        $this->responseCallback = $container->getResponseCallback();
    }

    /**
     * @param MWP_Worker_Request $request
     * @param callable           $deferredCallback
     * @param bool               $catch
     *
     * @throws Exception
     * @throws MWP_Worker_Exception
     */
    public function handleRequest(MWP_Worker_Request $request, $deferredCallback, $catch = true)
    {
        $request->initialize();
        $this->requestStack->push($request);
        $this->responseCallback->set($deferredCallback);
        $container  = $this->getContainer();
        $actionName = $request->getAction();
        $params     = $request->getParams();
        $context    = $container->getWordPressContext();
        if (!is_array($params)) {
            $params = array();
        }

        if (!$request->isMasterRequest()) {
            // This is a public request. Allow the plugin to hook onto WordPress.
            $publicRequestEvent = new MWP_Event_PublicRequest($request);
            $this->dispatcher->dispatch(MWP_Event_Events::PUBLIC_REQUEST, $publicRequestEvent);
            if ($publicRequestEvent->hasResponse()) {
                call_user_func($deferredCallback, null, $publicRequestEvent->getResponse());
            }

            return;
        }

        @ini_set('display_errors', false);

        try {
            // Get action info.
            $actionRegistry   = $container->getActionRegistry();
            $actionDefinition = $actionRegistry->getDefinition($actionName);
            $hookName         = $actionDefinition->getOption('hook_name');

            // This is a master request. Allow early hooks to verify and do everything required with the request.
            $masterRequestEvent = new MWP_Event_MasterRequest($request, $params, empty($hookName));
            $this->dispatcher->dispatch(MWP_Event_Events::MASTER_REQUEST, $masterRequestEvent);
            if ($masterRequestEvent->hasResponse()) {
                call_user_func($deferredCallback, null, $masterRequestEvent->getResponse());

                return;
            }
            $params = $masterRequestEvent->getParams();

            $callback = $actionDefinition->getCallback();

            // If the callback is an array with two members (['ClassName, 'methodName']) and implements ContainerAware,
            // inject the container before executing it.
            if (is_array($callback) && is_string($callback[0])) {
                $callback[0] = new $callback[0];
            }
            if (is_array($callback) && $callback[0] instanceof MWP_ServiceContainer_ContainerAwareInterface) {
                $callbackObject = $callback[0];
                /** @var MWP_ServiceContainer_ContainerAwareInterface $callbackObject */
                $callbackObject->setContainer($container);
            }

            // Check if the action call should be deferred.
            if ($hookName !== null && $deferredCallback !== null) {
                $proxy = new MWP_WordPress_HookProxy(array($this, 'hookResponse'), $request, $callback, $params, $actionDefinition, $deferredCallback);
                $context->addAction($hookName, $proxy->getCallable(), $actionDefinition->getOption('hook_priority'));

                mwp_logger()->debug('Finished MU context work');

                return;
            }

            // Allow listeners to modify action parameters.
            $actionRequestEvent = new MWP_Event_ActionRequest($request, $params, $actionDefinition);
            $this->dispatcher->dispatch(MWP_Event_Events::ACTION_REQUEST, $actionRequestEvent);
            $params = $actionRequestEvent->getParams();

            try {
                $data = call_user_func($callback, $params, $request);
            } catch (MWP_Worker_ActionResponse $actionResponse) {
                $data = $actionResponse->getData();
            }
            $response = $this->handleResponse($request, $params, $data);
            call_user_func($deferredCallback, null, $response);
        } catch (Exception $e) {
            if (!$catch) {
                throw $e;
            }

            $response = $this->handleException($request, $e);
            call_user_func($deferredCallback, $e, $response);
        }
    }

    /**
     * @param MWP_Worker_Request $request
     * @param array              $params
     * @param mixed              $data
     *
     * @return MWP_Http_ResponseInterface
     *
     * @throws RuntimeException If the action response doesn't get converted to an HTTP response.
     */
    private function handleResponse(MWP_Worker_Request $request, array $params, $data)
    {
        $actionResponseEvent = new MWP_Event_ActionResponse($request, $params, $data);
        $this->dispatcher->dispatch(MWP_Event_Events::ACTION_RESPONSE, $actionResponseEvent);

        if ($actionResponseEvent->getResponse() === null) {
            throw new RuntimeException('Action response did not get converted to an HTTP response.');
        }

        return $actionResponseEvent->getResponse();
    }

    /**
     * @param MWP_Worker_Request $request
     * @param Exception          $e
     *
     * @return MWP_Http_ResponseInterface|null
     */
    private function handleException(MWP_Worker_Request $request, Exception $e)
    {
        $errorEvent = new MWP_Event_ActionException($request, $e);
        $this->dispatcher->dispatch(MWP_Event_Events::ACTION_EXCEPTION, $errorEvent);

        return $errorEvent->getResponse();
    }

    /**
     * Callback for deferred actions. Used when the action is not executed immediately, but after a WordPress action hook.
     *
     * @param MWP_Worker_Request    $request
     * @param callable              $callback
     * @param array                 $params
     * @param MWP_Action_Definition $actionDefinition
     * @param callable              $deferredCallback
     */
    public function hookResponse(MWP_Worker_Request $request, $callback, array $params, MWP_Action_Definition $actionDefinition, $deferredCallback)
    {
        try {
            // Allow listeners to modify action parameters.
            $actionRequestEvent = new MWP_Event_ActionRequest($request, $params, $actionDefinition);
            $this->dispatcher->dispatch(MWP_Event_Events::ACTION_REQUEST, $actionRequestEvent);
            $params = $actionRequestEvent->getParams();

            try {
                $data = call_user_func($callback, $params, $request);
            } catch (MWP_Worker_ActionResponse $actionResponse) {
                $data = $actionResponse->getData();
            }
            $response = $this->handleResponse($request, $params, $data);
            call_user_func($deferredCallback, null, $response);
        } catch (Exception $e) {
            $response = $this->handleException($request, $e);
            call_user_func($deferredCallback, $e, $response);
        }
    }

    /**
     * Returns the service container.
     *
     * @return MWP_ServiceContainer_Interface
     */
    public function getContainer()
    {
        if ($this->container === null) {
            throw new RuntimeException('Kernel is not booted');
        }

        return $this->container;
    }
}
