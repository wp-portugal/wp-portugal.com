<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_FileReader
{

    /**
     * @var int
     */
    private $chunkByteSize = 4096;

    /**
     * @return int
     */
    public function getChunkByteSize()
    {
        return $this->chunkByteSize;
    }

    /**
     * @param int $chunkByteSize
     */
    public function setChunkByteSize($chunkByteSize)
    {
        $this->chunkByteSize = $chunkByteSize;
    }

    /**
     *
     *
     * @param string $realPath
     * @param int    $offset
     * @param int    $limit
     *
     * @return mixed
     */
    public function readFileContents($realPath, $offset = 0, $limit = 0)
    {
        if (!file_exists($realPath)) {
            return null;
        }

        $handle = fopen($realPath, "rb");
        if (!$handle) {
            return null;
        }

        $contentLength = 0;
        $buffer        = '';

        if ($limit === 0) {
            $limit = filesize($realPath) - $offset;
        }

        if ($offset !== 0) {
            fseek($handle, $offset);
        }

        while ($limit > 0) {
            $chunkSize     = $limit > $this->chunkByteSize ? $this->chunkByteSize : $limit;
            $limit         = $limit - $chunkSize;
            $contentLength = $contentLength + $chunkSize;

            $contents = fread($handle, $chunkSize);
            $buffer   = $buffer.$contents;
        }

        fclose($handle);

        return array($buffer, $contentLength);
    }
} 
