<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Signer_PhpSecLibSigner implements MWP_Signer_Interface
{
    public function verify($data, $signature, $publicKey)
    {
        $this->requireLibrary();

        $rsa = new Crypt_RSA();
        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $rsa->loadKey($publicKey, CRYPT_RSA_PUBLIC_FORMAT_PKCS1);
        $errorCatcher = new MWP_Debug_ErrorCatcher();
        $errorCatcher->register();
        $verify       = $rsa->verify($data, $signature);
        $errorMessage = $errorCatcher->yieldErrorMessage(true);

        if (!$verify && $errorMessage !== null && $errorMessage !== 'Signature representative out of range' && $errorMessage !== 'Invalid signature') {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::PHPSECLIB_VERIFY_ERROR, null, array(
                'error' => $errorMessage,
            ));
        }

        return $verify;
    }

    private function requireLibrary()
    {
        if (class_exists('Crypt_RSA', false)) {
            return;
        }

        require_once dirname(__FILE__).'/../../PHPSecLib/Crypt/RSA.php';
    }
}
