<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_FetchFiles extends MWP_Action_IncrementalBackup_AbstractFiles
{

    public function execute(array $params = array())
    {
        /**
         * Each file is structured like:
         * [
         *  "relativePath"          => file path relative to ABSPATH,
         *  "pathEncoded"           => is path url encoded?,
         *  "size"                  => file size sent for reference,
         *  "offset"                => number of bytes to offset hash start (integer, optional, default 0),
         *  "limit"                 => number of bytes to hash (integer, optional, default 0),
         * ]
         */
        $requestedFiles = $params['files'];

        $result = new MWP_IncrementalBackup_Model_FetchFilesResult();

        foreach ($requestedFiles as $requestedFile) {
            $relativePath        = $requestedFile['relativePath'];
            $decodedRelativePath = $requestedFile['pathEncoded'] ? $this->pathDecode($relativePath) : $relativePath;
            $realPath            = $this->getRealPath($decodedRelativePath);
            $offset              = isset($requestedFile['offset']) ? $requestedFile['offset'] : 0;
            $limit               = isset($requestedFile['limit']) ? $requestedFile['limit'] : -1;

            $file = new MWP_IncrementalBackup_Model_File();
            $file->setPathname($relativePath);
            $file->setStream(new MWP_Stream_FileLimit(new MWP_Stream_LazyFile($realPath), $offset, $limit));
            if (isset($requestedFile['encoding'])) {
                $file->setEncoding($requestedFile['encoding']);
            }
            $result->addFile($file);
        }

        $result->setServerStatistics(MWP_IncrementalBackup_Model_ServerStatistics::factory());

        return $result;
    }
} 
