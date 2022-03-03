<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Stream_Interface
{
    /**
     * Closes the stream and any underlying resources.
     */
    public function close();

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int|bool Returns the position of the file pointer or false on error
     */
    public function tell();

    /**
     * @return bool
     */
    public function isSeekable();

    /**
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function seek($offset, $whence = SEEK_SET);

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof();

    /**
     * Read data from the stream
     *
     * @param int $length Read up to $length bytes from the object and return
     *                    them. Fewer than $length bytes may be returned if
     *                    underlying stream call returns fewer bytes.
     *
     * @return string     Returns the data read from the stream.
     */
    public function read($length);
}
