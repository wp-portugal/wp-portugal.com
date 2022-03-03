<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Worker_RequestStack
{
    /**
     * @var MWP_Worker_Request[]
     */
    private $requests = array();

    /**
     * Pushes a Request on the stack.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     */
    public function push(MWP_Worker_Request $request)
    {
        $this->requests[] = $request;
    }

    /**
     * Pops the current request from the stack.
     *
     * This operation lets the current request go out of scope.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     *
     * @return MWP_Worker_Request|null
     */
    public function pop()
    {
        if (!$this->requests) {
            return null;
        }

        return array_pop($this->requests);
    }

    /**
     * @return MWP_Worker_Request|null
     */
    public function getCurrentRequest()
    {
        $lastRequest = end($this->requests);

        return $lastRequest ? $lastRequest : null;
    }

    /**
     * Gets the master Request.
     *
     * @return MWP_Worker_Request|null
     */
    public function getMasterRequest()
    {
        if (!$this->requests) {
            return null;
        }

        return $this->requests[0];
    }

    /**
     * Returns the parent request of the current.
     *
     * If current Request is the master request, it returns null.
     *
     * @return MWP_Worker_Request|null
     */
    public function getParentRequest()
    {
        $pos = count($this->requests) - 2;

        if (!isset($this->requests[$pos])) {
            return null;
        }

        return $this->requests[$pos];
    }
}
