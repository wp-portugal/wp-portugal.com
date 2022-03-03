<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_MysqliStatement implements MWP_IncrementalBackup_Database_StatementInterface
{

    /**
     * @var mysqli_result|bool
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
        if (is_bool($this->result)) {
            return $this->result;
        }

        return $this->result->fetch_assoc();
    }

    /**
     * @return array|null
     */
    public function fetchAll()
    {
        if (is_bool($this->result)) {
            return $this->result;
        }

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
        return $this->result->free_result();
    }
}
