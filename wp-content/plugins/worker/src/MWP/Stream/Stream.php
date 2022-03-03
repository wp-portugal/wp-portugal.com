<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_Stream implements MWP_Stream_Interface
{

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var bool
     */
    private $seekable;

    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource');
        }

        $this->stream = $stream;

        $meta           = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        return !$this->stream || feof($this->stream);
    }

    /**
     * Read data from the stream
     *
     * @param int $length Read up to $length bytes from the object and return
     *                    them. Fewer than $length bytes may be returned if
     *                    underlying stream call returns fewer bytes.
     *
     * @return string     Returns the data read from the stream.
     */
    public function read($length)
    {
        return fread($this->stream, $length);
    }

    public static function factory($resource)
    {
        $type = gettype($resource);

        if ($type == 'string') {
            $stream = fopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, $resource);
                fseek($stream, 0);
            }

            return new self($stream);
        }

        if ($type == 'resource') {
            return new self($resource);
        }

        if ($resource instanceof MWP_Stream_Interface) {
            return $resource;
        }

        throw new InvalidArgumentException('Invalid resource type: '.$type);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        return $this->seekable
            ? fseek($this->stream, $offset, $whence) === 0
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->stream !== null ? ftell($this->stream) : false;
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
