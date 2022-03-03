<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Signer_Interface
{
    /**
     * @param string $data
     * @param string $signature
     * @param string $publicKey
     *
     * @return bool
     */
    public function verify($data, $signature, $publicKey);
}