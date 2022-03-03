<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_FileManager_Model_DownloadFilesResult
{

    /**
     * @var MWP_FileManager_Model_Files[]
     */
    private $files;

    function __construct()
    {
    }

    /**
     * @return MWP_FileManager_Model_Files[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param MWP_FileManager_Model_Files[] $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function addFile(MWP_FileManager_Model_Files $file)
    {
        $this->files[] = $file;
    }
}
