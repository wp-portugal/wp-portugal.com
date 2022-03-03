<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_Event_AbstractRequest extends Symfony_EventDispatcher_Event
{

    private $request;

    /**
     * @var MWP_Http_ResponseInterface|null
     */
    private $response;

    /**
     * @param MWP_Worker_Request $request
     */
    public function __construct(MWP_Worker_Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return MWP_Worker_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return MWP_Http_ResponseInterface
     *
     * @throws RuntimeException If the response does not exist.
     */
    public function getResponse()
    {
        if ($this->response === null) {
            throw new RuntimeException('Response is not set');
        }

        return $this->response;
    }

    /**
     * @param MWP_Http_ResponseInterface|null $response
     */
    public function setResponse(MWP_Http_ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function hasResponse()
    {
        if ($this->response === null) {
            return false;
        }

        return true;
    }
}
