<?php

interface Symfony_Filesystem_Exception_IOExceptionInterface extends Symfony_Filesystem_Exception_ExceptionInterface
{
    /**
     * Returns the associated path for the exception
     *
     * @return string The path.
     */
    public function getPath();
}
