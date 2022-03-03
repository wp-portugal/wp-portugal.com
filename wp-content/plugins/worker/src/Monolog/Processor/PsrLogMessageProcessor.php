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
 * Processes a record's message according to PSR-3 rules
 *
 * It replaces {foo} with the value from $context['foo']
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Monolog_Processor_PsrLogMessageProcessor implements Monolog_Processor_ProcessorInterface
{
    /**
     * @param array $record
     *
     * @return array
     */
    public function callback(array $record)
    {
        if (false === strpos($record['message'], '{')) {
            return $record;
        }

        $replacements = array();
        foreach ($record['context'] as $key => $val) {
            if (!is_scalar($val) && !is_callable($val, '__toString')) {
                continue;
            }
            $replacements['{'.$key.'}'] = $val;
        }

        $record['message'] = strtr($record['message'], $replacements);

        return $record;
    }
}
