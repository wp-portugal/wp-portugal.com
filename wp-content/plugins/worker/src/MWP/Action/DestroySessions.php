<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_DestroySessions extends MWP_Action_Abstract
{
    public function execute()
    {
        $store = $this->container->getSessionStore();

        return array(
            'destroyed' => $store->destroyAll(),
        );
    }
}
