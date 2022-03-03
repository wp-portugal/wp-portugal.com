<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_MysqliConnection implements MWP_IncrementalBackup_Database_ConnectionInterface
{

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var mysqli
     */
    private $connection;

    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration)
    {
        $this->configuration = $configuration;

        if (!extension_loaded('mysqli')) {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException("Mysqli extension is not enabled.");
        }

        // Silence possible warnings thrown by mysqli
        // e.g. Warning: mysqli::mysqli(): Headers and client library minor version mismatch. Headers:50540 Library:50623
        /** @handled class */
        $this->connection = @new mysqli($configuration->getHost(), $configuration->getUsername(), $configuration->getPassword(), $configuration->getDatabase(), $configuration->getPort());

        if ($this->connection === null || !$this->connection->ping()) {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException();
        }

        $this->connection->set_charset($configuration->getCharset());
    }

    /**
     * {@inheritdoc}
     */
    public function query($query, $useResult = false)
    {
        $result = $this->connection->query($query, $useResult ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

        if ($result === false) {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException($this->connection->error, $this->connection->errno);
        }

        return new MWP_IncrementalBackup_Database_MysqliStatement($result);
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value)
    {
        return "'".$this->connection->real_escape_string($value)."'";
    }
}
