<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_ChecksumTables extends MWP_Action_IncrementalBackup_Abstract
{

    public function execute(array $params = array(), MWP_Worker_Request $request)
    {
        $tables = array_map(array($this, 'escapeName'), $params['query']);
        $query  = implode(',', $tables);

        $wpdb     = $this->container->getWordPressContext()->getDb();
        $results  = $wpdb->get_results('CHECKSUM TABLE '.$query, ARRAY_A);
        $checksum = array();

        foreach ($results as $row) {
            $checksum[$row['Table']] = $row['Checksum'];
        }

        return $this->createResult(array('checksum' => $checksum, 'db' => $this->container->getWordPressContext()->getConstant('DB_NAME')));
    }

    public function escapeName($tableName)
    {
        return "`{$tableName}`";
    }
}
