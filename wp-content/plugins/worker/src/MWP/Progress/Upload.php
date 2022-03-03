<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Progress_Upload extends MWP_Progress_Abstract
{
    /**
     * @var int
     */
    private $fileSize;

    /**
     * @var Monolog_Psr_LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $lastProgress = 0;

    public function __construct($fileSize, $threshold, Monolog_Psr_LoggerInterface $logger)
    {
        $this->fileSize = $fileSize;
        $this->setThreshold($threshold);
        $this->logger = $logger;
    }

    public function callback(&$curl, $downloadSize, $downloadedSize, $uploadSize, $uploadedSize = 0)
    {
        if (!$this->yieldCallback()) {
            return;
        }

        if (func_num_args() < 5) {
            $uploadedSize   = $uploadSize;
            $uploadSize     = $downloadedSize;
            $downloadedSize = $downloadSize;
            $downloadSize   = $curl;
        }

        $offset             = is_resource($curl) ? $this->calculateOffset($curl) : 0;
        $currentProgress    = $uploadedSize + $offset;
        $speed              = $this->formatBytes(($currentProgress - $this->lastProgress) / $this->getThreshold());
        $this->lastProgress = $currentProgress;

        $progress = round($currentProgress / $this->fileSize * 100, 2);

        global $forkedRequest;
        if (!$forkedRequest) {
            echo " ";
            flush();
        }

        $this->logger->info(
            'Upload progress: {progress}% (speed: {speed}/s)',
            array(
                'progress' => $progress,
                'speed'    => $speed,
            )
        );
    }
}
