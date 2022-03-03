<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Crypter_Factory
{
    public static function createCrypter()
    {
        if (extension_loaded('openssl')) {
            return self::createOpenSslCrypter();
        }

        return self::createPhpSecLibCrypter();
    }

    public static function createOpenSslCrypter()
    {
        return new MWP_Crypter_OpenSslCrypter();
    }

    public static function createPhpSecLibCrypter()
    {
        return new MWP_Crypter_PhpSecLibCrypter();
    }
}
