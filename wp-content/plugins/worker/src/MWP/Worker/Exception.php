<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Worker_Exception extends Exception
{
    const CONNECTION_PUBLIC_KEY_EXISTS = 10001;
    const CONNECTION_PUBLIC_KEY_NOT_FOUND = 10010;
    const CONNECTION_SIGNATURE_NOT_VALID = 10011;
    const CONNECTION_SIGNATURE_EMPTY = 10018;
    const OPENSSL_ENCRYPT_ERROR = 10002;
    const OPENSSL_DECRYPT_ERROR = 10003;
    const OPENSSL_VERIFY_ERROR = 10005;
    const PHPSECLIB_ENCRYPT_ERROR = 10006;
    const PHPSECLIB_DECRYPT_ERROR = 10007;
    const PHPSECLIB_VERIFY_ERROR = 10009;
    const NONCE_FORMAT_INVALID = 10012;
    const NONCE_EXPIRED = 10013;
    const NONCE_ALREADY_USED = 10014;
    const AUTHENTICATION_NO_ADMIN_USER = 10015;
    const AUTHENTICATION_MESSAGE_ID_EMPTY = 10016;
    const AUTHENTICATION_PUBLIC_KEY_EMPTY = 10017;
    const AUTHENTICATION_INVALID_SIGNATURE = 10019;
    const AUTHENTICATION_INVALID_SERVICE_SIGNATURE = 10040;
    const ACTION_NOT_REGISTERED = 10020;
    const CONNECTION_PUBLIC_KEY_NOT_PROVIDED = 10021;
    const CONNECTION_VERIFICATION_TEST_FAILED = 10022;
    const PHP_EVAL_ERROR = 10023;
    const LEGACY_AUTHENTICATION_INVALID_SIGNATURE = 10024;
    const LEGACY_AUTHENTICATION_KEY_EXISTS = 10025;
    const AUTO_LOGIN_USERNAME_REQUIRED = 10026;
    const FILESYSTEM_CREDENTIALS_ERROR = 10027;
    const SHELL_NOT_AVAILABLE = 10028;
    const BACKUP_DATABASE_METHOD_NOT_AVAILABLE = 10029;
    const BACKUP_DATABASE_FAILED = 10030;
    const BACKUP_DATABASE_MISSING_TABLES = 10031;
    const IO_EXCEPTION = 10032;
    const BACKUP_DATABASE_INVALID_OUTPUT_METHOD = 10033;
    const PHP_EXTENSION_REQUIRED_CURL = 10034;
    const JSON_RESPONSE_EXCEPTION = 10035;
    const WORKER_RECOVERING = 10036;
    const WORKER_UPDATING = 10037;
    const WORKER_RECOVER_STARTED = 10038;
    const CONNECTION_INVALID_KEY = 10100;

    const GENERAL_ERROR = 10000;

    static $codes = array();

    protected $errorName;

    protected $context;

    public function __construct($code, $message = null, array $context = array())
    {
        $this->errorName = $this->getErrorNameForCode($code);
        $this->context   = $context;

        if ($message === null) {
            $message = sprintf('Error [%d]: %s', $code, $this->errorName);
        }

        parent::__construct($message, $code);
    }

    private function getErrorNameForCode($code)
    {
        if (count(self::$codes) === 0) {
            $reflectionClass = new ReflectionClass(__CLASS__);
            self::$codes     = array_flip($reflectionClass->getConstants());
        }

        if (array_key_exists($code, self::$codes)) {
            return self::$codes[$code];
        }

        return self::GENERAL_ERROR;
    }

    /**
     * @return string
     */
    public function getErrorName()
    {
        return $this->errorName;
    }

    /**
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }
}
