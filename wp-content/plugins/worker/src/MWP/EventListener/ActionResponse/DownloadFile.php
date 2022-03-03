<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */



class MWP_EventListener_ActionResponse_DownloadFile implements Symfony_EventDispatcher_EventSubscriberInterface
{
    /** @var string */
    private $boundary;

    public function __construct($boundary)
    {
        $this->boundary = $boundary;
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $action = $event->getRequest()->getAction();
        if ($action !== 'download_file_action') {
            return;
        }

        if (!$event->getData() instanceof MWP_FileManager_Model_DownloadFilesResult) {
            return;
        }

        // Prevent other listeners from hijacking this response
        $event->stopPropagation();

        /** @var MWP_FileManager_Model_DownloadFilesResult $result */
        $result = $event->getData();
        $files  = $result->getFiles();

        $response = new MWP_Http_StreamingResponse(
            $files[0]->getStream(),
            200,
            array(
                'content-type'     => 'application/octet-stream',
                'content-location' => $files[0]->getPathname()
            )
        );

        $event->setResponse($response);
    }

    /**
     * {@inheritdoc}
     **/
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', 200),
        );
    }
}
