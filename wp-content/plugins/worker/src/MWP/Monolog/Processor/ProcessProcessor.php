<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Monolog_Processor_ProcessProcessor implements Monolog_Processor_ProcessorInterface
{
    public function callback(array $record)
    {
        if (!isset($record['context']['process']) || !$record['context']['process'] instanceof Symfony_Process_Process) {
            return $record;
        }
        /** @var Symfony_Process_Process $process */
        $process = $record['context']['process'];
        unset($record['context']['process']);
        $record['extra']['process_command'] = $process->getCommandLine();

        if ($process->getExitCode() !== null) {
            $record['extra']['process_exit_code']      = $process->getExitCode();
            $record['extra']['process_exit_code_text'] = $process->getExitCodeText();

            if (!$process->isSuccessful()) {
                $record['extra']['process_output']       = $process->getOutput();
                $record['extra']['process_error_output'] = $process->getErrorOutput();
            }
        }

        return $record;
    }
}
