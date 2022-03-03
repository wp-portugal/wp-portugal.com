<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_IncrementalBackup_Database_StatementInterface
{
    /**
     * @return array|null
     */
    public function fetch();

    /**
     * @return array|null
     */
    public function fetchAll();

    /**
     * @return bool
     */
    public function close();
}
