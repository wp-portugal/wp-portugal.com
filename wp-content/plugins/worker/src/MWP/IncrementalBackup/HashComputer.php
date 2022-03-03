<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_HashComputer
{

    // 100kb default value
    private $maxChunkByteSize = 102400;

    /**
     * @param int $maxChunkByteSize
     */
    public function setMaxChunkByteSize($maxChunkByteSize)
    {
        $this->maxChunkByteSize = $maxChunkByteSize;
    }

    /**
     * @return int
     */
    public function getMaxChunkByteSize()
    {
        return $this->maxChunkByteSize;
    }

    /**
     * Compute a full md5 file hash or compute a partial hash if $limit is present.
     * Returns null on failure.
     *
     * @param string  $realPath
     * @param int     $offset in bytes
     * @param int     $limit  in bytes
     * @param boolean $forcePartialHashing
     *
     * @return null|string
     */
    public function computeMd5Hash($realPath, $offset = 0, $limit = 0, $forcePartialHashing = false)
    {
        if ($limit === 0 && $offset === 0 && !$forcePartialHashing) {
            // md5_file is always faster if we don't chunk the file
            $hash = md5_file($realPath);

            return $hash !== false ? $hash : null;
        }

        $ctx = hash_init('md5');
        if (!$ctx) {
            // Fail to initialize file hashing
            return null;
        }

        // Calculate limit from file size and offset
        if ($limit === 0) {
            $limit = filesize($realPath) - $offset;
        }

        $fh = @fopen($realPath, "rb");
        if ($fh === false) {
            // Failed opening file, cleanup hash context
            hash_final($ctx);

            return null;
        }

        fseek($fh, $offset);

        while ($limit > 0) {
            // Limit chunk size to either our remaining chunk or max chunk size
            $chunkSize = $limit < $this->maxChunkByteSize ? $limit : $this->maxChunkByteSize;
            $limit     -= $chunkSize;

            $chunk = fread($fh, $chunkSize);
            hash_update($ctx, $chunk);
        }

        fclose($fh);

        return hash_final($ctx);
    }

    /**
     * Run md5sum process and return file hash, or null in case of error
     *
     * @param $realPath
     *
     * @return string|null
     */
    public function computeUnixMd5Sum($realPath)
    {
        try {
            $processBuilder = Symfony_Process_ProcessBuilder::create()
                ->setPrefix('md5sum')
                ->add($realPath);

            if (!mwp_is_shell_available()) {
                throw new MWP_Worker_Exception(MWP_Worker_Exception::SHELL_NOT_AVAILABLE, "Shell is not available");
            }

            $process = $processBuilder->getProcess();
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }

            // Output is in the format of "md5hash filename"
            $output = trim($process->getOutput());
            $parts  = explode(' ', $output);

            // Return only the first part of the output
            return trim($parts[0]);
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            mwp_logger()->error('MD5 command line sum failed', array(
                'process' => $e->getProcess(),
            ));
        } catch (Exception $e) {
            mwp_logger()->error('MD5 command line sum failed', array(
                'exception' => $e,
            ));
        }

        return null;
    }
} 
