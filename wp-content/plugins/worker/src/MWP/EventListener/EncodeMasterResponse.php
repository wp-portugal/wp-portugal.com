<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_EncodeMasterResponse implements Symfony_EventDispatcher_EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE  => array('onActionResponse', 100),
            MWP_Event_Events::ACTION_EXCEPTION => array('onActionException', 100),
        );
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $event->setResponse($this->getResponseForRequest($event->getRequest(), $event->getData()));
    }

    public function onActionException(MWP_Event_ActionException $event)
    {
        $event->setResponse($this->getResponseForRequest($event->getRequest(), $event->getData()));
    }

    private function getResponseForRequest(MWP_Worker_Request $request, $data)
    {
        if (strpos($request->getHeader('Accept'), 'application/json') === false) {
            return new MWP_Http_LegacyWorkerResponse($data);
        }

        return new MWP_Http_JsonResponse($data);
    }
}
