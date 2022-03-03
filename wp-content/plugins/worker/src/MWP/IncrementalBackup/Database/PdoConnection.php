<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_PdoConnection implements MWP_IncrementalBackup_Database_ConnectionInterface
{

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var PDO
     */
    private $connection;

    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration)
    {
        $this->configuration = $configuration;

        if (!extension_loaded('pdo_mysql')) {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException("PDO extension is disabled.");
        }

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        );

        // Mute the constructor since this error doesn't get thrown as an exception, but as a PHP warning:
        // Warning:  PDO::__construct(): The server requested authentication method unknown to the client [mysql_old_password]
        $this->connection = @new PDO(self::getDsn($this->configuration), $this->configuration->getUsername(), $this->configuration->getPassword(), $options);

        $this->connection->exec('SET NAMES '.$configuration->getCharset());
    }

    /**
     * {@inheritdoc}
     */
    public function query($query, $useResult = false)
    {
        /** @handled constant */
        $previousAttr = $this->connection->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);

        if ($useResult) {
            // Temporarily switch to unbuffered queries
            /** @handled constant */
            $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }

        $statement = new MWP_IncrementalBackup_Database_PdoStatement($this->connection->query($query));

        if ($useResult) {
            // Restore configuration
            /** @handled constant */
            $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $previousAttr);
        }

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value)
    {
        return $this->connection->quote($value);
    }

    /**
     * @param MWP_IncrementalBackup_Database_Configuration $configuration
     *
     * @return string
     */
    private static function getDsn(MWP_IncrementalBackup_Database_Configuration $configuration)
    {
        $pdoParameters = array(
            'dbname'  => $configuration->getDatabase(),
            'charset' => $configuration->getCharset(),
        );

        if ($configuration->isSocket()) {
            $pdoParameters['unix_socket'] = $configuration->getSocketPath();
        } else {
            $pdoParameters['host'] = $configuration->getHost();

            if (($port = $configuration->getPort()) !== null) {
                $pdoParameters['port'] = $configuration->getPort();
            }
        }

        $parameters = array();
        foreach ($pdoParameters as $name => $value) {
            $parameters[] = $name.'='.$value;
        }

        $dsn = sprintf("mysql:%s", implode(';', $parameters));

        return $dsn;
    }
}
