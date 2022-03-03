<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_ProcessOutput extends MWP_Stream_Callable
{

    /**
     * @var Symfony_Process_Process
     */
    private $process;

    /**
     * @var bool
     */
    private $ran = false;

    public function __construct(Symfony_Process_Process $process)
    {
        parent::__construct(array($this, 'getIncrementalOutput'));
        $this->process = $process;
    }

    /**
     * Returns incremental process output (even if empty string) or false if the process has finished
     * successfully and all output was already returned.
     *
     * @throws Symfony_Process_Exception_ProcessFailedException If the process did not exit successfully.
     *
     * @internal
     *
     * @return string|false
     */
    public function getIncrementalOutput()
    {
        if (!$this->ran) {
            $this->ran = true;
            try {
                $this->process->start();
            } catch (Symfony_Process_Exception_ExceptionInterface $e) {
                throw new Symfony_Process_Exception_ProcessFailedException($this->process);
            }
        }

        if ($this->process->isRunning()) {
            $output = $this->process->getIncrementalOutput();
            $this->process->clearOutput();

            if (strlen($output) < Symfony_Process_Pipes_PipesInterface::CHUNK_SIZE) {
                // Don't hog the processor while waiting for incremental process output.
                usleep(100000);
            }

            // The stream will be read again because we're returning a string.
            return (string)$output;
        } else {
            if (!$this->process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($this->process);
            }

            $output = $this->process->getIncrementalOutput();
            $this->process->clearOutput();

            // The process has finished and is successful. This part will probably get run twice,
            // first time we'll return final output, second time we'll return 'false' and break the loop.
            return strlen($output) ? $output : false;
        }
    }
}
