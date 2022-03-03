<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_ListTables extends MWP_Action_IncrementalBackup_Abstract
{

    public function listTables(array $params = array(), MWP_Worker_Request $request)
    {
        $wpdb   = $this->container->getWordPressContext()->getDb();
        $db     = $this->container->getWordPressContext()->getConstant('DB_NAME');
        $tables = $wpdb->get_results($wpdb->prepare('SELECT table_name AS "table", data_length as size,table_type as type FROM information_schema.TABLES WHERE table_schema = %s', $db), ARRAY_A);

        return $this->createResult(array('tables' => $tables, 'db_prefix' => $wpdb->prefix));
    }
}
