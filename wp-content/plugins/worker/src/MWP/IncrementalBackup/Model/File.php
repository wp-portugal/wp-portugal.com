<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Model_File
{

    /**
     * @var string
     */
    private $pathname;

    /**
     * @var MWP_Stream_Interface
     */
    private $stream;

    /**
     * @var string
     */
    private $encoding = 'binary';

    /**
     * @return string
     */
    public function getPathname()
    {
        return $this->pathname;
    }

    /**
     * @param string $pathname
     */
    public function setPathname($pathname)
    {
        $this->pathname = $pathname;
    }

    /**
     * @return MWP_Stream_Interface
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * @param MWP_Stream_Interface $stream
     */
    public function setStream($stream)
    {
        $this->stream = $stream;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
    }
}
