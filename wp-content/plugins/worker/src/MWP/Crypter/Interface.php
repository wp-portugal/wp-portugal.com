<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Crypter_Interface
{
    /**
     * Encrypts data with public key. It can be only encrypted with the corresponding private key.
     *
     * @param string $data
     * @param string $publicKey
     *
     * @return string
     *
     * @throws MWP_Worker_Exception If there's anything wrong with the OpenSSL extension.
     */
    public function publicEncrypt($data, $publicKey);

    /**
     * Decrypts data encrypted with private key. It can be only decrypted with the corresponding public key.
     *
     * @param string $data
     * @param string $publicKey
     *
     * @return string|null
     *
     * @throws MWP_Worker_Exception If there's anything wrong with the OpenSSL extension.
     */
    public function publicDecrypt($data, $publicKey);
}