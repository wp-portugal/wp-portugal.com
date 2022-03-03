<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Event_ActionException extends Symfony_EventDispatcher_Event
{

    private $request;

    /** @var Exception|Error */
    private $exception;

    private $data = array();

    /**
     * @var MWP_Http_ResponseInterface|null
     */
    private $response;

    public function __construct(MWP_Worker_Request $request, $exception)
    {
        $this->request = $request;
        $this->exception = $exception;
    }

    /**
     * @return Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return MWP_Http_ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param MWP_Http_ResponseInterface|null $response
     */
    public function setResponse(MWP_Http_ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return MWP_Worker_Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
