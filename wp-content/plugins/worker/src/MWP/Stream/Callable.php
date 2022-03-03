<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_Callable implements MWP_Stream_Interface
{

    /** @var callable */
    private $source;

    /** @var int */
    private $tellPos = 0;

    /** @var MWP_Stream_Buffer */
    private $buffer;

    /** @var array */
    private $arguments;

    /**
     * @param callable $source  Source of the stream data. The callable MAY
     *                          accept an integer argument used to control the
     *                          amount of data to return. The callable MUST
     *                          return a string when called, or false on error
     *                          or EOF.
     * @param array    $arguments
     */
    public function __construct($source, array $arguments = array())
    {
        $this->source    = $source;
        $this->buffer    = new MWP_Stream_Buffer();
        $this->arguments = $arguments;
    }

    public function close()
    {
        $this->tellPos   = false;
        $this->source    = null;
        $this->arguments = array();
    }

    public function tell()
    {
        return $this->tellPos;
    }

    public function eof()
    {
        if ($this->source !== null) {
            return false;
        }

        if ($this->source === null && $this->buffer !== null) {
            return $this->buffer->eof();
        }

        return true;
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    public function read($length)
    {
        $data    = $this->buffer->read($length);
        $readLen = strlen($data);
        $this->tellPos += $readLen;
        $remaining = $length - $readLen;

        if ($remaining) {
            $this->pump($remaining);
            $data .= $this->buffer->read($remaining);
            $this->tellPos += strlen($data) - $readLen;
        }

        return $data;
    }

    public function __toString()
    {
        $buffer = '';

        while (!$this->eof()) {
            $buffer .= $this->read(1048576);
        }

        return $buffer;
    }

    private function pump($length)
    {
        if ($this->source) {
            do {
                $data = call_user_func_array($this->source, array_merge(array($length), $this->arguments));
                if ($data === false || $data === null) {
                    $this->source = null;

                    return;
                }
                if ($data instanceof MWP_Stream_Interface) {
                    $this->source = null;
                    $this->buffer = $data;

                    return;
                }
                $this->buffer->write($data);
                $length -= strlen($data);
            } while ($length > 0);
        }
    }
}
