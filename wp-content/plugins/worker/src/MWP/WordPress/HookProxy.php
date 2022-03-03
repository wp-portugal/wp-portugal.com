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
 * This component makes it possible to delay calling a specific function with custom arguments.
 * WordPress' add_action will execute the function with its own arguments, and we can't use
 * PHP 5.3's lambda functions; so this is a workaround for it.
 *
 * It's also possible to call a proxy function with the result of the first callback. This is
 * so we can delay calling a function until WordPress is further bootstrapped, but at the same
 * time use its result in another context. Neat.
 */
class MWP_WordPress_HookProxy
{

    private $callback;

    private $args;

    /**
     * @param callable $callback Hook callback; function to execute.
     * @param mixed    ...$args  Arguments that will be passed to $callback
     */
    public function __construct($callback, $args = null)
    {
        $this->callback = $callback;
        $this->args     = func_get_args();
        array_shift($this->args);
    }

    public function hook()
    {
        call_user_func_array($this->callback, $this->args);
    }

    public function getCallable()
    {
        return array($this, 'hook');
    }
}
