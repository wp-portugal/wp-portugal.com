<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionResponse_SetActionData implements Symfony_EventDispatcher_EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => array('encodeResponse', 100),
        );
    }

    public function encodeResponse(MWP_Event_ActionResponse $event)
    {
        $event->setData(array('success' => $event->getData()));
    }
}
