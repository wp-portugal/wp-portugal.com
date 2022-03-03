<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_System_Environment
{

    /**
     * @var MWP_ServiceContainer_Interface
     */
    private $container;

    public function __construct(MWP_ServiceContainer_Interface $container)
    {
        $this->container = $container;
    }

    public function isPdoEnabled()
    {
        if ($this->container->getParameter('disable_pdo')) {
            return false;
        }

        return extension_loaded('pdo_mysql');
    }

    public function isMysqliEnabled()
    {
        if ($this->container->getParameter('disable_mysqli')) {
            return false;
        }

        return extension_loaded('mysqli');
    }

    public function isMysqlEnabled()
    {
        if ($this->container->getParameter('disable_mysql')) {
            return false;
        }

        return extension_loaded('mysql');
    }

    public function isCurlEnabled()
    {
        // Some hosting providers disable only curl_exec().
        return (function_exists('curl_init') && function_exists('curl_exec'));
    }

    public function isOpenSslLibraryEnabled()
    {
        if ($this->container->getParameter('prefer_phpseclib')) {
            return false;
        }

        if (!extension_loaded('openssl')) {
            return false;
        }

        $context = $this->container->getWordPressContext();

        $openSslParameters = $context->optionGet('mwp_openssl_parameters');

        if (isset($openSslParameters['time']) && time() - $openSslParameters['time'] < 86400) {
            return !empty($openSslParameters['working']);
        }

        $context->optionSet('mwp_openssl_parameters', array(
            'time'    => time(),
            'working' => false,
        ));

        $workingOpenSsl = $this->verifyIsOpenSslWorking();

        $context->optionSet('mwp_openssl_parameters', array(
            'time'    => time(),
            'working' => $workingOpenSsl,
        ));

        return $workingOpenSsl;
    }

    private function verifyIsOpenSslWorking()
    {
        $publicKey = <<<EOF
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwQzibtUfAtHeyFwgc0ev
HnvwSxnM8DBLEBgI/uMWV/GlhbRLqyHMiIe9p7UsMfeOgY4MJmug4j7ITGeYUVeX
Hit+GLlq5arntlYUyYm09YNPkBzfV6oxPcWScRVmmRXK2L40ZfRHdo24Wz8//Vwo
ZGWNX0ozq0I6yg5PAyyJt9+DV8dBCUVkDVaQ3qudn4kWSRVb/cD2foeddEcQ1Hni
dSgvfyrvTF7vPvyIpbHENEBFigUyoHzWXRWmAR2BkdPUN1gLHCaieQPeAOQS704H
HCDgCgja7zNOP/yT96H4pSyeD/VMj4dmudBjdiFCjfF0VEl5iv5siEBW6KHGP5Ye
0wIDAQAB
-----END PUBLIC KEY-----
EOF;

        $data      = 'my data';
        $signature = @base64_decode('Wu8wSnlTaxf3hSGj+6kAY0kP1B9VO3cZ6dTVGda0Ti8cHK/ea1hFqo9nUefppPmrVZrSMrFmkBWeOonHhesJxGUNd3AAd+MF9U8hl5a5H2LiA/IYCBZmWCdnJJrGnD8X0hEV0IEEPSvqK3BA6xzKYfCpi1weCH+BByp9asHdnPNKq3uegoXUxubQ6BOslCMZ6fMt9bNOY/2S5O9iV2N0OrPKa9Wseb2Z8qmo8T1l0Oczz66Gtff8/YHZPvZil45ggfeF8fDOHAdOT8LLDEb9k59X3DrKFI3h4OAqernP2ZrN5EpneCJeq5tSwdbDwbqIh+ghqD7ttb6oY2El6w17yw==');

        /** @handled function */
        $verifyResult = @openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA1);

        return $verifyResult === 1;
    }
}
