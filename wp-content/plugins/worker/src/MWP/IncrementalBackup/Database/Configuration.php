<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_Configuration
{

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $database;

    /**
     * @var bool
     */
    private $socket;

    /**
     * @var string|null
     */
    private $host;

    /**
     * @var string|null
     */
    private $socketPath;

    /**
     * @var string
     */
    private $charset = 'utf8';

    /**
     * @var int
     */
    private $port;

    /**
     * @param array $parameters
     */
    public function __construct(array $parameters = array())
    {
        if (isset($parameters['username'])) {
            $this->username = $parameters['username'];
        }

        if (isset($parameters['password'])) {
            $this->password = $parameters['password'];
        }

        if (isset($parameters['socket'])) {
            $this->socket     = true;
            $this->socketPath = $parameters['socket'];
        } elseif (isset($parameters['host'])) {
            $this->socket = false;
            $this->host   = $parameters['host'];
        }

        if (isset($parameters['port'])) {
            $this->port = $parameters['port'];
        }

        if (isset($parameters['database'])) {
            $this->database = $parameters['database'];
        }

        if (isset($parameters['charset'])) {
            $this->charset = $parameters['charset'];
        }
    }

    /**
     * @return string|null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string|null
     */
    public function getSocketPath()
    {
        return $this->socketPath;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return boolean
     */
    public function isSocket()
    {
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    public static function createFromWordPressContext(MWP_WordPress_Context $context)
    {
        return self::createFromArray(array(
            'username' => $context->getConstant('DB_USER'),
            'password' => $context->getConstant('DB_PASSWORD'),
            'database' => $context->getConstant('DB_NAME'),
            'host'     => $context->getConstant('DB_HOST'),
            'charset'  => $context->hasConstant('DB_CHARSET') ? $context->getConstant('DB_CHARSET') : 'utf8',
        ));
    }

    /**
     * $parameters array should contain:
     * - username: mysql username
     * - password: mysql password
     * - database: database name
     * - host: host - can contain port or be a mysql socket
     */
    public static function createFromArray(array $parameters = array())
    {
        $configuration = array(
            'username' => isset($parameters['username']) ? $parameters['username'] : null,
            'password' => isset($parameters['password']) ? $parameters['password'] : null,
            'database' => $parameters['database'],
        );

        $databaseHost = $parameters['host'];

        $port = 0;
        $host = $databaseHost;

        // Handle localhost:3306 cases
        if (strpos($databaseHost, ':') !== false) {
            list($host, $port) = explode(':', $databaseHost);
            $port = (int) $port;
        }

        // Database host can be a path to mysql socket
        if (strpos($databaseHost, '/') !== false || strpos($databaseHost, '\\') !== false) {
            $socket = end(explode(':', $databaseHost));

            $configuration['socket'] = $socket;
        } else {
            $configuration['host'] = $host;
            if (!empty($port)) {
                $configuration['port'] = $port;
            }
        }

        if (isset($parameters['charset'])) {
            $configuration['charset'] = $parameters['charset'];
        }

        return new MWP_IncrementalBackup_Database_Configuration($configuration);
    }
}
