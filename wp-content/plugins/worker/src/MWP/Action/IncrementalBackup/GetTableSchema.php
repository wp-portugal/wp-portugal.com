<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_GetTableSchema
{
    const ERROR_TABLE_NOT_FOUND = 1;

    public function execute(array $params = array())
    {
        $tables = $params['tables'];

        $result = array();

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $result[] = array(
                    'name'  => $table,
                    'error' => self::ERROR_TABLE_NOT_FOUND,
                );
                continue;
            }

            $columns        = $this->getTableColumns($table);
            $indexes        = $this->getTableIndexes($table);
            $tableInfo      = $this->getTableInfo($table);
            $createTableSql = $this->getCreateTableSql($table);

            $result[] = array(
                'name'           => $table,
                'info'           => $tableInfo,
                'columns'        => $columns,
                'indexes'        => $indexes,
                'createTableSql' => $createTableSql,
            );
        }

        return $result;
    }

    private function tableExists($table)
    {
        $exists = (int) mwp_context()->getDb()->get_var(
            sprintf('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = "%s" AND table_name = "%s"',
                mwp_context()->escapeParameter(mwp_context()->getDbName()),
                mwp_context()->escapeParameter($table))
        );

        return $exists >= 1;
    }

    private function getTableColumns($table)
    {
        return mwp_context()->getDb()->get_results(
            sprintf('SHOW FULL COLUMNS FROM `%s`', $this->escapeTableName($table)),
            ARRAY_A
        );
    }

    private function getTableIndexes($table)
    {
        return mwp_context()->getDb()->get_results(
            sprintf('SHOW INDEXES FROM `%s`', $this->escapeTableName($table)),
            ARRAY_A
        );
    }

    private function getCreateTableSql($table)
    {
        $createTableResults = mwp_context()->getDb()->get_results(
            sprintf('SHOW CREATE TABLE `%s`', $this->escapeTableName($table)),
            ARRAY_A
        );

        return isset($createTableResults[0]['Create Table']) ? $createTableResults[0]['Create Table'] : null;
    }

    private function getTableInfo($table)
    {
        $context = mwp_context();
        $db      = $context->getDb();
        $dbName  = $context->getDbName();

        $tableStatusResult = $db->get_results(
            sprintf('SHOW TABLE STATUS FROM `%s` WHERE Name = "%s"',
                $this->escapeTableName($dbName),
                $context->escapeParameter($table)), ARRAY_A
        );

        $charsetResult = $db->get_results(
            sprintf('SELECT CCSA.character_set_name FROM information_schema.`TABLES` T, information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA WHERE CCSA.collation_name = T.table_collation AND T.table_schema = "%s" AND T.table_name = "%s"',
                $context->escapeParameter($dbName),
                $context->escapeParameter($table)), ARRAY_A
        );

        $engine        = $tableStatusResult[0]['Engine'];
        $autoIncrement = $tableStatusResult[0]['Auto_increment'];
        $collation     = $tableStatusResult[0]['Collation'];
        $charset       = $charsetResult[0]['character_set_name'];

        return array(
            'engine'        => $engine,
            'autoIncrement' => $autoIncrement,
            'collation'     => $collation,
            'charset'       => $charset,
        );
    }

    /**
     * Escape backticks (`) in table names.
     * A table can contain a backtick character (`) and it has to be escaped with another backtick.
     * This is only relevant
     *
     * e.g. asd`asd should be converted into asd``asd.
     * The resulting SQL query should look like: SELECT * FROM `asd``asd`;
     *
     * @param string $table
     *
     * @return string
     */
    private function escapeTableName($table)
    {
        return str_replace('`', '``', $table);
    }
}
