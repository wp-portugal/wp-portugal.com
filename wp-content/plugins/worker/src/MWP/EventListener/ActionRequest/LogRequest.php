<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionRequest_LogRequest implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $logger;

    public function __construct(Monolog_Logger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_REQUEST  => array('onActionRequest', -200),
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', -200),
        );
    }

    public function onActionRequest(MWP_Event_ActionRequest $event)
    {
        $request = $event->getRequest();
        $this->logger->debug('Started master request: "{action}"', array(
            'action'  => $request->getAction(),
            'params'  => $request->getParams(),
            'setting' => $request->getSetting(),
        ));
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $request = $event->getRequest();
        $this->logger->debug('Finished master request: "{action}"', array(
            'action'  => $request->getAction(),
            'params'  => $request->getParams(),
            'setting' => $request->getSetting(),
        ));
    }
}
