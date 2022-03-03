<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_Limit implements MWP_Stream_Interface
{

    /**
     * @var MWP_Stream_Interface
     */
    private $stream;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var int
     */
    private $limit = -1;

    public function __construct(MWP_Stream_Interface $stream, $offset = 0, $limit = -1)
    {
        $this->stream = $stream;
        $this->setOffset($offset);
        $this->limit = $limit;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        // Always return true if the underlying stream is EOF
        if ($this->stream->eof()) {
            return true;
        }

        // No limit and the underlying stream is not at EOF
        if ($this->limit == -1) {
            return false;
        }

        $tell = $this->stream->tell();
        if ($tell === false) {
            return false;
        }

        return $tell >= $this->offset + $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        if ($this->limit == -1) {
            return $this->stream->read($length);
        }

        // Check if the current position is less than the total allowed
        // bytes + original offset
        $remaining = ($this->offset + $this->limit) - $this->stream->tell();
        if ($remaining > 0) {
            // Only return the amount of requested data, ensuring that the byte
            // limit is not exceeded
            return $this->stream->read(min($remaining, $length));
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setOffset($offset)
    {
        $current = $this->stream->tell();

        if ($current !== $offset) {
            // If the stream cannot seek to the offset position, then read to it
            if (!$this->stream->seek($offset)) {
                $this->stream->read($offset - $current);
            }
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->stream->tell() - $this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        return $this->stream->isSeekable();
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if ($whence !== SEEK_SET || $offset < 0) {
            return false;
        }

        $offset += $this->offset;

        if ($this->limit !== -1) {
            if ($offset > $this->offset + $this->limit) {
                $offset = $this->offset + $this->limit;
            }
        }

        return $this->stream->seek($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->stream->close();
    }

    public function __toString()
    {
        $buffer = '';

        while (!$this->eof()) {
            $buffer .= $this->read(1048576);
        }

        return $buffer;
    }
}
