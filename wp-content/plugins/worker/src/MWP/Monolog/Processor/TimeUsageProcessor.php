<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Monolog_Processor_TimeUsageProcessor implements Monolog_Processor_ProcessorInterface
{
    public function callback(array $record)
    {
        $timeUsage = $this->getTimeUsage();

        if ($timeUsage) {
            $record['extra'] = array_merge($record['extra'], $timeUsage);
        }

        return $record;
    }

    private function getTimeUsage()
    {
        if (!function_exists('getrusage')) {
            return null;
        }

        $ru = getrusage();

        return array(
            'user_time'   => $ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1000000,
            'system_time' => $ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1000000,
        );
    }
}
