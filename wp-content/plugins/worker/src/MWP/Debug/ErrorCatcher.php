<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Debug_ErrorCatcher
{
    private $errorMessage;

    private $registered;

    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        if (is_string($this->registered) && !($message = preg_replace('{^'.$this->registered.'\(.*?\): }i', '', $message))) {
            return;
        }

        $this->errorMessage = $message;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function yieldErrorMessage($unRegister = false)
    {
        $message            = $this->errorMessage;
        $this->errorMessage = null;

        if ($unRegister) {
            $this->unRegister();
        }

        return $message;
    }

    /**
     * Set the $capture parameter to "true" to capture any error message; or to a function name
     * to capture only error messages for that function. It will rely on PHP's standard error
     * reporting which always starts with the name of the function that generated the error.
     *
     * @param bool|string $capture
     */
    public function register($capture = true)
    {
        if ($this->registered) {
            throw new LogicException('The error catcher is already registered.');
        }

        if ($capture !== true && (!is_string($capture) || empty($capture))) {
            throw new InvalidArgumentException('The "capture" must be boolean true or a non-empty string.');
        }

        $this->registered   = $capture;
        $this->errorMessage = null;
        set_error_handler(array($this, 'handleError'));
    }

    public function unRegister()
    {
        if (!$this->registered) {
            throw new LogicException('The error catcher is not registered.');
        }

        $this->registered = false;
        restore_error_handler();
    }

    public function __destruct()
    {
        if ($this->registered) {
            $this->unRegister();
        }
    }
}
