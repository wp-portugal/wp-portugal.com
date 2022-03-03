<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @deprecated
 */
class MWP_Http_LegacyWorkerResponse extends MWP_Http_Response
{
    public function getContentAsString()
    {
        return '<MWPHEADER>'.base64_encode(serialize($this->content)).'<ENDMWPHEADER>';
    }
}
