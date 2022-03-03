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
 * Handler to send messages to a Graylog2 (http://www.graylog2.org) server
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class Monolog_Handler_LegacyGelfHandler extends Monolog_Handler_AbstractProcessingHandler
{
    /**
     * @var Gelf_Publisher the publisher object that sends the message to the server
     */
    protected $publisher;

    /**
     * @param Gelf_Publisher $publisher a publisher object
     * @param integer        $level     The minimum logging level at which this handler will be triggered
     * @param Boolean        $bubble    Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Gelf_Publisher $publisher, $level = Monolog_Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->publisher = $publisher;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->publisher = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->publisher->publish($record['formatted']);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new Monolog_Formatter_LegacyGelfMessageFormatter();
    }
}
