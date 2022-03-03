<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_StreamTables extends MWP_Action_IncrementalBackup_AbstractTablesAction
{
    /**
     * {@inheritdoc}
     */
    protected function executeAction(MWP_IncrementalBackup_Database_DumperInterface $dumper, array $tables = array(), array $params = array())
    {
        $this->assertTablesExist($tables);

        try {
            $createSeparateStreams = isset($params['options']['create_separate_streams']) ? $params['options']['create_separate_streams'] : null;
            $encoding              = isset($params['options']['encoding']) ? $params['options']['encoding'] : 'binary';

            if ($createSeparateStreams === true) {
                $result = $this->dumpToMultipleStreams($dumper, $tables);
            } else {
                $result = $this->dumpToSingleStream($dumper, $tables);
            }

            foreach ($result->getFiles() as $file) {
                $file->setEncoding($encoding);
            }

            return $result;
        } catch (Exception $e) {
            if ($e instanceof MWP_Worker_Exception) {
                throw $e;
            }

            // Convert any other exception to MWP_Worker_Exception
            throw new MWP_Worker_Exception(MWP_Worker_Exception::BACKUP_DATABASE_FAILED, $e->getMessage(), array(
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'type'    => get_class($e),
            ));
        }
    }

    /**
     * @param MWP_IncrementalBackup_Database_DumperInterface $dumper
     * @param array                                          $tables
     *
     * @return MWP_IncrementalBackup_Model_FetchFilesResult
     */
    protected function dumpToMultipleStreams(MWP_IncrementalBackup_Database_DumperInterface $dumper, array $tables)
    {
        $result = new MWP_IncrementalBackup_Model_FetchFilesResult();
        $result->setServerStatistics(MWP_IncrementalBackup_Model_ServerStatistics::factory());

        foreach ($tables as $table) {
            $stream = $dumper->createStream(array($table));

            $file = new MWP_IncrementalBackup_Model_File();
            $file->setPathname($table);
            $file->setStream($stream);
            $result->addFile($file);
        }

        return $result;
    }

    /**
     * @param MWP_IncrementalBackup_Database_DumperInterface $dumper
     * @param array                                          $tables
     *
     * @return MWP_IncrementalBackup_Model_FetchFilesResult
     */
    protected function dumpToSingleStream(MWP_IncrementalBackup_Database_DumperInterface $dumper, array $tables)
    {
        $stream = $dumper->createStream($tables);

        $file = new MWP_IncrementalBackup_Model_File();
        $file->setPathname('tables.sql');
        $file->setStream($stream);

        $result = new MWP_IncrementalBackup_Model_FetchFilesResult();
        $result->setServerStatistics(MWP_IncrementalBackup_Model_ServerStatistics::factory());
        $result->setFiles(array($file));

        return $result;
    }
}
