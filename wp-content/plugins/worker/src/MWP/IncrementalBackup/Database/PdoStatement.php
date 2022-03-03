<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_PdoStatement implements MWP_IncrementalBackup_Database_StatementInterface
{

    /**
     * @var PDOStatement
     */
    private $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch()
    {
        return $this->statement->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll()
    {
        return $this->statement->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->statement->closeCursor();
    }
}
