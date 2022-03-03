<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */



class MWP_Http_StreamingResponse extends MWP_Http_Response implements MWP_Http_StreamingResponseInterface
{


    public function __construct($stream, $statusCode = 200, array $headers = array())
    {
        parent::__construct(new MWP_Stream_Base64EncodedStream($stream), $statusCode, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function getContentAsString()
    {
        return base64_decode($this->content);
    }

    /**
     * @return MWP_Stream_Interface
     */
    public function createResponseStream()
    {
        return $this->content;
    }
}
