<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Progress_CurlCallbackInterface
{
    /**
     * NOTE: The reason the fifth argument is optional is because some curl builds don't pass the curl handle as
     * the first argument; instead they pass 4 arguments. The implementation (ideally an abstract one) should
     * handle that case.
     *
     * @param resource $curl
     * @param int      $downloadSize
     * @param int      $downloadedSize
     * @param int      $uploadSize
     * @param int      $uploadedSize
     */
    public function callback(&$curl, $downloadSize, $downloadedSize, $uploadSize, $uploadedSize = 0);

    public function getCallback();
}
