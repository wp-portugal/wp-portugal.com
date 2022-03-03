<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_Action_Abstract implements MWP_ServiceContainer_ContainerAwareInterface
{

    /**
     * @var MWP_ServiceContainer_Interface
     */
    protected $container;

    public function setContainer(MWP_ServiceContainer_Interface $container)
    {
        $this->container = $container;
    }
}
