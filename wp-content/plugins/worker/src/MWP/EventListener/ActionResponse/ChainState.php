<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionResponse_ChainState implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $container;

    public function __construct(MWP_ServiceContainer_Interface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', 200),
        );
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $rawData = $event->getRequest()->getData();

        if (empty($rawData['stateParameters'])) {
            return;
        }

        $stateAction = new MWP_Action_GetState();
        $stateAction->setContainer($this->container);
        $stateData = $stateAction->execute($rawData['stateParameters']);

        $actionData          = $event->getData();
        $actionData['state'] = $stateData;
        $event->setData($actionData);
    }
}
