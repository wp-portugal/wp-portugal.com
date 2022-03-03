<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_GetViewSchema
{
    const ERROR_VIEW_NOT_FOUND = 1;

    public function execute(array $params = array())
    {
        $views = $params['views'];

        $result = array();

        foreach ($views as $view) {
            if (!$this->viewExists($view)) {
                $result[] = array(
                    'name'  => $view,
                    'error' => self::ERROR_VIEW_NOT_FOUND,
                );
                continue;
            }

            $createTableSql = $this->getCreateViewSql($view);

            $result[] = array(
                'name'           => $view,
                'createViewSql' => $createTableSql,
            );
        }

        return $result;
    }

    private function viewExists($table)
    {
        $exists = (int) mwp_context()->getDb()->get_var(
            sprintf('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = "%s" AND table_name = "%s"',
                mwp_context()->escapeParameter(mwp_context()->getDbName()),
                mwp_context()->escapeParameter($table))
        );

        return $exists >= 1;
    }

    private function getCreateViewSql($table)
    {
        $createTableResults = mwp_context()->getDb()->get_results(
            sprintf('SHOW CREATE VIEW `%s`', $this->escapeTableName($table)),
            ARRAY_A
        );

        return isset($createTableResults[0]['Create View']) ? $createTableResults[0]['Create View'] : null;
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
