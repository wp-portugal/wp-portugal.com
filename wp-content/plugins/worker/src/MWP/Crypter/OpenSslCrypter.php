<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Crypter_OpenSslCrypter implements MWP_Crypter_Interface
{
    public function publicEncrypt($data, $publicKey)
    {
        $errorCatcher = new MWP_Debug_ErrorCatcher();
        $errorCatcher->register('openssl_public_encrypt');
        /** @handled function */
        $success   = @openssl_public_encrypt($data, $crypted, $publicKey);
        $lastError = $errorCatcher->yieldErrorMessage(true);

        if ($success === false) {
            $error = $errorRow = '';

            /** @handled function */
            while (($errorRow = openssl_error_string()) !== false) {
                $error = $errorRow."\n".$error;
            }

            throw new MWP_Worker_Exception(MWP_Worker_Exception::OPENSSL_ENCRYPT_ERROR, "There was an error while trying to use OpenSSL to encrypt a message.", array(
                'openSslError' => $error,
                'error'        => isset($lastError['message']) ? $lastError['message'] : null,
            ));
        }

        return $crypted;
    }

    public function publicDecrypt($data, $publicKey)
    {
        $errorCatcher = new MWP_Debug_ErrorCatcher();
        $errorCatcher->register('openssl_public_decrypt');
        /** @handled function */
        $success   = @openssl_public_decrypt($data, $decrypted, $publicKey);
        $lastError = $errorCatcher->yieldErrorMessage(true);

        if ($success === false && $lastError !== null) {
            $error = $errorRow = '';

            /** @handled function */
            while (($errorRow = openssl_error_string()) !== false) {
                $error = $errorRow."\n".$error;
            }

            throw new MWP_Worker_Exception(MWP_Worker_Exception::OPENSSL_DECRYPT_ERROR, "There was an error while trying to use OpenSSL to decrypt a message.", array(
                'openSslError' => $error,
                'error'        => $lastError,
            ));
        }

        return $decrypted === false ? null : $decrypted;
    }
}
