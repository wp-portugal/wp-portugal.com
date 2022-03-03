<?php

interface Monolog_Processor_ProcessorInterface
{
    public function callback(array $record);
}
