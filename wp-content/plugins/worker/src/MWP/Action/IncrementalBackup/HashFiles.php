<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_HashFiles extends MWP_Action_IncrementalBackup_AbstractFiles
{
    // 100MB
    const UNIX_HASH_THRESHOLD = 104857600;

    // 100KB
    const MAX_CHUNK_SIZE = 102400;

    public function execute(array $params = array(), MWP_Worker_Request $request)
    {
        $hashComputer = new MWP_IncrementalBackup_HashComputer();

        /**
         * Each file is structured like:
         * [
         *  "relativePath"          => file path relative to ABSPATH,
         *  "pathEncoded"           => is path url encoded?,
         *  "size"                  => file size sent for reference,
         *  "offset"                => number of bytes to offset hash start (integer, optional, default 0),
         *  "limit"                 => number of bytes to hash (integer, optional, default 0),
         *  "forcePartialHashing"   => partially hashes file instead of md5_file always (boolean, optional, default false),
         * ]
         */
        $files  = $params['files'];
        $result = array();

        // Allow overriding max chunk byte size per request for doing partial hashes
        $chunkByteSize = isset($params['maxChunkByteSize']) ? $params['maxChunkByteSize'] : self::MAX_CHUNK_SIZE;
        $hashComputer->setMaxChunkByteSize($chunkByteSize);

        $unixHashThreshold = isset($params['unixMd5Threshold']) ? $params['unixMd5Threshold'] : self::UNIX_HASH_THRESHOLD;

        foreach ($files as $file) {
            $relativePath        = $file['path'];
            $size                = $file['size'];
            $offset              = isset($file['offset']) ? $file['offset'] : 0;
            $limit               = isset($file['limit']) ? $file['limit'] : 0;
            $forcePartial        = isset($file['forcePartialHashing']) ? $file['forcePartialHashing'] : false;
            $decodedRelativePath = $file['pathEncoded'] ? $this->pathDecode($relativePath) : $relativePath;
            $realPath            = $this->getRealPath($decodedRelativePath);

            // Run a unix command to generate md5 hash if file size exceeds threshold
            // Ignore partial requests for big files because of speed problems
            if ($size > $unixHashThreshold && $offset === 0 && $limit === 0) {
                $hash = $hashComputer->computeUnixMd5Sum($realPath);

                if ($hash !== null) {
                    $result[] = array(
                        'path' => $relativePath,
                        'hash' => $hash,
                    );

                    continue;
                }
                // In case of a failed hashing fall back bellow to compute md5 hash from PHP
            }

            $hash     = $hashComputer->computeMd5Hash($realPath, $offset, $limit, $forcePartial);
            $result[] = array(
                'path' => $relativePath,
                'hash' => $hash,
            );
        }

        return $this->createResult(array(
            'files' => $result,
        ));
    }
}
