<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Http_ResponseInterface
{
    /**
     * @param mixed $content
     *
     * @return mixed
     */
    public function setContent($content);

    /**
     * @return mixed
     */
    public function getContent();

    /**
     * @return string
     */
    public function getContentAsString();

    /**
     * @return void
     */
    public function send();
}
