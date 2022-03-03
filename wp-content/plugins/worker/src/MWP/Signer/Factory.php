<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Signer_Factory
{
    public static function createSigner()
    {
        if (extension_loaded('openssl')) {
            return self::createOpenSslSigner();
        }

        return self::createPhpSecLibSigner();
    }

    public static function createOpenSslSigner()
    {
        return new MWP_Signer_OpenSslSigner();
    }

    public static function createPhpSecLibSigner()
    {
        return new MWP_Signer_PhpSecLibSigner();
    }
}
