<?php

class Symfony_Process_Callback
{
    private $process;

    private $callback;

    private $out;

    /**
     * @param Symfony_Process_Process $process
     * @param string                  $out
     * @param callable|null           $callback
     */
    public function __construct(Symfony_Process_Process $process, $out, $callback = null)
    {
        $this->process  = $process;
        $this->out      = $out;
        $this->callback = $callback;
    }

    public function callback($type, $data)
    {
        if ($this->out === $type) {
            $this->process->addOutput($data);
        } else {
            $this->process->addErrorOutput($data);
        }

        if (null !== $this->callback) {
            call_user_func($this->callback, $type, $data);
        }
    }
}