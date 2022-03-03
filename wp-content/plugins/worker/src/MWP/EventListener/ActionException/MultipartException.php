<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionException_MultipartException extends MWP_EventListener_ActionException_SetExceptionData
{

    /**
     * @var string
     */
    private $boundary;

    public function __construct($boundary)
    {
        $this->boundary = $boundary;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_EXCEPTION => array('onActionException', 300),
        );
    }

    public function onActionException(MWP_Event_ActionException $event)
    {
        $action = $event->getRequest()->getAction();
        if ($action !== 'fetch_files' && $action !== 'dump_tables') {
            return;
        }

        $event->stopPropagation();
        parent::onActionException($event);

        $parts = array();

        $parts[] = new MWP_Http_MultipartResponsePart(
            array(
                'content-type' => 'application/json',
                'x-mwp-error'  => 'error',
            ),
            json_encode($event->getData())
        );

        $event->setResponse(new MWP_Http_MultipartResponse($parts, $this->boundary));
    }
}
