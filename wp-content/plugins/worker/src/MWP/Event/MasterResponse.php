<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Event_MasterResponse extends Symfony_EventDispatcher_Event
{

    /**
     * @var MWP_Http_ResponseInterface|null
     */
    private $response;

    public function __construct(MWP_Http_ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return MWP_Http_ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Give listeners a chance to remove the response.
     *
     * @param MWP_Http_ResponseInterface|null $response
     */
    public function setResponse(MWP_Http_ResponseInterface $response = null)
    {
        $this->response = $response;
    }
}
