<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_IncrementalBackup_Database_DumperInterface
{
    /**
     * @param string $table
     * @param string $realpath
     */
    function dump($table, $realpath);

    /**
     * @param array $tables Table names
     *
     * @return MWP_Stream_Interface
     */
    function createStream(array $tables = array());
}
