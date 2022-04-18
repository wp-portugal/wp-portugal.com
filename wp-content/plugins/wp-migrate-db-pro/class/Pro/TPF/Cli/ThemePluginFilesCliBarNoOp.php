<?php

namespace DeliciousBrains\WPMDB\Pro\TPF\Cli;

/**
 * Class ThemePluginFilesCLIBarNoOp
 * Provides a mostly non-operative interface for
 * \cli\progress\bar when bar output is not desirable
 */
class ThemePluginFilesCliBarNoOp
{
    private $_message = '';

    public function __construct()
    {
    }

    public function setTotal()
    {
    }

    public function setMessage($message)
    {
        $this->_message = $message;
    }

    public function tick()
    {
    }

    public function finish()
    {
        // log last _message to show count of files migrated
        \WP_CLI::log($this->_message);
    }
}
