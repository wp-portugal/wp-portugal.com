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
 * Wrapper for master response callback. We need it to be able to invoke it from the fatal error handler.
 */
class MWP_Worker_ResponseCallback
{

    private $callback;

    /**
     * @return callable|null
     */
    public function get()
    {
        return $this->callback;
    }

    /**
     * @param callable|null $callback
     */
    public function set($callback = null)
    {
        $this->callback = $callback;
    }
}
