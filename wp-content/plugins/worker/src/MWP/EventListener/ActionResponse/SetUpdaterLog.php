<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionResponse_SetUpdaterLog implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $updaterSkin;

    function __construct(MWP_Updater_TraceableUpdaterSkin $updaterSkin)
    {
        $this->updaterSkin = $updaterSkin;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => 'onActionResponse',
        );
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $response = $event->getResponse();

        if ($response === null) {
            return;
        }

        $messages = $this->updaterSkin->get_upgrade_messages();

        if (!$messages) {
            return;
        }

        $data = $response->getContent();
        $data['updaterLog'] = $messages;
        $response->setContent($data);
    }
}
