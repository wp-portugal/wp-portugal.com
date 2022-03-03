<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_System_Utils
{
    /**
     * Converts value of 'memory_limit' php.ini directive to bytes.
     *
     * @param int|string $memoryLimit
     *
     * @return int Limit in bytes or -1 if it's unlimited.
     */
    public static function convertToBytes($memoryLimit)
    {
        $memoryLimit = (string)$memoryLimit;

        if ('-1' === $memoryLimit) {
            return -1;
        }

        $memoryLimit = strtolower($memoryLimit);
        $max         = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = intval($max);
        }

        switch (substr($memoryLimit, -1)) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 't':
                $max *= 1024;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'g':
                $max *= 1024;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'm':
                $max *= 1024;
            case 'k':
                $max *= 1024;
        }

        return $max;
    }

    public static function getCurrentMemoryLimit()
    {
        return MWP_System_Utils::convertToBytes(ini_get('memory_limit'));
    }

    public static function getWPMemoryLimit()
    {
        if (!defined('WP_MEMORY_LIMIT')) {
            return '64M';
        }

        return WP_MEMORY_LIMIT;
    }

    public static function getWPMaxMemoryLimit()
    {
        if (!defined('WP_MAX_MEMORY_LIMIT')) {
            return '256M';
        }

        return WP_MAX_MEMORY_LIMIT;
    }

    public static function setMemoryLimit($tryLimit)
    {
        $limitValue   = self::convertToBytes($tryLimit);
        $currentValue = self::getCurrentMemoryLimit();

        if ($currentValue === -1 || $currentValue >= $limitValue) {
            return;
        }

        @ini_set('memory_limit', $tryLimit);
    }
}
