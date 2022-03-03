<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_Registry
{

    /**
     * @var MWP_Action_Definition[]
     */
    private $definitions = array();

    public function addDefinition($name, MWP_Action_Definition $definition)
    {
        $this->definitions[$name] = $definition;
    }

    public function getDefinition($name)
    {
        if (!isset($this->definitions[$name])) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::ACTION_NOT_REGISTERED, sprintf('Action "%s" is not registered', $name), array(
                'action' => $name,
            ));
        }

        return $this->definitions[$name];
    }

    public function hasDefinition($name)
    {
        return isset($this->definitions[$name]);
    }
}
