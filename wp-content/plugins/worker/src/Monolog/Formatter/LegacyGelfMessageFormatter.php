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
 * Serializes a log message to GELF
 *
 * @see    http://www.graylog2.org/about/gelf
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class Monolog_Formatter_LegacyGelfMessageFormatter extends Monolog_Formatter_NormalizerFormatter
{
    /**
     * @var string the name of the system for the Gelf log message
     */
    protected $systemName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * Translates Monolog log levels to Graylog2 log priorities.
     */
    private $logLevels = array(
        Monolog_Logger::DEBUG     => 7,
        Monolog_Logger::INFO      => 6,
        Monolog_Logger::NOTICE    => 5,
        Monolog_Logger::WARNING   => 4,
        Monolog_Logger::ERROR     => 3,
        Monolog_Logger::CRITICAL  => 2,
        Monolog_Logger::ALERT     => 1,
        Monolog_Logger::EMERGENCY => 0,
    );

    public function __construct($systemName = null, $extraPrefix = null, $contextPrefix = 'ctxt_')
    {
        parent::__construct('U.u');

        $this->systemName = $systemName ? $systemName : php_uname('n');

        $this->extraPrefix   = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record  = parent::format($record);
        $message = new Gelf_Message();
        $message
            ->setTimestamp($record['datetime'])
            ->setShortMessage((string) $record['message'])
            ->setFacility($record['channel'])
            ->setHost($this->systemName)
            ->setLine(isset($record['extra']['line']) ? $record['extra']['line'] : null)
            ->setFile(isset($record['extra']['file']) ? $record['extra']['file'] : null)
            ->setLevel($this->logLevels[$record['level']]);

        // Do not duplicate these values in the additional fields
        unset($record['extra']['line']);
        unset($record['extra']['file']);

        if (isset($record['extra']['memory_usage'])) {
            $record['extra']['mem_usage'] = $record['extra']['memory_usage'];
            unset($record['extra']['memory_usage']);
        }

        if (isset($record['extra']['memory_real_usage'])) {
            $record['extra']['mem_real_usage'] = $record['extra']['memory_real_usage'];
            unset($record['extra']['memory_real_usage']);
        }

        if (isset($record['extra']['memory_peak_usage'])) {
            $record['extra']['mem_peak_usage'] = $record['extra']['memory_peak_usage'];
            unset($record['extra']['memory_peak_usage']);
        }

        foreach ($record['extra'] as $key => $val) {
            $message->setAdditional($this->extraPrefix.$key, is_scalar($val) ? $val : $this->toJson($val));
        }

        foreach ($record['context'] as $key => $val) {
            $message->setAdditional($this->contextPrefix.$key, is_scalar($val) ? $val : $this->toJson($val));
        }

        if (null === $message->getFile() && isset($record['context']['exception'])) {
            if (preg_match("/^(.+):([0-9]+)$/", $record['context']['exception']['file'], $matches)) {
                $message->setFile($matches[1]);
                $message->setLine($matches[2]);
            }
        }

        return $message;
    }
}
