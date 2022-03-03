<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Security_HashNonce implements MWP_Security_NonceInterface
{
    /**
     * How much is this nonce valid for use
     */
    const NONCE_LIFETIME = 43200;
    /**
     * Blacklist time of nonce. The minimum value is NONCE_LIFETIME +1
     */
    const NONCE_BLACKLIST_TIME = 86400;
    /**
     * @var string
     */
    protected $nonce;
    /**
     * @var int
     */
    protected $issueAt;

    /**
     * {@inherits}
     */
    public function setValue($value)
    {
        $parts = explode("_", $value);
        if (count($parts) == 2) {
            list($this->nonce, $this->issueAt) = $parts;
        }
    }

    /**
     * {@inherits}
     */
    public function verify()
    {
        if (empty($this->nonce) || (int) $this->issueAt == 0) {
            return false;
        }
        if ($this->issueAt + self::NONCE_LIFETIME < time()) {
            return false;
        }
        /** @handled function */
        $nonceUsed = get_transient('n_'.$this->nonce);

        if ($nonceUsed !== false) {
            return false;
        }
        /** @handled function */
        set_transient('n_'.$this->nonce, $this->issueAt, self::NONCE_BLACKLIST_TIME); //need shorter name, because of 64 char limit

        return true;
    }
}
