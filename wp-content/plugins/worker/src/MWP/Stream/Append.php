<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Append stream.
 */
class MWP_Stream_Append implements MWP_Stream_Interface
{

    /**
     * @var MWP_Stream_Interface[]
     */
    private $streams = array();

    private $current = 0;

    /**
     * Add a stream to the AppendStream
     *
     * @param MWP_Stream_Interface $stream Stream to append. Must be readable.
     */
    public function addStream(MWP_Stream_Interface $stream)
    {
        $this->streams[] = $stream;
    }

    /**
     * @return bool
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->isAtLastStream() && $this->getCurrentStream()->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        $data = '';

        while (!$this->eof()) {
            while ($this->getCurrentStream()->eof() && !$this->eof()) {
                $this->moveToNextStream();
            }

            $currentStreamData = $this->getCurrentStream()->read($length);
            $data .= $currentStreamData;
            $length -= strlen($currentStreamData);

            if ($length <= 0) {
                break;
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        foreach ($this->streams as $stream) {
            $stream->close();
        }
    }

    /**
     * Tell is not supported.
     */
    public function tell()
    {
        return false;
    }

    public function __toString()
    {
        $buffer = '';

        while (!$this->eof()) {
            $buffer .= $this->read(1048576);
        }

        return $buffer;
    }

    private function moveToNextStream()
    {
        if ($this->current >= count($this->streams)) {
            return false;
        }

        $this->current++;

        return true;
    }

    private function isAtLastStream()
    {
        return $this->current === count($this->streams) - 1;
    }

    private function getCurrentStream()
    {
        return $this->streams[$this->current];
    }
}
