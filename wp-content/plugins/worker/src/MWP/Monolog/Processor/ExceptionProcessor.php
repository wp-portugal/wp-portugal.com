<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Monolog_Processor_ExceptionProcessor implements Monolog_Processor_ProcessorInterface
{
    public function callback(array $record)
    {
        if (!isset($record['context']['exception']) || !$record['context']['exception'] instanceof Exception) {
            return $record;
        }

        /** @var Exception $exception */
        $exception = $record['context']['exception'];
        unset($record['context']['exception']);

        $record['file'] = $exception->getFile();
        $record['line'] = $exception->getLine();

        $record['extra']['exception_class']   = get_class($exception);
        $record['extra']['exception_message'] = $exception->getMessage();
        $record['extra']['exception_code']    = $exception->getCode();
        $record['extra']['exception_trace']   = $exception->getTraceAsString();

        return $record;
    }
}
