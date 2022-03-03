<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Model_FetchFilesResult
{

    /**
     * @var MWP_IncrementalBackup_Model_File[]
     */
    private $files = array();

    /**
     * @var MWP_IncrementalBackup_Model_ServerStatistics
     */
    private $serverStatistics;

    function __construct()
    {
    }

    /**
     * @return MWP_IncrementalBackup_Model_File[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param MWP_IncrementalBackup_Model_File[] $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * @param MWP_IncrementalBackup_Model_File $file
     */
    public function addFile(MWP_IncrementalBackup_Model_File $file)
    {
        $this->files[] = $file;
    }

    /**
     * @return MWP_IncrementalBackup_Model_ServerStatistics
     */
    public function getServerStatistics()
    {
        return $this->serverStatistics;
    }

    /**
     * @param MWP_IncrementalBackup_Model_ServerStatistics $serverStatistics
     */
    public function setServerStatistics($serverStatistics)
    {
        $this->serverStatistics = $serverStatistics;
    }
}
