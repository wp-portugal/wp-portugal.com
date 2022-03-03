<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Security_NonceInterface
{
    /**
     * Parse input string and sets inner fields
     *
     * @param string $value
     *
     * @return mixed
     */
    public function setValue($value);

    /**
     * Returns if nonce is valid
     *
     * @return bool
     */
    public function verify();
}
