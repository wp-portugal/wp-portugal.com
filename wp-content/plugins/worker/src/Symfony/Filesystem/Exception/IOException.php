<?php


class Symfony_Filesystem_Exception_IOException extends RuntimeException implements Symfony_Filesystem_Exception_IOExceptionInterface
{
    private $path;

    public function __construct($message, $code = 0, Exception $previous = null, $path = null)
    {
        $this->path = $path;

        if (version_compare(PHP_VERSION, '5.3', '>=')) {
            parent::__construct($message, $code, $previous);
        } else {
            parent::__construct($message, $code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->path;
    }
}
