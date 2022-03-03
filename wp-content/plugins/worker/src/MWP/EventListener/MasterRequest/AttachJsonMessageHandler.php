<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterRequest_AttachJsonMessageHandler implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $logger;

    private $handler;

    function __construct(Monolog_Logger $logger, MWP_Monolog_Handler_JsonMessageHandler $handler)
    {
        $this->logger  = $logger;
        $this->handler = $handler;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', -400),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        if (!$event->getRequest()->isAuthenticated()) {
            return;
        }

        if (strpos($event->getRequest()->getHeader('ACCEPT'), 'application/ldjson') === false) {
            return;
        }

        $this->logger->pushHandler($this->handler);
    }
}
