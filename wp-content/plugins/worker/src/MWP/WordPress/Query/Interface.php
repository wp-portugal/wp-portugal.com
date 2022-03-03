<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_WordPress_Query_Interface
{
    /**
     * @param array  $options
     *
     * @return array Array of results.
     */
    public function query(array $options = array());
}
