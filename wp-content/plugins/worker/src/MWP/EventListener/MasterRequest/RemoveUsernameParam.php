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
 * @deprecated Legacy protocol support.
 */
class MWP_EventListener_MasterRequest_RemoveUsernameParam implements Symfony_EventDispatcher_EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => 'onMasterRequest',
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        $params = $event->getParams();

        if (!array_key_exists('username', $params)) {
            return;
        }

        unset($params['username']);

        $event->setParams($params);
    }
}
