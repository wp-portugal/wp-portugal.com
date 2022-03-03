<?php

/**
 * Poor man's PHP 5.2 port of FilesystemIterator from PHP 5.3.
 *
 * WARNING: Do not use this one in PHP >=5.3, use native FilesystemIterator instead.
 *
 * @see http://php.net/manual/en/class.filesystemiterator.php
 * @see https://github.com/php/php-src/blob/master/ext/spl/spl_directory.c
 */
class Symfony_Filesystem_FilesystemIterator extends DirectoryIterator implements SeekableIterator
{
    const  CURRENT_AS_PATHNAME = 32;
    const  CURRENT_AS_FILEINFO = 0;
    const  CURRENT_AS_SELF = 16;
    const  CURRENT_MODE_MASK = 240;
    const  KEY_AS_PATHNAME = 0;
    const  KEY_AS_FILENAME = 256;
    const  FOLLOW_SYMLINKS = 512;
    const  KEY_MODE_MASK = 3840;
    const  NEW_CURRENT_AND_KEY = 256;
    const  SKIP_DOTS = 4096;
    const  UNIX_PATHS = 8192;

    private $flags;

    /**
     * @param string $path
     * @param int    $flags
     */
    public function __construct($path, $flags = null)
    {
        if ($flags === null) {
            $flags = self::KEY_AS_PATHNAME | self::CURRENT_AS_FILEINFO | self::SKIP_DOTS;
        }
        $this->flags = $flags;
        parent::__construct($path);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if ($this->flags & self::CURRENT_AS_PATHNAME) {
            return $this->getPathname();
        } elseif ($this->flags & self::CURRENT_AS_SELF) {
            return $this;
        } else {
            return $this->getFileInfo();
        }
    }

    /**
     * @return string
     */
    public function key()
    {
        if ($this->flags & self::KEY_AS_FILENAME) {
            return $this->getFilename();
        }

        return $this->getPathname();
    }

    /**
     */
    public function next()
    {
        do {
            parent::next();
        } while ($this->isDot());
    }

    /**
     */
    public function rewind()
    {
        parent::rewind();
        while ($this->isDot()) {
            $this->next();
        }
    }

    /**
     * @param int $flags
     */
    public function setFlags($flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Overridden to not crash PHP 5.2, because DirectoryIterator::seek was added in 5.3.
     *
     * @link http://lxr.php.net/xref/PHP_5_3/ext/spl/spl_directory.c#807
     */
    public function seek($position)
    {
        if (is_callable(array('parent', 'seek'))) {
            parent::seek($position);

            return;
        }

        if ($this->key() > $position) {
            $this->rewind();
        }

        while ($this->key() < $position) {
            if (!$this->valid()) {
                return;
            }
            $this->next();
        }
    }
}
