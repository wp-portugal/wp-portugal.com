<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Injects memory_get_peak_usage in all records
 *
 * @see    Monolog\Processor\MemoryProcessor::__construct() for options
 * @author Rob Jensen
 */
class Monolog_Processor_MemoryPeakUsageProcessor extends Monolog_Processor_MemoryProcessor
{
    /**
     * @param array $record
     *
     * @return array
     */
    public function callback(array $record)
    {
        $bytes     = memory_get_peak_usage($this->realUsage);
        $formatted = self::formatBytes($bytes);

        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'memory_peak_usage' => $formatted,
            )
        );

        return $record;
    }
}
