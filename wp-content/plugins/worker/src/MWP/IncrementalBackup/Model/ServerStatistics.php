<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Model_ServerStatistics
{

    /** @var int */
    private $memoryPeak;

    /** @var int */
    private $memoryUsage;

    /** @var int */
    private $averageLoad;

    /** @var bool */
    private $shellAvailable;

    function __construct($memoryPeak, $memoryUsage, $averageLoad, $shellAvailable)
    {
        $this->memoryPeak     = $memoryPeak;
        $this->memoryUsage    = $memoryUsage;
        $this->averageLoad    = $averageLoad;
        $this->shellAvailable = $shellAvailable;
    }

    /**
     * @return int
     */
    public function getMemoryPeak()
    {
        return $this->memoryPeak;
    }

    /**
     * @param int $memoryPeak
     */
    public function setMemoryPeak($memoryPeak)
    {
        $this->memoryPeak = $memoryPeak;
    }

    /**
     * @return int
     */
    public function getMemoryUsage()
    {
        return $this->memoryUsage;
    }

    /**
     * @param int $memoryUsage
     */
    public function setMemoryUsage($memoryUsage)
    {
        $this->memoryUsage = $memoryUsage;
    }

    /**
     * @return int
     */
    public function getAverageLoad()
    {
        return $this->averageLoad;
    }

    /**
     * @param int $averageLoad
     */
    public function setAverageLoad($averageLoad)
    {
        $this->averageLoad = $averageLoad;
    }

    /**
     * @return boolean
     */
    public function isShellAvailable()
    {
        return $this->shellAvailable;
    }

    /**
     * @param boolean $shellAvailable
     */
    public function setShellAvailable($shellAvailable)
    {
        $this->shellAvailable = $shellAvailable;
    }

    public function toArray()
    {
        return array(
            'memory_peak'     => $this->getMemoryPeak(),
            'memory_usage'    => $this->getMemoryUsage(),
            'average_load'    => $this->getAverageLoad(),
            'shell_available' => $this->isShellAvailable(),
        );
    }

    public static function factory()
    {
        return new self(
            memory_get_peak_usage(true),
            memory_get_usage(true),
            function_exists('sys_getloadavg') ? sys_getloadavg() : array(0, 0, 0),
            mwp_is_shell_available()
        );
    }
}
