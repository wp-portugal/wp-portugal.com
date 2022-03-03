<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_IncrementalBackup_Database_ConnectionInterface
{
    /**
     * @param string $query
     * @param bool   $useResult When true, the returned statement HAS TO BE CLOSED MANUALLY before issuing another query.
     *                          Use this for selecting large amounts of data
     *
     * @return MWP_IncrementalBackup_Database_StatementInterface
     */
    public function query($query, $useResult = false);

    /**
     * @param mixed $value any primitive value
     *
     * @return string Quoted string e.g. 'hello' with opening and closing quotes
     */
    public function quote($value);
}
