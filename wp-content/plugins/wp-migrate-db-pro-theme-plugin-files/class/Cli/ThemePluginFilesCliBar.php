<?php

namespace DeliciousBrains\WPMDBTP\Cli;

/**
 * Class ThemePluginFilesCLIBar
 * Simple wrapper for \cli\progress\bar that
 * provides setter access to the _message property
 */
class THemePluginFilesCliBar extends \cli\progress\Bar
{
    public function setMessage($message)
    {
        $this->_message = $message;
    }
}
