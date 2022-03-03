<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_MysqlStatement implements MWP_IncrementalBackup_Database_StatementInterface
{

    /**
     * @var resource
     */
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * @return array|null
     */
    public function fetch()
    {
        /** @handled function */
        $row = @mysql_fetch_assoc($this->result);
        if ($row === false) {
            return null;
        } else {
            return $row;
        }
    }

    /**
     * @return array|null
     */
    public function fetchAll()
    {
        $rows = array();

        while ($row = $this->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return bool
     */
    public function close()
    {
        /** @handled function */
        return @mysql_free_result($this->result);
    }
}
