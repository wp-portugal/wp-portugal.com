<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Security_NonceManager
{

    private $context;

    private $nonceValidFor;

    private $nonceBlacklistedFor;

    /**
     * @param MWP_WordPress_Context $context
     * @param int                   $nonceValidFor       How long (in seconds) is the nonce valid since its issue time.
     * @param int                   $nonceBlacklistedFor How long (in seconds) to keep used nonce in storage.
     */
    public function __construct(MWP_WordPress_Context $context, $nonceValidFor = 43200, $nonceBlacklistedFor = 86400)
    {
        if ($nonceBlacklistedFor < $nonceValidFor) {
            throw new LogicException('Nonce blacklist time must be higher than nonce lifetime.');
        }

        $this->context             = $context;
        $this->nonceValidFor       = $nonceValidFor;
        $this->nonceBlacklistedFor = $nonceBlacklistedFor;
    }

    /**
     * @param string $nonce
     *
     * @throws MWP_Security_Exception_NonceFormatInvalid
     * @throws MWP_Security_Exception_NonceExpired
     * @throws MWP_Security_Exception_NonceAlreadyUsed
     */
    public function useNonce($nonce)
    {
        $parts = explode('_', $nonce);

        if (count($parts) !== 2) {
            throw new MWP_Security_Exception_NonceFormatInvalid();
        }

        list($nonceValue, $issuedAt) = $parts;
        $issuedAt = (int) $issuedAt;

        if (!$nonceValue || !$issuedAt) {
            throw new MWP_Security_Exception_NonceFormatInvalid();
        }

        if ($issuedAt + $this->nonceValidFor < time()) {
            throw new MWP_Security_Exception_NonceExpired();
        }

        // There was a bug where the generated nonce was 42 characters long.
        $transientKey = substr('n_'.$nonceValue, 0, 40);
        $nonceUsed    = $this->context->transientGet($transientKey);

        if ($nonceUsed !== false) {
            throw new MWP_Security_Exception_NonceAlreadyUsed();
        }

        $this->context->transientSet($transientKey, $issuedAt, $this->nonceBlacklistedFor);
    }
}
