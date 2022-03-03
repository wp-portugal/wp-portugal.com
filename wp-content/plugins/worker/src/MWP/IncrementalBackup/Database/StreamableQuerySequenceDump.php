<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_StreamableQuerySequenceDump
{

    /**
     * @var MWP_IncrementalBackup_Database_ConnectionInterface
     */
    private $connection;

    /**
     * @var MWP_IncrementalBackup_Database_DumpOptions
     */
    private $options;

    public function __construct(MWP_IncrementalBackup_Database_ConnectionInterface $connection, MWP_IncrementalBackup_Database_DumpOptions $options)
    {
        $this->connection = $connection;
        $this->options    = $options;
    }

    /**
     * @return MWP_IncrementalBackup_Database_ConnectionInterface
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * @inherit
     */
    public function createStream()
    {
        $stream = new MWP_Stream_Append();

        $stream->addStream(MWP_Stream_Stream::factory("
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n"
        ));

        $allTables = self::arrayColumn($this->getConnection()->query('SHOW TABLES')->fetchAll());
        $tables    = array_intersect($allTables, $this->options->getTables() ? $this->options->getTables() : $allTables);

        foreach ($tables as $tableName) {
            $stream->addStream(
                new MWP_Stream_Callable(array($this, 'streamCreateTable'), array($tableName))
            );
        }

        $stream->addStream(MWP_Stream_Stream::factory("
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n"
        ));

        return $stream;
    }

    public function streamCreateTable($length, $tableName)
    {
        // Get the SHOW CREATE TABLE part
        $content = $this->getConnection()
            ->query("SHOW CREATE TABLE `{$tableName}`;")
            ->fetchAll();

        if (!is_array($content)) {
            return new MWP_Stream_Buffer();
        }

        $stream = new MWP_Stream_Append();

        foreach ($content as $entry) {
            // Add drop table query
            if ($this->options->isDropTables()) {
                $stream->addStream(MWP_Stream_Stream::factory("DROP TABLE IF EXISTS `$tableName`;\n"));
            }

            // Add create table query
            $stream->addStream(MWP_Stream_Stream::factory("
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n"
            ));
            $stream->addStream(MWP_Stream_Stream::factory($entry['Create Table'].";\n"));
            $stream->addStream(MWP_Stream_Stream::factory("/*!40101 SET character_set_client = @saved_cs_client */;\n\n"));
        }

        // Export content
        $stream->addStream(
            new MWP_Stream_Callable(array($this, 'createExportTableStream'), array($tableName))
        );

        return $stream;
    }

    public function createExportTableStream($length, $tableName)
    {
        $stream = new MWP_Stream_Append();

        $columns = $this->getConnection()
            ->query("SHOW COLUMNS IN `{$tableName}`;")
            ->fetchAll();

        if (is_array($columns)) {
            $columns = $this->repack($columns, 'Field');
        }

        $query     = $this->selectAllDataQuery($tableName, $columns);
        $statement = $this->getConnection()->query($query, true);

        // Go through row by row
        if (!$this->options->isSkipLockTables()) {
            $stream->addStream(MWP_Stream_Stream::factory("LOCK TABLES `$tableName` WRITE;\n"));
        }

        $stream->addStream(MWP_Stream_Stream::factory("/*!40000 ALTER TABLE `$tableName` DISABLE KEYS */;\n"));

        $stream->addStream(
            new MWP_Stream_Callable(array($this, 'createExportRowStream'), array($statement, $tableName, $columns))
        );

        $stream->addStream(MWP_Stream_Stream::factory("\n"));
        $stream->addStream(MWP_Stream_Stream::factory("/*!40000 ALTER TABLE `$tableName` ENABLE KEYS */;\n"));

        if (!$this->options->isSkipLockTables()) {
            $stream->addStream(MWP_Stream_Stream::factory("UNLOCK TABLES;\n"));
        }

        return $stream;
    }

    public function createExportRowStream($length, MWP_IncrementalBackup_Database_StatementInterface $statement, $tableName, $columns)
    {
        $row = $statement->fetch();
        if (!$row) {
            // This statement is using unbuffered queries and MUST be closed explicitly.
            $statement->close();

            return false;
        }

        return $this->createRowInsertStatement($tableName, $row, $columns)."\n";
    }

    /**
     * Repacks an array by making a key of a particular column
     *
     * @param array $array
     * @param       $column
     *
     * @return array
     */
    protected function repack(array $array, $column)
    {
        $repacked = array();
        foreach ($array as $element) {
            $repacked[$element[$column]] = $element;
        }

        return $repacked;
    }

    /**
     * Creates an SQL statement for fetching all data from a particular table
     *
     * @param $tableName
     * @param $columnData
     *
     * @return string
     */
    protected function selectAllDataQuery($tableName, $columnData)
    {
        $columns = array();
        foreach ($columnData as $columnName => $metadata) {
            if (strpos($metadata['Type'], 'blob') !== false) {
                $fullColumnName = "`{$tableName}`.`{$columnName}`";
                $columns[]      = "HEX($fullColumnName) as `{$columnName}`";
            } else {
                $columns[] = "`{$tableName}`.`{$columnName}`";
            }
        }
        $cols = join(', ', $columns);
        $sql  = "SELECT $cols FROM `$tableName`;";

        return $sql;
    }

    /**
     * Creates an sql statement for row insertion
     *
     * @param string $tableName
     * @param array  $row
     * @param array  $columns
     *
     * @return string
     */
    protected function createRowInsertStatement($tableName, array $row, array $columns = array())
    {
        $values = $this->createRowInsertValues($row, $columns);
        $joined = join(', ', $values);
        $sql    = "INSERT INTO `$tableName` VALUES($joined);";

        return $sql;
    }

    protected function createRowInsertValues($row, $columns)
    {
        $values = array();

        foreach ($row as $columnName => $value) {
            $type = $columns[$columnName]['Type'];

            // Used to determine if the column is enum in case some of the allowed values contain reserved type identifiers
            $trimmedType = strtolower(trim($type));

            // If it should not be enclosed
            if ($value === null) {
                $values[] = 'null';
            } elseif (strpos($trimmedType, 'enum') !== 0 &&
                (strpos($type, 'int') !== false
                    || strpos($type, 'float') !== false
                    || strpos($type, 'double') !== false
                    || strpos($type, 'decimal') !== false
                    || strpos($type, 'bool') !== false)
            ) {
                $values[] = $value;
            } elseif (strpos($type, 'blob') !== false) {
                $values[] = strlen($value) ? ('0x'.$value) : "''";
            } else {
                $values[] = $this->getConnection()->quote($value);
            }
        }

        return $values;
    }

    private static function arrayColumn($array, $columnIndex = 0)
    {
        $result = array();
        foreach ($array as $arr) {
            if (!is_array($arr)) {
                continue;
            }
            $arr = array_values($arr);
            $result[] = $arr[$columnIndex];
        }
        return $result;
    }
}
