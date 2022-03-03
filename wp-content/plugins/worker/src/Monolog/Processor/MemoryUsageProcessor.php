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
 * Injects memory_get_usage in all records
 *
 * @see    Monolog\Processor\MemoryProcessor::__construct() for options
 * @author Rob Jensen
 */
class Monolog_Processor_MemoryUsageProcessor extends Monolog_Processor_MemoryProcessor
{
    /**
     * @param array $record
     *
     * @return array
     */
    public function callback(array $record)
    {
        $bytesReal     = memory_get_usage(true);
        $formattedReal = self::formatBytes($bytesReal);
        $bytes         = memory_get_usage(false);
        $formatted     = self::formatBytes($bytes);

        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'memory_usage'      => $formatted,
                'memory_real_usage' => $formattedReal,
            )
        );

        return $record;
    }
}
