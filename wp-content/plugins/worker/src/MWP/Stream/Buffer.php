<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_Buffer
{

    private $hwm;

    private $buffer = '';

    /**
     * @param int $hwm High water mark, representing the preferred maximum
     *                 buffer size. If the size of the buffer exceeds the high
     *                 water mark, then calls to write will continue to succeed
     *                 but will return false to inform writers to slow down
     *                 until the buffer has been drained by reading from it.
     */
    public function __construct($hwm = 16384)
    {
        $this->hwm = $hwm;
    }

    public function close()
    {
        $this->buffer = '';
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    public function eof()
    {
        return strlen($this->buffer) === 0;
    }

    public function tell()
    {
        return false;
    }

    /**
     * Reads data from the buffer.
     *
     * @param $length
     *
     * @return string
     */
    public function read($length)
    {
        $currentLength = strlen($this->buffer);

        if ($length >= $currentLength) {
            // No need to slice the buffer because we don't have enough data.
            $result       = $this->buffer;
            $this->buffer = '';
        } else {
            // Slice up the result to provide a subset of the buffer.
            $result       = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        }

        return $result;
    }

    /**
     * Writes data to the buffer.
     *
     * @param $string
     *
     * @return bool|int
     */
    public function write($string)
    {
        $this->buffer .= $string;

        if (strlen($this->buffer) >= $this->hwm) {
            return false;
        }

        return strlen($string);
    }
}
