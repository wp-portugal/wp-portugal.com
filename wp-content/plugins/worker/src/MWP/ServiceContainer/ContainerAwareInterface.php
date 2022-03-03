<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_ServiceContainer_ContainerAwareInterface
{
    public function setContainer(MWP_ServiceContainer_Interface $container);
}
