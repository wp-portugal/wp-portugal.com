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
 * Registered by MWP_Action_IncrementalBackup_FetchFiles action
 */
class MWP_EventListener_ActionResponse_FetchFiles implements Symfony_EventDispatcher_EventSubscriberInterface
{

    /**
     * @var string
     */
    private $boundary;

    public function __construct($boundary)
    {
        $this->boundary = $boundary;
    }

    /**
     * Set MWP_Action_IncrementalBackup_FetchFiles action response
     */
    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $action = $event->getRequest()->getAction();
        if ($action !== 'fetch_files' && $action !== 'dump_tables') {
            return;
        }

        // Prevent other listeners from hijacking this response
        $event->stopPropagation();

        /** @var MWP_IncrementalBackup_Model_FetchFilesResult $result */
        $result = $event->getData();

        $parts = array();

        $parts[] = new MWP_Http_MultipartResponsePart(
            array('content-type' => 'application/json'),
            json_encode($result->getServerStatistics()->toArray())
        );

        foreach ($result->getFiles() as $file) {
            $parts[] = new MWP_Http_MultipartResponsePart(
                array(
                    'content-type'     => 'application/octet-stream',
                    'content-location' => $file->getPathname(),
                ),
                $file->getStream(),
                $file->getEncoding()
            );
        }

        $event->setResponse(new MWP_Http_MultipartResponse($parts, $this->boundary));
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
