<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_PhpDumper implements MWP_IncrementalBackup_Database_DumperInterface
{

    const METHOD_PDO = 1;
    const METHOD_MYSQLI = 2;
    const METHOD_MYSQL = 3;

    private $transferSize = 102400; // 100kb

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var MWP_System_Environment
     */
    private $environment;

    /**
     * @var MWP_IncrementalBackup_Database_DumpOptions
     */
    private $options;

    /**
     * @var MWP_IncrementalBackup_Database_ConnectionInterface
     */
    private $connection;

    /**
     * @param MWP_IncrementalBackup_Database_Configuration $configuration
     * @param MWP_System_Environment                       $environment
     * @param MWP_IncrementalBackup_Database_DumpOptions   $options
     */
    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration, MWP_System_Environment $environment, MWP_IncrementalBackup_Database_DumpOptions $options)
    {
        $this->configuration = $configuration;
        $this->environment   = $environment;
        $this->options       = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function dump($table, $realpath)
    {
        $stream = $this->createStream(array($table));
        $handle = @fopen($realpath, 'w');
        if ($handle === false) {
            $error   = error_get_last();
            $message = isset($error['message']) ? $error['message'] : sprintf('Unable to open file %s for writing.', $realpath);
            throw new MWP_Worker_Exception(MWP_Worker_Exception::IO_EXCEPTION, $message, $error);
        }

        while (!$stream->eof()) {
            fwrite($handle, $stream->read($this->transferSize));
        }

        fclose($handle);
    }

    /**
     * {@inheritdoc}
     */
    public function createStream(array $tables = array())
    {
        if (!$this->connection) {
            $this->createConnection();
        }
        $this->options->setTables($tables);
        $dumper = new MWP_IncrementalBackup_Database_StreamableQuerySequenceDump($this->connection, $this->options);

        return $dumper->createStream();
    }

    private function createConnection()
    {
        $connectionMethods = $this->options->getConnectionMethods();
        if (!empty($connectionMethods) && is_array($connectionMethods)) {
            $this->getPreferredConnection($connectionMethods);
            if ($this->connection !== null) {
                return $this->connection;
            }
        }

        if ($this->environment->isPdoEnabled()) {
            $this->connection = new MWP_IncrementalBackup_Database_PdoConnection($this->configuration);
        } elseif ($this->environment->isMysqliEnabled()) {
            $this->connection = new MWP_IncrementalBackup_Database_MysqliConnection($this->configuration);
        } elseif ($this->environment->isMysqlEnabled()) {
            $this->connection = new MWP_IncrementalBackup_Database_MysqlConnection($this->configuration);
        } else {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException("No mysql drivers available.");
        }

        return $this->connection;
    }

    private function getPreferredConnection($methods)
    {
        foreach ($methods as $method) {
            try {
                if ($method == self::METHOD_PDO && $this->environment->isPdoEnabled()) {
                    $this->connection = new MWP_IncrementalBackup_Database_PdoConnection($this->configuration);
                } elseif ($method == self::METHOD_MYSQLI && $this->environment->isMysqliEnabled()) {
                    $this->connection = new MWP_IncrementalBackup_Database_MysqliConnection($this->configuration);
                } elseif ($method == self::METHOD_MYSQL && $this->environment->isMysqlEnabled()) {
                    $this->connection = new MWP_IncrementalBackup_Database_MysqlConnection($this->configuration);
                }
            } catch (Exception $e) {
            }
        }
    }
}
