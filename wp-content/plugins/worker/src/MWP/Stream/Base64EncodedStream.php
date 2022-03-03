<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Stream_Base64EncodedStream extends MWP_Stream_Decorator
{

    /** @var MWP_Stream_Interface */
    private $buffer;

    const BASE64_BLOCK_SIZE = 4;
    const ORIGIN_BLOCK_SIZE = 3;

    public function __construct(MWP_Stream_Interface $stream)
    {
        parent::__construct($stream);
        $this->buffer = new MWP_Stream_Buffer();
    }

    public function eof()
    {
        return $this->buffer->eof() && $this->getStream()->eof();
    }

    public function read($length)
    {
        $readFromBuffer = $this->buffer->read($length);
        if (strlen($readFromBuffer) === $length) {
            return $readFromBuffer;
        }

        $remaining = $length - strlen($readFromBuffer);

        // Calculate the approximate length required to read so that the base64 encoded stream does not have padding.
        // base64 is calculated for blocks of 3 input characters resulting in 4 output characters.
        // strlen(base64_encode($str)) ==> strlen($str) * 4 / 3
        //
        // This leads to:
        //
        // strlen($str) ==> strlen(base64_encode($str)) * 3 / 4
        //
        // Meaning, to read $length characters from the base64 encoded string, read 3/4 of $length from the original stream.
        // $length is first rounded to the first larger number divisible by 4 since base64 encoded strings come in blocks of 4 characters.
        $closestGroupLength = $remaining + (self::BASE64_BLOCK_SIZE - $remaining % self::BASE64_BLOCK_SIZE);
        $read               = $closestGroupLength * self::ORIGIN_BLOCK_SIZE / self::BASE64_BLOCK_SIZE;
        $this->buffer->write(base64_encode($this->getStream()->read($read)));

        return $readFromBuffer.$this->buffer->read($remaining);
    }
}
