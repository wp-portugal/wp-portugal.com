<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_Definition
{

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var array
     */
    private $options;

    private static $defaultOptions = array(
        'hook_name'     => null,
        'hook_priority' => 10,
    );

    /**
     * First parameter is callback to be executed.
     * Second parameter accepts the following option names:
     *  - 'hook_name' - WordPress hook to attach the action to.
     *  - 'hook_priority' - WordPress hook priority; used only when 'hook_name' is set.
     *
     * @param callable $callback
     * @param array    $options
     */
    public function __construct($callback, array $options = array())
    {
        $this->validateOptions($options);
        $options += self::$defaultOptions;

        $this->callback = $callback;
        $this->options  = $options;
    }

    private function validateOptions(array $options)
    {
        foreach ($options as $optionName => $optionDefault) {
            if (!array_key_exists($optionName, self::$defaultOptions)) {
                throw new InvalidArgumentException(sprintf('Option "%s" is not registered, valid options are "%s"', $optionName, implode('", "', array_keys(self::$defaultOptions))));
            }
        }
    }

    /**
     * @return callable
     */
    public function getCallback()
    {
        return $this->callback;
    }

    public function getOption($name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('Option "%s" is not recognized', $name));
        }

        return $this->options[$name];
    }
}
