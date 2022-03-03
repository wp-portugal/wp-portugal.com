<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterResponse_LogResponse implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $logger;

    public function __construct(Monolog_Logger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_RESPONSE => array('onMasterResponse'),
        );
    }

    public function onMasterResponse(MWP_Event_MasterResponse $event)
    {
        $response = $event->getResponse();
        $content  = $response->getContent();

        $status = 'unknown';

        if (is_array($content)) {
            if (array_key_exists('success', $content)) {
                $status = 'success';
            } elseif (array_key_exists('error', $content)) {
                $status = 'error';
            }
        }

        $this->logger->debug('Master response: {status}', array(
            'status'  => $status,
            'content' => $content,
        ));
    }
}
